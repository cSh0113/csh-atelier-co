<?php
$pageTitle = 'Product';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pdo = db();

$id   = (int)($_GET['id'] ?? 0);
$prod = ['id'=>0,'name'=>'','slug'=>'','description'=>'','message'=>'','price'=>'','stock'=>0,
         'category_id'=>null,'cause_id'=>null,'image_url'=>'','image_hover'=>'',
         'is_remade'=>0,'recycled_pct'=>0,'status'=>'active','material'=>'','featured'=>0];

if ($id) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id=?'); $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) { flash('Product not found.', 'error'); redirect('admin/products.php'); }
    $prod = $found;
    $pageTitle = 'Edit product';
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $prod['name']         = trim($_POST['name'] ?? '');
    $prod['description']  = trim($_POST['description'] ?? '');
    $prod['message']      = trim($_POST['message'] ?? '');
    $prod['price']        = (float)($_POST['price'] ?? 0);
    $prod['stock']        = (int)($_POST['stock'] ?? 0);
    $prod['category_id']  = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
    $prod['cause_id']     = ($_POST['cause_id'] ?? '') !== '' ? (int)$_POST['cause_id'] : null;
    $prod['image_url']    = trim($_POST['image_url'] ?? '');
    $prod['image_hover']  = trim($_POST['image_hover'] ?? '');
    $prod['material']     = trim($_POST['material'] ?? '');
    $prod['featured']     = isset($_POST['featured']) ? 1 : 0;
    $prod['is_remade']    = isset($_POST['is_remade']) ? 1 : 0;
    $prod['recycled_pct'] = max(0, min(100, (int)($_POST['recycled_pct'] ?? 0)));
    $prod['status']       = isset($_POST['is_active']) ? 'active' : 'hidden';

    if ($prod['name'] === '')  $errors[] = 'Give the product a name.';
    if ($prod['price'] <= 0)   $errors[] = 'Price must be more than zero.';

    // optional file upload overrides the URL field
    if (!empty($_FILES['image_file']['name'])) {
        $up = upload_image($_FILES['image_file'], 'prod');
        if ($up) $prod['image_url'] = $up;
        else $errors[] = 'That image could not be uploaded (JPG/PNG/WebP, max 5 MB).';
    }

    if (!$errors) {
        $slug = $prod['slug'] ?: slugify($prod['name']);
        if ($id) {
            $pdo->prepare('UPDATE products SET name=?,description=?,message=?,price=?,stock=?,
                           category_id=?,cause_id=?,image_url=?,image_hover=?,is_remade=?,recycled_pct=?,
                           material=?,featured=?,status=? WHERE id=?')
                ->execute([$prod['name'],$prod['description'],$prod['message'],$prod['price'],$prod['stock'],
                           $prod['category_id'],$prod['cause_id'],$prod['image_url'],$prod['image_hover'],
                           $prod['is_remade'],$prod['recycled_pct'],$prod['material'],$prod['featured'],
                           $prod['status'],$id]);
            audit_log('edit', 'product', $id, $prod['name']);
            flash('Product updated.');
        } else {
            // guarantee a unique slug
            $base = $slug; $n = 1;
            while (true) {
                $c = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug=?'); $c->execute([$slug]);
                if (!(int)$c->fetchColumn()) break;
                $slug = $base . '-' . (++$n);
            }
            $pdo->prepare('INSERT INTO products (name,slug,description,message,price,stock,category_id,cause_id,
                           image_url,image_hover,is_remade,recycled_pct,material,featured,status)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$prod['name'],$slug,$prod['description'],$prod['message'],$prod['price'],$prod['stock'],
                           $prod['category_id'],$prod['cause_id'],$prod['image_url'],$prod['image_hover'],
                           $prod['is_remade'],$prod['recycled_pct'],$prod['material'],$prod['featured'],
                           $prod['status']]);
            audit_log('create', 'product', (int)$pdo->lastInsertId(), $prod['name']);
            flash('Product created.');
        }
        redirect('admin/products.php');
    }
}

$cats   = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$causes = $pdo->query('SELECT id,name FROM causes ORDER BY name')->fetchAll();
require __DIR__ . '/_layout.php';
?>

<div class="adm-card" style="max-width:900px">
  <div class="adm-card__h">
    <div>
      <h2><?= $id ? 'Edit product' : 'New product' ?></h2>
      <p>Every brand piece should carry a message, that's the whole point of the label.</p>
    </div>
    <a class="abtn abtn--sm" href="<?= url('admin/products.php') ?>">← Back</a>
  </div>

  <?php foreach ($errors as $er): ?>
    <div class="adm-flash adm-flash--error"><?= e($er) ?></div>
  <?php endforeach; ?>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="afield">
      <label>Product name</label>
      <input type="text" name="name" value="<?= e($prod['name']) ?>" required maxlength="160">
    </div>

    <div class="afield">
      <label>The message it carries</label>
      <textarea name="message" rows="3" placeholder="What does this garment say, and why does it matter?"><?= e($prod['message']) ?></textarea>
    </div>

    <div class="afield">
      <label>Description</label>
      <textarea name="description" rows="4"><?= e($prod['description']) ?></textarea>
    </div>

    <div class="arow">
      <div class="afield">
        <label>Price (R)</label>
        <input type="number" name="price" step="0.01" min="0" value="<?= e($prod['price']) ?>" required>
      </div>
      <div class="afield">
        <label>Stock</label>
        <input type="number" name="stock" min="0" value="<?= (int)$prod['stock'] ?>">
      </div>
    </div>

    <div class="arow">
      <div class="afield">
        <label>Category</label>
        <select name="category_id">
          <option value="">none</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$prod['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="afield">
        <label>Cause / message</label>
        <select name="cause_id">
          <option value="">none</option>
          <?php foreach ($causes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$prod['cause_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="afield">
      <label>Upload image</label>
      <input type="file" name="image_file" accept="image/*">
    </div>

    <div class="arow">
      <div class="afield">
        <label>...or image URL</label>
        <input type="text" name="image_url" value="<?= e($prod['image_url']) ?>" placeholder="uploads/... or https://">
      </div>
      <div class="afield">
        <label>Second image (hover)</label>
        <input type="text" name="image_hover" value="<?= e($prod['image_hover']) ?>">
      </div>
    </div>

    <div class="arow">
      <div class="afield">
        <label>Recycled material (%)</label>
        <input type="number" name="recycled_pct" min="0" max="100" value="<?= (int)$prod['recycled_pct'] ?>">
      </div>
      <div class="afield">
        <label>Flags</label>
        <div style="display:flex;gap:18px;padding-top:9px;font-size:.88rem">
          <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-family:inherit;font-size:.88rem;color:var(--ink)">
            <input type="checkbox" name="is_remade" value="1" style="width:auto" <?= (int)$prod['is_remade'] ? 'checked' : '' ?>> Remade
          </label>
          <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-family:inherit;font-size:.88rem;color:var(--ink)">
            <input type="checkbox" name="is_active" value="1" style="width:auto" <?= $prod['status']==='active' ? 'checked' : '' ?>> Live
          </label>
        </div>
      </div>
    </div>

    <button class="abtn abtn--go" type="submit"><?= $id ? 'Save changes' : 'Create product' ?></button>
  </form>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
