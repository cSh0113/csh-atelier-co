<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$pageTitle = 'The Marketplace';

$q        = trim($_GET['q'] ?? '');
$catSlug  = trim($_GET['category'] ?? '');
$cond     = trim($_GET['condition'] ?? '');
$sort     = trim($_GET['sort'] ?? 'newest');
$minPrice = isset($_GET['min']) && is_numeric($_GET['min']) ? (float)$_GET['min'] : null;
$maxPrice = isset($_GET['max']) && is_numeric($_GET['max']) ? (float)$_GET['max'] : null;
$location = trim($_GET['location'] ?? '');
$size     = trim($_GET['size'] ?? '');
$brand    = trim($_GET['brand'] ?? '');
$colour   = trim($_GET['colour'] ?? '');

$sql = "
    SELECT l.*, 
           u.name AS seller_name, 
           u.avatar_url AS seller_avatar,
           c.name AS category_name
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.status = 'active'
";
$params = [];

if ($q !== '') {
    $sql .= " AND (l.title LIKE :q OR l.description LIKE :q OR l.brand LIKE :q) ";
    $params['q'] = "%$q%";
}
if ($catSlug !== '') {
    $sql .= " AND c.slug = :cat ";
    $params['cat'] = $catSlug;
}
if ($cond !== '') {
    $sql .= " AND l.item_condition = :cond ";
    $params['cond'] = $cond;
}
if ($minPrice !== null) {
    $sql .= " AND l.price >= :minPrice ";
    $params['minPrice'] = $minPrice;
}
if ($maxPrice !== null) {
    $sql .= " AND l.price <= :maxPrice ";
    $params['maxPrice'] = $maxPrice;
}
if ($size !== '') {
    $sql .= " AND l.item_size LIKE :size ";
    $params['size'] = "%$size%";
}
if ($brand !== '') {
    $sql .= " AND l.brand LIKE :brand ";
    $params['brand'] = "%$brand%";
}
if ($colour !== '') {
    $sql .= " AND l.colour LIKE :colour ";
    $params['colour'] = "%$colour%";
}
if ($location !== '') {
    $sql .= " AND (u.city LIKE :loc OR u.province LIKE :loc) ";
    $params['loc'] = "%$location%";
}

switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY l.price ASC ";
        break;
    case 'price_desc':
        $sql .= " ORDER BY l.price DESC ";
        break;
    case 'views':
        $sql .= " ORDER BY l.views DESC ";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY l.id DESC ";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Image paths come in from a few different places and Safari on iPhone is
// the fussiest about them, so everything gets straightened out here first.
function format_listing_image(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') {
        return 'assets/img/placeholder.svg';
    }
    // Backslashes from Windows paths become forward slashes.
    $url = str_replace('\\', '/', $url);

    // Drop my own domain off the front. A path from the root loads over
    // whatever the page is already using, so the browser never blocks it.
    $host = $_SERVER['HTTP_HOST'] ?? 'cshatelier.ct.ws';
    $url = preg_replace('#^https?://' . preg_quote($host, '#') . '#i', '', $url);

    // Anything outside the site gets forced onto https.
    if (strpos($url, 'http://') === 0) {
        $url = 'https://' . substr($url, 7);
    }

    // Anything left over gets a leading slash so it starts at the root.
    if (!preg_match('#^https?://#i', $url) && strpos($url, '/') !== 0) {
        $url = '/' . $url;
    }

    return $url;
}

include __DIR__ . '/includes/header.php';
?>

<style>
.csh-market-grid {
  display: grid !important;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)) !important;
  gap: 24px !important;
}
.csh-card {
  background: var(--bg-raised, #fff) !important;
  border-radius: var(--r-lg, 16px) !important;
  overflow: hidden !important;
  box-shadow: var(--nm-out, 0 4px 18px rgba(0,0,0,0.06)) !important;
  border: 1px solid var(--line, #e2ded6) !important;
  display: flex !important;
  flex-direction: column !important;
  position: relative !important;
  min-width: 0 !important;
}
.csh-card__thumb {
  display: block !important;
  width: 100% !important;
  height: 250px !important;
  min-height: 250px !important;
  position: relative !important;
  overflow: hidden !important;
  background: var(--bg-sunken, #eceae5) !important;
  text-decoration: none !important;
  flex-shrink: 0 !important;
}
.csh-card__thumb img {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
  display: block !important;
}
.csh-card__body {
  padding: 12px 14px 14px 14px !important;
  display: flex !important;
  flex-direction: column !important;
  flex: 1 !important;
  justify-content: space-between !important;
  background: inherit !important;
  gap: 6px !important;
}
.csh-card__title {
  color: inherit !important;
  text-decoration: none !important;
  font-weight: 700 !important;
  font-size: 0.96rem !important;
  display: block !important;
  margin-bottom: 2px !important;
  line-height: 1.3 !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
}
.csh-card__meta {
  color: var(--ink-faint, #8a8a8a) !important;
  font-size: 0.78rem !important;
  margin-bottom: 4px !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
}
/* Price gets its own full width row. Squeezed next to anything else the
   rounded corner clips the last digit off. */
.csh-card__price {
  font-size: 1.15rem !important;
  font-weight: 800 !important;
  color: var(--leaf, #3d6b4f) !important;
  line-height: 1.2 !important;
  display: block !important;
  margin: 2px 0 6px 0 !important;
}
.csh-card__footer {
  border-top: 1px solid var(--line, #e2ded6) !important;
  padding-top: 8px !important;
  margin-top: 2px !important;
  display: flex !important;
  align-items: center !important;
}
.csh-card__seller {
  display: inline-flex !important;
  align-items: center !important;
  gap: 7px !important;
  text-decoration: none !important;
  color: inherit !important;
  width: 100% !important;
  min-width: 0 !important;
}
.csh-card__avatar {
  width: 22px !important;
  height: 22px !important;
  border-radius: 50% !important;
  object-fit: cover !important;
  flex-shrink: 0 !important;
}
.csh-card__avatar-fallback {
  width: 22px !important;
  height: 22px !important;
  border-radius: 50% !important;
  background: var(--leaf, #3d6b4f) !important;
  color: #fff !important;
  display: grid !important;
  place-items: center !important;
  font-size: 0.68rem !important;
  font-weight: 800 !important;
  flex-shrink: 0 !important;
}
.csh-card__seller-name {
  font-size: 0.78rem !important;
  font-weight: 600 !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  color: var(--ink-soft, #5b5b5b) !important;
  flex: 1 !important;
  min-width: 0 !important;
}

@media (max-width: 650px) {
  .csh-market-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 12px !important;
  }
  .csh-card__thumb {
    height: 195px !important;
    min-height: 195px !important;
  }
  .csh-card__body {
    padding: 10px 12px 12px 12px !important;
  }
  .csh-card__price {
    font-size: 1.05rem !important;
  }
}
</style>

<div class="container" style="max-width: 1240px; margin: 30px auto; padding: 0 16px;">
  <div style="margin-bottom: 24px;">
    <h1 style="font-size: 2.2rem; margin: 0 0 8px; font-family: var(--font-heading, serif);">The Marketplace</h1>
    <p style="color: var(--ink-soft, #5b5b5b); margin: 0; font-size: 0.96rem;">
      Preloved clothing and accessories listed by members. Every purchase here keeps a garment in circulation and out of landfill.
    </p>
  </div>

  <!-- Filters -->
  <form method="GET" action="marketplace.php" style="background: var(--bg-raised, #fff); border-radius: var(--r-lg, 16px); padding: 16px 20px; box-shadow: var(--nm-out, 0 4px 20px rgba(0,0,0,0.05)); margin-bottom: 30px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; align-items: center;">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search listings..." style="padding: 9px 13px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
      
      <select name="category" style="padding: 9px 13px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= e($c['slug']) ?>" <?= $catSlug === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="condition" style="padding: 9px 13px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
        <option value="">Any condition</option>
        <option value="new" <?= $cond === 'new' ? 'selected' : '' ?>>New</option>
        <option value="like_new" <?= $cond === 'like_new' ? 'selected' : '' ?>>Like New</option>
        <option value="good" <?= $cond === 'good' ? 'selected' : '' ?>>Good</option>
        <option value="well_loved" <?= $cond === 'well_loved' ? 'selected' : '' ?>>Well Loved</option>
      </select>

      <select name="sort" style="padding: 9px 13px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
        <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most Popular</option>
      </select>

      <input type="text" name="size" value="<?= e($size) ?>" placeholder="Size" style="padding: 9px 13px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
      <input type="text" name="brand" value="<?= e($brand) ?>" placeholder="Brand" style="padding: 9px 13px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
      <input type="text" name="colour" value="<?= e($colour) ?>" placeholder="Colour" style="padding: 9px 13px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
    </div>

    <div style="display: flex; gap: 10px; margin-top: 10px; align-items: center; flex-wrap: wrap;">
      <input type="number" step="0.01" name="min" value="<?= $minPrice !== null ? e($minPrice) : '' ?>" placeholder="Min R" style="width: 100px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
      <input type="number" step="0.01" name="max" value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>" placeholder="Max R" style="width: 100px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;">
      <input type="text" name="location" value="<?= e($location) ?>" placeholder="City / province" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem; flex: 1; min-width: 140px;">
      
      <button type="submit" style="padding: 9px 18px; border-radius: 999px; background: var(--bg-sunken, #eceae5); border: 0; font-weight: 600; cursor: pointer; font: inherit; font-size: 0.88rem;">
        Apply filters
      </button>
      <a href="sell.php" style="padding: 9px 18px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; text-decoration: none; font-weight: 700; font-size: 0.88rem; display: inline-block;">
        + List an item
      </a>
      <?php if (!empty($_GET)): ?>
        <a href="marketplace.php" style="color: var(--ink-faint, #8a8a8a); font-size: 0.84rem; text-decoration: underline; margin-left: 6px;">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Items Grid -->
  <?php if (empty($listings)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--bg-raised, #fff); border-radius: var(--r-lg, 16px);">
      <p style="font-size: 1.1rem; color: var(--ink-soft, #5b5b5b); margin-bottom: 12px;">No marketplace listings matched your search.</p>
      <a href="marketplace.php" style="display: inline-block; padding: 10px 20px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; text-decoration: none; font-weight: 600;">Clear all filters</a>
    </div>
  <?php else: ?>
    <div class="csh-market-grid">
      <?php foreach ($listings as $l): ?>
        <?php $imgUrl = format_listing_image($l['image_url'] ?? ''); ?>
        <article class="csh-card">
          <!-- Thumbnail with Member Badge -->
          <a href="listing.php?slug=<?= e($l['slug']) ?>" class="csh-card__thumb">
            <img src="<?= e($imgUrl) ?>" alt="<?= e($l['title']) ?>">
            <span style="position: absolute; top: 12px; left: 12px; background: rgba(0,0,0,0.65); color: #fff; font-size: 0.68rem; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; padding: 4px 8px; border-radius: 4px; backdrop-filter: blur(4px);">
              Member
            </span>
          </a>

          <!-- Details -->
          <div class="csh-card__body">
            <div>
              <a href="listing.php?slug=<?= e($l['slug']) ?>" class="csh-card__title">
                <?= e($l['title']) ?>
              </a>
              <div class="csh-card__meta">
                <?= !empty($l['item_size']) ? 'Size ' . e($l['item_size']) . ' &middot; ' : '' ?>
                <?= e(ucwords(str_replace('_', ' ', $l['item_condition']))) ?>
              </div>
              <div class="csh-card__price">
                R<?= number_format((float)$l['price'], 2) ?>
              </div>
            </div>

            <!-- Seller Row in Footer -->
            <div class="csh-card__footer">
              <a href="member.php?id=<?= (int)$l['seller_id'] ?>" class="csh-card__seller">
                <?php if (!empty($l['seller_avatar'])): ?>
                  <img src="<?= e(format_listing_image($l['seller_avatar'])) ?>" alt="<?= e($l['seller_name']) ?>" class="csh-card__avatar">
                <?php else: ?>
                  <span class="csh-card__avatar-fallback">
                    <?= e(mb_strtoupper(mb_substr($l['seller_name'], 0, 1))) ?>
                  </span>
                <?php endif; ?>
                <span class="csh-card__seller-name">
                  <?= e($l['seller_name']) ?>
                </span>
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>