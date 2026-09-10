<?php
$pageTitle = 'Cash flow';
require __DIR__ . '/_layout.php';
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01', strtotime('-5 months'));
$to   = $_GET['to']   ?? date('Y-m-d');

function q1(PDO $p, string $sql, array $a = []) { $s = $p->prepare($sql); $s->execute($a); return $s->fetchColumn(); }

/* Everything that came in. */
$gross     = (float)q1($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','shipped','delivered') AND created_at BETWEEN ? AND ?", [$from, $to . ' 23:59:59']);
$shipIn    = (float)q1($pdo, "SELECT COALESCE(SUM(shipping),0) FROM orders WHERE status IN ('paid','shipped','delivered') AND created_at BETWEEN ? AND ?", [$from, $to . ' 23:59:59']);
$creditVal = (float)q1($pdo, "SELECT COALESCE(SUM(credit_value),0) FROM orders WHERE status IN ('paid','shipped','delivered') AND created_at BETWEEN ? AND ?", [$from, $to . ' 23:59:59']);
$pending   = (float)q1($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status='pending'");
$refunded  = (float)q1($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status='cancelled' AND created_at BETWEEN ? AND ?", [$from, $to . ' 23:59:59']);

/* Split between my own stock and member sales. */
$brandRev  = (float)q1($pdo, "SELECT COALESCE(SUM(oi.line_total),0) FROM order_items oi
                              JOIN orders o ON o.id=oi.order_id
                              WHERE oi.seller_id IS NULL AND o.status IN ('paid','shipped','delivered')
                                AND o.created_at BETWEEN ? AND ?", [$from, $to . ' 23:59:59']);
$mktGMV    = (float)q1($pdo, "SELECT COALESCE(SUM(oi.line_total),0) FROM order_items oi
                              JOIN orders o ON o.id=oi.order_id
                              WHERE oi.seller_id IS NOT NULL AND o.status IN ('paid','shipped','delivered')
                                AND o.created_at BETWEEN ? AND ?", [$from, $to . ' 23:59:59']);
$commission = $mktGMV * SELLER_COMMISSION;
$payoutOwed = (float)q1($pdo, "SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE seller_id IS NOT NULL AND payout_done=0") * (1 - SELLER_COMMISSION);

/* Credits members are holding. This is money I still owe against stock,
   so I keep an eye on it. */
$creditsOut = (int)q1($pdo, 'SELECT COALESCE(SUM(credits),0) FROM users');
$liability  = credits_to_rand($creditsOut);

$netRevenue = $brandRev + $commission + $shipIn;

/* Month by month, for the chart. */
$months = $pdo->prepare(
 "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, DATE_FORMAT(created_at,'%b') m,
         COUNT(*) n,
         COALESCE(SUM(total),0) rev,
         COALESCE(SUM(credit_value),0) cred
  FROM orders WHERE status IN ('paid','shipped','delivered') AND created_at BETWEEN ? AND ?
  GROUP BY ym,m ORDER BY ym");
$months->execute([$from, $to . ' 23:59:59']);
$series = $months->fetchAll();
$maxRev = 0; foreach ($series as $r) $maxRev = max($maxRev, (float)$r['rev']);

/* The latest transactions. */
$recent = $pdo->prepare(
 "SELECT o.order_no, o.full_name, o.total, o.credit_value, o.payment_method, o.status, o.created_at
  FROM orders o WHERE o.created_at BETWEEN ? AND ? ORDER BY o.created_at DESC LIMIT 12");
$recent->execute([$from, $to . ' 23:59:59']);
$recent = $recent->fetchAll();
?>

<div class="adm-card" style="margin-bottom:16px">
  <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div class="afield" style="margin:0">
      <label>From</label>
      <input type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="afield" style="margin:0">
      <label>To</label>
      <input type="date" name="to" value="<?= e($to) ?>">
    </div>
    <button class="abtn abtn--go">Apply</button>
  </form>
</div>

<section class="adm-stats">
  <div class="adm-stat adm-stat--accent">
    <p class="adm-stat__k">Net revenue</p>
    <div class="adm-stat__v"><?= money($netRevenue) ?></div>
    <p class="adm-stat__s">Brand sales + commission + shipping</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Gross takings</p>
    <div class="adm-stat__v"><?= money($gross) ?></div>
    <p class="adm-stat__s">All completed orders in range</p>
  </div>
  <div class="adm-stat adm-stat--warn">
    <p class="adm-stat__k">Awaiting payment</p>
    <div class="adm-stat__v"><?= money($pending) ?></div>
    <p class="adm-stat__s">Orders still pending</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Cancelled / refunded</p>
    <div class="adm-stat__v"><?= money($refunded) ?></div>
    <p class="adm-stat__s">Money that did not land</p>
  </div>
</section>

<section class="adm-stats">
  <div class="adm-stat">
    <p class="adm-stat__k">Brand sales (B2C)</p>
    <div class="adm-stat__v"><?= money($brandRev) ?></div>
    <p class="adm-stat__s">Your own pieces</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Marketplace GMV (C2C)</p>
    <div class="adm-stat__v"><?= money($mktGMV) ?></div>
    <p class="adm-stat__s">Member to member value traded</p>
  </div>
  <div class="adm-stat adm-stat--accent">
    <p class="adm-stat__k">Commission earned</p>
    <div class="adm-stat__v"><?= money($commission) ?></div>
    <p class="adm-stat__s"><?= (int)(SELLER_COMMISSION * 100) ?>% of marketplace sales</p>
  </div>
  <div class="adm-stat adm-stat--warn">
    <p class="adm-stat__k">Owed to sellers</p>
    <div class="adm-stat__v"><?= money($payoutOwed) ?></div>
    <p class="adm-stat__s">Not yet paid out in credits</p>
  </div>
</section>

<section class="adm-stats">
  <div class="adm-stat adm-stat--warn">
    <p class="adm-stat__k">Credit liability</p>
    <div class="adm-stat__v"><?= money($liability) ?></div>
    <p class="adm-stat__s"><?= number_format($creditsOut) ?> credits members can still spend</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Credits redeemed</p>
    <div class="adm-stat__v"><?= money($creditVal) ?></div>
    <p class="adm-stat__s">Discount given via credits in range</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Shipping collected</p>
    <div class="adm-stat__v"><?= money($shipIn) ?></div>
    <p class="adm-stat__s">Charged to customers</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Avg order value</p>
    <div class="adm-stat__v"><?= money(count($series) ? ($gross / max(1, array_sum(array_column($series, 'n')))) : 0) ?></div>
    <p class="adm-stat__s">Across the selected range</p>
  </div>
</section>

<div class="adm-grid adm-grid--2">
  <div class="adm-card">
    <div class="adm-card__h"><div><h2>Revenue by month</h2><p>Completed orders only</p></div></div>
    <?php if (!$series): ?>
      <div class="adm-empty">No completed orders in this range.</div>
    <?php else: ?>
      <div class="adm-chart">
        <?php foreach ($series as $r):
          $h = $maxRev > 0 ? max(4, (int)round(((float)$r['rev'] / $maxRev) * 140)) : 4; ?>
          <div class="adm-chart__col">
            <div class="adm-chart__bar" style="height:<?= $h ?>px"
                 title="<?= e($r['m']) ?>: <?= money($r['rev']) ?> from <?= (int)$r['n'] ?> order(s)"></div>
            <span class="adm-chart__lb"><?= e($r['m']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="adm-card">
    <div class="adm-card__h"><div><h2>Recent money movements</h2></div></div>
    <?php if (!$recent): ?>
      <div class="adm-empty">Nothing in this range.</div>
    <?php else: ?>
      <div class="adm-table__wrap">
        <table class="adm-table">
          <thead><tr><th>Order</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><b><?= e($r['order_no']) ?></b><br><small style="color:var(--ink-faint)"><?= e($r['full_name']) ?></small></td>
              <td><span class="pill pill--mute"><?= e(strtoupper($r['payment_method'])) ?></span></td>
              <td><b><?= money($r['total']) ?></b>
                <?php if ((float)$r['credit_value'] > 0): ?>
                  <br><small style="color:var(--leaf)"><?= money($r['credit_value']) ?> in credits</small>
                <?php endif; ?>
              </td>
              <td><span class="pill <?= in_array($r['status'], ['paid','shipped','delivered'], true) ? 'pill--ok' : ($r['status'] === 'cancelled' ? 'pill--warn' : '') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
