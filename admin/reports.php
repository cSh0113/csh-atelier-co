<?php
$pageTitle = 'Reports and safety';
require __DIR__ . '/_layout.php';
$pdo = db();

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';

    if ($act === 'status') {
        $s = $_POST['status'] ?? 'open';
        if (in_array($s, ['open','reviewed','closed'], true)) {
            $pdo->prepare('UPDATE member_reports SET status=? WHERE id=?')->execute([$s, $id]);
            audit_log('report_status', 'member_report', $id, 'Set to ' . $s);
            flash('Report marked ' . $s . '.');
        }
    } elseif ($act === 'suspend') {
        $who = (int)($_POST['who'] ?? 0);
        $pdo->prepare("UPDATE users SET status='suspended' WHERE id=? AND role<>'admin'")->execute([$who]);
        audit_log('suspend_member', 'user', $who, 'Suspended from a report');
        flash('Member suspended.');
    } elseif ($act === 'reactivate') {
        $who = (int)($_POST['who'] ?? 0);
        $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$who]);
        audit_log('reactivate_member', 'user', $who, 'Reactivated');
        flash('Member reactivated.');
    } elseif ($act === 'clear_block') {
        $b = (int)($_POST['blocker'] ?? 0); $t = (int)($_POST['blocked'] ?? 0);
        $pdo->prepare('DELETE FROM member_blocks WHERE blocker_id=? AND blocked_id=?')->execute([$b, $t]);
        flash('Block removed.');
    }
    redirect('admin/reports.php?tab=' . urlencode((string)($_POST['back'] ?? 'reports')));
}

$tab = $_GET['tab'] ?? 'reports';

$counts = [
  'open'    => (int)$pdo->query("SELECT COUNT(*) FROM member_reports WHERE status='open'")->fetchColumn(),
  'blocks'  => (int)$pdo->query('SELECT COUNT(*) FROM member_blocks')->fetchColumn(),
  'ratings' => (int)$pdo->query('SELECT COUNT(*) FROM member_ratings')->fetchColumn(),
];
?>

<section class="adm-stats">
  <div class="adm-stat adm-stat--warn">
    <p class="adm-stat__k">Open reports</p>
    <div class="adm-stat__v"><?= $counts['open'] ?></div>
    <p class="adm-stat__s">Needing a decision</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Active blocks</p>
    <div class="adm-stat__v"><?= $counts['blocks'] ?></div>
    <p class="adm-stat__s">Member to member</p>
  </div>
  <div class="adm-stat adm-stat--accent">
    <p class="adm-stat__k">Ratings given</p>
    <div class="adm-stat__v"><?= $counts['ratings'] ?></div>
    <p class="adm-stat__s">Trust and communication scores</p>
  </div>
  <div class="adm-stat">
    <p class="adm-stat__k">Suspended</p>
    <div class="adm-stat__v"><?= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='suspended'")->fetchColumn() ?></div>
    <p class="adm-stat__s">Currently blocked from signing in</p>
  </div>
</section>

<div class="adm-filters">
  <a href="<?= url('admin/reports.php?tab=reports') ?>" class="<?= $tab==='reports'?'is-on':'' ?>">Reports</a>
  <a href="<?= url('admin/reports.php?tab=blocks') ?>"  class="<?= $tab==='blocks'?'is-on':'' ?>">Blocks</a>
  <a href="<?= url('admin/reports.php?tab=ratings') ?>" class="<?= $tab==='ratings'?'is-on':'' ?>">Ratings</a>
</div>

<?php if ($tab === 'reports'):
  $rows = $pdo->query(
    "SELECT r.*, u1.name reporter, u2.name reported, u2.status reported_status, u2.id reported_id
     FROM member_reports r
     JOIN users u1 ON u1.id = r.reporter_id
     JOIN users u2 ON u2.id = r.reported_id
     ORDER BY FIELD(r.status,'open','reviewed','closed'), r.created_at DESC LIMIT 200")->fetchAll(); ?>

<div class="adm-card">
  <div class="adm-card__h">
    <div><h2>Member reports</h2><p>Reports raised from chats, profiles and listings.</p></div>
  </div>
  <?php if (!$rows): ?>
    <div class="adm-empty">No reports. That is a good sign.</div>
  <?php else: ?>
  <div class="adm-table__wrap">
    <table class="adm-table">
      <thead><tr><th>Reported</th><th>By</th><th>Reason</th><th>Details</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <b><?= e($r['reported']) ?></b><br>
            <span class="pill <?= $r['reported_status']==='suspended'?'pill--warn':'pill--ok' ?>">
              <?= e(ucfirst($r['reported_status'])) ?></span>
          </td>
          <td><?= e($r['reporter']) ?><br>
              <small style="color:var(--ink-faint)"><?= e(date('d M Y', strtotime($r['created_at']))) ?></small></td>
          <td><span class="pill"><?= e(ucfirst($r['reason'])) ?></span></td>
          <td style="max-width:340px"><small><?= e(mb_substr($r['details'], 0, 260)) ?></small></td>
          <td>
            <span class="pill <?= $r['status']==='open'?'pill--warn':($r['status']==='closed'?'pill--mute':'') ?>">
              <?= e(ucfirst($r['status'])) ?></span>
          </td>
          <td>
            <div class="adm-actions">
              <form method="post" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="back" value="reports">
                <input type="hidden" name="status" value="reviewed">
                <button class="abtn abtn--sm" name="action" value="status">Mark reviewed</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="back" value="reports">
                <input type="hidden" name="status" value="closed">
                <button class="abtn abtn--sm" name="action" value="status">Close</button>
              </form>
              <?php if ($r['reported_status'] !== 'suspended'): ?>
                <form method="post" style="display:inline" data-confirm="Suspend this member?">
                  <?= csrf_field() ?><input type="hidden" name="who" value="<?= (int)$r['reported_id'] ?>">
                  <input type="hidden" name="back" value="reports">
                  <button class="abtn abtn--sm abtn--danger" name="action" value="suspend">Suspend</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?><input type="hidden" name="who" value="<?= (int)$r['reported_id'] ?>">
                  <input type="hidden" name="back" value="reports">
                  <button class="abtn abtn--sm abtn--go" name="action" value="reactivate">Reactivate</button>
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

<?php elseif ($tab === 'blocks'):
  $rows = $pdo->query(
    "SELECT b.*, u1.name blocker, u2.name blocked
     FROM member_blocks b JOIN users u1 ON u1.id=b.blocker_id JOIN users u2 ON u2.id=b.blocked_id
     ORDER BY b.created_at DESC LIMIT 200")->fetchAll(); ?>
<div class="adm-card">
  <div class="adm-card__h"><div><h2>Blocks</h2><p>Who has blocked whom. Repeated blocks against one member are a warning sign.</p></div></div>
  <?php if (!$rows): ?><div class="adm-empty">No blocks recorded.</div><?php else: ?>
  <div class="adm-table__wrap"><table class="adm-table">
    <thead><tr><th>Blocker</th><th>Blocked</th><th>When</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $b): ?>
      <tr>
        <td><?= e($b['blocker']) ?></td>
        <td><b><?= e($b['blocked']) ?></b></td>
        <td><small><?= e(date('d M Y', strtotime($b['created_at']))) ?></small></td>
        <td>
          <form method="post" data-confirm="Remove this block?">
            <?= csrf_field() ?>
            <input type="hidden" name="blocker" value="<?= (int)$b['blocker_id'] ?>">
            <input type="hidden" name="blocked" value="<?= (int)$b['blocked_id'] ?>">
            <input type="hidden" name="back" value="blocks">
            <button class="abtn abtn--sm" name="action" value="clear_block">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<?php else:
  $rows = $pdo->query(
    "SELECT r.*, u1.name rater, u2.name rated
     FROM member_ratings r JOIN users u1 ON u1.id=r.rater_id JOIN users u2 ON u2.id=r.rated_id
     ORDER BY r.created_at DESC LIMIT 200")->fetchAll(); ?>
<div class="adm-card">
  <div class="adm-card__h"><div><h2>Member ratings</h2><p>Buyer and seller trust scores shown on public profiles.</p></div></div>
  <?php if (!$rows): ?><div class="adm-empty">No ratings yet.</div><?php else: ?>
  <div class="adm-table__wrap"><table class="adm-table">
    <thead><tr><th>Rated member</th><th>By</th><th>Communication</th><th>Trust</th><th>Comment</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['rated']) ?></b></td>
        <td><?= e($r['rater']) ?><br><small style="color:var(--ink-faint)"><?= e(date('d M Y', strtotime($r['created_at']))) ?></small></td>
        <td><?= (int)$r['communication'] ?>/5</td>
        <td><?= (int)$r['trust'] ?>/5</td>
        <td><small><?= e(mb_substr((string)$r['comment'], 0, 200)) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
