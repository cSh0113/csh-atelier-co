<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Sign in';
if (is_logged_in()) redirect('account.php');
$error = '';
$next = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    // simple throttle
    $_SESSION['login_tries'] = ($_SESSION['login_tries'] ?? 0) + 1;
    if ($_SESSION['login_tries'] > 8) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u && $u['status'] === 'suspended') {
            $error = 'This account has been suspended.';
        } elseif ($u && password_verify($pass, $u['password_hash'])) {
            unset($_SESSION['login_tries']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$u['id'];
            cart_merge_guest((int)$u['id']);
            flash('Welcome back, ' . $u['name'] . '.');
            redirect($u['role'] === 'admin' ? 'admin/index.php'
                     : ($next !== '' ? ltrim($next, '/') : 'account.php'));
        } else {
            $error = 'Those details do not match an account.';
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap shell">
  <div class="auth-card" data-reveal>
    <h1>Welcome back</h1>
    <p class="sub">Sign in to buy, sell and spend your credits.</p>

    <div class="social-auth" aria-label="Social sign in options">
      <a class="btn social-btn <?= GOOGLE_CLIENT_ID ? '' : 'is-disabled' ?>" href="<?= GOOGLE_CLIENT_ID ? url('oauth.php?provider=google&next='.urlencode($next)) : '#' ?>" <?= GOOGLE_CLIENT_ID ? '' : 'aria-disabled="true" onclick="return false"' ?>>
        <strong>G</strong> Continue with Google
      </a>
      <?php if (!GOOGLE_CLIENT_ID): ?><p class="small muted">Google sign in will appear here once the provider app is connected.</p><?php endif; ?>
    </div>

    <?php if ($error): ?>
      <div class="panel-in mb-1" style="border-left:3px solid var(--clay)">
        <span class="small"><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="email">Email</label>
        <input id="email" name="email" type="email" required autofocus></div>
      <div class="field"><label for="password">Password</label>
        <input id="password" name="password" type="password" required></div>
      <button class="btn btn-primary btn-block btn-lg mt-2" type="submit">Sign in</button>
    </form>
    <p class="auth-alt">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
