<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: marketplace.php');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("
    SELECT l.*, 
           u.id AS seller_id,
           u.name AS seller_name, 
           u.avatar_url AS seller_avatar, 
           u.city AS seller_city, 
           u.province AS seller_province,
           u.created_at AS seller_joined,
           c.name AS category_name
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.slug = ? AND l.status = 'active'
    LIMIT 1
");
$stmt->execute([$slug]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: marketplace.php');
    exit;
}

$up = $pdo->prepare("UPDATE listings SET views = views + 1 WHERE id = ?");
$up->execute([(int)$item['id']]);

$pageTitle = $item['title'] . ' - CSH Atelier';

function format_listing_image(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') {
        return 'assets/img/placeholder.svg';
    }
    $url = str_replace('\\', '/', $url);
    $host = $_SERVER['HTTP_HOST'] ?? 'cshatelier.ct.ws';
    $url = preg_replace('#^https?://' . preg_quote($host, '#') . '#i', '', $url);

    if (strpos($url, 'http://') === 0) {
        $url = 'https://' . substr($url, 7);
    }
    if (!preg_match('#^https?://#i', $url) && strpos($url, '/') !== 0) {
        $url = '/' . $url;
    }
    return $url;
}

$imgUrl = format_listing_image($item['image_url'] ?? '');

$me = is_logged_in() ? current_user() : null;
$myId = $me ? (int)$me['id'] : 0;
$isOwnListing = $myId > 0 && $myId === (int)$item['seller_id'];

include __DIR__ . '/includes/header.php';
?>

<style>
.listing-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
  align-items: start;
}
.listing-img-box {
  width: 100%;
  height: 440px;
  min-height: 440px;
  border-radius: var(--r-lg, 16px);
  overflow: hidden;
  background: var(--bg-sunken, #eceae5);
  box-shadow: var(--nm-out, 0 4px 20px rgba(0,0,0,0.06));
  display: block;
}
.listing-img-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.seller-box {
  background: var(--bg-raised, #fff);
  border-radius: 14px;
  padding: 18px 20px;
  border: 1px solid var(--line, #e2ded6);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin: 24px 0;
}

@media (max-width: 850px) {
  .listing-layout {
    grid-template-columns: 1fr;
    gap: 24px;
  }
  .listing-img-box {
    height: 320px;
    min-height: 320px;
  }
}
</style>

<div class="container" style="max-width: 1080px; margin: 36px auto; padding: 0 16px;">
  <div style="margin-bottom: 20px; font-size: 0.88rem; color: var(--ink-faint, #8a8a8a);">
    <a href="marketplace.php" style="color: inherit; text-decoration: none;">Marketplace</a> &rsaquo;
    <span style="color: var(--ink, #1a1a1a);"><?= e($item['category_name'] ?? 'Wardrobe') ?></span> &rsaquo;
    <span><?= e($item['title']) ?></span>
  </div>

  <div class="listing-layout">
    <!-- Image Box -->
    <div class="listing-img-box">
      <img src="<?= e($imgUrl) ?>" alt="<?= e($item['title']) ?>">
    </div>

    <!-- Product Details -->
    <div>
      <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
        <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--leaf, #3d6b4f); letter-spacing: 0.5px;">
          <?= e($item['brand'] ?: 'Preloved Garment') ?>
        </span>
        <span style="font-size: 0.78rem; color: var(--ink-faint, #8a8a8a);">
          <?= (int)$item['views'] ?> views
        </span>
      </div>

      <h1 style="margin: 0 0 12px; font-size: 2rem; font-family: var(--font-heading, serif); line-height: 1.2;">
        <?= e($item['title']) ?>
      </h1>

      <div style="font-size: 1.8rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin-bottom: 20px;">
        R<?= number_format((float)$item['price'], 2) ?>
      </div>

      <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px;">
        <?php if (!empty($item['item_size'])): ?>
          <span style="padding: 6px 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); font-size: 0.86rem; font-weight: 600;">
            Size: <?= e($item['item_size']) ?>
          </span>
        <?php endif; ?>
        <span style="padding: 6px 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); font-size: 0.86rem; font-weight: 600;">
          Condition: <?= e(ucwords(str_replace('_', ' ', $item['item_condition']))) ?>
        </span>
        <?php if (!empty($item['colour'])): ?>
          <span style="padding: 6px 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); font-size: 0.86rem; font-weight: 600;">
            Colour: <?= e($item['colour']) ?>
          </span>
        <?php endif; ?>
      </div>

      <div style="margin-bottom: 26px;">
        <h3 style="font-size: 1rem; margin: 0 0 8px;">Description</h3>
        <p style="margin: 0; color: var(--ink-soft, #5b5b5b); line-height: 1.6; font-size: 0.95rem;">
          <?= nl2br(e($item['description'] ?: 'No detailed description provided by the seller.')) ?>
        </p>
      </div>

      <!-- Seller Info -->
      <div class="seller-box">
        <a href="member.php?id=<?= (int)$item['seller_id'] ?>" style="display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit;">
          <?php if (!empty($item['seller_avatar'])): ?>
            <img src="<?= e(format_listing_image($item['seller_avatar'])) ?>" alt="<?= e($item['seller_name']) ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid var(--leaf, #3d6b4f);">
          <?php else: ?>
            <span style="width: 48px; height: 48px; border-radius: 50%; background: var(--leaf, #3d6b4f); color: #fff; display: grid; place-items: center; font-size: 1.2rem; font-weight: 800;">
              <?= e(mb_strtoupper(mb_substr($item['seller_name'], 0, 1))) ?>
            </span>
          <?php endif; ?>
          <div>
            <b style="font-size: 0.96rem; display: block;"><?= e($item['seller_name']) ?></b>
            <small style="color: var(--ink-faint, #8a8a8a); font-size: 0.8rem;">
              <?= e($item['seller_city'] ?: 'South Africa') ?><?= !empty($item['seller_province']) ? ', ' . e($item['seller_province']) : '' ?>
            </small>
          </div>
        </a>

        <?php if (!$isOwnListing): ?>
        <a href="messages.php?with=<?= (int)$item['seller_id'] ?>" style="padding: 9px 18px; border-radius: 999px; background: var(--bg-sunken, #eceae5); color: var(--ink, #1a1a1a); text-decoration: none; font-weight: 700; font-size: 0.84rem;">
          Message
        </a>
        <?php endif; ?>
      </div>

      <div style="display: flex; gap: 12px; margin-top: 24px;">
        <?php if ($isOwnListing): ?>
          <div style="flex: 1; text-align: center; padding: 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); color: var(--ink-soft, #5b5b5b); font-weight: 700; font-size: 0.95rem;">
            This is your own listing
          </div>
        <?php else: ?>
          <button type="button" data-add-cart data-type="listing" data-id="<?= (int)$item['id'] ?>"
                  style="flex: 1; text-align: center; padding: 14px; border: 0; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; font-weight: 700; font-size: 0.95rem; cursor: pointer;">
            Add to Cart
          </button>
          <a href="messages.php?with=<?= (int)$item['seller_id'] ?>" style="flex: 1; text-align: center; padding: 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); color: var(--ink, #1a1a1a); text-decoration: none; font-weight: 700; font-size: 0.95rem;">
            Message Seller
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>