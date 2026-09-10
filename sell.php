<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'sell';
$pageTitle = 'Sell an item';
require_login();
$pdo = db();
$user = current_user();
$errors = [];
$editId = (int)($_GET['id'] ?? $_POST['listing_id'] ?? 0);
$editListing = null;
if ($editId) {
    $q = $pdo->prepare('SELECT * FROM listings WHERE id=? AND seller_id=?'); $q->execute([$editId,$user['id']]); $editListing=$q->fetch();
    if (!$editListing) { flash('Listing not found.','error'); redirect('account.php?tab=listings'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $cat   = (int)($_POST['category_id'] ?? 0) ?: null;
    $cause = (int)($_POST['cause_id'] ?? 0) ?: null;
    $size  = trim($_POST['item_size'] ?? '');
    $cond  = $_POST['item_condition'] ?? 'good';
    $brand = trim($_POST['brand'] ?? '');
    $colour= trim($_POST['colour'] ?? '');
    $visibility = in_array($_POST['visibility'] ?? 'public', ['public','members'], true) ? $_POST['visibility'] : 'public';

    if ($title === '')            $errors[] = 'Give your item a title.';
    if ($price <= 0)              $errors[] = 'Set a price above R0.';
    if ($price > 100000)          $errors[] = 'That price looks too high.';
    if (!in_array($cond, ['new','like_new','good','well_loved'], true)) $cond = 'good';

    $img = $editListing['image_url'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $img = upload_image($_FILES['image'], 'listing');
        if (!$img) $errors[] = 'Image must be a JPG, PNG, WEBP or GIF under 5MB.';
    }

    if (!$errors) {
        if ($editId) {
            $pdo->prepare('UPDATE listings SET title=?,description=?,price=?,category_id=?,cause_id=?,item_size=?,item_condition=?,brand=?,colour=?,image_url=?,visibility=? WHERE id=? AND seller_id=?')
                ->execute([$title,$desc,$price,$cat,$cause,$size,$cond,$brand,$colour,$img,$visibility,$editId,$user['id']]);
            flash('Listing updated.');
            redirect('account.php?tab=listings');
        }
        $slug = slugify($title) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
        $pdo->prepare(
          'INSERT INTO listings
             (seller_id,title,slug,description,price,category_id,cause_id,
              item_size,item_condition,brand,colour,image_url,visibility,status)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"pending")')
            ->execute([$user['id'],$title,$slug,$desc,$price,$cat,$cause,
                       $size,$cond,$brand,$colour,$img,$visibility]);

        flash('Listing submitted! It will appear once approved (usually within a day).');
        redirect('account.php?tab=listings');
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$causes     = $pdo->query('SELECT * FROM causes ORDER BY id')->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<section class="sec" style="padding-top:44px">
  <div class="shell" style="max-width:820px">
    <div class="sec-head">
      <span class="mono">Reuse</span>
      <h2 data-reveal><?= $editId ? 'Edit listing' : 'List an item' ?></h2>
      <p data-reveal data-delay="1">
        Somebody out there is looking for exactly what you&rsquo;ve stopped wearing.
        List it here and keep it in circulation.
      </p>
    </div>

    <?php if ($errors): ?>
      <div class="panel-in mb-2" style="border-left:3px solid var(--clay)">
        <?php foreach ($errors as $er): ?><div class="small"><?= e($er) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form class="panel" method="post" enctype="multipart/form-data" data-reveal>
      <?= csrf_field() ?>

      <div class="field">
        <label for="title">What are you selling?</label>
        <input id="title" name="title" required maxlength="180"
               placeholder="e.g. Vintage denim jacket" value="<?= e($_POST['title'] ?? ($editListing['title'] ?? '')) ?>">
      </div>

      <div class="grid grid-2" style="gap:16px">
        <div class="field">
          <label for="price">Price (ZAR)</label>
          <input id="price" name="price" type="number" step="1" min="1" required
                 placeholder="250" value="<?= e($_POST['price'] ?? ($editListing['price'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="item_condition">Condition</label>
          <select id="item_condition" name="item_condition">
            <?php foreach (['new','like_new','good','well_loved'] as $c): ?>
              <option value="<?= $c ?>" <?= (($_POST['item_condition'] ?? ($editListing['item_condition'] ?? 'good'))===$c)?'selected':'' ?>>
                <?= e(condition_label($c)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid grid-2" style="gap:16px">
        <div class="field">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id">
            <option value="">Choose&hellip;</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="item_size">Size</label>
          <input id="item_size" name="item_size" placeholder="M / 32 / One size"
                 value="<?= e($_POST['item_size'] ?? ($editListing['item_size'] ?? '')) ?>">
        </div>
      </div>

      <div class="grid grid-2" style="gap:16px">
        <div class="field">
          <label for="brand">Brand <span class="muted">(optional)</span></label>
              <input id="brand" name="brand" value="<?= e($_POST['brand'] ?? ($editListing['brand'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="colour">Colour <span class="muted">(optional)</span></label>
              <input id="colour" name="colour" value="<?= e($_POST['colour'] ?? ($editListing['colour'] ?? '')) ?>">
        </div>
      </div>

      <div class="field">
        <label for="cause_id">Does it carry a message? <span class="muted">(optional)</span></label>
        <select id="cause_id" name="cause_id">
          <option value="">Not specifically</option>
          <?php foreach ($causes as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="description">Describe it</label>
        <textarea id="description" name="description" rows="4"
          placeholder="Be honest about wear and fit, it builds trust and sells faster."><?= e($_POST['description'] ?? ($editListing['description'] ?? '')) ?></textarea>
      </div>

      <div class="field">
        <label for="image">Photo</label>
        <input id="image" name="image" type="file" accept="image/*" data-preview="#preview">
        <div class="hint">Natural light, plain background. JPG/PNG/WEBP, max 5MB.</div>
        <div id="preview" class="mt-1" style="max-width:200px;border-radius:var(--r-sm);overflow:hidden"></div>
      </div>

      <div class="field"><label for="visibility">Who can view this listing?</label><select id="visibility" name="visibility"><option value="public" <?= (($_POST['visibility'] ?? ($editListing['visibility'] ?? 'public'))==='public')?'selected':'' ?>>Everyone</option><option value="members" <?= (($_POST['visibility'] ?? ($editListing['visibility'] ?? 'public'))==='members')?'selected':'' ?>>Signed-in members only</option></select></div>
      <?php if ($editId): ?><input type="hidden" name="listing_id" value="<?= $editId ?>"><?php endif; ?>
      <button class="btn btn-primary btn-lg btn-block mt-2" type="submit"><?= $editId ? 'Save listing changes' : 'Submit listing' ?></button>
      <p class="small muted center mt-1">
        Listings are reviewed before going live. We keep <?= (int)(SELLER_COMMISSION*100) ?>%
        of a sale to run the platform, and you keep the rest.
      </p>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
