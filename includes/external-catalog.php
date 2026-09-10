<?php
$pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : db();

$externalCatalog = [];

/*
|--------------------------------------------------------------------------
| Fixed image for the Women's Empowerment Crop Top
|--------------------------------------------------------------------------
*/
$cropTopImage = 'https://imgproxy.fourthwall.dev/7CyXBn_WNfWyvaEpQsCReUE--fLQhtCIoJZyRlc0wxs/w:720/sm:1/enc/cfiHK1dos0J5l78a/RTZnBehOct6wC137/E6Tl-PURGwkdM9hi/efYS0GMMoL2O0APl/wxpj--jBinGXG9jW/jtRNJAWyRqSK5NHp/ytAwVigQeFFk1PED/DwOmcrn4HT0vbsdx/Q0PWv-Q76to8V3L-/-cEULejkYBtG1xnP/aREBpWj9rNU0Qdks/8c2O5Yxw_xRG5a-h/5N99Rr-I7fbidwKY/bAChfUTniRLPJucT/PSTkbecEbZM.jpg';

try {
    $externalCatalog = $pdo->query(
        'SELECT id, name, price, stock, image_url, external_url, external_source
         FROM products
         WHERE status = "active"
         AND external_source = "CSH Innovations Co."
         ORDER BY name'
     )->fetchAll();

} catch (Throwable $e) {
    /*
     * Falls back to the JSON file if the catalogue tables are not in the
     * database yet, so the shop page still has something to show.
     */
    $externalCatalogFile = __DIR__ . '/../data/external_catalog.json';

    $raw = is_file($externalCatalogFile)
        ? json_decode(file_get_contents($externalCatalogFile), true)
        : [];

    foreach (is_array($raw) ? $raw : [] as $item) {
        $externalCatalog[] = [
            'id'              => 0,
            'name'            => $item['name'] ?? 'CSH Innovations product',
            'price'           => $item['price_zar'] ?? ((float)($item['price_usd'] ?? 0) * 18.5),
            'stock'           => !empty($item['available']) ? 999 : 0,
            'image_url'       => $item['image'] ?? '',
            'external_url'    => $item['url'] ?? 'https://cshinnovations.com/collections/all',
            'external_source' => 'CSH Innovations Co.'
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Apply image corrections
|--------------------------------------------------------------------------
*/
foreach ($externalCatalog as &$item ) {
    $productName = strtolower(trim((string)($item['name'] ?? '')));

    /*
     * One product came through from the feed with the wrong image, so it
     * gets corrected here rather than me editing the feed by hand.
     */
    if (
        strpos($productName, 'crop top') !== false ||
        strpos($productName, 'cropped top') !== false
    ) {
        $item['image_url'] = $cropTopImage;
    }
}
unset($item);
?>

<?php if ($externalCatalog): ?>

<section class="external-catalog" aria-labelledby="external-catalog-title">

  <div class="sec-head">
    <span class="mono">Official CSH Innovations catalogue</span>

    <h2 id="external-catalog-title">
      More pieces from the main label
    </h2>

    <p>
      Prices are shown in South African rand.
      Add an available item to the same cart or view the official product page.
    </p>
  </div>

  <div class="grid grid-4 external-catalog-grid">

    <?php foreach ($externalCatalog as $i => $item): ?>

      <?php
      $imageUrl = trim((string)($item['image_url'] ?? ''));

      if ($imageUrl === '') {
          $imageUrl = img_or_placeholder('', $item['name']);
      }

      $externalUrl = !empty($item['external_url'])
          ? $item['external_url']
          : 'https://cshinnovations.com/collections/all';
      ?>

      <article
        class="card external-product"
        data-reveal
        data-delay="<?= (int )($i % 4) ?>"
      >

        <a
          href="<?= e($externalUrl) ?>"
          target="_blank"
          rel="noopener noreferrer"
          class="external-product__link"
        >

          <div class="thumb">

            <span class="tag tag-c2c">
              CSH Innovations Co.
            </span>

            <img
              src="<?= e($imageUrl) ?>"
              alt="<?= e($item['name']) ?>"
              loading="lazy"
              decoding="async"
              onerror="this.onerror=null;this.src='<?= e(rtrim(SITE_URL, '/') . '/assets/img/placeholder.svg') ?>';"
            >

          </div>

          <div class="body">

            <h3><?= e($item['name']) ?></h3>

            <div class="meta">
              Company-label product · ZAR
            </div>

            <div class="price">
              <?= money($item['price']) ?>
            </div>

            <?php if ((int)$item['stock'] < 1): ?>
              <div class="small muted">
                Currently sold out
              </div>
            <?php endif; ?>

          </div>

        </a>

        <div class="external-product__actions">

          <?php if ((int)$item['stock'] > 0 && (int)$item['id'] > 0): ?>

            <button
              class="btn btn-leaf btn-sm"
              data-add-cart
              data-type="product"
              data-id="<?= (int)$item['id'] ?>"
            >
              Add to cart
            </button>

          <?php else: ?>

            <button class="btn btn-sm" disabled>
              <?= (int)$item['id'] > 0 ? 'Sold out' : 'View main website' ?>
            </button>

          <?php endif; ?>

          <a
            class="btn btn-sm"
            href="<?= e($externalUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
          >
            View main website &rarr;
          </a>

        </div>

      </article>

    <?php endforeach; ?>

  </div>

</section>

<?php endif; ?>
