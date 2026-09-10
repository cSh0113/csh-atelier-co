<?php
/* ============================================================
   CSH ATELIER CO. configuration

   Every setting the site depends on lives here: the database, the domain,
   the credit rate and the payment keys. Each one checks for an environment
   variable first and falls back to the value written here, which is what
   lets the same code run on my machine and on the live host.
   ============================================================ */

// ---------- DATABASE ----------
// InfinityFree production database. Environment variables still take
// precedence when the project is run locally or in another environment.
define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ---------- SITE ----------
define('SITE_NAME', 'CSH Atelier Co.');
// Use the hosted domain in production. Set SITE_URL in the environment to
// override this for local development or a staging URL.
if (!defined('SITE_URL')) {
    $envUrl = getenv('SITE_URL');
    if ($envUrl) {
        define('SITE_URL', rtrim($envUrl, '/'));
    } else {
        define('SITE_URL', 'https://cshatelier.ct.ws');
    }
}
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Store credits: how many credits equal R1
define('CREDIT_RATE', 10);          // 10 credits = R1.00
define('CREDITS_PER_ITEM', 50);     // default award per donated item
define('SIGNUP_BONUS', 20);
define('SHIPPING_FLAT', 150.00);
define('SELLER_COMMISSION', 0.05);  // platform keeps 5% of a C2C sale

// Paystack keys for the checkout.
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: '');
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: '');
define('PAYSTACK_CURRENCY', 'ZAR');

// Giphy powers the GIF picker in the chat. Leave it blank and the GIF tab
// simply does not appear, the emoji picker still works on its own.
define('GIPHY_API_KEY', getenv('GIPHY_API_KEY') ?: '');

// Google and Apple sign in. If the keys are blank the buttons just stay hidden.
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');


// ---------- ERROR REPORTING ----------
// Turn display off in production.
define('DEBUG', false);
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ---------- SESSION (hardened) ----------
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Regenerate the session ID periodically to limit fixation risk.
if (!isset($_SESSION['_born'])) {
    $_SESSION['_born'] = time();
} elseif (time() - $_SESSION['_born'] > 300) {
    session_regenerate_id(true);
    $_SESSION['_born'] = time();
}

// ---------- DATABASE CONNECTION (PDO singleton) ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (DEBUG) {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('Service temporarily unavailable.');
        }
    }
    return $pdo;
}
