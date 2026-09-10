<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$me = current_user();
$myId = (int)$me['id'];
$pdo = db();

$memberId = (int)($_GET['id'] ?? $_POST['member_id'] ?? 0);
if ($memberId <= 0) {
    header('Location: marketplace.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, avatar_url, role FROM users WHERE id = ? AND status = 'active'");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member || $member['role'] === 'admin' || $memberId === $myId) {
    header('Location: member.php?id=' . $memberId);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');

    try {
        // Inspect table columns dynamically
        $cols = $pdo->query("SHOW COLUMNS FROM `member_ratings`")->fetchAll(PDO::FETCH_COLUMN);

        $targetCol = in_array('member_id', $cols, true) ? 'member_id' : (in_array('rated_user_id', $cols, true) ? 'rated_user_id' : (in_array('rated_id', $cols, true) ? 'rated_id' : 'user_id'));
        $raterCol  = in_array('rater_id', $cols, true) ? 'rater_id' : (in_array('reviewer_id', $cols, true) ? 'reviewer_id' : 'user_id');

        $chk = $pdo->prepare("SELECT id FROM `member_ratings` WHERE `{$targetCol}` = ? AND `{$raterCol}` = ?");
        $chk->execute([$memberId, $myId]);
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
        } else {
            // Build insert matching all schema variations (rater_id, reviewer_id, member_id, etc.)
            $fields = [];
            if (in_array('rater_id', $cols, true))     { $fields['rater_id'] = $myId; }
            if (in_array('reviewer_id', $cols, true))  { $fields['reviewer_id'] = $myId; }
            if (in_array('member_id', $cols, true))    { $fields['member_id'] = $memberId; }
            if (in_array('rated_user_id', $cols, true)){ $fields['rated_user_id'] = $memberId; }
            if (in_array('rated_id', $cols, true))     { $fields['rated_id'] = $memberId; }
            if (in_array('user_id', $cols, true) && !in_array('member_id', $cols, true) && !in_array('rated_user_id', $cols, true)) {
                $fields['user_id'] = $memberId;
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
        }

        header('Location: member.php?id=' . $memberId . '&reviewed=1#reviews');
        exit;
    } catch (Exception $e) {
        $error = 'Could not save rating: ' . $e->getMessage();
    }
}

$pageTitle = 'Rate ' . $member['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 540px; margin: 40px auto; padding: 0 16px;">
  <div style="background: var(--bg-raised, #fff); border-radius: 16px; padding: 32px; border: 1px solid var(--line, #e2ded6); box-shadow: var(--nm-out, 0 6px 25px rgba(0,0,0,0.06)); text-align: center;">
    
    <?php if (!empty($member['avatar_url'])): ?>
      <img src="<?= e($member['avatar_url']) ?>" alt="<?= e($member['name']) ?>" style="width: 76px; height: 76px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; border: 2px solid var(--leaf, #3d6b4f);">
    <?php else: ?>
      <div style="width: 76px; height: 76px; border-radius: 50%; background: var(--leaf, #3d6b4f); color: #fff; display: grid; place-items: center; font-size: 1.8rem; font-weight: 800; margin: 0 auto 12px;">
        <?= e(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?>
      </div>
    <?php endif; ?>

    <h1 style="margin: 0 0 6px; font-size: 1.45rem;">Rate <?= e($member['name']) ?></h1>
    <p style="margin: 0 0 24px; color: var(--ink-faint, #8a8a8a); font-size: 0.88rem;">Rate your buying or selling interaction.</p>

    <?php if (!empty($error)): ?>
      <div style="padding: 12px 14px; border-radius: 8px; background: #fdeeec; color: #8f3a30; margin-bottom: 20px; font-size: 0.88rem; text-align: left; line-height: 1.45;">
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="rate-member.php?id=<?= $memberId ?>">
      <input type="hidden" name="submit_rating" value="1">
      <input type="hidden" name="member_id" value="<?= $memberId ?>">

      <div style="margin-bottom: 20px;">
        <label style="display: block; font-weight: 700; font-size: 0.9rem; margin-bottom: 8px;">Star Rating</label>
        <div style="display: inline-flex; flex-direction: row-reverse; font-size: 2.4rem; gap: 6px;">
          <input type="radio" id="r5" name="rating" value="5" checked style="display:none;" />
          <label for="r5" style="color:#d1c7b7;cursor:pointer;">★</label>
          <input type="radio" id="r4" name="rating" value="4" style="display:none;" />
          <label for="r4" style="color:#d1c7b7;cursor:pointer;">★</label>
          <input type="radio" id="r3" name="rating" value="3" style="display:none;" />
          <label for="r3" style="color:#d1c7b7;cursor:pointer;">★</label>
          <input type="radio" id="r2" name="rating" value="2" style="display:none;" />
          <label for="r2" style="color:#d1c7b7;cursor:pointer;">★</label>
          <input type="radio" id="r1" name="rating" value="1" style="display:none;" />
          <label for="r1" style="color:#d1c7b7;cursor:pointer;">★</label>
        </div>
      </div>

      <div style="margin-bottom: 24px; text-align: left;">
        <label style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 6px;">Review Comments (Optional)</label>
        <textarea name="comment" rows="3" placeholder="Share your feedback with the community..." style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.9rem; color: inherit;"></textarea>
      </div>

      <div style="display: flex; gap: 10px; justify-content: center;">
        <a href="member.php?id=<?= $memberId ?>" style="padding: 11px 22px; border-radius: 999px; background: var(--bg-sunken, #eceae5); color: var(--ink, #1a1a1a); text-decoration: none; font-weight: 700; font-size: 0.9rem;">Cancel</a>
        <button type="submit" style="padding: 11px 26px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; border: 0; font-weight: 700; cursor: pointer; font: inherit; font-size: 0.9rem;">Publish Review</button>
      </div>
    </form>
  </div>
</div>

<style>
.container input:checked ~ label,
.container label:hover,
.container label:hover ~ label {
  color: #f5a623 !important;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>