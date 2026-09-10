<?php
// Serves uploaded files through PHP instead of direct URL access.
// InfinityFree blocks direct access to /uploads/ with a 403, so
// everything goes through here instead.

$file = basename($_GET['f'] ?? '');
if ($file === '' || strpos($file, '..') !== false || strpos($file, '/') !== false) {
    http_response_code(400);
    exit;
}

$path = __DIR__ . '/uploads/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
finfo_close($finfo);

// Only serve images, video and audio. Nothing else leaves this directory.
$safe = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    'video/mp4', 'video/webm', 'video/quicktime', 'video/ogg', 'video/3gpp',
    'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav',
    'audio/x-wav', 'audio/aac', 'audio/x-m4a', 'audio/3gpp',
    'application/octet-stream',
];
if (!in_array($mime, $safe, true)) {
    http_response_code(403);
    exit;
}

// A voice note is audio, but the browser wrapped it in a container that
// sniffs as video. The audio player will refuse that, so anything saved
// as a chat voice note goes out with an audio content type instead.
if (strpos($file, 'chat-voice-') === 0) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $audioTypes = [
        'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4',
        'mp3'  => 'audio/mpeg', 'wav' => 'audio/wav', 'aac' => 'audio/aac',
        '3gp'  => 'audio/3gpp',
    ];
    if (isset($audioTypes[$ext])) $mime = $audioTypes[$ext];
}

// Let the browser cache it for a day so it does not re-download on
// every poll cycle.
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
header('Accept-Ranges: bytes');

// Range request support for audio/video seeking.
$size = filesize($path);
if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
    $start = (int)$m[1];
    $end = $m[2] !== '' ? (int)$m[2] : $size - 1;
    if ($start > $end || $end >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
    header('Content-Length: ' . ($end - $start + 1));
    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    echo fread($fp, $end - $start + 1);
    fclose($fp);
    exit;
}

readfile($path);