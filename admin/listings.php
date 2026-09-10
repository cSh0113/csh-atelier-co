<?php
$pageTitle = 'Member listings';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $st = $pdo->prepare('SELECT l.*, u.name AS seller FROM listings l JOIN users u ON u.id=l.seller_id WHERE l.id=?');
    $st->execute([$id]);
    $listing = $st->fetch();

    if (!$listing) {
        flash('That listing no longer exists.', 'error');
    } elseif ($action === 'approve') {
        $pdo->prepare("UPDATE listings SET status='active', reject_reason=NULL WHERE id=?")->execute([$id]);
        audit_log('approve', 'listing', $id, $listing['title']);
        flash('"' . $listing['title'] . '" is now live on the marketplace.');
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        $pdo->prepare("UPDATE listings SET status='removed', reject_reason=? WHERE id=?")
            ->execute([$reason !== '' ? $reason : 'Did not meet listing guidelines.', $id]);
        audit_log('reject', 'listing', $id, $reason);
        flash('Listing rejected and the seller can see the reason.');
    } elseif ($action === 'relist') {
        $pdo->prepare("UPDATE listings SET status='pending', reject_reason=NULL WHERE id=?")->execute([$id]);
        audit_log('relist', 'listing', $id, $listing['title']);
        flash('Listing sent back to the review queue.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM listings WHERE id=?')->execute([$id]);
        audit_log('delete', 'listing', $id, $listing['title']);
        flash('Listing deleted permanently.');
    }
    redirect('admin/listings.php' . (isset($_POST['back']) ? '?status=' . urlencode((string)$_POST['back']) : ''));
}

/* ---------- filter ---------- */
$allowed = ['all','pending','active','sold','removed'];
$status  = in_array($_GET['status'] ?? 'pending', $allowed, true) ? ($_GET['status'] ?? 'pending') : 'pending';

$sql = 'SELECT l.*, u.name AS seller, u.email AS seller_email, c.name AS category
        FROM listings l
        JOIN users u ON u.id = l.seller_id
        LEFT JOIN categories c ON c.id = l.category_id';
$params = [];
if ($status !== 'all') { $sql .= ' WHERE l.status = ?'; $params[] = $status; }
$sql .= ' ORDER BY l.created_at DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$counts = [];
foreach ($pdo->query("SELECT status, COUNT(*) n FROM listings GROUP BY status") as $r) $counts[$r['status']] = (int)$r['n'];
$counts['all'] = array_sum($counts);

require __DIR__ . '/_layout.php';
?>

<div class="adm-filters">
  <?php foreach ($allowed as $s): ?>
    <a href="<?= url('admin/listings.php?status=' . $s) ?>" class="<?= $status === $s ? 'is-on' : '' ?>">
      <?= e(ucfirst($s)) ?> <span style="opacity:.6">(<?= (int)($counts[$s] ?? 0) ?>)</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="adm-card">
  <div class="adm-card__h">
    <div>
      <h2><?= e(ucfirst($status)) ?> listings</h2>
      <p>Consumer-to-consumer items. Approving one publishes it to the marketplace.</p>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="adm-empty">No listings in this state.</div>
  <?php else: ?>
    <div class="adm-table__wrap">
      <table class="adm-table">
        <thead>
          <tr><th>Item</th><th>Seller</th><th>Category</th><th>Price</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $l): ?>
          <tr id="l<?= (int)$l['id'] ?>">
            <td style="min-width:230px">
              <div class="adm-cellitem">
                <img class="adm-thumb" src="<?= e(img_or_placeholder($l['image_url'], (string)$l['id'])) ?>" alt="">
                <span>
                  <b><?= e($l['title']) ?></b>
                  <small><?= e(condition_label($l['item_condition'])) ?><?= $l['item_size'] ? ' · Size ' . e($l['item_size']) : '' ?></small>
                  <?php if ($l['reject_reason']): ?>
                    <small style="color:var(--clay)">Reason: <?= e($l['reject_reason']) ?></small>
                  <?php endif; ?>
                </span>
              </div>
            </td>
            <td><?= e($l['seller']) ?><br><small style="color:var(--ink-faint)"><?= e($l['seller_email']) ?></small></td>
            <td><?= e($l['category'] ?? '-') ?></td>
            <td><?= money($l['price']) ?></td>
            <td>
              <span class="pill <?= $l['status']==='active'?'pill--ok':($l['status']==='pending'?'pill--warn':'pill--mute') ?>">
                <?= e(ucfirst($l['status'])) ?>
              </span>
            </td>
            <td>
              <div class="adm-actions">
                <button class="abtn abtn--sm" type="button" data-listing-detail="<?= (int)$l['id'] ?>">View details</button>
                <?php if ($l['status'] !== 'active'): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>">
                    <button class="abtn abtn--sm abtn--go" name="action" value="approve">Approve</button>
                  </form>
                <?php endif; ?>
                <?php if ($l['status'] === 'pending' || $l['status'] === 'active'): ?>
                  <button class="abtn abtn--sm abtn--danger" type="button" data-listing-reject="<?= (int)$l['id'] ?>">Reject</button>
                <?php endif; ?>

                <?php if ($l['status'] === 'removed'): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                    <input type="hidden" name="back" value="<?= e($status) ?>">
                    <button class="abtn abtn--sm" name="action" value="relist">Relist</button>
                  </form>
                <?php endif; ?>

                <form method="post" style="display:inline" data-confirm="Delete this listing permanently?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                  <input type="hidden" name="back" value="<?= e($status) ?>">
                  <button class="abtn abtn--sm abtn--danger" name="action" value="delete">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="adm-modal" data-listing-modal aria-hidden="true">
  <div class="adm-modal__box" role="dialog" aria-modal="true" aria-labelledby="listing-modal-title">
    <button class="adm-modal__close" type="button" data-listing-close aria-label="Close">&times;</button>
    <img data-listing-image class="adm-modal__image" src="" alt="">
    <h3 id="listing-modal-title" data-listing-title></h3>
    <p data-listing-description class="muted"></p>
    <div class="adm-modal__meta" data-listing-meta></div>
    <form method="post" data-listing-reject-form class="adm-modal__reject">
      <?= csrf_field() ?><input type="hidden" name="id" data-listing-id><input type="hidden" name="back" value="<?= e($status) ?>">
      <input type="hidden" name="action" value="reject">
      <label for="reject_reason">Rejection reason</label>
      <textarea id="reject_reason" name="reason" rows="3" required placeholder="Explain what needs to be changed before approval."></textarea>
      <button class="abtn abtn--danger" type="submit">Reject listing</button>
    </form>
  </div>
</div>

<script>
const listingData = <?= json_encode(array_map(static function($l) {
  return ['id'=>(int)$l['id'],'title'=>$l['title'],'description'=>$l['description'],'image'=>img_or_placeholder($l['image_url'], (string)$l['id']),'seller'=>$l['seller'],'condition'=>condition_label($l['item_condition']),'size'=>$l['item_size'],'price'=>money($l['price']),'category'=>$l['category']];
}, $rows), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
(function(){
  const modal=document.querySelector('[data-listing-modal]'); if(!modal) return;
  const byId=id=>listingData.find(x=>x.id===Number(id));
  const open=(item,reject)=>{ if(!item)return; modal.querySelector('[data-listing-title]').textContent=item.title; modal.querySelector('[data-listing-description]').textContent=item.description||'No description provided.'; modal.querySelector('[data-listing-image]').src=item.image; modal.querySelector('[data-listing-meta]').textContent=[item.seller,item.category,item.condition,item.size?'Size '+item.size:'',item.price].filter(Boolean).join(' · '); modal.querySelector('[data-listing-id]').value=item.id; modal.querySelector('[data-listing-reject-form]').style.display=reject?'grid':'none'; modal.setAttribute('aria-hidden','false'); modal.classList.add('is-open'); };
  document.querySelectorAll('[data-listing-detail]').forEach(b=>b.onclick=()=>open(byId(b.dataset.listingDetail),false));
  document.querySelectorAll('[data-listing-reject]').forEach(b=>b.onclick=()=>open(byId(b.dataset.listingReject),true));
  document.querySelector('[data-listing-close]').onclick=()=>{modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');};
})();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
