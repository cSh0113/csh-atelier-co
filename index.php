<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'home';
$pageTitle = null;

$pdo = db();

// Newest member listings (the C2C heart of the site)
$listings = $pdo->query(
    'SELECT l.*, u.name AS seller_name, c.name AS cat_name
     FROM listings l
     JOIN users u ON u.id = l.seller_id
     LEFT JOIN categories c ON c.id = l.category_id
     WHERE l.status = "active"
     ORDER BY l.created_at DESC LIMIT 8')->fetchAll();

// Remade brand pieces (the C2B loop closing)
$remade = $pdo->query(
    'SELECT * FROM products
     WHERE status = "active" AND is_remade = 1
     ORDER BY created_at DESC LIMIT 4')->fetchAll();

$causes = $pdo->query('SELECT * FROM causes ORDER BY id')->fetchAll();
$stats  = impact_stats();

include __DIR__ . '/includes/header.php';
?>

<!-- ==================== HERO ==================== -->
<section class="hero hero-home">
  <div class="shell hero-grid">
    <div>
      <div class="hero-brand" data-reveal>
        <span>CSH</span> Atelier Co.
      </div>
      <div class="hero-kicker" data-reveal>
        <span class="mono">CSH Atelier Co. &#183; Circular fashion &#183; South Africa</span>
      </div>
      <h1 data-reveal data-delay="1">
        Buy it. Wear it.<br>
        Then <em>pass it on.</em>
      </h1>
      <p class="lede" data-reveal data-delay="2">
        A marketplace where members sell their preloved clothing to each other,
        and where the pieces nobody wants come back to us, to be remade into
        something that carries a message worth wearing.
      </p>
      <div class="hero-cta" data-reveal data-delay="3">
        <a class="btn btn-primary btn-lg" href="<?= url('marketplace.php') ?>">
          Shop the marketplace
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a class="btn btn-lg" href="<?= url('sell.php') ?>">List your clothing</a>
      </div>
      <p class="small muted mt-2 hero-note" data-reveal data-delay="4">
        Donate what you no longer wear and earn store credits,
        <a href="<?= url('donate.php') ?>" style="color:var(--leaf);font-weight:700">see how &rarr;</a>
      </p>
    </div>

    <!-- The loop drawn as a circle. Five stops now, spaced evenly,
         and ReWear sits second because that is the step I want people
         to actually notice first. -->
    <div class="loop" data-reveal data-delay="2">
      <svg viewBox="0 0 400 400" aria-hidden="true">
        <circle class="ring" cx="200" cy="200" r="150"/>
        <path class="arc" d="M200 50 A150 150 0 1 1 199 50"/>
      </svg>
      <div class="loop-node" style="left:50%; top:12.5%">
        <svg viewBox="0 0 24 24"><path d="M8 3l4 2 4-2 3 3-2 3v10H7V9L5 6z"/></svg>
        <b>Wear</b><span>with meaning</span>
      </div>
      <div class="loop-node loop-node--star" style="left:85.7%; top:38.4%">
        <svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.4-5.7M20 4v4h-4"/><path d="M12 8.5V12l2.5 1.6"/></svg>
        <b>ReWear</b><span>one more time</span>
      </div>
      <div class="loop-node" style="left:72%; top:80.3%">
        <svg viewBox="0 0 24 24"><path d="M3 12h4l2-7 4 14 2-7h6"/></svg>
        <b>Resell</b><span>member to member</span>
      </div>
      <div class="loop-node" style="left:28%; top:80.3%">
        <svg viewBox="0 0 24 24"><path d="M12 3v18M5 10l7-7 7 7"/></svg>
        <b>Donate</b><span>earn credits</span>
      </div>
      <div class="loop-node" style="left:14.3%; top:38.4%">
        <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7M21 4v5h-5"/></svg>
        <b>Remade</b><span>back to the rail</span>
      </div>
    </div>
  </div>
</section>

<!-- ============ THE CIRCULAR LOOP ============ -->
<section class="sec loop" data-loop>
  <div class="shell">
    <div class="loop-head">
      <span class="mono" data-rise>Reduce &#183; ReWear &#183; Reuse &#183; Recycle</span>
      <h2 data-rise>Nothing here is thrown away.</h2>
      <p class="lede" data-rise>
        Every garment gets another turn. Wear it again, sell it to another member,
        send it back to us to be remade, or let us recycle the fibre. Whatever you
        choose, you earn credits and something stays out of landfill.
      </p>
    </div>

    <ol class="loop-steps">
      <li class="loop-step">
        <span class="loop-step__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3l4 2 4-2 4 3-2 3v11H6V9L4 6z"/></svg></span>
        <b>Wear it, then ReWear it</b>
        <small>Buy a piece that carries a message, then keep reaching for it.</small>
      </li>
      <li class="loop-step">
        <span class="loop-step__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11V4a1 1 0 0 1 1-1h7l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg></span>
        <b>Resell it</b>
        <small>Done with it? List it and another member gives it a second life.</small>
      </li>
      <li class="loop-step">
        <span class="loop-step__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9M9 6l3-3 3 3"/><path d="M4 13a8 8 0 0 0 16 0"/><path d="M8 19h8"/></svg></span>
        <b>Send it back</b>
        <small>Too worn to resell? Donate it to us and earn store credits.</small>
      </li>
      <li class="loop-step">
        <span class="loop-step__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 19H5a2 2 0 0 1-1.7-3l1.7-2.8M17 19h2a2 2 0 0 0 1.7-3L17 9M12 3l2.5 4.3M9.5 7.2 12 3M7 19l2-3.5M15 19h-6"/></svg></span>
        <b>Remade</b>
        <small>We rebuild it into something new, or recycle the fibre entirely.</small>
      </li>
    </ol>

    <p class="loop-foot" data-rise>
      And then it starts again. That is the whole idea.
    </p>
  </div>
</section>

<!-- ==================== IMPACT ==================== -->
<section class="sec" style="padding-top:0">
  <div class="shell">
    <div class="impact">
      <div class="stat" data-reveal>
        <b data-count="<?= (int)$stats['items_diverted'] ?>">0</b>
        <span>Garments kept out of landfill</span>
      </div>
      <div class="stat" data-reveal data-delay="1">
        <b data-count="<?= (int)$stats['water_litres'] ?>">0</b>
        <span>Litres of water saved (est.)</span>
      </div>
      <div class="stat" data-reveal data-delay="2">
        <b data-count="<?= (int)$stats['co2_kg'] ?>" data-suffix="kg">0</b>
        <span>CO&#8322;e avoided (est.)</span>
      </div>
      <div class="stat" data-reveal data-delay="3">
        <b data-count="<?= (int)$stats['members'] ?>">0</b>
        <span>Members circulating clothing</span>
      </div>
    </div>
    <p class="small muted center mt-2" data-reveal>
      Water and CO&#8322;e figures are widely cited industry estimates per garment,
      shown to illustrate impact, not measured results.
    </p>
  </div>
</section>

<!-- ==================== MARKETPLACE (C2C) ==================== -->
<section class="sec">
  <div class="shell">
    <div class="sec-head head-row">
      <div>
        <span class="mono">Member to member</span>
        <h2 data-reveal>The Marketplace</h2>
        <p data-reveal data-delay="1">
          Clothing listed by real members, ready for a second life.
          Every piece bought here is one less made from scratch.
        </p>
      </div>
      <a class="btn" href="<?= url('marketplace.php') ?>" data-reveal>Browse all &rarr;</a>
    </div>

    <?php if ($listings): ?>
    <div class="grid grid-4">
      <?php foreach ($listings as $i => $l): ?>
      <a class="card" href="<?= url('listing.php?slug=' . urlencode($l['slug'])) ?>"
         data-reveal data-delay="<?= $i % 4 ?>">
        <div class="thumb">
          <span class="tag tag-c2c">Member</span>
          <img src="<?= e(img_or_placeholder($l['image_url'], $l['title'])) ?>" alt="<?= e($l['title']) ?>" loading="lazy">
        </div>
        <div class="body">
          <h3><?= e($l['title']) ?></h3>
          <div class="meta">
            <?= e($l['item_size'] ? 'Size ' . $l['item_size'] . ' · ' : '') ?><?= e(condition_label($l['item_condition'])) ?>
          </div>
          <div class="seller">
            <span class="avatar"><?= e(strtoupper(substr($l['seller_name'], 0, 1))) ?></span>
            <span class="small muted"><?= e($l['seller_name']) ?></span>
          </div>
          <div class="price"><?= money($l['price']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="empty">
        <h3>No listings yet</h3>
        <p>Be the first to give a garment a second life.</p>
        <a class="btn btn-primary" href="<?= url('sell.php') ?>">List an item</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ==================== HOW THE LOOP WORKS ==================== -->
<section class="sec" style="background:var(--bg-sunken)">
  <div class="shell">
    <div class="sec-head center" style="max-width:640px;margin-inline:auto">
      <span class="mono">Reduce &#183; ReWear &#183; Reuse &#183; Recycle</span>
      <h2 data-reveal>Four ways to keep clothing alive</h2>
      <p data-reveal data-delay="1" style="margin-inline:auto">
        Most clothing is worn seven times before it&rsquo;s discarded.
        Here&rsquo;s how we try to break that cycle.
      </p>
    </div>

    <!-- Four cards now instead of three. ReWear gets the gold treatment
         through .step--rewear so the eye lands on it before the others. -->
    <div class="steps steps-4">
      <div class="step" data-reveal>
        <div class="num">REDUCE</div>
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M5 12h14"/><circle cx="12" cy="12" r="9"/></svg></div>
        <h3>Buy less, buy better</h3>
        <p>
          Every piece we make carries a message and is built to last,
          so it stays in your wardrobe instead of the bin. Shop
          <a href="<?= url('shop.php') ?>" style="color:var(--leaf);font-weight:600">The Label</a>.
        </p>
      </div>
      <div class="step step--rewear" data-reveal data-delay="1">
        <span class="step__flag">Our focus</span>
        <div class="num">REWEAR</div>
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.4-5.7M20 4v4h-4"/><path d="M12 8.5V12l2.5 1.6"/></svg></div>
        <h3>Wear it again, and again</h3>
        <p>
          This is the one we push hardest. ReWear costs nothing, because the
          garment already exists. Style it differently, mend the small stuff,
          and give it a proper life before you pass it on.
          <a href="<?= url('how-it-works.php') ?>" style="color:var(--leaf);font-weight:600">See the loop</a>.
        </p>
      </div>
      <div class="step" data-reveal data-delay="2">
        <div class="num">REUSE</div>
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7M21 4v5h-5"/></svg></div>
        <h3>Sell it to someone who&rsquo;ll wear it</h3>
        <p>
          List clothing you no longer wear on the marketplace. Another member
          gets it, you get paid in credits or cash.
          <a href="<?= url('sell.php') ?>" style="color:var(--leaf);font-weight:600">Start selling</a>.
        </p>
      </div>
      <div class="step" data-reveal data-delay="3">
        <div class="num">RECYCLE</div>
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M12 3v18M5 10l7-7 7 7"/><path d="M3 20h18"/></svg></div>
        <h3>Send it back to us</h3>
        <p>
          Too worn to resell? Donate it. We remake what we can into new pieces
          and responsibly recycle the rest, and you earn credits.
          <a href="<?= url('donate.php') ?>" style="color:var(--leaf);font-weight:600">Donate now</a>.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== REMADE (C2B closing the loop) ==================== -->
<?php if ($remade): ?>
<section class="sec">
  <div class="shell">
    <div class="sec-head head-row">
      <div>
        <span class="mono">Closing the loop</span>
        <h2 data-reveal>Remade by us</h2>
        <p data-reveal data-delay="1">
          These started life as donated garments. We rebuilt them, printed the
          message on them, and put them back on the rail.
        </p>
      </div>
      <a class="btn" href="<?= url('shop.php?remade=1') ?>" data-reveal>See all remade &rarr;</a>
    </div>

    <div class="grid grid-4">
      <?php foreach ($remade as $i => $p): ?>
      <a class="card" href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>"
         data-reveal data-delay="<?= $i % 4 ?>">
        <div class="thumb">
          <span class="tag tag-remade">Remade</span>
          <img src="<?= e(img_or_placeholder($p['image_url'], $p['name'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        </div>
        <div class="body">
          <h3><?= e($p['name']) ?></h3>
          <div class="meta"><?= e($p['material']) ?></div>
          <div class="price"><?= money($p['price']) ?>
            <small>&#183; <?= (int)$p['recycled_pct'] ?>% recycled</small></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ==================== THE MESSAGE ==================== -->
<section class="sec">
  <div class="shell split">
    <div data-reveal>
        <span class="mono">Why we make what we make</span>
      <h2 class="serif" style="font-size:clamp(1.9rem,4vw,3.1rem);font-weight:400;line-height:1.08;margin:12px 0 18px">
        Clothing is the loudest thing you wear.
      </h2>
      <p class="muted mb-2">
        Every garment we design carries a message about something that matters:
        gender based violence, mental health, the environment, and the right of
        every person to be seen. You wear it, someone reads it, and a
        conversation starts that might not have happened otherwise.
      </p>
      <p class="muted mb-3">
        That message doesn&rsquo;t stop when you&rsquo;re done with the garment.
        Pass it on through the marketplace, or send it back to us and we&rsquo;ll
        give it a new life. The message keeps moving.
      </p>
      <a class="btn btn-primary" href="<?= url('about.php') ?>">Read our story</a>
    </div>

    <div class="causes" data-reveal data-delay="1">
      <?php foreach ($causes as $c): ?>
      <div class="cause">
        <svg viewBox="0 0 24 24">
          <?php if ($c['icon'] === 'shield'): ?>
            <path d="M12 3l7 3v5c0 4-3 7-7 9-4-2-7-5-7-9V6z"/><path d="M9 12l2 2 4-4"/>
          <?php elseif ($c['icon'] === 'heart'): ?>
            <path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-2 5 5 0 0 1 9 2c-2 4.5-9 9-9 9z"/>
          <?php elseif ($c['icon'] === 'leaf'): ?>
            <path d="M4 20c0-9 6-14 16-15 0 10-5 16-14 16H4z"/><path d="M4 20c4-4 7-6 11-8"/>
          <?php else: ?>
            <path d="M12 3l2.6 5.6 6.1.8-4.5 4.2 1.2 6L12 16.8 6.6 19.6l1.2-6L3.3 9.4l6.1-.8z"/>
          <?php endif; ?>
        </svg>
        <h3><?= e($c['name']) ?></h3>
        <p><?= e($c['tagline']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==================== CTA ==================== -->
<section class="sec">
  <div class="shell">
    <div class="panel center" data-reveal
         style="padding:clamp(38px,6vw,70px);border-radius:var(--r-xl)">
      <span class="mono">Join the loop</span>
      <h2 class="serif mt-1 mb-2"
          style="font-size:clamp(1.8rem,4vw,3rem);font-weight:400">
        Your wardrobe already has a next chapter in it.
      </h2>
      <p class="muted mb-3" style="max-width:52ch;margin-inline:auto">
        Sign up free, list what you no longer wear, and start earning credits
        towards pieces that mean something.
      </p>
      <div class="flex" style="justify-content:center;flex-wrap:wrap">
        <a class="btn btn-primary btn-lg" href="<?= url('register.php') ?>">Create a free account</a>
        <a class="btn btn-lg" href="<?= url('how-it-works.php') ?>">How it works</a>
      </div>
    </div>
  </div>
</section>

<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/6a564e49d45ecd1d4e159346/1jtgi3ksv';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->

<?php include __DIR__ . '/includes/footer.php'; ?>
