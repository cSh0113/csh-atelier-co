<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('marketplace.php');
require_csrf();
$type = ($_POST['item_type'] ?? '') === 'product' ? 'product' : 'listing';
$id = (int)($_POST['item_id'] ?? 0);
$return = trim($_POST['return'] ?? 'marketplace.php');
$return = preg_replace('/[^a-zA-Z0-9_\-\/.?=&]/', '', $return) ?: 'marketplace.php';
$pdo = db(); $uid = (int)current_user()['id'];
$action = $_POST['action'] ?? 'toggle';
if ($action === 'move_to_cart') {
    if ($type === 'listing') { $check=$pdo->prepare('SELECT id FROM listings WHERE id=? AND status="active"'); } else { $check=$pdo->prepare('SELECT id FROM products WHERE id=? AND status="active" AND stock>0'); }
    $check->execute([$id]);
    if ($check->fetchColumn()) { cart_add($type,$id,1); $pdo->prepare('DELETE FROM wishlists WHERE user_id=? AND item_type=? AND item_id=?')->execute([$uid,$type,$id]); flash('Moved to your cart.'); }
    else flash('That item is no longer available.','error');
    redirect('account.php?tab=wishlist');
}
$st = $pdo->prepare('SELECT id FROM wishlists WHERE user_id=? AND item_type=? AND item_id=?');
$st->execute([$uid,$type,$id]);
if ($st->fetch()) {
    $pdo->prepare('DELETE FROM wishlists WHERE user_id=? AND item_type=? AND item_id=?')->execute([$uid,$type,$id]);
    flash('Removed from your wishlist.');
} else {
    $pdo->prepare('INSERT INTO wishlists (user_id,item_type,item_id) VALUES (?,?,?)')->execute([$uid,$type,$id]);
    flash('Saved to your wishlist.');
}
redirect($return);
