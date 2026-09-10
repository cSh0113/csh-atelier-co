<?php
/**
 * The top half of every admin page. Sidebar, header and the badge counts.
 * The bottom half lives in _layout_end.php so the two wrap around whatever
 * the actual page wants to show.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$adminUser = current_user();
$here = basename($_SERVER['SCRIPT_NAME']);

/* The little red numbers on the sidebar, so I can see what is waiting
   for me without opening every page. */
$pendingListings  = (int)db()->query("SELECT COUNT(*) FROM listings  WHERE status='pending'")->fetchColumn();
$pendingDonations = (int)db()->query("SELECT COUNT(*) FROM donations WHERE outcome='pending'")->fetchColumn();
$newOrders        = (int)db()->query("SELECT COUNT(*) FROM orders    WHERE status='pending'")->fetchColumn();
$toPack           = (int)db()->query("SELECT COUNT(*) FROM orders    WHERE status='paid'")->fetchColumn();

$nav = [
  ['index.php',     'Dashboard',  'grid',    0],
  ['listings.php',  'Listings',   'tag',     $pendingListings],
  ['donations.php', 'Donations',  'recycle', $pendingDonations],
  ['orders.php',    'Orders',     'box',     $newOrders],
  ['shipping.php',  'Shipping',   'truck',   $toPack],
  ['cashflow.php',  'Cash flow',  'chart',   0],
  ['products.php',  'Products',   'shirt',   0],
  ['users.php',     'Members',    'users',   0],
  ['reports.php',   'Reports',    'users',   0],
  ['credits.php',   'Credits',    'coin',    0],
];

function ico(string $n): string {
  $p = [
    'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'tag'     => '<path d="M3 11V4a1 1 0 0 1 1-1h7l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    'recycle' => '<path d="M7 19H5a2 2 0 0 1-1.7-3l1.7-2.8M12 3l2 3.5M17 19h2a2 2 0 0 0 1.7-3L17 9M7 19l2-3.5M12 3 9.5 7.2a2 2 0 0 0 1.7 3H15"/><path d="m5 13 2 3.5M19 9l-3.6.6"/>',
    'box'     => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    'shirt'   => '<path d="M8 3l4 2 4-2 4 3-2 3v11H6V9L4 6z"/>',
    'users'   => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 8.2a3 3 0 0 1 0 5.6M18.5 20a5.6 5.6 0 0 0-2.2-4.3"/>',
    'truck'   => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
    'chart'   => '<path d="M3 3v18h18"/><path d="M7 15l3.5-4 3 2.5L20 7"/>',
    'coin'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h4a2 2 0 0 1 0 4h-4"/>',
  ][$n] ?? '';
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?>, <?= e(SITE_NAME) ?> Admin</title>
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">

<button class="adm-burger" data-adm-burger aria-label="Menu">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
</button>

<aside class="adm-side" data-adm-side>
  <a class="adm-brand" href="<?= url('index.php') ?>">
    <span class="adm-brand__mark">CSH</span>
    <span class="adm-brand__txt">Atelier<small>admin console</small></span>
  </a>

  <nav class="adm-nav">
    <?php foreach ($nav as [$file, $label, $icon, $badge]): ?>
      <a href="<?= url('admin/' . $file) ?>" class="adm-nav__i<?= $here === $file ? ' is-on' : '' ?>">
        <span class="adm-nav__ico"><?= ico($icon) ?></span>
        <span><?= e($label) ?></span>
        <?php if ($badge > 0): ?><b class="adm-badge"><?= $badge ?></b><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="adm-side__foot">
    <a class="adm-nav__i" href="<?= url('index.php') ?>">
      <span class="adm-nav__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg></span>
      <span>View site</span>
    </a>
    <a class="adm-nav__i" href="<?= url('logout.php') ?>">
      <span class="adm-nav__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17l5-5-5-5M20 12H9M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6"/></svg></span>
      <span>Sign out</span>
    </a>
  </div>
</aside>

<main class="adm-main">
  <header class="adm-top glass">
    <div>
      <p class="adm-eyebrow">Admin console</p>
      <h1 class="adm-h1"><?= e($pageTitle ?? 'Dashboard') ?></h1>
    </div>
    <div class="adm-who">
      <span class="adm-who__av"><?= e(strtoupper(substr($adminUser['name'], 0, 1))) ?></span>
      <span class="adm-who__t"><b><?= e($adminUser['name']) ?></b><small>Administrator</small></span>
    </div>
  </header>

  <?php foreach (get_flashes() as $f): ?>
    <div class="adm-flash adm-flash--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="adm-content">
