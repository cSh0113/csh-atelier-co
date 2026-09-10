<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db(); $user = current_user(); $orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$st = $pdo->prepare('SELECT o.*,oi.id AS order_item_id,oi.title,oi.item_type,oi.item_id,r.rating,r.body AS review_body FROM orders o JOIN order_items oi ON oi.order_id=o.id LEFT JOIN reviews r ON r.order_item_id=oi.id AND r.reviewer_id=? WHERE o.id=? AND o.user_id=?');
$st->execute([$user['id'],$orderId,$user['id']]); $items=$st->fetchAll();
if (!$items) { flash('Order not found.', 'error'); redirect('account.php?tab=orders'); }
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_csrf(); $itemId=(int)($_POST['order_item_id']??0); $rating=(int)($_POST['rating']??0); $body=trim($_POST['body']??'');
  if ($rating<1||$rating>5) $errors[]='Choose a rating from 1 to 5.';
  if (!$errors) {
    $check=$pdo->prepare('SELECT oi.*,o.status,o.user_id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.id=? AND oi.order_id=? AND o.user_id=?'); $check->execute([$itemId,$orderId,$user['id']]); $item=$check->fetch();
    if (!$item) $errors[]='That item is not part of this order.';
    elseif (!in_array($item['status'],['paid','shipped','delivered'],true)) $errors[]='Reviews are available after payment is confirmed.';
    else { $pdo->prepare('INSERT INTO reviews (order_id,order_item_id,reviewer_id,item_type,item_id,rating,body) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),body=VALUES(body)')->execute([$orderId,$itemId,$user['id'],$item['item_type'],$item['item_id'],$rating,$body]); flash('Review saved.'); redirect('review.php?order_id='.$orderId); }
  }
}
$pageTitle='Review order'; include __DIR__.'/includes/header.php';
?>
<section class="sec" style="padding-top:44px"><div class="shell" style="max-width:760px"><div class="sec-head"><span class="mono"><?= e($items[0]['order_no']) ?></span><h2>Review your items</h2></div>
<?php foreach($errors as $error): ?><div class="panel-in mb-2" style="border-left:3px solid var(--clay)"><?= e($error) ?></div><?php endforeach; ?>
<?php foreach($items as $item): ?><form method="post" class="panel mb-2"><h3><?= e($item['title']) ?></h3><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="order_item_id" value="<?= (int)$item['order_item_id'] ?>"><div class="field"><label>Rating</label><select name="rating"><option value="">Choose a rating</option><?php for($r=5;$r>=1;$r--): ?><option value="<?= $r ?>" <?= (int)$item['rating']===$r?'selected':'' ?>><?= $r ?> / 5</option><?php endfor; ?></select></div><div class="field"><label>Review</label><textarea name="body" rows="3" placeholder="How was this item?"><?= e($item['review_body'] ?? '') ?></textarea></div><button class="btn btn-primary" type="submit">Save review</button></form><?php endforeach; ?>
</div></section><?php include __DIR__.'/includes/footer.php'; ?>
