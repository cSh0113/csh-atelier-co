<?php
$pageTitle = 'Members';
require __DIR__ . '/_layout.php';
$pdo = db();
$me  = current_user();

/* ---------- RBAC actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';

    if ($id === (int)$me['id']) {
        flash('You cannot change your own account from here.', 'error');
        redirect('admin/users.php');
    }

    $st = $pdo->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$id]);
    $u = $st->fetch();

    if (!$u) {
        flash('Member not found.', 'error');
    } elseif ($act === 'promote') {
        $pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$id]);
        flash($u['name'] . ' is now an administrator.');
    } elseif ($act === 'demote') {
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($admins <= 1) {
            flash('You cannot remove the last administrator.', 'error');
        } else {
            $pdo->prepare("UPDATE users SET role='member' WHERE id=?")->execute([$id]);
            flash($u['name'] . ' is now a member.');
        }
    } elseif ($act === 'suspend') {
        $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$id]);
        flash($u['name'] . ' suspended, they can no longer sign in.');
    } elseif ($act === 'activate') {
        $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
        flash($u['name'] . ' reactivated.');
    } elseif ($act === 'credit') {
        $amt  = (int)($_POST['amount'] ?? 0);
        $note = trim($_POST['note'] ?? '') ?: 'Manual adjustment by admin';
        if ($amt === 0) {
            flash('Enter an amount above zero.', 'error');
        } else {
            try {
                adjust_credits($id, $amt, 'admin_adjust', null, $note);
                flash(($amt > 0 ? 'Added ' : 'Deducted ') . abs($amt) . ' credits for ' . $u['name'] . '.');
            } catch (Throwable $ex) {
                flash($ex->getMessage(), 'error');
            }
        }
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        flash('Account deleted.');
    }
    redirect('admin/users.php');
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $st = $pdo->prepare('SELECT * FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 200');
    $st->execute(['%' . $q . '%', '%' . $q . '%']);
} else {
    $st = $pdo->query('SELECT * FROM users ORDER BY created_at DESC LIMIT 200');
}
$rows = $st->fetchAll();

/* What each member has been up to. */
$stats = [];
foreach ($pdo->query('SELECT seller_id, COUNT(*) n FROM listings GROUP BY seller_id') as $r) $stats[$r['seller_id']]['listings'] = (int)$r['n'];
foreach ($pdo->query('SELECT user_id, COUNT(*) n FROM donations GROUP BY user_id') as $r) $stats[$r['user_id']]['donations'] = (int)$r['n'];
?>

<div class="adm-card">
  <div class="adm-card__h">
    <div>
      <h2>Members &amp; roles</h2>
      <p>Role based access control: members trade, administrators govern the platform.</p>
    </div>
    <form method="get" style="display:flex;gap:8px">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or email"
             style="padding:9px 15px;border:0;border-radius:999px;background:var(--bg-sunken);box-shadow:var(--nm-in-sm);color:var(--ink);font-family:inherit;font-size:.84rem;outline:none">
      <button class="abtn abtn--sm">Search</button>
    </form>
  </div>

  <?php if (!$rows): ?>
    <div class="adm-empty">No members found.</div>
  <?php else: ?>
    <div class="adm-table__wrap">
      <table class="adm-table">
        <thead><tr><th>Member</th><th>Role</th><th>Activity</th><th>Credits</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $u):
          $isMe = (int)$u['id'] === (int)$me['id']; ?>
          <tr>
            <td>
              <div class="adm-cellitem">
                <span class="adm-who__av" style="width:38px;height:38px"><?= e(strtoupper(substr($u['name'],0,1))) ?></span>
                <span>
                  <b><?= e($u['name']) ?><?= $isMe ? ' <small style="color:var(--leaf)">(you)</small>' : '' ?></b>
                  <small><?= e($u['email']) ?></small>
                  <?php if ($u['city']): ?><small><?= e($u['city']) ?><?= $u['province'] ? ', ' . e($u['province']) : '' ?></small><?php endif; ?>
                </span>
              </div>
            </td>
            <td><span class="pill <?= $u['role']==='admin' ? 'pill--ok' : '' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
            <td>
              <small>
                <?= (int)($stats[$u['id']]['listings'] ?? 0) ?> listing(s)<br>
                <?= (int)($stats[$u['id']]['donations'] ?? 0) ?> donation(s)<br>
                <?= (int)$u['items_diverted'] ?> item(s) diverted
              </small>
            </td>
            <td>
              <b><?= number_format((int)$u['credits']) ?></b><br>
              <small style="color:var(--ink-faint)">≈ <?= money(credits_to_rand((int)$u['credits'])) ?></small>
            </td>
            <td>
              <span class="pill <?= $u['status']==='active' ? 'pill--ok' : 'pill--warn' ?>"><?= e(ucfirst($u['status'])) ?></span>
            </td>
            <td>
              <?php if ($isMe): ?>
                <small style="color:var(--ink-faint)">-</small>
              <?php else: ?>
                <div class="adm-actions">
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <?php if ($u['role'] === 'member'): ?>
                      <button class="abtn abtn--sm" name="action" value="promote">Make admin</button>
                    <?php else: ?>
                      <button class="abtn abtn--sm" name="action" value="demote">Make member</button>
                    <?php endif; ?>
                  </form>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <?php if ($u['status'] === 'active'): ?>
                      <button class="abtn abtn--sm abtn--danger" name="action" value="suspend">Suspend</button>
                    <?php else: ?>
                      <button class="abtn abtn--sm abtn--go" name="action" value="activate">Reactivate</button>
                    <?php endif; ?>
                  </form>
                  <form method="post" style="display:inline"
                        onsubmit="this.amount.value = prompt('Credits to add (use a negative number to deduct):','50') || 0; return parseInt(this.amount.value,10) !== 0;">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="amount" value="0">
                    <button class="abtn abtn--sm" name="action" value="credit">Credits</button>
                  </form>
                  <form method="post" style="display:inline" data-confirm="Delete this account and all their data?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="abtn abtn--sm abtn--danger" name="action" value="delete">Delete</button>
                  </form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
