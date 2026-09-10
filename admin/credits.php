<?php
$pageTitle = 'Credit ledger';
require __DIR__ . '/_layout.php';
$pdo = db();

$reasons = ['all','donation','sale','purchase','refund','admin_adjust','signup_bonus'];
$filter  = in_array($_GET['reason'] ?? 'all', $reasons, true) ? ($_GET['reason'] ?? 'all') : 'all';

$sql = 'SELECT ct.*, u.name AS member, u.email
        FROM credit_transactions ct JOIN users u ON u.id = ct.user_id';
$params = [];
if ($filter !== 'all') { $sql .= ' WHERE ct.reason = ?'; $params[] = $filter; }
$sql .= ' ORDER BY ct.created_at DESC LIMIT 250';
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$earned = (int)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM credit_transactions WHERE amount > 0')->fetchColumn();
$spent  = (int)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM credit_transactions WHERE amount < 0')->fetchColumn();
$live   = (int)$pdo->query('SELECT COALESCE(SUM(credits),0) FROM users')->fetchColumn();
?>

<section class="adm-stats">
  <div class="adm-stat adm-stat--accent">
    <p class="adm-stat__k">Credits issued</p>
    <div class="adm-stat__v"><?= number_format($earned) ?></div>
    <p class="adm-stat__s">Earned from donations and sales</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Credits redeemed</p>
    <div class="adm-stat__v"><?= number_format(abs($spent)) ?></div>
    <p class="adm-stat__s">Spent against orders</p>
  </div>
  <div class="adm-stat adm-stat--warn">
    <p class="adm-stat__k">Outstanding balance</p>
    <div class="adm-stat__v"><?= number_format($live) ?></div>
    <p class="adm-stat__s">≈ <?= money(credits_to_rand($live)) ?> liability</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Credit rate</p>
    <div class="adm-stat__v"><?= (int)CREDIT_RATE ?> : R1</div>
    <p class="adm-stat__s">Credits per rand of store value</p>
  </div>
</section>

<div class="adm-filters">
  <?php foreach ($reasons as $r): ?>
    <a href="<?= url('admin/credits.php?reason=' . $r) ?>" class="<?= $filter === $r ? 'is-on' : '' ?>">
      <?= e(ucwords(str_replace('_', ' ', $r))) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="adm-card">
  <div class="adm-card__h">
    <div><h2>Every credit movement</h2><p>A full audit trail, this is what makes the loop trustworthy.</p></div>
  </div>

  <?php if (!$rows): ?>
    <div class="adm-empty">No credit activity yet.</div>
  <?php else: ?>
    <div class="adm-table__wrap">
      <table class="adm-table">
        <thead><tr><th>When</th><th>Member</th><th>Reason</th><th>Reference</th><th>Amount</th><th>Balance after</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
          <tr>
            <td><small><?= e(date('d M Y H:i', strtotime($t['created_at']))) ?></small></td>
            <td><?= e($t['member']) ?><br><small style="color:var(--ink-faint)"><?= e($t['email']) ?></small></td>
            <td>
              <span class="pill"><?= e(ucwords(str_replace('_', ' ', $t['reason']))) ?></span>
              <?php if ($t['note']): ?><br><small style="color:var(--ink-faint)"><?= e($t['note']) ?></small><?php endif; ?>
            </td>
            <td><small><?= e($t['reference'] ?? '-') ?></small></td>
            <td>
              <b style="color:<?= (int)$t['amount'] >= 0 ? 'var(--leaf)' : 'var(--clay)' ?>">
                <?= (int)$t['amount'] >= 0 ? '+' : '' ?><?= number_format((int)$t['amount']) ?>
              </b>
            </td>
            <td><?= number_format((int)$t['balance_after']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
