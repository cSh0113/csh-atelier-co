<?php
$pageTitle = 'Donations';
require __DIR__ . '/_layout.php';

function excerpt_txt(?string $s, int $n = 70): string {
    $s = trim((string)$s);
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '...' : $s;
}

$pdo = db();
$perItem = (int)setting('credits_per_item', CREDITS_PER_ITEM);

/* ---------- process a donation ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id      = (int)($_POST['id'] ?? 0);
    $outcome = $_POST['outcome'] ?? '';
    $credits = max(0, (int)($_POST['credits'] ?? 0));
    $note    = trim($_POST['admin_note'] ?? '');

    $st = $pdo->prepare('SELECT * FROM donations WHERE id = ?');
    $st->execute([$id]);
    $don = $st->fetch();

    if (!$don) {
        flash('Donation not found.', 'error');
    } elseif (!in_array($outcome, ['remade','resold','recycled','declined'], true)) {
        flash('Pick a valid outcome.', 'error');
    } elseif ($don['outcome'] !== 'pending') {
        flash('That donation has already been processed.', 'error');
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE donations
                           SET outcome=?, credits_awarded=?, admin_note=?, processed_at=NOW()
                           WHERE id=?")
                ->execute([$outcome, $credits, $note !== '' ? $note : null, $id]);

            if ($credits > 0 && $outcome !== 'declined') {
                adjust_credits(
                    (int)$don['user_id'], $credits, 'donation', $don['reference'],
                    $don['item_count'] . ' item(s), ' . $outcome
                );
                // impact counter on the member profile
                $pdo->prepare('UPDATE users SET items_diverted = items_diverted + ? WHERE id = ?')
                    ->execute([(int)$don['item_count'], (int)$don['user_id']]);
            }

            $pdo->commit();
            flash('Donation ' . $don['reference'] . ' processed' .
                  ($credits > 0 ? ' and ' . $credits . ' credits awarded.' : '.'));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Could not process: ' . $ex->getMessage(), 'error');
        }
    }
    redirect('admin/donations.php?outcome=' . urlencode((string)($_POST['back'] ?? 'pending')));
}

/* ---------- filter ---------- */
$allowed = ['all','pending','remade','resold','recycled','declined'];
$filter  = in_array($_GET['outcome'] ?? 'pending', $allowed, true) ? ($_GET['outcome'] ?? 'pending') : 'pending';

$sql = 'SELECT d.*, u.name AS member, u.email
        FROM donations d JOIN users u ON u.id = d.user_id';
$params = [];
if ($filter !== 'all') { $sql .= ' WHERE d.outcome = ?'; $params[] = $filter; }
$sql .= ' ORDER BY d.created_at DESC LIMIT 200';
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$counts = [];
foreach ($pdo->query('SELECT outcome, COUNT(*) n FROM donations GROUP BY outcome') as $r) $counts[$r['outcome']] = (int)$r['n'];
$counts['all'] = array_sum($counts);
?>

<div class="adm-filters">
  <?php foreach ($allowed as $s): ?>
    <a href="<?= url('admin/donations.php?outcome=' . $s) ?>" class="<?= $filter === $s ? 'is-on' : '' ?>">
      <?= e(ucfirst($s)) ?> <span style="opacity:.6">(<?= (int)($counts[$s] ?? 0) ?>)</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="adm-card">
  <div class="adm-card__h">
    <div>
      <h2>Donated garments</h2>
      <p>Decide what happens to each bag: remake it, resell it, recycle the fibre, or decline it.
         Awarding credits pays the member back, <?= $perItem ?> credits per item is the default.</p>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="adm-empty">Nothing here yet.</div>
  <?php else: ?>
    <div class="adm-table__wrap">
      <table class="adm-table">
        <thead>
          <tr><th>Reference</th><th>Member</th><th>Items</th><th>Method</th><th>Outcome</th><th style="min-width:330px">Process</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $d): ?>
          <tr>
            <td>
              <b><?= e($d['reference']) ?></b><br>
              <small style="color:var(--ink-faint)"><?= e(date('d M Y', strtotime($d['created_at']))) ?></small>
              <?php if ($d['description']): ?>
                <br><small style="color:var(--ink-soft)"><?= e(excerpt_txt($d['description'], 60)) ?></small>
              <?php endif; ?>
            </td>
            <td><?= e($d['member']) ?><br><small style="color:var(--ink-faint)"><?= e($d['email']) ?></small></td>
            <td><b><?= (int)$d['item_count'] ?></b></td>
            <td><span class="pill pill--mute"><?= $d['dropoff_method'] === 'courier' ? 'Collect' : 'Dropoff' ?></span></td>
            <td>
              <span class="pill <?= $d['outcome']==='pending'?'pill--warn':($d['outcome']==='declined'?'pill--mute':'pill--ok') ?>">
                <?= e(ucfirst($d['outcome'])) ?>
              </span>
              <?php if ((int)$d['credits_awarded'] > 0): ?>
                <br><small style="color:var(--leaf)">+<?= (int)$d['credits_awarded'] ?> credits</small>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($d['outcome'] === 'pending'): ?>
                <form method="post" style="display:grid;gap:8px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                  <input type="hidden" name="back" value="<?= e($filter) ?>">
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <select name="outcome" class="afield" style="flex:1;min-width:120px;padding:8px 12px;border:0;border-radius:12px;background:var(--bg-sunken);box-shadow:var(--nm-in-sm);color:var(--ink);font-family:inherit;font-size:.82rem">
                      <option value="remade">Remade</option>
                      <option value="resold">Resold</option>
                      <option value="recycled">Recycled</option>
                      <option value="declined">Declined</option>
                    </select>
                    <input type="number" name="credits" min="0" step="1"
                           value="<?= (int)$d['item_count'] * $perItem ?>"
                           style="width:96px;padding:8px 12px;border:0;border-radius:12px;background:var(--bg-sunken);box-shadow:var(--nm-in-sm);color:var(--ink);font-family:inherit;font-size:.82rem"
                           title="Credits to award">
                  </div>
                  <input type="text" name="admin_note" placeholder="Note (optional)" maxlength="200"
                         style="padding:8px 12px;border:0;border-radius:12px;background:var(--bg-sunken);box-shadow:var(--nm-in-sm);color:var(--ink);font-family:inherit;font-size:.82rem">
                  <button class="abtn abtn--sm abtn--go" type="submit">Process &amp; award</button>
                </form>
              <?php else: ?>
                <small style="color:var(--ink-faint)">
                  Processed <?= $d['processed_at'] ? e(date('d M Y', strtotime($d['processed_at']))) : '' ?>
                  <?= $d['admin_note'] ? '<br>' . e($d['admin_note']) : '' ?>
                </small>
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
