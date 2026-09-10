<?php
$pageTitle = 'Brand products';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pdo = db();
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = (int)($_GET['category'] ?? 0);
$minPrice = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
$maxPrice = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        audit_log('delete', 'product', $id, 'Brand product deleted');
        flash('Product deleted.');
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $pdo->prepare("UPDATE products SET status = IF(status='active','hidden','active') WHERE id=?")->execute([$id]);
        audit_log('toggle', 'product', $id, 'Brand product visibility changed');
        flash('Product visibility updated.');
    }
    redirect('admin/products.php');
}

require __DIR__ . '/_layout.php';

$where = ['1=1']; $params = [];
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.message LIKE ?)'; $needle = '%'.$search.'%'; array_push($params, $needle, $needle, $needle); }
if (in_array($statusFilter, ['active','hidden'], true)) { $where[] = 'p.status=?'; $params[] = $statusFilter; }
if ($categoryFilter > 0) { $where[] = 'p.category_id=?'; $params[] = $categoryFilter; }
if ($minPrice !== null) { $where[] = 'p.price>=?'; $params[] = $minPrice; }
if ($maxPrice !== null) { $where[] = 'p.price<=?'; $params[] = $maxPrice; }
$stmt = $pdo->prepare(
  'SELECT p.*, c.name AS category, cz.name AS cause
   FROM products p
   LEFT JOIN categories c  ON c.id  = p.category_id
   LEFT JOIN causes     cz ON cz.id = p.cause_id
   WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll();
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
?>

<div class="adm-card">
  <div class="adm-card__h">
    <div><h2>Brand pieces (B2C)</h2><p>Clothing CSH designs and sells, including pieces remade from donations.</p></div>
    <a class="abtn abtn--go" href="<?= url('admin/product-form.php') ?>">+ New product</a>
  </div>

  <form method="get" class="adm-product-filters">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search products, descriptions or messages">
    <select name="status"><option value="">All statuses</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Live</option><option value="hidden" <?= $statusFilter==='hidden'?'selected':'' ?>>Hidden</option></select>
    <select name="category"><option value="0">All categories</option><?php foreach ($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter===(int)$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select>
    <input type="number" name="min" step="0.01" min="0" value="<?= $minPrice !== null ? e($minPrice) : '' ?>" placeholder="Min R">
    <input type="number" name="max" step="0.01" min="0" value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>" placeholder="Max R">
    <button class="abtn abtn--sm abtn--go" type="submit">Search</button>
    <?php if ($search !== '' || $statusFilter !== '' || $categoryFilter > 0 || $minPrice !== null || $maxPrice !== null): ?><a class="abtn abtn--sm" href="<?= url('admin/products.php') ?>">Clear</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="adm-empty">No products yet. Add your first piece.</div>
  <?php else: ?>
    <div class="adm-table__wrap">
      <table class="adm-table">
        <thead><tr><th>Product</th><th>Cause</th><th>Category</th><th>Price</th><th>Stock</th><th>State</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
          <tr>
            <td style="min-width:230px">
              <div class="adm-cellitem">
                <img class="adm-thumb" src="<?= e(img_or_placeholder($p['image_url'], (string)$p['id'])) ?>" alt="">
                <span>
                  <b><?= e($p['name']) ?></b>
                  <?php if ((int)$p['is_remade'] === 1): ?>
                    <small style="color:var(--leaf)">Remade from donations</small>
                  <?php endif; ?>
                  <?php if ((int)$p['recycled_pct'] > 0): ?>
                    <small><?= (int)$p['recycled_pct'] ?>% recycled material</small>
                  <?php endif; ?>
                </span>
              </div>
            </td>
            <td><?= $p['cause'] ? '<span class="pill">' . e($p['cause']) . '</span>' : '-' ?></td>
            <td><?= e($p['category'] ?? '-') ?></td>
            <td><?= money($p['price']) ?></td>
            <td><?= (int)$p['stock'] ?></td>
            <td>
              <span class="pill <?= $p['status']==='active' ? 'pill--ok' : 'pill--mute' ?>">
                <?= $p['status']==='active' ? 'Live' : 'Hidden' ?>
              </span>
            </td>
            <td>
              <div class="adm-actions">
                <a class="abtn abtn--sm" href="<?= url('admin/product-form.php?id=' . (int)$p['id']) ?>">Edit</a>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="abtn abtn--sm" name="action" value="toggle"><?= $p['status']==='active' ? 'Hide' : 'Show' ?></button>
                </form>
                <form method="post" style="display:inline" data-confirm="Delete this product permanently?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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

<?php require __DIR__ . '/_layout_end.php'; ?>