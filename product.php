<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$prodId = (int)($_GET['id'] ?? 0);

if ($prodId <= 0) {
    header('Location: shop.php');
    exit;
}

// Create the reviews table if this database has not got it yet.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_reviews` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `product_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `rating` INT NOT NULL DEFAULT 5,
        `comment` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_product` (`product_id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
} catch (Exception $e) {}

// The product itself.
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, cs.name AS cause_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN causes cs ON p.cause_id = cs.id
    WHERE p.id = ? AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$prodId]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: shop.php');
    exit;
}

$me = is_logged_in() ? current_user() : null;
$myId = $me ? (int)$me['id'] : 0;
$pageTitle = $item['name'] . ' - CSH Atelier';

$reviewSuccess = '';
$reviewError = '';

// Somebody pressed add to cart.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    cart_add('product', (int)$item['id'], $qty);
    header("Location: product.php?id={$prodId}&added=1");
    exit;
}

// Somebody left a review.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$me) {
        header("Location: login.php");
        exit;
    }
    $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');

    try {
        $chk = $pdo->prepare("SELECT id FROM `product_reviews` WHERE product_id = ? AND user_id = ?");
        $chk->execute([$prodId, $myId]);
        $existingId = $chk->fetchColumn();

        if ($existingId) {
            $up = $pdo->prepare("UPDATE `product_reviews` SET rating = ?, comment = ?, created_at = NOW() WHERE id = ?");
            $up->execute([$rating, $comment, (int)$existingId]);
            $reviewSuccess = 'Your review has been updated!';
        } else {
            $ins = $pdo->prepare("INSERT INTO `product_reviews` (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $ins->execute([$prodId, $myId, $rating, $comment]);
            $reviewSuccess = 'Thank you! Your review has been submitted.';
        }
    } catch (Exception $e) {
        $reviewError = 'Could not save review: ' . $e->getMessage();
    }
}

// Reviews plus the average, for the stars at the top.
$avgScore = 5.0;
$totalReviews = 0;
$reviews = [];
try {
    $rStmt = $pdo->prepare("SELECT COUNT(*) as cnt, AVG(rating) as avg_score FROM `product_reviews` WHERE product_id = ?");
    $rStmt->execute([$prodId]);
    $rData = $rStmt->fetch();
    $totalReviews = (int)($rData['cnt'] ?? 0);
    $avgScore = $totalReviews > 0 ? round((float)$rData['avg_score'], 1) : 5.0;

    $lStmt = $pdo->prepare("
        SELECT pr.*, u.name as user_name, u.avatar_url as user_avatar 
        FROM `product_reviews` pr
        JOIN `users` u ON pr.user_id = u.id
        WHERE pr.product_id = ?
        ORDER BY pr.id DESC
    ");
    $lStmt->execute([$prodId]);
    $reviews = $lStmt->fetchAll();
} catch (Exception $e) {}

function resolve_img(?string $url): string {
    $url = trim((string)$url);
    if ($url === '' || strpos($url, 'imgproxy.fourthwall.dev') !== false) return 'assets/img/placeholder.svg';
    if (strpos($url, 'http://') === 0) return 'https://' . substr($url, 7);
    return $url;
}
$imgUrl = resolve_img($item['image_url']);

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
  height: 480px;
  border-radius: 16px;
  overflow: hidden;
  background: var(--bg-sunken, #eceae5);
  box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}
.listing-img-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.btn-primary {
  display: inline-block;
  text-align: center;
  padding: 13px 28px;
  border-radius: 999px;
  background: var(--leaf, #3d6b4f);
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  text-decoration: none;
  border: 0;
  cursor: pointer;
  box-sizing: border-box;
}
.btn-secondary {
  display: inline-block;
  text-align: center;
  padding: 12px 24px;
  border-radius: 999px;
  background: var(--bg-sunken, #eceae5);
  color: var(--ink, #1a1a1a);
  font-weight: 700;
  font-size: 0.92rem;
  text-decoration: none;
  border: 1px solid var(--line, #e2ded6);
  box-sizing: border-box;
}

/* The clickable stars on the review form. */
.star-select {
  display: inline-flex;
  flex-direction: row-reverse;
  font-size: 2.2rem;
  gap: 4px;
}
.star-select input { display: none; }
.star-select label { color: #d1c7b7; cursor: pointer; }
.star-select input:checked ~ label,
.star-select label:hover,
.star-select label:hover ~ label { color: #f5a623; }

@media (max-width: 850px) {
  .listing-layout { grid-template-columns: 1fr; gap: 24px; }
  .listing-img-box { height: 340px; }
}
</style>

<div class="container" style="max-width: 1120px; margin: 36px auto 60px; padding: 0 16px;">

  <!-- Breadcrumbs -->
  <div style="margin-bottom: 20px; font-size: 0.88rem; color: var(--ink-faint, #8a8a8a);">
    <a href="shop.php" style="color: inherit; text-decoration: none;">The Label</a> &rsaquo;
    <span style="color: var(--ink, #1a1a1a);"><?= e($item['category_name'] ?? 'Collection') ?></span> &rsaquo;
    <span><?= e($item['name']) ?></span>
  </div>

  <?php if (isset($_GET['added'])): ?>
    <div style="background: #dcf3e4; color: #1e4620; padding: 12px 18px; border-radius: 10px; margin-bottom: 24px; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
      <span>Piece added to your cart!</span>
      <a href="cart.php" style="color: inherit; text-decoration: underline; font-weight: 800;">View Cart &amp; Checkout &rarr;</a>
    </div>
  <?php endif; ?>

  <div class="listing-layout">
    <!-- Image Box -->
    <div class="listing-img-box">
      <img src="<?= e($imgUrl) ?>" alt="<?= e($item['name']) ?>">
    </div>

    <!-- Details Box -->
    <div>
      <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
        <span style="font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: var(--leaf, #3d6b4f); letter-spacing: 0.5px;">
          <?= e($item['external_source'] ?: 'CSH Atelier Co.') ?>
        </span>
        <span style="color: #f5a623; font-size: 0.95rem; font-weight: 700;">
          ★ <?= $avgScore ?> (<?= $totalReviews ?> reviews)
        </span>
      </div>

      <h1 style="margin: 0 0 12px; font-size: 2rem; font-family: var(--font-heading, serif); line-height: 1.25;">
        <?= e($item['name']) ?>
      </h1>

      <div style="font-size: 1.8rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin-bottom: 18px;">
        R<?= number_format((float)$item['price'], 2) ?>
      </div>

      <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px;">
        <?php if (!empty($item['cause_name'])): ?>
          <span style="padding: 6px 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); font-size: 0.84rem; font-weight: 600;">
            Cause: <?= e($item['cause_name']) ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($item['recycled_pct'])): ?>
          <span style="padding: 6px 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); font-size: 0.84rem; font-weight: 600;">
            ♻ <?= (int)$item['recycled_pct'] ?>% Recycled Materials
          </span>
        <?php endif; ?>
        <span style="padding: 6px 14px; border-radius: 999px; background: var(--bg-sunken, #eceae5); font-size: 0.84rem; font-weight: 600;">
          Status: <?= ((int)$item['stock'] > 0) ? 'In Stock' : 'Sold Out' ?>
        </span>
      </div>

      <?php if (!empty($item['message'])): ?>
        <div style="background: rgba(61,107,79,0.08); border-left: 4px solid var(--leaf, #3d6b4f); padding: 12px 16px; border-radius: 6px; margin-bottom: 22px;">
          <b style="color: var(--leaf, #3d6b4f); font-size: 0.8rem; text-transform: uppercase; display: block; margin-bottom: 2px;">The Message Behind This Piece</b>
          <span style="font-size: 0.95rem; font-style: italic;"><?= e($item['message']) ?></span>
        </div>
      <?php endif; ?>

      <div style="margin-bottom: 26px;">
        <h3 style="font-size: 1rem; margin: 0 0 6px;">Description</h3>
        <p style="margin: 0; color: var(--ink-soft, #5b5b5b); line-height: 1.6; font-size: 0.95rem;">
          <?= nl2br(e($item['description'] ?: 'Official piece from CSH Atelier & Innovations collection.')) ?>
        </p>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
        <?php if ((int)$item['stock'] > 0): ?>
          <form method="POST" action="product.php?id=<?= $prodId ?>" style="flex: 1; min-width: 180px; margin: 0; display: flex; gap: 8px;">
            <input type="hidden" name="add_to_cart" value="1">
            <input type="number" name="qty" value="1" min="1" max="10" style="width: 65px; padding: 11px; border-radius: 999px; border: 1px solid var(--line, #e2ded6); text-align: center; font: inherit; font-weight: 700;">
            <button type="submit" class="btn-primary" style="flex: 1;">Add to Cart</button>
          </form>
        <?php else: ?>
          <div style="flex: 1; min-width: 180px; padding: 13px; text-align: center; background: var(--bg-sunken, #eceae5); color: var(--ink-soft, #5b5b5b); border-radius: 999px; font-weight: 700;">
            Currently Sold Out
          </div>
        <?php endif; ?>

        <!-- View on Main Website button -->
        <?php if (!empty($item['external_url'])): ?>
          <a href="<?= e($item['external_url']) ?>" target="_blank" rel="noopener" class="btn-secondary">
            View on main website &rarr;
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Ratings & Reviews Section -->
  <section id="reviews" style="background: var(--bg-raised, #fff); border-radius: 16px; padding: 32px; border: 1px solid var(--line, #e2ded6); margin-top: 48px;">
    <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--line, #e2ded6); padding-bottom: 16px;">
      <div>
        <h2 style="margin: 0 0 4px; font-size: 1.4rem;">Product Reviews &amp; Ratings</h2>
        <p style="margin: 0; font-size: 0.88rem; color: var(--ink-faint, #8a8a8a);">Customer feedback on fit, quality, and condition.</p>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="color:#f5a623;font-size:1.2rem;letter-spacing:2px;">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <?= ($i <= round($avgScore)) ? '★' : '<span style="color:#d1c7b7;">★</span>' ?>
          <?php endfor; ?>
        </span>
        <b style="font-size: 1.25rem;"><?= $avgScore ?></b>
        <span style="color: var(--ink-faint, #8a8a8a); font-size: 0.88rem;">/ 5.0 (<?= $totalReviews ?>)</span>
      </div>
    </div>

    <?php if (!empty($reviewSuccess)): ?>
      <div style="padding: 12px 18px; border-radius: 10px; background: #dcf3e4; color: #1e4620; margin-bottom: 20px; font-weight: 600;">
        <?= e($reviewSuccess) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($reviewError)): ?>
      <div style="padding: 12px 18px; border-radius: 10px; background: #fdeeec; color: #8f3a30; margin-bottom: 20px; font-weight: 600;">
        <?= e($reviewError) ?>
      </div>
    <?php endif; ?>

    <!-- Review list -->
    <?php if (empty($reviews)): ?>
      <p style="color: var(--ink-soft, #5b5b5b); font-style: italic; margin-bottom: 30px;">There are no reviews for this product yet. Be the first to share feedback!</p>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 34px;">
        <?php foreach ($reviews as $r): ?>
          <div style="padding: 16px 20px; background: var(--bg, #f6f4f0); border-radius: 12px; border: 1px solid var(--line, #e2ded6);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <b style="font-size: 0.92rem;"><?= e($r['user_name']) ?></b>
              <span style="font-size: 0.78rem; color: var(--ink-faint, #8a8a8a);"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
            </div>
            <div style="color: #f5a623; font-size: 1.1rem; margin-bottom: 6px;">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <?= ($s <= (int)$r['rating']) ? '★' : '<span style="color:#d1c7b7;">★</span>' ?>
              <?php endfor; ?>
            </div>
            <?php if (!empty($r['comment'])): ?>
              <p style="margin: 0; font-size: 0.9rem; color: var(--ink-soft, #5b5b5b); line-height: 1.5;"><?= nl2br(e($r['comment'])) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Review form -->
    <div style="padding-top: 20px; border-top: 1px solid var(--line, #e2ded6);">
      <?php if (!$me): ?>
        <p style="color: var(--ink-soft, #5b5b5b);">
          <a href="login.php" style="color: var(--leaf, #3d6b4f); font-weight: 700; text-decoration: underline;">Log in</a> to leave a rating and review for this product.
        </p>
      <?php else: ?>
        <h3 style="margin: 0 0 12px; font-size: 1.15rem;">Leave a Review</h3>
        <form method="POST" action="product.php?id=<?= $prodId ?>#reviews">
          <input type="hidden" name="submit_review" value="1">
          <div style="margin-bottom: 14px;">
            <label style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 6px;">Your Rating</label>
            <div class="star-select">
              <input type="radio" id="st5" name="rating" value="5" checked /><label for="st5">★</label>
              <input type="radio" id="st4" name="rating" value="4" /><label for="st4">★</label>
              <input type="radio" id="st3" name="rating" value="3" /><label for="st3">★</label>
              <input type="radio" id="st2" name="rating" value="2" /><label for="st2">★</label>
              <input type="radio" id="st1" name="rating" value="1" /><label for="st1">★</label>
            </div>
          </div>
          <div style="margin-bottom: 18px;">
            <label style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 6px;">Review Comments (Optional)</label>
            <textarea name="comment" rows="3" placeholder="Tell other members about the quality, texture, and fit..." style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.9rem;"></textarea>
          </div>
          <button type="submit" class="btn-primary">Submit Product Review</button>
        </form>
      <?php endif; ?>
    </div>
  </section>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>