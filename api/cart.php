<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

function out(bool $ok, string $msg = '', array $extra = []): void {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg, 'count' => cart_count()], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'POST required.');
if (!verify_csrf($_POST['csrf_token'] ?? null)) out(false, 'Security token expired, please refresh.');

$action = $_POST['action'] ?? '';
$type   = ($_POST['type'] ?? '') === 'listing' ? 'listing' : 'product';
$id     = (int)($_POST['id'] ?? 0);

if ($action !== 'add' || $id <= 0) out(false, 'Invalid request.');

if ($type === 'product') {
    $q = db()->prepare('SELECT id,name,stock FROM products WHERE id = ? AND status = "active"');
    $q->execute([$id]);
    $item = $q->fetch();
    if (!$item)                 out(false, 'That item is unavailable.');
    if ((int)$item['stock'] < 1) out(false, 'That item is sold out.');
    cart_add('product', $id, 1);
    out(true, $item['name'] . ' added to your bag');
}

$q = db()->prepare('SELECT l.id,l.title,l.seller_id FROM listings l WHERE l.id = ? AND l.status = "active"');
$q->execute([$id]);
$item = $q->fetch();
if (!$item) out(false, 'That listing has sold.');

$u = current_user();
if ($u && (int)$u['id'] === (int)$item['seller_id']) out(false, 'That is your own listing.');

cart_add('listing', $id, 1);
out(true, $item['title'] . ' added to your bag');
