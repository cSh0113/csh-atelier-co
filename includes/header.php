<?php
require_once __DIR__ . '/functions.php';
$__user  = current_user();
$__cart  = cart_count();
$__page  = $page ?? '';
$__title = isset($pageTitle) ? $pageTitle . ', ' . SITE_NAME : SITE_NAME . ', Clothing with a message, and a second life';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__title) ?></title>
<meta name="description" content="<?= e($pageDesc ?? 'Sustainable clothing that carries a message. Buy remade pieces, sell your own clothing to other members, or donate items and earn store credits.') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<script>
  window.CSH_BASE = <?= json_encode(rtrim(SITE_URL, '/')) ?>;
  window.CSH_CSRF = <?= json_encode(csrf_token()) ?>;
  // This runs before the page paints on purpose. If I wait for the main JS
  // file the site flashes light for a moment before going dark.
  (function(){ try{ var t=localStorage.getItem('csh-theme');
    if(t) document.documentElement.setAttribute('data-theme',t); }catch(e){} })();
</script>
<style>
.profile-dropdown-wrap {
  position: relative;
  display: inline-block;
}
.profile-dropdown-menu {
  position: absolute;
  right: 0;
  top: 48px;
  background: var(--bg-raised, #fff);
  border: 1px solid var(--line, #e2ded6);
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.12);
  min-width: 170px;
  padding: 6px;
  z-index: 200;
}
.profile-dropdown-menu[hidden] {
  display: none !important;
}
.profile-dropdown-menu a, .profile-dropdown-menu button {
  display: block;
  width: 100%;
  text-align: left;
  padding: 10px 14px;
  border-radius: 8px;
  font: inherit;
  font-size: 0.88rem;
  color: var(--ink, #1a1a1a);
  background: none;
  border: 0;
  text-decoration: none;
  cursor: pointer;
}
.profile-dropdown-menu a:hover, .profile-dropdown-menu button:hover {
  background: var(--bg-sunken, #eceae5);
}
.profile-dropdown-menu a.danger {
  color: #b4483c;
  font-weight: 700;
}
</style>
</head>
<body>
<div class="progress" data-progress></div>

<header class="site-header">
  <div class="shell hdr">
    <a class="logo" href="<?= url('index.php') ?>">CSH <span>Atelier Co.</span></a>

    <nav class="nav">
      <a href="<?= url('marketplace.php') ?>" class="<?= $__page==='marketplace'?'on':'' ?>">Marketplace</a>
      <a href="<?= url('shop.php') ?>"        class="<?= $__page==='shop'?'on':'' ?>">The Label</a>
      <a href="<?= url('sell.php') ?>"        class="<?= $__page==='sell'?'on':'' ?>">Sell</a>
      <a href="<?= url('donate.php') ?>"      class="<?= $__page==='donate'?'on':'' ?>">Donate</a>
      <a href="<?= url('how-it-works.php') ?>"class="<?= $__page==='how'?'on':'' ?>">How it works</a>
      <a href="<?= url('about.php') ?>"       class="<?= $__page==='about'?'on':'' ?>">About</a>
      <?php if ($__user): ?><a href="<?= url('messages.php') ?>" class="<?= $__page==='messages'?'on':'' ?>">Messages</a><?php endif; ?>
    </nav>

    <div class="hdr-right">
      <?php if ($__user): ?>
        <a class="credit-chip hide-sm" href="<?= url('account.php?tab=credits') ?>" title="Store credits">
          <b><?= number_format((int)$__user['credits']) ?></b> credits
        </a>
      <?php endif; ?>

      <button class="icon-btn" data-theme-toggle aria-label="Toggle dark mode">
        <svg viewBox="0 0 24 24" data-icon-sun><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8"/></svg>
      </button>

      <a class="icon-btn" href="<?= url('cart.php') ?>" aria-label="Cart">
        <svg viewBox="0 0 24 24"><path d="M6 6h15l-1.6 9H7.4z"/><path d="M6 6L5 3H2"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg>
        <?php if ($__cart): ?><span class="badge" data-cart-badge><?= $__cart ?></span><?php endif; ?>
      </a>

      <?php if ($__user): ?>
        <!-- Profile dropdown maintaining exact original icon button styling -->
        <div class="profile-dropdown-wrap">
          <button class="icon-btn" id="profileMenuToggle" aria-label="Account">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c0-3.6 3.4-5.6 7.5-5.6s7.5 2 7.5 5.6"/></svg>
          </button>

          <div id="profileDropdown" class="profile-dropdown-menu" hidden>
            <div style="padding: 10px 14px; border-bottom: 1px solid var(--line, #e2ded6); margin-bottom: 4px;">
              <b style="display: block; font-size: 0.9rem;"><?= e($__user['name']) ?></b>
              <small style="color: var(--ink-faint, #8a8a8a); font-size: 0.78rem;"><?= e($__user['email'] ?? '') ?></small>
            </div>
            <a href="<?= url(is_admin() ? 'admin/index.php' : 'account.php') ?>">Dashboard</a>
            <a href="<?= url('account.php?tab=listings') ?>">My Wardrobe</a>
            <a href="<?= url('account.php?tab=settings') ?>">Settings</a>
            <hr style="border: 0; border-top: 1px solid var(--line, #e2ded6); margin: 4px 0;">
            <a href="<?= url('logout.php') ?>" class="danger">Sign out</a>
          </div>
        </div>
      <?php else: ?>
        <a class="btn btn-sm hide-sm" href="<?= url('login.php') ?>">Sign in</a>
      <?php endif; ?>

      <button class="icon-btn burger" data-menu-open aria-label="Menu">
        <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- mobile drawer -->
<div class="scrim" data-scrim></div>
<nav class="mnav" data-mnav>
  <div class="flex between mb-2">
    <strong>Menu</strong>
    <button class="icon-btn" data-menu-close aria-label="Close">
      <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <a href="<?= url('marketplace.php') ?>">Marketplace</a>
  <a href="<?= url('shop.php') ?>">The Label</a>
  <a href="<?= url('sell.php') ?>">Sell an item</a>
  <a href="<?= url('donate.php') ?>">Donate &amp; earn</a>
  <a href="<?= url('how-it-works.php') ?>">How it works</a>
  <a href="<?= url('about.php') ?>">About</a>
  <hr style="border:0;border-top:1px solid var(--line);margin:12px 0">
  <?php if ($__user): ?>
    <a href="<?= url('messages.php') ?>">Messages</a>
    <a href="<?= url('account.php') ?>">My account</a>
    <a href="<?= url('account.php?tab=credits') ?>"><?= number_format((int)$__user['credits']) ?> credits</a>
    <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>">Admin</a><?php endif; ?>
    <a href="<?= url('logout.php') ?>">Sign out</a>
  <?php else: ?>
    <a href="<?= url('login.php') ?>">Sign in</a>
    <a href="<?= url('register.php') ?>">Create account</a>
  <?php endif; ?>
</nav>

<?php $flashes = get_flashes(); if ($flashes): ?>
<div class="flash-wrap" data-flash-wrap>
  <?php foreach ($flashes as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<main>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var toggleBtn = document.getElementById('profileMenuToggle');
  var dropdown = document.getElementById('profileDropdown');

  if (toggleBtn && dropdown) {
    toggleBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdown.hidden = !dropdown.hidden;
    });

    document.addEventListener('click', function(e) {
      if (!dropdown.contains(e.target) && e.target !== toggleBtn) {
        dropdown.hidden = true;
      }
    });
  }
});
</script>