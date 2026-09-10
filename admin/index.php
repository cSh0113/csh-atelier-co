<?php
$pageTitle = 'Dashboard';
require __DIR__ . '/_layout.php';

$pdo = db();

/* The big numbers across the top. */
$members   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
$liveList  = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status='active'")->fetchColumn();
$revenue   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancelled'")->fetchColumn();
$creditsOut= (int)$pdo->query("SELECT COALESCE(SUM(credits),0) FROM users")->fetchColumn();
$completed = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','shipped','delivered')")->fetchColumn();
$subscribers = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='subscribed'")->fetchColumn();
$auditEvents = (int)$pdo->query("SELECT COUNT(*) FROM admin_audit_logs")->fetchColumn();
$impact    = impact_stats();

/* Six months of orders, grouped by month, for the bar chart. */
$chart = $pdo->query(
  "SELECT DATE_FORMAT(created_at,'%b') AS m, DATE_FORMAT(created_at,'%Y-%m') AS ym,
          COUNT(*) AS n, COALESCE(SUM(total),0) AS rev
   FROM orders
   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status<>'cancelled'
   GROUP BY ym, m ORDER BY ym"
)->fetchAll();
$maxRev = 0.0;
foreach ($chart as $c) $maxRev = max($maxRev, (float)$c['rev']);

/* What is sitting waiting for me to action. */
$queueListings = $pdo->query(
  "SELECT l.id,l.title,l.price,l.image_url,l.created_at,u.name AS seller
   FROM listings l JOIN users u ON u.id=l.seller_id
   WHERE l.status='pending' ORDER BY l.created_at ASC LIMIT 5")->fetchAll();

$queueDonations = $pdo->query(
  "SELECT d.id,d.reference,d.item_count,d.created_at,u.name AS member
   FROM donations d JOIN users u ON u.id=d.user_id
   WHERE d.outcome='pending' ORDER BY d.created_at ASC LIMIT 5")->fetchAll();

$recentOrders = $pdo->query(
  "SELECT id,order_no,full_name,total,status,created_at
   FROM orders ORDER BY created_at DESC LIMIT 6")->fetchAll();
?>

<section class="adm-stats">
  <div class="adm-stat">
    <p class="adm-stat__k">Members</p>
    <div class="adm-stat__v"><?= number_format($members) ?></div>
    <p class="adm-stat__s">Registered circular members</p>
  </div>
  <div class="adm-stat adm-stat--accent">
    <p class="adm-stat__k">Live listings</p>
    <div class="adm-stat__v"><?= number_format($liveList) ?></div>
    <p class="adm-stat__s">Member to member items on sale</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Revenue</p>
    <div class="adm-stat__v"><?= money($revenue) ?></div>
    <p class="adm-stat__s">All orders, excluding cancelled</p>
  </div>
  <div class="adm-stat adm-stat--warn">
    <p class="adm-stat__k">Credits in circulation</p>
    <div class="adm-stat__v"><?= number_format($creditsOut) ?></div>
    <p class="adm-stat__s">≈ <?= money(credits_to_rand($creditsOut)) ?> of store value</p>
  </div>
</section>

<section class="adm-stats">
  <div class="adm-stat"><p class="adm-stat__k">Completed orders</p><div class="adm-stat__v"><?= number_format($completed) ?></div><p class="adm-stat__s">Paid, shipped, or delivered</p></div>
  <div class="adm-stat"><p class="adm-stat__k">Newsletter subscribers</p><div class="adm-stat__v"><?= number_format($subscribers) ?></div><p class="adm-stat__s">Active subscriptions</p></div>
  <div class="adm-stat"><p class="adm-stat__k">Audit events</p><div class="adm-stat__v"><?= number_format($auditEvents) ?></div><p class="adm-stat__s">Administrator actions recorded</p></div>
</section>

<section class="adm-stats">
  <div class="adm-stat">
    <p class="adm-stat__k">Items diverted</p>
    <div class="adm-stat__v"><?= number_format($impact['items_diverted']) ?></div>
    <p class="adm-stat__s">Kept out of landfill</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Remade pieces</p>
    <div class="adm-stat__v"><?= number_format($impact['remade']) ?></div>
    <p class="adm-stat__s">Rebuilt from donated garments</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Water saved (est.)</p>
    <div class="adm-stat__v"><?= number_format($impact['water_litres'] / 1000, 1) ?>k L</div>
    <p class="adm-stat__s">Estimated, ~2 700 L per new tee</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">CO₂e avoided (est.)</p>
    <div class="adm-stat__v"><?= number_format($impact['co2_kg']) ?> kg</div>
    <p class="adm-stat__s">Estimated, ~7 kg per new tee</p>
  </div>
</section>

<div class="adm-grid adm-grid--2">

  <!-- CHART -->
  <div class="adm-card">
    <div class="adm-card__h">
      <div><h2>Orders &amp; revenue</h2><p>Last six months</p></div>
    </div>
    <?php if (!$chart): ?>
      <div class="adm-empty">No orders yet, once sales come in, they'll chart here.</div>
    <?php else: ?>
      <div class="adm-chart">
        <?php foreach ($chart as $c):
          $h = $maxRev > 0 ? max(4, (int)round(((float)$c['rev'] / $maxRev) * 140)) : 4; ?>
          <div class="adm-chart__col">
            <div class="adm-chart__bar" style="height: <?= $h ?>px"
                 title="<?= e($c['m']) ?>: <?= money($c['rev']) ?> across <?= (int)$c['n'] ?> order(s)"></div>
            <span class="adm-chart__lb"><?= e($c['m']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- RECENT ORDERS -->
  <div class="adm-card">
    <div class="adm-card__h">
      <div><h2>Recent orders</h2></div>
      <a class="abtn abtn--sm" href="<?= url('admin/orders.php') ?>">All orders</a>
    </div>
    <?php if (!$recentOrders): ?>
      <div class="adm-empty">No orders yet.</div>
    <?php else: ?>
      <div class="adm-table__wrap">
        <table class="adm-table">
          <tbody>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td><b><?= e($o['order_no']) ?></b><br><small style="color:var(--ink-faint)"><?= e($o['full_name']) ?></small></td>
              <td><?= money($o['total']) ?></td>
              <td><span class="pill <?= $o['status']==='delivered'?'pill--ok':($o['status']==='cancelled'?'pill--warn':'') ?>"><?= e(ucfirst($o['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="adm-grid adm-grid--2" style="margin-top:16px">

  <!-- LISTINGS AWAITING REVIEW -->
  <div class="adm-card">
    <div class="adm-card__h">
      <div><h2>Listings awaiting review</h2><p>Member items needing approval before they go live</p></div>
      <a class="abtn abtn--sm" href="<?= url('admin/listings.php?status=pending') ?>">Review all</a>
    </div>
    <?php if (!$queueListings): ?>
      <div class="adm-empty">Nothing waiting. The queue is clear.</div>
    <?php else: ?>
      <div class="adm-table__wrap">
        <table class="adm-table">
          <thead><tr><th>Item</th><th>Seller</th><th>Price</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($queueListings as $l): ?>
            <tr>
              <td>
                <div class="adm-cellitem">
                  <img class="adm-thumb" src="<?= e(img_or_placeholder($l['image_url'], (string)$l['id'])) ?>" alt="">
                  <b><?= e($l['title']) ?></b>
                </div>
              </td>
              <td><?= e($l['seller']) ?></td>
              <td><?= money($l['price']) ?></td>
              <td><a class="abtn abtn--sm" href="<?= url('admin/listings.php?status=pending#l' . (int)$l['id']) ?>">Review</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- DONATIONS -->
  <div class="adm-card">
    <div class="adm-card__h">
      <div><h2>Donations to process</h2><p>Award credits once items are received</p></div>
      <a class="abtn abtn--sm" href="<?= url('admin/donations.php?outcome=pending') ?>">Process</a>
    </div>
    <?php if (!$queueDonations): ?>
      <div class="adm-empty">No donations waiting.</div>
    <?php else: ?>
      <div class="adm-table__wrap">
        <table class="adm-table">
          <thead><tr><th>Ref</th><th>Member</th><th>Items</th></tr></thead>
          <tbody>
          <?php foreach ($queueDonations as $d): ?>
            <tr>
              <td><b><?= e($d['reference']) ?></b></td>
              <td><?= e($d['member']) ?></td>
              <td><?= (int)$d['item_count'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
