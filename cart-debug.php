<?php
// Temporary diagnostic. Tells me exactly why the cart badge and the cart
// page disagree. Delete this file once the cart is behaving.

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$o = cart_owner();
$u = current_user();

echo "WHO AM I\n";
echo "========\n";
echo "logged in      : " . (is_logged_in() ? 'yes' : 'no') . "\n";
echo "user id        : " . var_export($o['user_id'], true) . "\n";
echo "guest cart key : " . var_export($o['session_id'], true) . "\n";
echo "php session id : " . session_id() . "\n";
echo "session user_id: " . var_export($_SESSION['user_id'] ?? null, true) . "\n";
echo "session born   : " . var_export($_SESSION['_born'] ?? null, true) . "\n";
if ($u) echo "user status    : " . $u['status'] . "\n";
echo "\n";

$pdo = db();

echo "EVERY ROW IN cart_items (whole table)\n";
echo "=====================================\n";
$all = $pdo->query('SELECT * FROM cart_items ORDER BY id DESC LIMIT 40')->fetchAll();
if (!$all) {
    echo "The table is completely empty. Nothing was ever inserted.\n";
} else {
    printf("%-5s %-8s %-34s %-9s %-8s %-4s %s\n",
           'id', 'user_id', 'session_id', 'item_type', 'item_id', 'qty', 'added_at');
    foreach ($all as $r) {
        printf("%-5s %-8s %-34s %-9s %-8s %-4s %s\n",
            $r['id'],
            var_export($r['user_id'], true),
            var_export($r['session_id'], true),
            $r['item_type'],
            $r['item_id'],
            $r['qty'],
            $r['added_at']);
    }
}
echo "\n";

echo "ROWS THAT MATCH MY IDENTITY\n";
echo "===========================\n";
echo "matching on: (user_id <=> " . var_export($o['user_id'], true) .
     ") AND (session_id <=> " . var_export($o['session_id'], true) . ")\n\n";
$mine = $pdo->prepare('SELECT * FROM cart_items
                       WHERE (user_id <=> ?) AND (session_id <=> ?)
                       ORDER BY added_at DESC');
$mine->execute([$o['user_id'], $o['session_id']]);
$rows = $mine->fetchAll();

if (!$rows) {
    echo "No rows match. If the table above has rows but none match here,\n";
    echo "the cart was filled under a different identity, which means the\n";
    echo "session changed between adding and viewing.\n";
} else {
    foreach ($rows as $r) {
        echo "cart row #{$r['id']}: {$r['item_type']} #{$r['item_id']} qty {$r['qty']}\n";

        if ($r['item_type'] === 'product') {
            $q = $pdo->prepare('SELECT id, name, status, stock FROM products WHERE id = ?');
            $q->execute([$r['item_id']]);
            $item = $q->fetch();
            if (!$item) {
                echo "   FAILS: no product with id {$r['item_id']} exists at all\n";
            } elseif ($item['status'] !== 'active') {
                echo "   FAILS: product status is '{$item['status']}', cart_get only accepts 'active'\n";
            } else {
                echo "   OK: '{$item['name']}' is active, should show on the cart page\n";
            }
        } else {
            $q = $pdo->prepare('SELECT id, title, status, seller_id FROM listings WHERE id = ?');
            $q->execute([$r['item_id']]);
            $item = $q->fetch();
            if (!$item) {
                echo "   FAILS: no listing with id {$r['item_id']} exists at all\n";
            } elseif ($item['status'] !== 'active') {
                echo "   FAILS: listing status is '{$item['status']}', cart_get only accepts 'active'\n";
            } else {
                // cart_get also joins users, so a missing seller kills the row too
                $s = $pdo->prepare('SELECT id, name, status FROM users WHERE id = ?');
                $s->execute([$item['seller_id']]);
                $seller = $s->fetch();
                if (!$seller) {
                    echo "   FAILS: listing is active but seller #{$item['seller_id']} does not exist,\n";
                    echo "          and cart_get uses JOIN users so the row disappears\n";
                } else {
                    echo "   OK: '{$item['title']}' is active, seller '{$seller['name']}' exists\n";
                }
            }
        }
        echo "\n";
    }
}

echo "WHAT EACH FUNCTION REPORTS\n";
echo "==========================\n";
$cart = cart_get();
echo "cart_count() says : " . cart_count() . "\n";
echo "cart_get() returns: " . count($cart['items']) . " items, subtotal " . $cart['subtotal'] . "\n";
echo "\n";
echo "If cart_count is higher than cart_get, the extra rows are the ones\n";
echo "marked FAILS above.\n";
