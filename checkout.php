<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Checkout';
$pdo  = db();
$cart = cart_get();
$user = current_user();

if (!$cart['items']) { flash('Your bag is empty.', 'info'); redirect('cart.php'); }

$errors = [];
$containsBrand = false;
foreach ($cart['items'] as $cartItem) {
    if ($cartItem['type'] === 'product') { $containsBrand = true; break; }
}
$canCollect = !$containsBrand;
$deliveryMethod = 'shipping';
$shippingAmount = SHIPPING_FLAT;
$maxCredits = 0;
$paystackReturn = $_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['reference']);
$pendingCheckout = $_SESSION['pending_checkout'] ?? null;
$checkoutData = $_POST;
$paystackReference = null;

if ($paystackReturn) {
    if (!$pendingCheckout || empty($pendingCheckout['reference'])
        || !hash_equals($pendingCheckout['reference'], (string)$_GET['reference'])) {
        flash('That payment session has expired. Please start checkout again.', 'error');
        redirect('cart.php');
    }
    $checkoutData = $pendingCheckout['data'];
    $paystackReference = (string)$_GET['reference'];
}

if ($user) {
    // can't spend more credits than the order is worth
    $orderTotal = $cart['subtotal'] + $shippingAmount;
    $maxCredits = min((int)$user['credits'], rand_to_credits($orderTotal));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $paystackReturn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf();

    $name    = trim($checkoutData['full_name'] ?? '');
    $email   = trim($checkoutData['email'] ?? '');
    $phone   = trim($checkoutData['phone'] ?? '');
    $addr    = trim($checkoutData['address'] ?? '');
    $city    = trim($checkoutData['city'] ?? '');
    $prov    = trim($checkoutData['province'] ?? '');
    $postal  = trim($checkoutData['postal_code'] ?? '');
    $pay     = 'card';
    $deliveryMethod = $checkoutData['delivery_method'] ?? 'shipping';
    if ($deliveryMethod === 'collection' && !$canCollect) {
        $deliveryMethod = 'shipping';
        $errors[] = 'Collection is only available for member to member items. CSH Atelier Co. pieces require delivery.';
    }
    if (!in_array($deliveryMethod, ['shipping','collection'], true)) $deliveryMethod = 'shipping';
    $shippingAmount = $deliveryMethod === 'collection' ? 0.0 : SHIPPING_FLAT;
    $orderTotal = $cart['subtotal'] + $shippingAmount;
    $maxCredits = $user ? min((int)$user['credits'], rand_to_credits($orderTotal)) : 0;
    $useCred = $user ? max(0, min((int)($checkoutData['credits_used'] ?? 0), $maxCredits)) : 0;

    if ($name === '')                               $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($addr === '')                               $errors[] = 'Enter a delivery address.';
    if ($city === '')                               $errors[] = 'Enter your city.';

    if (!$errors && $pay === 'card' && !$paystackReturn) {
        try {
            $reference = 'CSH-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $payment = paystack_initialize($email, (int)round(max(0, $orderTotal - credits_to_rand($useCred)) * 100), $reference, [
                'site' => SITE_NAME,
                'delivery_method' => $deliveryMethod,
            ]);
            $_SESSION['pending_checkout'] = [
                'reference' => $reference,
                'data' => $checkoutData,
            ];
            redirect($payment['data']['authorization_url']);
        } catch (Throwable $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors && $paystackReturn) {
        try {
            $verified = paystack_verify($paystackReference);
            $transaction = $verified['data'] ?? [];
            $expectedAmount = (int)round(max(0, $orderTotal - credits_to_rand($useCred)) * 100);
            if (($transaction['status'] ?? '') !== 'success'
                || (int)($transaction['amount'] ?? -1) !== $expectedAmount
                || ($transaction['currency'] ?? '') !== PAYSTACK_CURRENCY) {
                throw new RuntimeException('Payment could not be verified. No order was created.');
            }
            unset($_SESSION['pending_checkout']);
        } catch (Throwable $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Check stock again with the rows locked before I take any money.
            // Two people can reach checkout with the last item at the same time.
            $lines = [];
            $subtotal = 0.0;

            foreach ($cart['items'] as $it) {
                if ($it['type'] === 'product') {
                    $q = $pdo->prepare('SELECT * FROM products WHERE id = ? AND status="active" FOR UPDATE');
                    $q->execute([$it['item']['id']]);
                    $p = $q->fetch();
                    if (!$p)                        throw new RuntimeException('An item is no longer available.');
                    if ((int)$p['stock'] < $it['qty'])
                        throw new RuntimeException('"' . $p['name'] . '" does not have enough stock left.');

                    $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')
                        ->execute([$it['qty'], $p['id']]);

                    $line = (float)$p['price'] * $it['qty'];
                    $subtotal += $line;
                    $lines[] = ['product', (int)$p['id'], null, $p['name'],
                                (float)$p['price'], $it['qty'], $line];
                } else {
                    $q = $pdo->prepare('SELECT * FROM listings WHERE id = ? FOR UPDATE');
                    $q->execute([$it['item']['id']]);
                    $l = $q->fetch();
                    if (!$l || $l['status'] !== 'active')
                        throw new RuntimeException('A member listing in your bag has just sold.');

                    $pdo->prepare('UPDATE listings SET status = "sold" WHERE id = ?')->execute([$l['id']]);

                    $line = (float)$l['price'];
                    $subtotal += $line;
                    $lines[] = ['listing', (int)$l['id'], (int)$l['seller_id'], $l['title'],
                                (float)$l['price'], 1, $line];
                }
            }

            $creditValue = credits_to_rand($useCred);
            $shippingAmount = $deliveryMethod === 'collection' ? 0.0 : SHIPPING_FLAT;
            $total = max(0, $subtotal + $shippingAmount - $creditValue);
            $orderNo = 'ORD-' . (2000 + (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() + 1);

            $pdo->prepare(
              'INSERT INTO orders (order_no,user_id,full_name,email,phone,address,city,province,
                                   postal_code,subtotal,shipping,delivery_method,credits_used,credit_value,total,
                                   payment_method,status)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,? ,\'paid\')')
                ->execute([$orderNo, $user['id'] ?? null, $name, $email, $phone, $addr, $city,
                           $prov, $postal, $subtotal, $shippingAmount, $deliveryMethod, $useCred, $creditValue,
                           $total, $pay]);
            $orderId = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare(
              'INSERT INTO order_items (order_id,item_type,item_id,seller_id,title,unit_price,qty,line_total)
               VALUES (?,?,?,?,?,?,?,?)');
            foreach ($lines as $ln) {
                $ins->execute([$orderId, $ln[0], $ln[1], $ln[2], $ln[3], $ln[4], $ln[5], $ln[6]]);
            }

            // Spend buyer credits
            if ($useCred > 0 && $user) {
                adjust_credits((int)$user['id'], -$useCred, 'purchase', $orderNo,
                               'Applied to order ' . $orderNo);
            }

            // Pay sellers (in credits, less commission) and count their impact
            foreach ($lines as $ln) {
                if ($ln[0] === 'listing' && $ln[2]) {
                    $payout = rand_to_credits($ln[6] * (1 - SELLER_COMMISSION));
                    adjust_credits((int)$ln[2], $payout, 'sale', $orderNo,
                                   'Sold: ' . $ln[3]);
                    $pdo->prepare('UPDATE users SET items_diverted = items_diverted + 1 WHERE id = ?')
                        ->execute([$ln[2]]);
                }
            }

            // Empty the cart
            $o = cart_owner();
            $pdo->prepare('DELETE FROM cart_items WHERE (user_id <=> ?) AND (session_id <=> ?)')
                ->execute([$o['user_id'], $o['session_id']]);

            $pdo->commit();

            $_SESSION['last_order'] = $orderNo;
            if (!empty($user['id'])) {
                notify_user_email((int)$user['id'], 'notify_orders', 'Order ' . $orderNo . ' confirmed',
                                  'Your payment was verified and your order has been confirmed.');
            }
            flash('Order ' . $orderNo . ' confirmed. Thank you for keeping clothing in the loop.');
            redirect('account.php?tab=orders');

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex->getMessage();
            $cart = cart_get();
        }
    }
}

include __DIR__ . '/includes/header.php';
$orderTotal = $cart['subtotal'] + $shippingAmount;
?>
<section class="sec" style="padding-top:44px">
  <div class="shell">
    <div class="sec-head"><h2 data-reveal>Checkout</h2></div>

    <?php foreach ($errors as $er): ?>
      <div class="panel-in mb-2" style="border-left:3px solid var(--clay)">
        <span class="small"><?= e($er) ?></span></div>
    <?php endforeach; ?>

    <form method="post" class="split" style="grid-template-columns:1.4fr 1fr;align-items:start">
      <?= csrf_field() ?>

      <div class="panel" data-reveal>
        <h3 class="mb-2">Delivery details</h3>
        <div class="field"><label for="full_name">Full name</label>
          <input id="full_name" name="full_name" required
                 value="<?= e($checkoutData['full_name'] ?? ($user['name'] ?? '')) ?>"></div>
        <div class="grid grid-2" style="gap:16px">
          <div class="field"><label for="email">Email</label>
            <input id="email" name="email" type="email" required
                   value="<?= e($checkoutData['email'] ?? ($user['email'] ?? '')) ?>"></div>
          <div class="field"><label for="phone">Phone</label>
            <input id="phone" name="phone" value="<?= e($checkoutData['phone'] ?? '') ?>"></div>
        </div>
        <div class="field"><label for="address">Street address</label>
          <input id="address" name="address" required value="<?= e($checkoutData['address'] ?? '') ?>"></div>
        <div class="grid grid-3" style="gap:16px">
          <div class="field"><label for="city">City</label>
            <input id="city" name="city" required
                   value="<?= e($checkoutData['city'] ?? ($user['city'] ?? '')) ?>"></div>
          <div class="field"><label for="province">Province</label>
            <select id="province" name="province">
              <?php foreach (['Gauteng','Western Cape','KwaZulu-Natal','Eastern Cape','Free State',
                              'Limpopo','Mpumalanga','North West','Northern Cape'] as $pv): ?>
                <option <?= (($checkoutData['province'] ?? ($user['province'] ?? ''))===$pv)?'selected':'' ?>><?= $pv ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="postal_code">Postal code</label>
            <input id="postal_code" name="postal_code" value="<?= e($checkoutData['postal_code'] ?? '') ?>"></div>
        </div>

        <h3 class="mb-2 mt-3">Payment</h3>
        <div class="flex" style="gap:10px;flex-wrap:wrap">
          <label class="chip"><input type="radio" name="payment_method" value="card" checked> Paystack card payment</label>
        </div>
        <p class="small muted mt-1">
          You will be redirected to Paystack&rsquo;s secure checkout to complete payment.
        </p>

        <h3 class="mb-2 mt-3">Delivery method</h3>
        <div class="delivery-options">
          <label class="chip delivery-option <?= $deliveryMethod === 'shipping' ? 'on' : '' ?>">
            <input type="radio" name="delivery_method" value="shipping" <?= $deliveryMethod === 'shipping' ? 'checked' : '' ?>>
            Delivery · R150
          </label>
          <?php if ($canCollect): ?>
          <label class="chip delivery-option <?= $deliveryMethod === 'collection' ? 'on' : '' ?>">
            <input type="radio" name="delivery_method" value="collection" <?= $deliveryMethod === 'collection' ? 'checked' : '' ?>>
            Negotiate collection with the seller
          </label>
          <p class="small muted mt-1">Collection is available because this bag contains member listings only. Arrange the collection details directly with the seller after purchase.</p>
          <?php else: ?>
          <p class="small muted mt-1">This bag includes a CSH Atelier Co. piece, so delivery is required.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel" data-reveal data-delay="1" style="position:sticky;top:84px">
        <h3 class="mb-2">Your order</h3>
        <?php foreach ($cart['items'] as $it): ?>
          <div class="totals">
            <span><?= e($it['item']['title']) ?><?= $it['qty']>1 ? ' &times;'.$it['qty'] : '' ?></span>
            <span><?= money($it['line_total']) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="totals"><span><?= $deliveryMethod === 'collection' ? 'Collection' : 'Shipping' ?></span><span><?= $shippingAmount ? money($shippingAmount) : 'Negotiated with seller' ?></span></div>

        <?php if ($user && $maxCredits > 0): ?>
        <div class="credit-box">
          <div class="row">
            <strong class="small">Use store credits</strong>
            <span class="small muted"><?= number_format((int)$user['credits']) ?> available</span>
          </div>
          <label class="small muted" for="credits_used">Credits to use</label>
          <input class="credit-number" id="credits_used" type="number" name="credits_used" min="0" max="<?= $maxCredits ?>" value="<?= (int)($_POST['credits_used'] ?? 0) ?>"
                 step="1" inputmode="numeric" data-credit-slider data-rate="<?= CREDIT_RATE ?>"
                 data-subtotal="<?= e($cart['subtotal']) ?>" data-shipping="<?= SHIPPING_FLAT ?>">
          <div class="row mt-1">
            <span class="small"><strong data-credit-out>0</strong> credits</span>
            <span class="small" style="color:var(--leaf)">&minus; <span data-credit-rand>R0.00</span></span>
          </div>
        </div>
        <?php elseif (!$user): ?>
          <p class="small muted mb-2">
            <a href="<?= url('login.php?next=/checkout.php') ?>" style="color:var(--leaf);font-weight:700">Sign in</a>
            to pay with store credits.
          </p>
        <?php endif; ?>

        <div class="totals grand">
          <span>To pay</span>
          <span data-pay-total><?= money($orderTotal) ?></span>
        </div>

        <button class="btn btn-primary btn-block btn-lg mt-2" type="submit">Place order</button>
        <p class="small muted center mt-1">
          <?= count($cart['items']) ?> item<?= count($cart['items'])===1?'':'s' ?> staying in circulation.
        </p>
      </div>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
