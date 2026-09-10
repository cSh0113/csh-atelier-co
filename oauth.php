<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$provider = $_GET['provider'] ?? 'google';

// Google has to send the member back to this exact address or it refuses
// the login, so I build it off the live host instead of typing it out.
$host = $_SERVER['HTTP_HOST'] ?? 'cshatelier.ct.ws';
$redirectUri = 'https://' . $host . '/oauth.php';

$googleClientId     = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : (getenv('GOOGLE_CLIENT_ID') ?: '');
$googleClientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : (getenv('GOOGLE_CLIENT_SECRET') ?: '');

if (empty($googleClientId) || empty($googleClientSecret)) {
    header('Location: login.php?error=oauth_not_configured');
    exit;
}

// Step one. Nobody has a code yet, so send them off to Google to sign in.
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = [
        'client_id'     => $googleClientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account'
    ];

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    exit;
}

// Step two. They are back with a code, so check the state matches first.
$state = $_GET['state'] ?? '';
if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
    header('Location: login.php?error=invalid_state');
    exit;
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: login.php?error=no_code');
    exit;
}

// Swap that code for a real access token.
$tokenPost = [
    'code'          => $code,
    'client_id'     => $googleClientId,
    'client_secret' => $googleClientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenPost));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode((string)$response, true);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    header('Location: login.php?error=token_failed');
    exit;
}

// Now I can ask Google who they actually are.
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode((string)$userResponse, true);
$email    = trim($userData['email'] ?? '');
$name     = trim($userData['name'] ?? 'CSH Member');
$avatar   = trim($userData['picture'] ?? '');

if (empty($email)) {
    header('Location: login.php?error=no_email');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    if (empty($user['avatar_url']) && !empty($avatar)) {
        try {
            $up = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $up->execute([$avatar, (int)$user['id']]);
        } catch (Exception $e) {}
    }
} else {
    $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    try {
        $ins = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, avatar_url, credits, items_diverted, status, created_at)
            VALUES (?, ?, ?, 'member', ?, 20, 0, 'active', NOW())
        ");
        $ins->execute([$name, $email, $randomPass, $avatar ?: null]);
        $userId = (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        $ins = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, credits, items_diverted, status, created_at)
            VALUES (?, ?, ?, 'member', 20, 0, 'active', NOW())
        ");
        $ins->execute([$name, $email, $randomPass]);
        $userId = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

$_SESSION['user_id'] = (int)$user['id'];
header('Location: account.php');
exit;