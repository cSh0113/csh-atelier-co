<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'donate';
$pageTitle = 'Donate & earn credits';
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_csrf();
    $user = current_user();

    $count  = max(1, (int)($_POST['item_count'] ?? 1));
    $desc   = trim($_POST['description'] ?? '');
    $method = ($_POST['dropoff_method'] ?? 'dropoff') === 'courier' ? 'courier' : 'dropoff';
    $addr   = trim($_POST['address'] ?? '');

    if ($count > 50) $errors[] = 'For more than 50 items, please contact us directly.';
    if ($method === 'courier' && $addr === '') $errors[] = 'We need a pickup address for courier collection.';

    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $photo = upload_image($_FILES['photo'], 'donation');
    }

    if (!$errors) {
        $ref = next_reference('DON', 'donations', 'id');
        $pdo->prepare(
          'INSERT INTO donations (user_id,reference,item_count,description,photo_url,
                                  dropoff_method,address)
           VALUES (?,?,?,?,?,?,?)')
            ->execute([$user['id'],$ref,$count,$desc,$photo,$method,$addr]);

        flash("Donation {$ref} logged. Once we receive and sort your items we'll add your credits.");
        redirect('account.php?tab=donations');
    }
}

$perItem = (int)(setting('credits_per_item') ?: CREDITS_PER_ITEM);
include __DIR__ . '/includes/header.php';
?>
<section class="sec" style="padding-top:44px">
  <div class="shell split">
    <div data-reveal>
      <span class="mono">Recycle &#183; C2B</span>
      <h2 class="serif" style="font-size:clamp(1.9rem,4vw,3rem);font-weight:400;line-height:1.08;margin:12px 0 18px">
        Send it back.<br>Get credits.
      </h2>
      <p class="muted mb-2">
        When a garment is too worn to resell, don&rsquo;t bin it. Send it to us and
        we&rsquo;ll decide the kindest next step for it, and pay you in store
        credits either way.
      </p>

      <div class="steps" style="grid-template-columns:1fr;gap:12px">
        <div class="panel-in">
          <strong class="small">1 &#183; You send it</strong>
          <p class="small muted mt-1" style="margin:0">Drop it at our studio or book a courier collection.</p>
        </div>
        <div class="panel-in">
          <strong class="small">2 &#183; We sort it</strong>
          <p class="small muted mt-1" style="margin:0">
            Wearable pieces are cleaned and resold. Good fabric gets
            <strong>remade</strong> into new pieces. Everything else is recycled responsibly.
          </p>
        </div>
        <div class="panel-in">
          <strong class="small">3 &#183; You earn</strong>
          <p class="small muted mt-1" style="margin:0">
            Around <strong><?= $perItem ?> credits per item</strong>
            (<?= money(credits_to_rand($perItem)) ?> value), spendable on anything on the site.
          </p>
        </div>
      </div>

      <div class="panel-in mt-2" style="border-left:3px solid var(--leaf)">
        <strong class="small">Where it actually goes</strong>
        <p class="small muted mt-1" style="margin:0">
          We publish the outcome of every donation on your account page:
          remade, resold, or recycled. No vague promises.
        </p>
      </div>
    </div>

    <div data-reveal data-delay="1">
      <?php if (!is_logged_in()): ?>
        <div class="panel center" style="padding:44px 32px">
          <h3 class="mb-1">Sign in to donate</h3>
          <p class="muted small mb-3">Credits are added to your account, so we need to know who you are.</p>
          <a class="btn btn-primary btn-block mb-1" href="<?= url('login.php?next=/donate.php') ?>">Sign in</a>
          <a class="btn btn-block" href="<?= url('register.php') ?>">Create a free account</a>
        </div>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="panel-in mb-2" style="border-left:3px solid var(--clay)">
            <?php foreach ($errors as $er): ?><div class="small"><?= e($er) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form class="panel" method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <h3 class="mb-2">Log a donation</h3>

          <div class="field">
            <label for="item_count">How many items?</label>
            <input id="item_count" name="item_count" type="number" min="1" max="50" value="1" required>
            <div class="hint">Estimated credits: <strong data-est><?= $perItem ?></strong></div>
          </div>

          <div class="field">
            <label for="dropoff_method">How will we get them?</label>
            <select id="dropoff_method" name="dropoff_method" data-method>
              <option value="dropoff">I&rsquo;ll drop them off</option>
              <option value="courier">Please collect (courier)</option>
            </select>
          </div>

          <div class="field" data-addr style="display:none">
            <label for="address">Pickup address</label>
            <input id="address" name="address" placeholder="Street, suburb, city">
          </div>

          <div class="field">
            <label for="description">What&rsquo;s in the bag?</label>
            <textarea id="description" name="description" rows="3"
              placeholder="e.g. 2 t-shirts, a hoodie with a torn cuff, one pair of jeans"></textarea>
          </div>

          <div class="field">
            <label for="photo">Photo <span class="muted">(optional)</span></label>
            <input id="photo" name="photo" type="file" accept="image/*" data-preview="#dpreview">
            <div id="dpreview" class="mt-1" style="max-width:180px;border-radius:var(--r-sm);overflow:hidden"></div>
          </div>

          <button class="btn btn-leaf btn-lg btn-block" type="submit">Log my donation</button>
        </form>
        <script>
        (function(){
          var m=document.querySelector('[data-method]'), a=document.querySelector('[data-addr]');
          if(m&&a){ m.addEventListener('change',function(){ a.style.display = this.value==='courier'?'':'none'; }); }
          var c=document.getElementById('item_count'), est=document.querySelector('[data-est]');
          if(c&&est){ c.addEventListener('input',function(){
            est.textContent = Math.max(1,parseInt(this.value||1,10)) * <?= $perItem ?>; }); }
        })();
        </script>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
