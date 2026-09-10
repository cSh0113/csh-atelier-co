<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$page = 'shop';
$pageTitle = 'The Label';
$pageDesc = 'Our own pieces, each one carrying a message, each one made with recycled or reclaimed material wherever we can.';

$q      = trim($_GET['q'] ?? '');
$cause  = trim($_GET['cause'] ?? 'all');
$view   = trim($_GET['view'] ?? 'grid');
$sort   = trim($_GET['sort'] ?? 'newest');

// Add to cart straight from the grid, without opening the product page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $prodId = (int)($_POST['product_id'] ?? 0);
    $qty    = max(1, (int)($_POST['qty'] ?? 1));
    if ($prodId > 0) {
        cart_add('product', $prodId, $qty);
        header("Location: shop.php?added=1&view=" . urlencode($view) . "&cause=" . urlencode($cause) . "#prod-" . $prodId);
        exit;
    }
}

$sql = "
    SELECT p.*, c.name AS category_name, cs.name AS cause_name, cs.slug AS cause_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN causes cs ON p.cause_id = cs.id
    WHERE p.status = 'active'
";
$params = [];

if ($q !== '') {
    $sql .= " AND (p.name LIKE :q OR p.description LIKE :q OR p.message LIKE :q) ";
    $params['q'] = "%$q%";
}

if ($cause === 'remade') {
    $sql .= " AND p.is_remade = 1 ";
} elseif ($cause !== 'all' && $cause !== '') {
    $sql .= " AND (cs.slug = :cause OR cs.name LIKE :cause_name) ";
    $params['cause'] = $cause;
    $params['cause_name'] = "%$cause%";
}

switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC ";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC ";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.id DESC ";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

function get_prod_image(?string $path): string {
    $path = trim((string)$path);
    if ($path === '' || strpos($path, 'imgproxy.fourthwall.dev') !== false) {
        return 'assets/img/placeholder.svg';
    }
    if (strpos($path, 'http://') === 0) {
        return 'https://' . substr($path, 7);
    }
    return $path;
}

include __DIR__ . '/includes/header.php';
?>

<style>
.label-wrap {
  max-width: 1240px;
  margin: 32px auto 60px;
  padding: 0 16px;
  box-sizing: border-box;
}
.label-header { margin-bottom: 24px; }
.label-title {
  font-family: var(--font-heading, serif);
  font-size: clamp(2rem, 4vw, 2.8rem);
  font-weight: 800;
  margin: 0 0 10px;
}
.label-desc {
  color: var(--ink-soft, #5b5b5b);
  max-width: 680px;
  font-size: 1.02rem;
  line-height: 1.55;
  margin: 0;
}

/* The filter panel down the side. */
.filter-bar {
  background: var(--bg-raised, #fff);
  border: 1px solid var(--line, #e2ded6);
  border-radius: 16px;
  padding: 16px 20px;
  box-shadow: 0 4px 18px rgba(0,0,0,0.04);
  margin-bottom: 24px;
  box-sizing: border-box;
  width: 100%;
}
.filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}
.filter-chip {
  padding: 8px 16px;
  border-radius: 999px;
  background: var(--bg-sunken, #eceae5);
  color: var(--ink-soft, #5b5b5b);
  font-weight: 600;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.15s;
}
.filter-chip.active {
  background: var(--leaf, #3d6b4f);
  color: #fff;
}
.filter-tools {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  box-sizing: border-box;
}
.search-form {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
  margin: 0;
}
.search-input {
  width: 100%;
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid var(--line, #e2ded6);
  background: var(--bg, #f6f4f0);
  font: inherit;
  font-size: 0.9rem;
  color: inherit;
  box-sizing: border-box;
}
.view-toggles {
  display: inline-flex;
  align-items: center;
  background: var(--bg, #f6f4f0);
  padding: 3px;
  border-radius: 10px;
  border: 1px solid var(--line, #e2ded6);
  flex-shrink: 0;
}
.view-btn {
  padding: 6px 14px;
  border-radius: 7px;
  color: var(--ink-soft, #5b5b5b);
  font-weight: 700;
  font-size: 0.82rem;
  text-decoration: none;
  background: transparent;
  border: 0;
  cursor: pointer;
}
.view-btn.active {
  background: var(--leaf, #3d6b4f);
  color: #fff;
}

/* Banner pointing across to the other CSH sectors. */
.explore-card {
  background: var(--bg-raised, #fff);
  border-radius: 16px;
  padding: 22px 24px;
  border: 1px solid var(--line, #e2ded6);
  margin-bottom: 30px;
  box-shadow: 0 4px 18px rgba(0,0,0,0.03);
}
.explore-tag {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--ink-faint, #8a8a8a);
  font-weight: 800;
  margin-bottom: 4px;
}
.explore-title { margin: 0 0 4px; font-size: 1.3rem; font-family: var(--font-heading, serif); }
.explore-subtitle { color: var(--ink-soft, #5b5b5b); font-size: 0.9rem; margin: 0 0 14px; }
.explore-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
.explore-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: var(--bg-sunken, #eceae5);
  color: var(--ink, #1a1a1a);
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
  text-decoration: none;
}
.explore-btn:hover { background: var(--line, #e2ded6); }

/* Grid view, the default. */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 22px;
}
.prod-card {
  background: var(--bg-raised, #fff);
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--line, #e2ded6);
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 16px rgba(0,0,0,0.05);
  position: relative;
}
.prod-card__thumb {
  display: block;
  width: 100%;
  height: 260px;
  background: var(--bg-sunken, #eceae5);
  position: relative;
  overflow: hidden;
}
.prod-card__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.source-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: rgba(0,0,0,0.72);
  color: #fff;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 4px 8px;
  border-radius: 6px;
  backdrop-filter: blur(4px);
}
.prod-card__body {
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  flex: 1;
  justify-content: space-between;
  gap: 12px;
}
.prod-card__name {
  font-weight: 700;
  font-size: 0.98rem;
  color: inherit;
  text-decoration: none;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.prod-card__meta {
  color: var(--ink-faint, #8a8a8a);
  font-size: 0.78rem;
  margin-top: 3px;
}
.prod-card__footer {
  border-top: 1px solid var(--line, #e2ded6);
  padding-top: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.prod-card__price {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--leaf, #3d6b4f);
}
.btn-cart {
  padding: 8px 16px;
  border-radius: 999px;
  background: var(--leaf, #3d6b4f);
  color: #fff;
  font-weight: 700;
  font-size: 0.82rem;
  border: 0;
  cursor: pointer;
  text-decoration: none;
}
.btn-soldout {
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--bg-sunken, #eceae5);
  color: var(--ink-soft, #5b5b5b);
  font-weight: 700;
  font-size: 0.82rem;
}

/* List view, for when you want the detail instead of the picture. */
.products-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.prod-row {
  background: var(--bg-raised, #fff);
  border-radius: 14px;
  border: 1px solid var(--line, #e2ded6);
  padding: 14px 18px;
  display: flex;
  align-items: center;
  gap: 18px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}
.prod-row__thumb {
  width: 86px;
  height: 86px;
  border-radius: 10px;
  background: var(--bg-sunken, #eceae5);
  overflow: hidden;
  flex-shrink: 0;
  display: block;
}
.prod-row__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.prod-row__info { flex: 1; min-width: 0; }
.prod-row__name {
  font-size: 1.02rem;
  font-weight: 700;
  color: inherit;
  text-decoration: none;
  display: block;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.prod-row__actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}

@media (max-width: 650px) {
  .products-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .prod-card__thumb { height: 195px; }
  .prod-card__body { padding: 10px 12px; }
  .prod-card__name { font-size: 0.88rem; }
  .prod-card__price { font-size: 1.05rem; }
  .filter-tools { flex-direction: column; align-items: stretch; }
  .view-toggles { align-self: flex-end; }
  .explore-buttons { flex-direction: column; align-items: stretch; }
  .explore-btn { justify-content: center; }
}
</style>

<div class="label-wrap">
  <div class="label-header">
    <h1 class="label-title">The Label</h1>
    <p class="label-desc">
      Our own pieces, each one carrying a message, each one made with recycled or reclaimed material wherever we can.
    </p>
  </div>

  <?php if (isset($_GET['added'])): ?>
    <div style="background: #dcf3e4; color: #1e4620; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
      <span>Item added to your cart!</span>
      <a href="cart.php" style="color: inherit; text-decoration: underline; font-weight: 800;">View Cart &rarr;</a>
    </div>
  <?php endif; ?>

  <!-- Filters & Search -->
  <div class="filter-bar">
    <div class="filter-chips">
      <a href="shop.php?cause=all&view=<?= e($view) ?>" class="filter-chip <?= ($cause === 'all' || $cause === '') ? 'active' : '' ?>">Everything</a>
      <a href="shop.php?cause=gbv-awareness&view=<?= e($view) ?>" class="filter-chip <?= $cause === 'gbv-awareness' ? 'active' : '' ?>">GBV Awareness</a>
      <a href="shop.php?cause=mental-health&view=<?= e($view) ?>" class="filter-chip <?= $cause === 'mental-health' ? 'active' : '' ?>">Mental Health</a>
      <a href="shop.php?cause=environment&view=<?= e($view) ?>" class="filter-chip <?= $cause === 'environment' ? 'active' : '' ?>">Environment</a>
      <a href="shop.php?cause=empowerment&view=<?= e($view) ?>" class="filter-chip <?= $cause === 'empowerment' ? 'active' : '' ?>">Empowerment</a>
      <a href="shop.php?cause=remade&view=<?= e($view) ?>" class="filter-chip <?= $cause === 'remade' ? 'active' : '' ?>">Remade only</a>
    </div>

    <div class="filter-tools">
      <form method="GET" action="shop.php" class="search-form">
        <input type="hidden" name="cause" value="<?= e($cause) ?>">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search pieces or messages..." class="search-input">
      </form>

      <div class="view-toggles">
        <a href="shop.php?cause=<?= urlencode($cause) ?>&q=<?= urlencode($q) ?>&view=grid" class="view-btn <?= $view === 'grid' ? 'active' : '' ?>">Grid</a>
        <a href="shop.php?cause=<?= urlencode($cause) ?>&q=<?= urlencode($q) ?>&view=list" class="view-btn <?= $view === 'list' ? 'active' : '' ?>">List</a>
      </div>
    </div>
  </div>

  <!-- Explore collections banner -->
  <div class="explore-card">
    <div class="explore-tag">From CSH Innovations Co.</div>
    <h2 class="explore-title">Explore every collection</h2>
    <p class="explore-subtitle">Browse the full MDL'AN® catalogue and complete purchases on the official store.</p>
    <div class="explore-buttons">
      <a href="https://cshinnovations.com/collections/all" target="_blank" rel="noopener" class="explore-btn">All Products &rarr;</a>
      <a href="https://cshinnovations.com/collections/women-empowerment" target="_blank" rel="noopener" class="explore-btn">GBV Awareness &rarr;</a>
      <a href="https://cshinnovations.com/collections/mental-health" target="_blank" rel="noopener" class="explore-btn">Mental Health Awareness &rarr;</a>
      <a href="https://cshinnovations.com/collections/csh-art-ur-art" target="_blank" rel="noopener" class="explore-btn">CSH Art &#183; Ur Art &rarr;</a>
    </div>
  </div>

  <!-- Product Listing Output -->
  <?php if (empty($products)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--bg-raised, #fff); border-radius: 16px; border: 1px solid var(--line, #e2ded6);">
      <p style="font-size: 1.1rem; color: var(--ink-soft, #5b5b5b); margin-bottom: 12px;">No pieces matched your selection.</p>
      <a href="shop.php" style="display: inline-block; padding: 10px 22px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; text-decoration: none; font-weight: 700; font-size: 0.9rem;">View all pieces</a>
    </div>
  <?php elseif ($view === 'list'): ?>
    <div class="products-list">
      <?php foreach ($products as $p): ?>
        <?php $img = get_prod_image($p['image_url']); ?>
        <article class="prod-row" id="prod-<?= (int)$p['id'] ?>">
          <a href="product.php?id=<?= (int)$p['id'] ?>" class="prod-row__thumb">
            <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>">
          </a>
          <div class="prod-row__info">
            <a href="product.php?id=<?= (int)$p['id'] ?>" class="prod-row__name"><?= e($p['name']) ?></a>
            <div style="font-size: 0.8rem; color: var(--ink-faint, #8a8a8a);">
              <?= e($p['external_source'] ?: ($p['category_name'] ?? 'Official Label')) ?> &middot; ZAR
            </div>
          </div>
          <div class="prod-row__actions">
            <div class="prod-card__price">R<?= number_format((float)$p['price'], 2) ?></div>
            <?php if ((int)$p['stock'] <= 0): ?>
              <span class="btn-soldout">Sold out</span>
            <?php else: ?>
              <form method="POST" action="shop.php?view=list&cause=<?= urlencode($cause) ?>" style="margin:0;">
                <input type="hidden" name="add_to_cart" value="1">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn-cart">Add to Cart</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="products-grid">
      <?php foreach ($products as $p): ?>
        <?php $img = get_prod_image($p['image_url']); ?>
        <article class="prod-card" id="prod-<?= (int)$p['id'] ?>">
          <a href="product.php?id=<?= (int)$p['id'] ?>" class="prod-card__thumb">
            <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>">
            <span class="source-badge"><?= e($p['external_source'] ?: 'CSH Atelier') ?></span>
          </a>

          <div class="prod-card__body">
            <div>
              <a href="product.php?id=<?= (int)$p['id'] ?>" class="prod-card__name"><?= e($p['name']) ?></a>
              <div class="prod-card__meta">
                <?= e($p['external_source'] ?: ($p['category_name'] ?? 'Company-label product')) ?> &middot; ZAR
              </div>
            </div>

            <div class="prod-card__footer">
              <div class="prod-card__price">
                R<?= number_format((float)$p['price'], 2) ?>
              </div>
              <?php if ((int)$p['stock'] <= 0): ?>
                <span class="btn-soldout">Sold out</span>
              <?php else: ?>
                <form method="POST" action="shop.php?view=grid&cause=<?= urlencode($cause) ?>" style="margin:0;">
                  <input type="hidden" name="add_to_cart" value="1">
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="btn-cart">Add to Cart</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>