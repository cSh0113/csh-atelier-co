<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Your bag';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $act = $_POST['action'] ?? '';
    if ($act === 'qty')    cart_set_qty((int)$_POST['cart_id'], (int)$_POST['qty']);
    if ($act === 'remove') cart_remove((int)$_POST['cart_id']);
    if ($act === 'wishlist') {
        $cartRow = cart_get(); foreach ($cartRow['items'] as $row) if ((int)$row['cart_id']===(int)$_POST['cart_id']) { $pdo=db(); $pdo->prepare('INSERT IGNORE INTO wishlists(user_id,item_type,item_id) VALUES(?,?,?)')->execute([(int)current_user()['id'],$row['type'],(int)$row['item']['id']]); cart_remove((int)$_POST['cart_id']); flash('Moved to your wishlist.'); break; }
    }
    redirect('cart.php');
}

$cart = cart_get();

// Clean up any orphaned cart rows so the badge stays in sync with
// what this page actually shows. A row is orphaned when the listing
// was sold, delisted, or is still pending admin approval.
$rawCount = 0;
$cleaned = 0;
$o = cart_owner();
$rawStmt = db()->prepare('SELECT * FROM cart_items WHERE (user_id <=> ?) AND (session_id <=> ?)');
$rawStmt->execute([$o['user_id'], $o['session_id']]);
$rawRows = $rawStmt->fetchAll();
$rawCount = count($rawRows);

foreach ($rawRows as $raw) {
    $found = false;
    foreach ($cart['items'] as $ci) {
        if ((int)$ci['cart_id'] === (int)$raw['id']) { $found = true; break; }
    }
    if (!$found) {
        // This row points at a listing or product that no longer qualifies,
        // so remove it to keep the badge honest.
        db()->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$raw['id']]);
        $cleaned++;
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="sec" style="padding-top:44px">
  <div class="shell">
    <div class="sec-head"><h2 data-reveal>Your bag</h2></div>

    <?php if (!$cart['items']): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24"><path d="M6 6h15l-1.6 9H7.4z"/><path d="M6 6L5 3H2"/></svg>
        <h3>Your bag is empty</h3>
        <p>Find something with a story already in it.</p>
        <div class="flex" style="justify-content:center;flex-wrap:wrap">
          <a class="btn btn-primary" href="<?= url('marketplace.php') ?>">Browse the marketplace</a>
          <a class="btn" href="<?= url('shop.php') ?>">Shop The Label</a>
        </div>
      </div>
    <?php else: ?>
      <div class="split" style="grid-template-columns:1.6fr .9fr;align-items:start">
        <div class="panel" data-reveal>
          <?php foreach ($cart['items'] as $it): $item = $it['item']; ?>
          <div class="row-item">
            <div class="thumb-sm">
              <img src="<?= e(img_or_placeholder($item['image_url'], $item['title'])) ?>" alt="<?= e($item['title']) ?>">
            </div>
            <div>
              <strong><?= e($item['title']) ?></strong>
              <div class="small muted">
                <?php if ($it['type'] === 'listing'): ?>
                  Member listing<?= isset($item['seller_name']) ? ' &#183; ' . e($item['seller_name']) : '' ?>
                <?php else: ?>
                  <?= !empty($item['external_source']) ? 'CSH Innovations Co.' : 'CSH Atelier' ?><?= !empty($item['is_remade']) ? ' &#183; Remade' : '' ?>
                  <?php if (!empty($item['external_url'])): ?><br><a class="small" href="<?= e($item['external_url']) ?>" target="_blank" rel="noopener noreferrer">View on main website &rarr;</a><?php endif; ?>
                <?php endif; ?>
              </div>
              <div class="flex mt-1" style="gap:10px">
                <?php if ($it['type'] === 'product'): ?>
                <form method="post" class="qty">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="qty">
                  <input type="hidden" name="cart_id" value="<?= (int)$it['cart_id'] ?>">
                  <button name="qty" value="<?= max(1,$it['qty']-1) ?>" aria-label="Decrease">&minus;</button>
                  <span><?= (int)$it['qty'] ?></span>
                  <button name="qty" value="<?= $it['qty']+1 ?>" aria-label="Increase">+</button>
                </form>
                <?php else: ?>
                  <span class="small muted">One of a kind</span>
                <?php endif; ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="cart_id" value="<?= (int)$it['cart_id'] ?>">
                  <button class="small muted" style="text-decoration:underline">Remove</button>
                </form>
                <?php if (is_logged_in()): ?><form method="post"><input type="hidden" name="action" value="wishlist"><input type="hidden" name="cart_id" value="<?= (int)$it['cart_id'] ?>"><?= csrf_field() ?><button class="small" style="color:var(--leaf);text-decoration:underline">Save for later</button></form><?php endif; ?>
              </div>
            </div>
            <strong><?= money($it['line_total']) ?></strong>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="panel" data-reveal data-delay="1" style="position:sticky;top:84px">
          <h3 class="mb-2">Summary</h3>
          <div class="totals"><span>Subtotal</span><span><?= money($cart['subtotal']) ?></span></div>
          <div class="totals"><span>Shipping</span><span><?= money(SHIPPING_FLAT) ?></span></div>
          <div class="totals grand"><span>Total</span><span><?= money($cart['subtotal'] + SHIPPING_FLAT) ?></span></div>
          <a class="btn btn-primary btn-block btn-lg mt-2" href="<?= url('checkout.php') ?>">Checkout</a>
          <?php if (is_logged_in()): $u = current_user(); if ($u['credits'] > 0): ?>
            <p class="small muted center mt-1">
              You have <strong style="color:var(--leaf)"><?= number_format($u['credits']) ?></strong>
              credits (<?= money(credits_to_rand($u['credits'])) ?>) to use at checkout.
            </p>
          <?php endif; endif; ?>
          <p class="small muted center mt-1">
            Buying preloved? You&rsquo;re saving roughly
            <?= number_format(count($cart['items']) * 2700) ?> litres of water.
          </p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>