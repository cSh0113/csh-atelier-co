<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
require_csrf();
$email = strtolower(trim($_POST['email'] ?? ''));
$return = trim($_POST['return'] ?? 'index.php');
$return = preg_replace('/[^a-zA-Z0-9_\-\/.?=&]/', '', $return) ?: 'index.php';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('Enter a valid email address.', 'error');
    redirect($return);
}

try {
    $uid = current_user()['id'] ?? null;
    db()->prepare('INSERT INTO newsletter_subscribers (email,user_id,status) VALUES (?,?,"subscribed")
                   ON DUPLICATE KEY UPDATE user_id=COALESCE(VALUES(user_id),user_id), status="subscribed"')
        ->execute([$email, $uid]);
    if ($uid) db()->prepare('UPDATE users SET newsletter_subscribed=1 WHERE id=?')->execute([$uid]);
    flash('You are subscribed to the CSH Atelier newsletter.');
} catch (Throwable $e) {
    flash('Newsletter signup is temporarily unavailable.', 'error');
}
redirect($return);
