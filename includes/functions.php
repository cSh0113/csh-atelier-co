<?php
require_once __DIR__ . '/../config/config.php';

/* Small helpers I use on nearly every page. Formatting and escaping. */

/** Everything a user typed goes through here before it hits the page,
 *  otherwise somebody drops a script tag into a listing title and it runs. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Rands, always two decimals, always with the R in front. */
function money($amount): string
{
    return 'R' . number_format((float)$amount, 2);
}

/** Credits to rands, using the rate set in config. */
function credits_to_rand(int $credits): float
{
    return round($credits / CREDIT_RATE, 2);
}

/** Rands back to credits, the other direction. */
function rand_to_credits(float $rand): int
{
    return (int)round($rand * CREDIT_RATE);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item-' . time();
}

/** Builds a link off SITE_URL so I never hardcode the domain in a page. */
function url(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Asset paths stay relative, never absolute. My normal pages sit at the root
 * and the admin pages sit one folder down, so working it out this way means
 * the CSS still loads on localhost, on a dev tunnel and on the live domain.
 */
function asset(string $path): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $prefix = (substr($dir, -6) === '/admin') ? '../' : '';
    return $prefix . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/* Flash messages. Drop a note in the session, show it once on the next page. */

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Only sends the mail if the member actually opted in to that type. */
function notify_user_email(int $userId, string $preference, string $subject, string $body): bool
{
    $allowed = ['notify_orders', 'notify_messages', 'notify_marketing'];
    if (!in_array($preference, $allowed, true)) return false;
    $st = db()->prepare('SELECT email,name,' . $preference . ' AS enabled FROM users WHERE id=? AND status="active"');
    $st->execute([$userId]);
    $u = $st->fetch();
    if (!$u || !(int)$u['enabled'] || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) return false;
    $headers = 'From: CSH Atelier Co. <admin@cshinnovations.com>\r\n' .
               'Reply-To: admin@cshinnovations.com\r\n' .
               'Content-Type: text/plain; charset=UTF-8';
    return @mail($u['email'], $subject, "Hi {$u['name']},\n\n{$body}\n\nCSH Atelier Co.", $headers);
}

/* CSRF tokens. Every form on the site carries one so a random site cannot
   post to my forms on a logged in member's behalf. */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/** No valid token means the request dies right here. */
function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        die('Invalid or expired security token. Please go back and try again.');
    }
}

/* Login, logout and who is currently signed in. */

function current_user(): ?array
{
    static $user = null;
    if ($user !== null) return $user ?: null;

    if (empty($_SESSION['user_id'])) { $user = false; return null; }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "active"');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $user = $row ?: false;
    return $row ?: null;
}

function is_logged_in(): bool { return current_user() !== null; }

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('Please sign in to continue.', 'info');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Access denied.');
    }
}

/* Store credits. This is the money side of the loop so it has to be exact. */

/**
 * Moves credits and writes the ledger line in one go. Both happen or neither
 * does, so a balance can never drift away from its history.
 * Positive amount means they earned, negative means they spent.
 */
function adjust_credits(int $userId, int $amount, string $reason,
                        ?string $reference = null, ?string $note = null): int
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();

    try {
        // Lock the row first. If someone checks out on two tabs at once
        // without this, both reads see the old balance and they spend twice.
        $s = $pdo->prepare('SELECT credits FROM users WHERE id = ? FOR UPDATE');
        $s->execute([$userId]);
        $balance = (int)$s->fetchColumn();

        $newBalance = $balance + $amount;
        if ($newBalance < 0) {
            throw new RuntimeException('Insufficient credits.');
        }

        $pdo->prepare('UPDATE users SET credits = ? WHERE id = ?')
            ->execute([$newBalance, $userId]);

        $pdo->prepare('INSERT INTO credit_transactions
                (user_id, amount, balance_after, reason, reference, note)
                VALUES (?,?,?,?,?,?)')
            ->execute([$userId, $amount, $newBalance, $reason, $reference, $note]);

        if ($own) $pdo->commit();
        return $newBalance;
    } catch (Throwable $ex) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

/* Cart. Works for a guest too, which is why there is a session key below. */

/** Whose cart is this. A member id if they are logged in, otherwise a
 *  session key so a guest can still shop before they register. */
function cart_owner(): array
{
    $u = current_user();
    if ($u) return ['user_id' => $u['id'], 'session_id' => null];

    if (empty($_SESSION['cart_key'])) {
        $_SESSION['cart_key'] = bin2hex(random_bytes(16));
    }
    return ['user_id' => null, 'session_id' => $_SESSION['cart_key']];
}

function cart_add(string $type, int $itemId, int $qty = 1): bool
{
    // Only these two values exist in the item_type enum. Anything else
    // used to get stored as a blank string, which left a row in the cart
    // that nothing could ever resolve back to a real item. Now it just
    // refuses, so a bad call shows up straight away instead of quietly
    // filling the table with rows the cart page cannot display.
    if ($type !== 'product' && $type !== 'listing') {
        error_log("cart_add called with invalid type: " . var_export($type, true));
        return false;
    }
    if ($itemId <= 0 || $qty < 1) return false;

    $o = cart_owner();
    $sql = 'INSERT INTO cart_items (user_id, session_id, item_type, item_id, qty)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)';
    db()->prepare($sql)->execute([$o['user_id'], $o['session_id'], $type, $itemId, $qty]);
    return true;
}

function cart_set_qty(int $cartId, int $qty): void
{
    $o = cart_owner();
    if ($qty < 1) { cart_remove($cartId); return; }
    db()->prepare('UPDATE cart_items SET qty = ? WHERE id = ?
                   AND (user_id <=> ?) AND (session_id <=> ?)')
        ->execute([$qty, $cartId, $o['user_id'], $o['session_id']]);
}

function cart_remove(int $cartId): void
{
    $o = cart_owner();
    db()->prepare('DELETE FROM cart_items WHERE id = ?
                   AND (user_id <=> ?) AND (session_id <=> ?)')
        ->execute([$cartId, $o['user_id'], $o['session_id']]);
}

/** Pulls the cart with the product and listing details already joined on,
 *  so the cart page does not have to query again inside a loop. */
function cart_get(): array
{
    $o = cart_owner();
    $stmt = db()->prepare('SELECT * FROM cart_items
                           WHERE (user_id <=> ?) AND (session_id <=> ?)
                           ORDER BY added_at DESC');
    $stmt->execute([$o['user_id'], $o['session_id']]);
    $rows = $stmt->fetchAll();

    $items = [];
    $subtotal = 0.0;

    foreach ($rows as $r) {
        if ($r['item_type'] === 'product') {
            $q = db()->prepare('SELECT *
                                FROM products WHERE id = ? AND status = "active"');
        } else {
            $q = db()->prepare('SELECT l.id, l.title, l.price, l.image_url,
                                       1 AS stock, 0 AS is_remade,
                                       l.seller_id, u.name AS seller_name
                                FROM listings l
                                JOIN users u ON u.id = l.seller_id
                                WHERE l.id = ? AND l.status = "active"');
        }
        $q->execute([$r['item_id']]);
        $item = $q->fetch();
        if (!$item) { continue; }   // item gone/sold, skip
        if ($r['item_type'] === 'product') {
            $item['title'] = $item['name'];
            $item['external_source'] = $item['external_source'] ?? null;
            $item['external_url'] = $item['external_url'] ?? null;
        }

        $qty  = max(1, (int)$r['qty']);
        if ($r['item_type'] === 'listing') $qty = 1;   // a listing is one item only, never a quantity
        $line = (float)$item['price'] * $qty;
        $subtotal += $line;

        $items[] = [
            'cart_id'   => (int)$r['id'],
            'type'      => $r['item_type'],
            'qty'       => $qty,
            'line_total'=> $line,
            'item'      => $item,
        ];
    }

    return [
        'items'    => $items,
        'count'    => array_sum(array_column($items, 'qty')),
        'subtotal' => $subtotal,
    ];
}

function cart_count(): int
{
    $o = cart_owner();
    // Only count items whose listing or product is still active and exists,
    // so the badge matches what cart_get() actually shows on the page.
    $s = db()->prepare('
        SELECT COALESCE(SUM(c.qty), 0)
        FROM cart_items c
        LEFT JOIN products p ON c.item_type = "product" AND p.id = c.item_id AND p.status = "active"
        LEFT JOIN listings l ON c.item_type = "listing" AND l.id = c.item_id AND l.status = "active"
        WHERE (c.user_id <=> ?) AND (c.session_id <=> ?)
          AND (p.id IS NOT NULL OR l.id IS NOT NULL)
    ');
    $s->execute([$o['user_id'], $o['session_id']]);
    return (int)$s->fetchColumn();
}

/** After login I move the guest cart across so nothing they picked is lost. */
function cart_merge_guest(int $userId): void
{
    if (empty($_SESSION['cart_key'])) return;
    $key = $_SESSION['cart_key'];

    $rows = db()->prepare('SELECT * FROM cart_items WHERE session_id = ?');
    $rows->execute([$key]);
    foreach ($rows->fetchAll() as $r) {
        db()->prepare('INSERT INTO cart_items (user_id, item_type, item_id, qty)
                       VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)')
            ->execute([$userId, $r['item_type'], $r['item_id'], $r['qty']]);
    }
    db()->prepare('DELETE FROM cart_items WHERE session_id = ?')->execute([$key]);
    unset($_SESSION['cart_key']);
}

/* Image uploads. Members upload their own listing photos so this part has
   to be strict about what it accepts. */

/**
 * Checks the file, renames it and stores it. Gives back the public URL,
 * or null if it was not a real image.
 */
function upload_image(array $file, string $prefix = 'img'): ?string
{
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 5 * 1024 * 1024 || $file['size'] < 100) return null; // 5 MB cap

    // Read the actual file type, not the extension. Anyone can rename
    // a php script to .jpg, so the extension proves nothing.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) return null;
    $dimensions = @getimagesize($file['tmp_name']);
    if (!$dimensions || $dimensions[0] < 200 || $dimensions[1] < 200 || $dimensions[0] > 8000 || $dimensions[1] > 8000) return null;

    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);

    $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) return null;

    // Serve through the media proxy so InfinityFree's 403 on /uploads/ is bypassed.
    return SITE_URL . '/media.php?f=' . urlencode($name);
}

/* Chat attachments: photos, videos and voice notes members send each
   other. Kept separate from upload_image() above because a quick chat
   snapshot does not need the minimum dimensions a product photo does,
   and video and voice need their own size caps and file types. */

/** Turns a php.ini size like "8M" or "512K" into plain bytes. */
function ini_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '') return 0;
    $unit = strtolower($val[strlen($val) - 1]);
    $num = (int)$val;
    if ($unit === 'g') return $num * 1024 * 1024 * 1024;
    if ($unit === 'm') return $num * 1024 * 1024;
    if ($unit === 'k') return $num * 1024;
    return $num;
}

/**
 * The real ceiling for one upload on this server. Shared hosting often
 * sets upload_max_filesize and post_max_size lower than anything I ask
 * for, and the smaller of the two always wins, so the app has to work
 * off what the server actually permits rather than what I hoped for.
 */
function max_upload_bytes(): int
{
    $u = ini_bytes((string)ini_get('upload_max_filesize'));
    $p = ini_bytes((string)ini_get('post_max_size'));
    $candidates = array_filter([$u, $p]);
    return $candidates ? min($candidates) : 2 * 1024 * 1024;
}

/**
 * Checks the file, renames it and stores it. Returns either an array
 * with ['url' => '...'] on success, or ['error' => '...'] with a
 * human readable message on failure. This replaced the old version
 * that just returned null, which made every upload failure look the same.
 */
function upload_chat_media(array $file, string $kind): array
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file was received by the server.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $codes = [
            UPLOAD_ERR_INI_SIZE => 'The file is larger than the server allows.',
            UPLOAD_ERR_FORM_SIZE => 'The file is larger than the form allows.',
            UPLOAD_ERR_PARTIAL => 'The file was only partly uploaded. Try again.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temp folder. Contact the admin.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not save the file. Contact the admin.',
        ];
        return ['error' => $codes[$file['error']] ?? 'Upload error code ' . $file['error']];
    }

    $limits = [
        'image' => ['max' => 8 * 1024 * 1024, 'mimes' => [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        ]],
        'video' => ['max' => 40 * 1024 * 1024, 'mimes' => [
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
        ]],
        // Whatever the browser's own microphone recorder produces. A voice
        // note is audio only, but the browser still wraps it in a WebM or
        // MP4 container, and finfo reads the container not the contents,
        // so it comes back as video/webm on iOS Safari and some Android
        // browsers. Those have to be allowed here or every voice note from
        // a phone gets rejected. The extension I store it under is the
        // audio one, because that is what the audio player expects.
        'voice' => ['max' => 12 * 1024 * 1024, 'mimes' => [
            'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
            'audio/aac' => 'aac', 'audio/x-m4a' => 'm4a', 'audio/3gpp' => '3gp',
            'video/webm' => 'webm', 'video/mp4' => 'm4a', 'video/ogg' => 'ogg',
            'video/3gpp' => '3gp', 'video/quicktime' => 'm4a',
            'application/octet-stream' => 'webm',
        ]],
    ];
    if (!isset($limits[$kind])) {
        return ['error' => 'Unknown media type: ' . $kind];
    }

    // Never allow more than the server itself will accept.
    $cap = min($limits[$kind]['max'], max_upload_bytes());
    $capMb = round($cap / 1048576, 1);
    if ($file['size'] > $cap) {
        $sizeMb = round($file['size'] / 1048576, 1);
        return ['error' => "That file is {$sizeMb} MB but the limit is {$capMb} MB."];
    }
    if ($file['size'] < 100) {
        return ['error' => 'That file is too small to be valid.'];
    }

    // Same rule as every other upload on the site: trust the file's
    // real contents, never the extension the browser sent.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = $limits[$kind]['mimes'];
    if (!isset($allowed[$mime])) {
        // Log what was actually detected so I can see what InfinityFree
        // is reporting and add it to the list if it is a real audio type.
        return ['error' => "That file type ({$mime}) is not supported for {$kind}. Try a different format."];
    }

    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
    if (!is_writable(UPLOAD_DIR)) {
        return ['error' => 'The upload folder is not writable. Contact the admin.'];
    }

    $name = 'chat-' . $kind . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        return ['error' => 'Could not save the file. Try again.'];
    }

    // Same proxy as listing images, so InfinityFree does not 403 the file.
    return ['url' => SITE_URL . '/media.php?f=' . urlencode($name)];
}

function audit_log(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
{
    $u = current_user();
    if (!$u || !is_admin()) return;
    try {
        db()->prepare('INSERT INTO admin_audit_logs (admin_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?)')
            ->execute([(int)$u['id'], $action, $entityType, $entityId, $details]);
    } catch (Throwable $e) {
        // If the database has not run the later upgrade files yet, swallow it
        // so the admin panel still opens instead of throwing a fatal.
    }
}

/** Placeholder so a listing with no photo does not render a broken image. */
function img_or_placeholder(?string $url, string $seed = ''): string
{
    if ($url) {
        if (preg_match('~^https?://~i', $url) || str_starts_with($url, '/')) return $url;
        return asset($url);
    }
    return asset('assets/img/placeholder.svg') . '?s=' . urlencode($seed);
}

/* Settings I can change from the admin panel without editing config. */

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

/* The counters on the home page. */

/** The site wide impact numbers that count up on the home page. */
function impact_stats(): array
{
    $pdo = db();
    $donatedItems = (int)$pdo->query(
        'SELECT COALESCE(SUM(item_count),0) FROM donations
         WHERE outcome IN ("remade","resold","recycled")')->fetchColumn();
    $resold = (int)$pdo->query(
        'SELECT COUNT(*) FROM listings WHERE status = "sold"')->fetchColumn();
    $remade = (int)$pdo->query(
        'SELECT COUNT(*) FROM products WHERE is_remade = 1')->fetchColumn();
    $members = (int)$pdo->query(
        'SELECT COUNT(*) FROM users WHERE role = "member"')->fetchColumn();

    // These are estimates, not measured results, and I say so on the page.
    // The commonly cited figures for one new cotton tee are around
    // 2700 litres of water and about 7 kg of CO2e, so I work off those.
    return [
        'items_diverted' => $donatedItems + $resold,
        'resold'         => $resold,
        'remade'         => $remade,
        'members'        => $members,
        'water_litres'   => ($donatedItems + $resold) * 2700,
        'co2_kg'         => ($donatedItems + $resold) * 7,
    ];
}

/* Paystack. This is what actually takes the card payment at checkout. */

function paystack_request(string $endpoint, string $method = 'GET', ?array $payload = null): array
{
    $ch = curl_init('https://api.paystack.co/' . ltrim($endpoint, '/'));
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $error) throw new RuntimeException('Payment service unavailable. Please try again.');
    $data = json_decode($raw, true);
    if (!is_array($data) || $status >= 400 || empty($data['status'])) {
        throw new RuntimeException($data['message'] ?? 'Payment service rejected the request.');
    }
    return $data;
}

function paystack_initialize(string $email, int $amountCents, string $reference, array $metadata): array
{
    return paystack_request('transaction/initialize', 'POST', [
        'email'        => $email,
        'amount'       => $amountCents,
        'currency'     => PAYSTACK_CURRENCY,
        'reference'    => $reference,
        'callback_url' => url('checkout.php'),
        'metadata'     => $metadata,
    ]);
}

function paystack_verify(string $reference): array
{
    return paystack_request('transaction/verify/' . rawurlencode($reference));
}

/* Odds and ends that did not belong anywhere else. */

function condition_label(string $c): string
{
    return [
        'new'        => 'New with tags',
        'like_new'   => 'Like new',
        'good'       => 'Good',
        'well_loved' => 'Well loved',
    ][$c] ?? ucfirst($c);
}

function next_reference(string $prefix, string $table, string $column): string
{
    $n = (int)db()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() + 1001;
    return $prefix . '-' . $n;
}