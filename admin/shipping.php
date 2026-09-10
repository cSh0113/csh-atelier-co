<?php
$pageTitle = 'Shipping';
require __DIR__ . '/_layout.php';
$pdo = db();

$COURIERS = ['The Courier Guy','PostNet','Aramex','Fastway','Paxi (PEP)','Pargo','RAM','Internal delivery'];

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    $st  = $pdo->prepare('SELECT * FROM orders WHERE id=?'); $st->execute([$id]);
    $o   = $st->fetch();

    if (!$o) {
        flash('Order not found.', 'error');
    } elseif ($act === 'ship') {
        $courier  = trim($_POST['courier'] ?? '');
        $tracking = trim($_POST['tracking_no'] ?? '');
        if ($courier === '') {
            flash('Choose a courier before marking it shipped.', 'error');
        } else {
            $pdo->prepare("UPDATE orders SET status='shipped', courier=?, tracking_no=?, shipped_at=NOW() WHERE id=?")
                ->execute([$courier, $tracking !== '' ? $tracking : null, $id]);
            flash('Order ' . $o['order_no'] . ' marked shipped with ' . $courier . '.');
        }
    } elseif ($act === 'delivered') {
        $pdo->prepare("UPDATE orders SET status='delivered', delivered_at=NOW() WHERE id=?")->execute([$id]);
        flash('Order ' . $o['order_no'] . ' marked delivered.');
    } elseif ($act === 'note') {
        $pdo->prepare('UPDATE orders SET admin_note=? WHERE id=?')
            ->execute([trim($_POST['admin_note'] ?? '') ?: null, $id]);
        flash('Note saved.');
    }
    redirect('admin/shipping.php?stage=' . urlencode((string)($_POST['back'] ?? 'to_pack')));
}

/* ---------- stage filter ---------- */
$stage = $_GET['stage'] ?? 'to_pack';
$where = match ($stage) {
    'to_pack'   => "status = 'paid'",
    'in_transit'=> "status = 'shipped'",
    'delivered' => "status = 'delivered'",
    'unpaid'    => "status = 'pending'",
    default     => "1=1",
};
$orders = $pdo->query("SELECT * FROM orders WHERE $where ORDER BY created_at ASC LIMIT 100")->fetchAll();

$itemsBy = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($in)");
    $q->execute($ids);
    foreach ($q->fetchAll() as $r) $itemsBy[$r['order_id']][] = $r;
}

$counts = [
  'unpaid'     => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
  'to_pack'    => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn(),
  'in_transit' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='shipped'")->fetchColumn(),
  'delivered'  => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn(),
];
$labels = ['unpaid'=>'Awaiting payment','to_pack'=>'To pack','in_transit'=>'In transit','delivered'=>'Delivered'];
?>

<div class="adm-filters">
  <?php foreach ($labels as $k => $lab): ?>
    <a href="<?= url('admin/shipping.php?stage=' . $k) ?>" class="<?= $stage === $k ? 'is-on' : '' ?>">
      <?= e($lab) ?> <span style="opacity:.6">(<?= $counts[$k] ?>)</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="adm-card">
  <div class="adm-card__h">
    <div>
      <h2><?= e($labels[$stage] ?? 'All orders') ?></h2>
      <p>Pack, dispatch and track. Printing a packing slip opens your browser print dialog.</p>
    </div>
  </div>

  <?php if (!$orders): ?>
    <div class="adm-empty">Nothing at this stage right now.</div>
  <?php else: ?>
    <?php foreach ($orders as $o): $items = $itemsBy[$o['id']] ?? []; ?>
      <div class="adm-card" style="box-shadow:var(--nm-in-sm);margin-bottom:14px;opacity:1;transform:none">
        <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:12px">
          <div>
            <b style="font-size:1.02rem"><?= e($o['order_no']) ?></b>
            <span class="pill <?= $o['status']==='delivered'?'pill--ok':'' ?>" style="margin-left:8px"><?= e(ucfirst($o['status'])) ?></span>
            <div style="font-size:.86rem;color:var(--ink-soft);margin-top:6px">
              <?= e($o['full_name']) ?><?= $o['phone'] ? ' &#183; ' . e($o['phone']) : '' ?><br>
              <?= e($o['address']) ?>, <?= e($o['city']) ?><?= $o['province'] ? ', ' . e($o['province']) : '' ?>
              <?= $o['postal_code'] ? ' ' . e($o['postal_code']) : '' ?>
            </div>
          </div>
          <div style="text-align:right">
            <b><?= money($o['total']) ?></b>
            <?php if ($o['tracking_no']): ?>
              <div style="font-size:.78rem;color:var(--ink-faint);margin-top:4px">
                <?= e($o['courier']) ?><br><?= e($o['tracking_no']) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div style="font-size:.86rem;margin-bottom:12px">
          <?php foreach ($items as $li): ?>
            <div style="padding:4px 0;border-bottom:1px solid var(--line)">
              <?= (int)$li['qty'] ?>x <?= e($li['title']) ?>
              <?= $li['seller_id'] ? '<span class="pill pill--mute" style="font-size:.6rem;padding:2px 8px">from a member</span>' : '<span class="pill" style="font-size:.6rem;padding:2px 8px">brand</span>' ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <?php if ($o['status'] === 'paid'): ?>
            <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <input type="hidden" name="back" value="<?= e($stage) ?>">
              <select name="courier" style="padding:8px 13px;border:0;border-radius:12px;background:var(--bg-sunken);box-shadow:var(--nm-in-sm);color:var(--ink);font-family:inherit;font-size:.82rem">
                <option value="">Choose courier</option>
                <?php foreach ($COURIERS as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
              </select>
              <input type="text" name="tracking_no" placeholder="Tracking number"
                     style="padding:8px 13px;border:0;border-radius:12px;background:var(--bg-sunken);box-shadow:var(--nm-in-sm);color:var(--ink);font-family:inherit;font-size:.82rem">
              <button class="abtn abtn--sm abtn--go" name="action" value="ship">Mark shipped</button>
            </form>
          <?php elseif ($o['status'] === 'shipped'): ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <input type="hidden" name="back" value="<?= e($stage) ?>">
              <button class="abtn abtn--sm abtn--go" name="action" value="delivered">Mark delivered</button>
            </form>
          <?php endif; ?>

          <button class="abtn abtn--sm" onclick="printSlip(<?= (int)$o['id'] ?>)">Print packing slip</button>
        </div>

        <!-- hidden packing slip -->
        <div id="slip<?= (int)$o['id'] ?>" style="display:none">
          <h2 style="margin:0 0 4px">CSH Atelier Co.</h2>
          <p style="margin:0 0 14px;font-size:.85rem">Packing slip &#183; <?= e($o['order_no']) ?> &#183; <?= e(date('d M Y', strtotime($o['created_at']))) ?></p>
          <p style="margin:0 0 14px;font-size:.9rem">
            <b><?= e($o['full_name']) ?></b><br>
            <?= e($o['address']) ?><br><?= e($o['city']) ?><?= $o['province'] ? ', ' . e($o['province']) : '' ?> <?= e($o['postal_code']) ?><br>
            <?= e($o['phone']) ?>
          </p>
          <table style="width:100%;border-collapse:collapse;font-size:.88rem">
            <tr><th style="text-align:left;border-bottom:1px solid #000">Item</th><th style="text-align:right;border-bottom:1px solid #000">Qty</th></tr>
            <?php foreach ($items as $li): ?>
              <tr><td style="padding:5px 0"><?= e($li['title']) ?></td><td style="text-align:right"><?= (int)$li['qty'] ?></td></tr>
            <?php endforeach; ?>
          </table>
          <p style="margin-top:16px;font-size:.85rem">Total paid: <b><?= money($o['total']) ?></b></p>
          <p style="margin-top:22px;font-size:.8rem">Thank you for keeping clothing in the loop.</p>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function printSlip(id){
  var html = document.getElementById('slip' + id).innerHTML;
  var w = window.open('', '', 'width=760,height=900');
  w.document.write('<html><head><title>Packing slip</title><style>body{font-family:Arial,Helvetica,sans-serif;padding:30px;color:#000}</style></head><body>' + html + '</body></html>');
  w.document.close(); w.focus(); w.print(); w.close();
}
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
