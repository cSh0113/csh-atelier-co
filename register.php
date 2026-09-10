<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Create account';
if (is_logged_in()) redirect('account.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $city  = trim($_POST['city'] ?? '');

    if ($name === '')                                  $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 8)                             $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $pass2)                              $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $chk = db()->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = 'That email is already registered.';
    }

    if (!$errors) {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare('INSERT INTO users (name,email,password_hash,city,credits) VALUES (?,?,?,?,?)')
            ->execute([$name, $email, $hash, $city, SIGNUP_BONUS]);
        $uid = (int)db()->lastInsertId();

        db()->prepare('INSERT INTO credit_transactions (user_id,amount,balance_after,reason,note)
                       VALUES (?,?,?,"signup_bonus","Welcome bonus")')
            ->execute([$uid, SIGNUP_BONUS, SIGNUP_BONUS]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;
        cart_merge_guest($uid);
        flash('Welcome to the loop! We added ' . SIGNUP_BONUS . ' credits to get you started.');
        redirect('account.php');
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap shell">
  <div class="auth-card" data-reveal>
    <h1>Join the loop</h1>
    <p class="sub">Free to join. <?= SIGNUP_BONUS ?> credits on signup.</p>

    <div class="social-auth" aria-label="Social signup options">
      <a class="btn social-btn <?= GOOGLE_CLIENT_ID ? '' : 'is-disabled' ?>" href="<?= GOOGLE_CLIENT_ID ? url('oauth.php?provider=google') : '#' ?>" <?= GOOGLE_CLIENT_ID ? '' : 'aria-disabled="true" onclick="return false"' ?>>
        <strong>G</strong> Continue with Google
      </a>
      <?php if (!GOOGLE_CLIENT_ID): ?><p class="small muted">Google signup will appear here once the provider app is connected.</p><?php endif; ?>
    </div>

    <?php foreach ($errors as $er): ?>
      <div class="panel-in mb-1" style="border-left:3px solid var(--clay)">
        <span class="small"><?= e($er) ?></span></div>
    <?php endforeach; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="name">Your name</label>
        <input id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>"></div>
      <div class="field"><label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="field"><label for="city">City <span class="muted">(optional)</span></label>
        <input id="city" name="city" value="<?= e($_POST['city'] ?? '') ?>"></div>
      <div class="field"><label for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="8">
        <div class="hint">At least 8 characters.</div></div>
      <div class="field"><label for="password2">Confirm password</label>
        <input id="password2" name="password2" type="password" required></div>
      <button class="btn btn-primary btn-block btn-lg mt-2" type="submit">Create my account</button>
    </form>
    <p class="auth-alt">Already a member? <a href="<?= url('login.php') ?>">Sign in</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
