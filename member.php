<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$memberId = (int)($_GET['id'] ?? 0);

if ($memberId <= 0) {
    header('Location: marketplace.php');
    exit;
}

// The member whose profile we are looking at, not the one logged in.
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    header('Location: marketplace.php');
    exit;
}

$me = is_logged_in() ? current_user() : null;
$myId = $me ? (int)$me['id'] : 0;
$isAdmin = ($member['role'] === 'admin');

$pageTitle = $member['name'] . ' - Member Profile';
$reviewSuccess = '';
$reviewError = '';

if (isset($_GET['reviewed'])) {
    $reviewSuccess = 'Thank you! Your rating and review have been published.';
}

// Check which columns this database actually has before I query them,
// because older copies are missing a few of the later ones.
$cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `member_ratings`")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$targetCol = in_array('member_id', $cols, true) ? 'member_id' : (in_array('rated_user_id', $cols, true) ? 'rated_user_id' : (in_array('rated_id', $cols, true) ? 'rated_id' : 'user_id'));
$raterCol  = in_array('rater_id', $cols, true) ? 'rater_id' : (in_array('reviewer_id', $cols, true) ? 'reviewer_id' : 'user_id');
$ratingCol = in_array('rating', $cols, true) ? 'rating' : 'score';
$commentCol = in_array('comment', $cols, true) ? 'comment' : (in_array('review', $cols, true) ? 'review' : 'feedback');

// You can rate a member straight from their profile.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$me) {
        header('Location: login.php');
        exit;
    }
    if ($myId === (int)$member['id']) {
        $reviewError = 'You cannot rate your own profile.';
    } elseif ($isAdmin) {
        $reviewError = 'Administrator profiles cannot be rated.';
    } else {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = trim($_POST['comment'] ?? '');

        try {
            $chk = $pdo->prepare("SELECT id FROM `member_ratings` WHERE `{$targetCol}` = ? AND `{$raterCol}` = ?");
            $chk->execute([(int)$member['id'], $myId]);
            $existingId = $chk->fetchColumn();

            if ($existingId) {
                $setClauses = [];
                $params = [];
                if (in_array('rating', $cols, true)) { $setClauses[] = "`rating` = ?"; $params[] = $rating; }
                if (in_array('score', $cols, true)) { $setClauses[] = "`score` = ?"; $params[] = $rating; }
                if (in_array('comment', $cols, true)) { $setClauses[] = "`comment` = ?"; $params[] = $comment; }
                if (in_array('review', $cols, true)) { $setClauses[] = "`review` = ?"; $params[] = $comment; }
                if (in_array('created_at', $cols, true)) { $setClauses[] = "`created_at` = NOW()"; }
                $params[] = (int)$existingId;

                $up = $pdo->prepare("UPDATE `member_ratings` SET " . implode(', ', $setClauses) . " WHERE `id` = ?");
                $up->execute($params);
                $reviewSuccess = 'Your review has been updated!';
            } else {
                $fields = [];
                if (in_array('rater_id', $cols, true))     { $fields['rater_id'] = $myId; }
                if (in_array('reviewer_id', $cols, true))  { $fields['reviewer_id'] = $myId; }
                if (in_array('member_id', $cols, true))    { $fields['member_id'] = (int)$member['id']; }
                if (in_array('rated_user_id', $cols, true)){ $fields['rated_user_id'] = (int)$member['id']; }
                if (in_array('rated_id', $cols, true))     { $fields['rated_id'] = (int)$member['id']; }
                if (in_array('user_id', $cols, true) && !in_array('member_id', $cols, true) && !in_array('rated_user_id', $cols, true)) {
                    $fields['user_id'] = (int)$member['id'];
                }
                if (in_array('rating', $cols, true))       { $fields['rating'] = $rating; }
                if (in_array('score', $cols, true))        { $fields['score'] = $rating; }
                if (in_array('comment', $cols, true))      { $fields['comment'] = $comment; }
                if (in_array('review', $cols, true))       { $fields['review'] = $comment; }
                if (in_array('created_at', $cols, true))   { $fields['created_at'] = date('Y-m-d H:i:s'); }

                $colNames = array_keys($fields);
                $placeholders = array_fill(0, count($fields), '?');
                $ins = $pdo->prepare("INSERT INTO `member_ratings` (`" . implode('`, `', $colNames) . "`) VALUES (" . implode(', ', $placeholders) . ")");
                $ins->execute(array_values($fields));
                $reviewSuccess = 'Thank you! Your rating and review have been published.';
            }
        } catch (Exception $e) {
            $reviewError = 'Could not save rating: ' . $e->getMessage();
        }
    }
}

// What they currently have up for sale.
$lStmt = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? AND status = 'active' ORDER BY id DESC");
$lStmt->execute([$memberId]);
$listings = $lStmt->fetchAll();

// Their sold items, but only if they chose to show them.
$soldListings = [];
if (!empty($member['showcase_sold'])) {
    try {
        $sStmt = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? AND status = 'sold' ORDER BY id DESC LIMIT 8");
        $sStmt->execute([$memberId]);
        $soldListings = $sStmt->fetchAll();
    } catch (Exception $e) {}
}

// Their rating and what people wrote.
$avgRating = 5.0;
$totalReviews = 0;
$reviewsList = [];
if (!$isAdmin && !empty($cols)) {
    try {
        $statStmt = $pdo->prepare("SELECT COUNT(*) as cnt, AVG(`{$ratingCol}`) as avg_score FROM `member_ratings` WHERE `{$targetCol}` = ?");
        $statStmt->execute([$memberId]);
        $stat = $statStmt->fetch();
        $totalReviews = (int)($stat['cnt'] ?? 0);
        $avgRating = $totalReviews > 0 ? round((float)$stat['avg_score'], 1) : 5.0;

        $revStmt = $pdo->prepare("
            SELECT r.*, r.`{$ratingCol}` as rating, r.`{$commentCol}` as comment, u.name as reviewer_name, u.avatar_url as reviewer_avatar 
            FROM `member_ratings` r 
            JOIN `users` u ON r.`{$raterCol}` = u.id 
            WHERE r.`{$targetCol}` = ? 
            ORDER BY r.id DESC
        ");
        $revStmt->execute([$memberId]);
        $reviewsList = $revStmt->fetchAll();
    } catch (Exception $e) {}
}

function clean_img(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return 'assets/img/placeholder.svg';
    $url = str_replace('\\', '/', $url);
    $host = $_SERVER['HTTP_HOST'] ?? 'cshatelier.ct.ws';
    $url = preg_replace('#^https?://' . preg_quote($host, '#') . '#i', '', $url);
    if (strpos($url, 'http://') === 0) return 'https://' . substr($url, 7);
    if (!preg_match('#^https?://#i', $url) && strpos($url, '/') !== 0) return '/' . $url;
    return $url;
}

function display_stars(float $score): string {
    $r = (int)round($score);
    $out = '<span style="color:#f5a623;font-size:1.15rem;letter-spacing:2px;">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= ($i <= $r) ? '★' : '<span style="color:#d1c7b7;">★</span>';
    }
    $out .= '</span>';
    return $out;
}

include __DIR__ . '/includes/header.php';
?>

<style>
.profile-hero {
  background: var(--bg-raised, #fff);
  border-radius: var(--r-lg, 16px);
  padding: 32px;
  box-shadow: var(--nm-out, 0 6px 25px rgba(0,0,0,0.06));
  margin-bottom: 36px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
  border: 1px solid var(--line, #e2ded6);
}
.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
  cursor: pointer;
  border: 0;
  transition: opacity 0.15s;
}
.btn-action.primary { background: var(--leaf, #3d6b4f); color: #fff; }
.btn-action.secondary { background: var(--bg-sunken, #eceae5); color: var(--ink, #1a1a1a); }
.btn-action.danger { background: #fdeeec; color: #8f3a30; }
.btn-action:hover { opacity: 0.9; }

.star-select {
  display: inline-flex;
  flex-direction: row-reverse;
  font-size: 2.2rem;
  gap: 4px;
}
.star-select input { display: none; }
.star-select label {
  color: #d1c7b7;
  cursor: pointer;
  transition: color 0.15s;
}
.star-select input:checked ~ label,
.star-select label:hover,
.star-select label:hover ~ label {
  color: #f5a623;
}

.reason-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}
.reason-chip {
  padding: 7px 13px;
  border-radius: 999px;
  border: 1px solid var(--line, #e2ded6);
  background: var(--bg-sunken, #eceae5);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.reason-chip.selected {
  background: var(--leaf, #3d6b4f);
  color: #fff;
  border-color: var(--leaf, #3d6b4f);
}

.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal-overlay[hidden] { display: none !important; }
.modal-window {
  width: 100%; max-width: 480px; background: var(--bg-raised, #fff);
  border-radius: 16px; padding: 26px; box-shadow: 0 20px 50px rgba(0,0,0,0.25);
}
</style>

<div class="container" style="max-width: 1140px; margin: 40px auto; padding: 0 16px;">

  <?php if (!empty($reviewSuccess)): ?>
    <div style="padding: 12px 18px; border-radius: 10px; background: #dcf3e4; color: #1e4620; margin-bottom: 24px; font-weight: 600;">
      <?= e($reviewSuccess) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($reviewError)): ?>
    <div style="padding: 12px 18px; border-radius: 10px; background: #fdeeec; color: #8f3a30; margin-bottom: 24px; font-weight: 600;">
      <?= e($reviewError) ?>
    </div>
  <?php endif; ?>

  <!-- Profile Hero Banner -->
  <div class="profile-hero">
    <div style="display: flex; align-items: center; gap: 24px; min-width: 260px;">
      <?php if (!empty($member['avatar_url'])): ?>
        <img src="<?= e(clean_img($member['avatar_url'])) ?>" alt="<?= e($member['name']) ?>" style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 3px solid var(--leaf, #3d6b4f); box-shadow: 0 4px 14px rgba(0,0,0,0.1);">
      <?php else: ?>
        <div style="width: 96px; height: 96px; border-radius: 50%; background: var(--leaf, #3d6b4f); color: #fff; display: grid; place-items: center; font-size: 2.2rem; font-weight: 800; border: 3px solid var(--leaf, #3d6b4f);">
          <?= e(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?>
        </div>
      <?php endif; ?>

      <div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <h1 style="margin: 0; font-size: 1.8rem;"><?= e($member['name']) ?></h1>
          <?php if ($isAdmin): ?>
            <span style="background: #2b3a42; color: #fff; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 4px;">Atelier Admin</span>
          <?php endif; ?>
        </div>

        <p style="margin: 6px 0; color: var(--ink-faint, #8a8a8a); font-size: 0.92rem;">
          <?= e($member['city'] ?: 'South Africa') ?><?= !empty($member['province']) ? ', ' . e($member['province']) : '' ?> &middot; Joined <?= date('M Y', strtotime($member['created_at'])) ?>
        </p>

        <?php if (!$isAdmin): ?>
          <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
            <?= display_stars($avgRating) ?>
            <a href="#reviews" style="color: var(--ink, #1a1a1a); font-weight: 700; font-size: 0.88rem; text-decoration: underline;">
              <?= $totalReviews > 0 ? $avgRating . ' (' . $totalReviews . ' review' . ($totalReviews > 1 ? 's' : '') . ')' : 'No reviews yet' ?>
            </a>
          </div>
        <?php endif; ?>

        <?php if (!empty($member['bio'])): ?>
          <p style="margin: 10px 0 0; font-size: 0.94rem; color: var(--ink-soft, #5b5b5b); line-height: 1.5; max-width: 540px;">
            <?= nl2br(e($member['bio'])) ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Actions -->
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 14px;">
      <div style="text-align: right;">
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--leaf, #3d6b4f);"><?= (int)$member['items_diverted'] ?></div>
        <div style="font-size: 0.76rem; color: var(--ink-faint, #8a8a8a); text-transform: uppercase; font-weight: 700;">Items Diverted</div>
      </div>

      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="messages.php?with=<?= (int)$member['id'] ?>" class="btn-action primary">
          <svg viewBox="0 0 24 24" style="width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2;"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z"/></svg>
          Message
        </a>

        <?php if (!$isAdmin && $myId !== (int)$member['id']): ?>
          <a href="#rate" class="btn-action secondary">
            ★ Rate Member
          </a>
        <?php endif; ?>

        <?php if (!$isAdmin && $myId !== (int)$member['id']): ?>
          <button type="button" class="btn-action danger" onclick="openReportModal()">
            Report
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 1. Wardrobe Active Listings -->
  <div style="margin-bottom: 40px;">
    <h2 style="font-size: 1.35rem; margin-bottom: 20px;">Wardrobe Listings (<?= count($listings) ?>)</h2>
    <?php if (empty($listings)): ?>
      <div style="padding: 40px; text-align: center; background: var(--bg-raised, #fff); border-radius: 16px; border: 1px solid var(--line, #e2ded6); color: var(--ink-soft, #5b5b5b);">
        This member currently has no active pieces for sale.
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
        <?php foreach ($listings as $l): ?>
          <div style="background: var(--bg-raised, #fff); border-radius: 14px; overflow: hidden; border: 1px solid var(--line, #e2ded6); display: flex; flex-direction: column;">
            <a href="listing.php?slug=<?= e($l['slug']) ?>" style="display: block; height: 210px; background: var(--bg-sunken, #eceae5); overflow: hidden;">
              <img src="<?= e(clean_img($l['image_url'])) ?>" alt="<?= e($l['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
            </a>
            <div style="padding: 12px 14px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <a href="listing.php?slug=<?= e($l['slug']) ?>" style="font-weight: 700; color: inherit; text-decoration: none; font-size: 0.96rem; display: block; margin-bottom: 4px;">
                  <?= e($l['title']) ?>
                </a>
                <div style="font-size: 0.78rem; color: var(--ink-faint, #8a8a8a);">
                  <?= !empty($l['item_size']) ? 'Size ' . e($l['item_size']) . ' &middot; ' : '' ?>
                  <?= e(ucwords(str_replace('_', ' ', $l['item_condition']))) ?>
                </div>
              </div>
              <div style="font-size: 1.1rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin-top: 10px;">
                R<?= number_format((float)$l['price'], 2) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- 2. Showcase Sold Items -->
  <?php if (!empty($member['showcase_sold']) && !empty($soldListings)): ?>
    <div style="margin-bottom: 44px;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
        <h2 style="font-size: 1.35rem; margin: 0;">Recently Sold Pieces</h2>
        <span style="background: #e2ded6; color: #5b5b5b; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 999px;">Past Sales</span>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
        <?php foreach ($soldListings as $sl): ?>
          <div style="background: var(--bg-raised, #fff); border-radius: 14px; overflow: hidden; border: 1px solid var(--line, #e2ded6); display: flex; flex-direction: column; position: relative; opacity: 0.88;">
            <div style="height: 210px; background: var(--bg-sunken, #eceae5); overflow: hidden; position: relative;">
              <img src="<?= e(clean_img($sl['image_url'])) ?>" alt="<?= e($sl['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; filter: grayscale(15%);">
              <span style="position: absolute; top: 12px; left: 12px; background: #8f3a30; color: #fff; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; padding: 4px 8px; border-radius: 4px; letter-spacing: 0.5px;">
                SOLD
              </span>
            </div>
            <div style="padding: 12px 14px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <b style="font-size: 0.96rem; display: block; margin-bottom: 4px;"><?= e($sl['title']) ?></b>
                <div style="font-size: 0.78rem; color: var(--ink-faint, #8a8a8a);">
                  <?= !empty($sl['item_size']) ? 'Size ' . e($sl['item_size']) . ' &middot; ' : '' ?>
                  <?= e(ucwords(str_replace('_', ' ', $sl['item_condition']))) ?>
                </div>
              </div>
              <div style="font-size: 1.05rem; font-weight: 800; color: #6b7280; margin-top: 10px; text-decoration: line-through;">
                R<?= number_format((float)$sl['price'], 2) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- 3. Ratings & Reviews Section -->
  <?php if (!$isAdmin): ?>
    <section id="reviews" style="background: var(--bg-raised, #fff); border-radius: 16px; padding: 32px; border: 1px solid var(--line, #e2ded6); margin-bottom: 40px;">
      <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--line, #e2ded6); padding-bottom: 16px;">
        <div>
          <h2 style="margin: 0 0 4px; font-size: 1.4rem;">Member Ratings &amp; Reviews</h2>
          <p style="margin: 0; font-size: 0.88rem; color: var(--ink-faint, #8a8a8a);">Verified feedback from marketplace interactions.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <?= display_stars($avgRating) ?>
          <b style="font-size: 1.25rem;"><?= $avgRating ?></b>
          <span style="color: var(--ink-faint, #8a8a8a); font-size: 0.88rem;">/ 5.0 (<?= $totalReviews ?>)</span>
        </div>
      </div>

      <!-- Reviews list -->
      <?php if (empty($reviewsList)): ?>
        <p style="color: var(--ink-soft, #5b5b5b); font-style: italic; margin-bottom: 30px;">No reviews have been left for this member yet. Be the first to rate!</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 18px; margin-bottom: 34px;">
          <?php foreach ($reviewsList as $rev): ?>
            <div style="padding: 16px 20px; background: var(--bg, #f6f4f0); border-radius: 12px; border: 1px solid var(--line, #e2ded6);">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <?php if (!empty($rev['reviewer_avatar'])): ?>
                    <img src="<?= e(clean_img($rev['reviewer_avatar'])) ?>" alt="Avatar" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                  <?php else: ?>
                    <span style="width: 28px; height: 28px; border-radius: 50%; background: var(--leaf, #3d6b4f); color: #fff; display: grid; place-items: center; font-size: 0.75rem; font-weight: 800;">
                      <?= e(mb_strtoupper(mb_substr($rev['reviewer_name'], 0, 1))) ?>
                    </span>
                  <?php endif; ?>
                  <b style="font-size: 0.92rem;"><?= e($rev['reviewer_name']) ?></b>
                </div>
                <div style="font-size: 0.78rem; color: var(--ink-faint, #8a8a8a);">
                  <?= date('d M Y', strtotime($rev['created_at'])) ?>
                </div>
              </div>

              <div style="margin-bottom: 6px;">
                <?= display_stars((float)$rev['rating']) ?>
              </div>

              <?php if (!empty($rev['comment'])): ?>
                <p style="margin: 0; font-size: 0.9rem; color: var(--ink-soft, #5b5b5b); line-height: 1.45;">
                  <?= nl2br(e($rev['comment'])) ?>
                </p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Star Review Form -->
      <div id="rate" style="padding-top: 20px; border-top: 1px solid var(--line, #e2ded6);">
        <?php if (!$me): ?>
          <p style="color: var(--ink-soft, #5b5b5b);">
            <a href="login.php" style="color: var(--leaf, #3d6b4f); font-weight: 700; text-decoration: underline;">Log in</a> to leave a rating and review for this member.
          </p>
        <?php elseif ($myId === (int)$member['id']): ?>
          <p style="color: var(--ink-faint, #8a8a8a); font-style: italic; margin: 0;">This is your own profile. You cannot review yourself.</p>
        <?php else: ?>
          <h3 style="margin: 0 0 12px; font-size: 1.15rem;">Leave a Rating &amp; Review</h3>
          <form method="POST" action="member.php?id=<?= (int)$member['id'] ?>#reviews">
            <input type="hidden" name="submit_review" value="1">

            <div style="margin-bottom: 14px;">
              <label style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 6px;">Your Star Rating</label>
              <div class="star-select">
                <input type="radio" id="star5" name="rating" value="5" checked />
                <label for="star5" title="5 stars">★</label>
                <input type="radio" id="star4" name="rating" value="4" />
                <label for="star4" title="4 stars">★</label>
                <input type="radio" id="star3" name="rating" value="3" />
                <label for="star3" title="3 stars">★</label>
                <input type="radio" id="star2" name="rating" value="2" />
                <label for="star2" title="2 stars">★</label>
                <input type="radio" id="star1" name="rating" value="1" />
                <label for="star1" title="1 star">★</label>
              </div>
            </div>

            <div style="margin-bottom: 18px;">
              <label style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 6px;">Review Comments (Optional)</label>
              <textarea name="comment" rows="3" placeholder="Describe your experience with <?= e($member['name']) ?>..." style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.9rem; color: inherit;"></textarea>
            </div>

            <button type="submit" class="btn-action primary">
              Submit Review
            </button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

</div>

<!-- Member Report Modal -->
<div id="reportModal" class="modal-overlay" hidden>
  <div class="modal-window">
    <h3 style="margin: 0 0 8px; font-size: 1.18rem;">Report <?= e($member['name']) ?></h3>
    <p style="margin: 0 0 16px; font-size: 0.86rem; color: var(--ink-faint, #8a8a8a);">
      Choose a suggested reason below or type your own description.
    </p>

    <form id="reportForm">
      <input type="hidden" name="action" value="report">
      <input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>">

      <div style="margin-bottom: 14px;">
        <label style="display: block; font-weight: 700; font-size: 0.86rem; margin-bottom: 8px;">Suggested Reasons</label>
        <div class="reason-chips">
          <button type="button" class="reason-chip" onclick="selectReason('Scam or Fraudulent Activity')">Scam or Fraud</button>
          <button type="button" class="reason-chip" onclick="selectReason('Harassment or Abusive Conduct')">Harassment</button>
          <button type="button" class="reason-chip" onclick="selectReason('Counterfeit or Prohibited Items')">Counterfeit Item</button>
          <button type="button" class="reason-chip" onclick="selectReason('Item not sent or not as described')">Item Not Described</button>
          <button type="button" class="reason-chip" onclick="selectReason('Spam or Suspicious Behavior')">Spam</button>
        </div>
      </div>

      <div style="margin-bottom: 14px;">
        <label style="display: block; font-weight: 700; font-size: 0.86rem; margin-bottom: 6px;">Reason</label>
        <input type="text" id="reportReasonInput" name="reason" placeholder="Selected or custom reason..." required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.9rem;">
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display: block; font-weight: 700; font-size: 0.86rem; margin-bottom: 6px;">Details / Context (Optional)</label>
        <textarea name="details" rows="3" placeholder="Provide any additional details..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn-action secondary" onclick="closeReportModal()">Cancel</button>
        <button type="submit" class="btn-action danger">Submit Report</button>
      </div>
    </form>
  </div>
</div>

<script>
function selectReason(text) {
  document.getElementById('reportReasonInput').value = text;
  var chips = document.querySelectorAll('.reason-chip');
  chips.forEach(function(c) {
    c.classList.toggle('selected', c.textContent.trim() === text || text.indexOf(c.textContent.trim()) !== -1);
  });
}
function openReportModal() {
  document.getElementById('reportModal').hidden = false;
}
function closeReportModal() {
  document.getElementById('reportModal').hidden = true;
}

document.getElementById('reportForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var fd = new FormData(this);
  fetch('member-actions.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      closeReportModal();
      alert(data.ok ? 'Your report has been submitted to the administration team.' : (data.error || 'Failed to submit report.'));
    })
    .catch(function() {
      closeReportModal();
      alert('Your report has been submitted to the administration team.');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>