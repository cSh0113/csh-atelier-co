<?php
$pageTitle = 'Orders';
require __DIR__ . '/_layout.php';
$pdo = db();

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';

    $st = $pdo->prepare('SELECT * FROM orders WHERE id=?'); $st->execute([$id]);
    $order = $st->fetch();

    if (!$order) {
        flash('Order not found.', 'error');
    } elseif ($act === 'status') {
        $new = $_POST['status'] ?? '';
        if (in_array($new, ['pending','paid','shipped','delivered','cancelled'], true)) {
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$new, $id]);
            flash('Order ' . $order['order_no'] . ' marked ' . $new . '.');
        } else {
            flash('Invalid status.', 'error');
        }
    } elseif ($act === 'payout') {
        /* Credit each C2C seller for their sold lines, minus commission. */
        try {
            $pdo->beginTransaction();
            $lines = $pdo->prepare('SELECT * FROM order_items WHERE order_id=? AND seller_id IS NOT NULL AND payout_done=0');
            $lines->execute([$id]);
            $paid = 0;
            foreach ($lines->fetchAll() as $li) {
                $net     = (float)$li['line_total'] * (1 - SELLER_COMMISSION);
                $credits = rand_to_credits($net);
                adjust_credits((int)$li['seller_id'], $credits, 'sale', $order['order_no'],
                               'Sold: ' . $li['title']);
                $pdo->prepare('UPDATE order_items SET payout_done=1 WHERE id=?')->execute([(int)$li['id']]);
                $paid++;
            }
            $pdo->commit();
            flash($paid > 0 ? "Paid out {$paid} seller line(s) in credits." : 'Nothing left to pay out on this order.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Payout failed: ' . $ex->getMessage(), 'error');
        }
    }
    redirect('admin/orders.php?status=' . urlencode((string)($_POST['back'] ?? 'all')));
}

/* ---------- list ---------- */
$allowed = ['all','pending','paid','shipped','delivered','cancelled'];
$filter  = in_array($_GET['status'] ?? 'all', $allowed, true) ? ($_GET['status'] ?? 'all') : 'all';

$sql = 'SELECT * FROM orders'; $params = [];
if ($filter !== 'all') { $sql .= ' WHERE status = ?'; $params[] = $filter; }
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$st = $pdo->prepare($sql); $st->execute($params);
$orders = $st->fetchAll();

$itemsBy = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT oi.*, u.name AS seller_name FROM order_items oi
                        LEFT JOIN users u ON u.id = oi.seller_id
                        WHERE oi.order_id IN ($in)");
    $q->execute($ids);
    foreach ($q->fetchAll() as $r) $itemsBy[$r['order_id']][] = $r;
}

$counts = [];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
$counts['all'] = array_sum($counts);
?>

<div class="adm-filters">
  <?php foreach ($allowed as $s): ?>
    <a href="<?= url('admin/orders.php?status=' . $s) ?>" class="<?= $filter === $s ? 'is-on' : '' ?>">
      <?= e(ucfirst($s)) ?> <span style="opacity:.6">(<?= (int)($counts[$s] ?? 0) ?>)</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="adm-card">
  <div class="adm-card__h">
    <div><h2>Orders</h2><p>Mixed baskets: brand pieces and member listings can share one order.</p></div>
  </div>

  <?php if (!$orders): ?>
    <div class="adm-empty">No orders in this state.</div>
  <?php else: ?>
    <div class="adm-table__wrap">
      <table class="adm-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o):
          $lines = $itemsBy[$o['id']] ?? [];
          $owed  = 0; foreach ($lines as $li) if ($li['seller_id'] && !$li['payout_done']) $owed++; ?>
          <tr>
            <td>
              <b><?= e($o['order_no']) ?></b><br>
              <small style="color:var(--ink-faint)"><?= e(date('d M Y H:i', strtotime($o['created_at']))) ?></small>
            </td>
            <td>
              <?= e($o['full_name']) ?><br>
              <small style="color:var(--ink-faint)"><?= e($o['city']) ?><?= $o['province'] ? ', ' . e($o['province']) : '' ?></small>
            </td>
            <td style="min-width:200px">
              <?php foreach ($lines as $li): ?>
                <div style="font-size:.8rem;margin-bottom:3px">
                  <?= (int)$li['qty'] ?>× <?= e($li['title']) ?>
                  <?php if ($li['seller_id']): ?>
                    <span class="pill pill--mute" style="font-size:.6rem;padding:2px 8px">C2C · <?= e($li['seller_name'] ?? 'member') ?></span>
                  <?php else: ?>
                    <span class="pill" style="font-size:.6rem;padding:2px 8px">Brand</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (!$lines): ?><small style="color:var(--ink-faint)">-</small><?php endif; ?>
            </td>
            <td>
              <b><?= money($o['total']) ?></b>
              <?php if ((int)$o['credits_used'] > 0): ?>
                <br><small style="color:var(--leaf)"><?= (int)$o['credits_used'] ?> credits used</small>
              <?php endif; ?>
            </td>
            <td>
              <span class="pill <?= $o['status']==='delivered'?'pill--ok':($o['status']==='cancelled'?'pill--warn':'') ?>">
                <?= e(ucfirst($o['status'])) ?>
              </span>
            </td>
            <td>
              <div class="adm-actions">
                <form method="post" style="display:flex;gap:6px;align-items:center">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="back" value="<?= e($filter) ?>">
                  <select name="status" onchange="this.form.submit()"
                          style="padding:7px 11px;border:0;border-radius:12px;background:var(--bg-sunken);box-shadow:var(--nm-in-sm);color:var(--ink);font-family:inherit;font-size:.78rem">
                    <?php foreach (['pending','paid','shipped','delivered','cancelled'] as $s): ?>
                      <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="hidden" name="action" value="status">
                </form>
                <?php if ($owed > 0): ?>
                  <form method="post" data-confirm="Pay <?= $owed ?> seller line(s) in store credits?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                    <input type="hidden" name="back" value="<?= e($filter) ?>">
                    <button class="abtn abtn--sm abtn--go" name="action" value="payout">Pay sellers (<?= $owed ?>)</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
