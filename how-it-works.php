<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'how';
$pageTitle = 'How it works';
$stats = impact_stats();
$perItem = (int)(setting('credits_per_item') ?: CREDITS_PER_ITEM);
include __DIR__ . '/includes/header.php';
?>
<section class="sec" style="padding-top:52px">
  <div class="shell" style="max-width:760px;text-align:center">
    <span class="mono" data-reveal>The circular model</span>
    <h1 class="serif" data-reveal data-delay="1"
        style="font-size:clamp(2.2rem,5vw,3.6rem);font-weight:400;line-height:1.06;margin:14px 0 18px">
      One garment.<br>Many lives.
    </h1>
    <p class="muted" data-reveal data-delay="2">
      A t-shirt takes about 2,700 litres of water to make and is worn, on average,
      just seven times before being thrown away. Everything below exists to change
      that number.
    </p>
  </div>
</section>

<!-- THE THREE R's -->
<section class="sec" style="background:var(--bg-sunken);padding-top:60px">
  <div class="shell">
    <div class="steps">
      <div class="step" data-reveal>
        <div class="num">01 &#183; REDUCE</div>
        <div class="ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></div>
        <h3>Buy fewer, better things</h3>
        <p>
          Everything we make is designed to be kept: heavier fabric, better
          seams, and a message printed on it that doesn&rsquo;t go out of season.
          The most sustainable garment is the one you don&rsquo;t replace.
        </p>
        <a class="btn btn-sm mt-2" href="<?= url('shop.php') ?>">Shop The Label</a>
      </div>

      <div class="step" data-reveal data-delay="1">
        <div class="num">02 &#183; REUSE</div>
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7M21 4v5h-5"/></svg></div>
        <h3>Pass it to the next person</h3>
        <p>
          List anything you&rsquo;ve stopped wearing on the marketplace. Another
          member buys it, wears it, and eventually passes it on again. You get
          paid; the garment keeps going.
        </p>
        <a class="btn btn-sm mt-2" href="<?= url('sell.php') ?>">List an item</a>
      </div>

      <div class="step" data-reveal data-delay="2">
        <div class="num">03 &#183; RECYCLE</div>
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M12 3v18M5 10l7-7 7 7"/><path d="M3 20h18"/></svg></div>
        <h3>When it&rsquo;s truly done, send it back</h3>
        <p>
          Torn, stained or stretched, it still has value as material. Donate it
          and we&rsquo;ll remake what we can and recycle the rest. You earn about
          <?= $perItem ?> credits per item.
        </p>
        <a class="btn btn-sm mt-2" href="<?= url('donate.php') ?>">Donate &amp; earn</a>
      </div>
    </div>
  </div>
</section>

<!-- CREDITS EXPLAINER -->
<section class="sec">
  <div class="shell split">
    <div data-reveal>
      <span class="mono">Store credits</span>
      <h2 class="serif mt-1 mb-2" style="font-size:clamp(1.7rem,3.4vw,2.6rem);font-weight:400">
        The currency of the loop
      </h2>
      <p class="muted mb-2">
        Credits are what you earn for keeping clothing in circulation, whether
        you donate it back to us or sell it to another member. They work like money
        anywhere on the site.
      </p>
      <div class="panel-in">
        <div class="spec"><span>Donate an item</span><strong>+<?= $perItem ?> credits</strong></div>
        <div class="spec"><span>Sell on the marketplace</span><strong>Credits or payout</strong></div>
        <div class="spec"><span>Signup bonus</span><strong>+<?= SIGNUP_BONUS ?> credits</strong></div>
        <div class="spec"><span>Credit value</span><strong><?= CREDIT_RATE ?> credits = R1.00</strong></div>
        <div class="spec"><span>Expiry</span><strong>Never</strong></div>
      </div>
      <p class="small muted mt-2">
        Spend credits at checkout on marketplace listings or our own pieces,
        partially or in full.
      </p>
    </div>

    <div data-reveal data-delay="1">
      <div class="panel" style="padding:34px">
        <h3 class="mb-2">A worked example</h3>
        <div class="row-item" style="grid-template-columns:1fr auto">
          <div><strong class="small">You donate 4 old garments</strong>
            <div class="small muted">2 resold, 1 remade, 1 recycled</div></div>
          <strong style="color:var(--leaf)">+<?= $perItem*4 ?></strong>
        </div>
        <div class="row-item" style="grid-template-columns:1fr auto">
          <div><strong class="small">You sell a jacket for R420</strong>
            <div class="small muted">less <?= (int)(SELLER_COMMISSION*100) ?>% platform fee</div></div>
          <strong style="color:var(--leaf)">+<?= rand_to_credits(420*(1-SELLER_COMMISSION)) ?></strong>
        </div>
        <div class="row-item" style="grid-template-columns:1fr auto">
          <div><strong class="small">Signup bonus</strong></div>
          <strong style="color:var(--leaf)">+<?= SIGNUP_BONUS ?></strong>
        </div>
        <div class="totals grand">
          <span>Balance</span>
          <span><?= number_format($perItem*4 + rand_to_credits(420*(1-SELLER_COMMISSION)) + SIGNUP_BONUS) ?> credits</span>
        </div>
        <p class="small muted center mt-1">
          &asymp; <?= money(credits_to_rand($perItem*4 + rand_to_credits(420*(1-SELLER_COMMISSION)) + SIGNUP_BONUS)) ?>
          to spend on the site
        </p>
      </div>
    </div>
  </div>
</section>

<!-- IMPACT -->
<section class="sec" style="background:var(--bg-sunken)">
  <div class="shell">
    <div class="sec-head center" style="max-width:600px;margin-inline:auto">
      <span class="mono">Where we are so far</span>
      <h2 data-reveal>The loop in numbers</h2>
    </div>
    <div class="impact">
      <div class="stat" data-reveal><b data-count="<?= (int)$stats['items_diverted'] ?>">0</b><span>Garments circulated</span></div>
      <div class="stat" data-reveal data-delay="1"><b data-count="<?= (int)$stats['remade'] ?>">0</b><span>Pieces remade by us</span></div>
      <div class="stat" data-reveal data-delay="2"><b data-count="<?= (int)$stats['water_litres'] ?>">0</b><span>Litres of water saved (est.)</span></div>
      <div class="stat" data-reveal data-delay="3"><b data-count="<?= (int)$stats['members'] ?>">0</b><span>Members</span></div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="sec">
  <div class="shell" style="max-width:780px">
    <div class="sec-head"><h2 data-reveal>Common questions</h2></div>
    <?php
    $faqs = [
      ['Who sets the price on a marketplace listing?',
       'The member selling it. We only review listings before they go live to keep the marketplace safe and accurate.'],
      ['What happens to clothing I donate?',
       'We sort it into three streams: good enough to resell, good fabric to remake into new pieces, or recycling. You can see the outcome of your donation on your account page.'],
      ['Can I use credits and cash together?',
       'Yes. At checkout you choose how many credits to apply, and pay the balance with card or EFT.'],
      ['Do credits expire?',
       'No. They stay on your account until you use them.'],
      ['How do I get paid when my listing sells?',
       'Your earnings land as store credits by default. You can request a cash payout from your account once you pass R200.'],
      ['Is the clothing cleaned?',
       'Items we resell ourselves are cleaned and checked. Member to member listings are sold as described by the seller, so read the condition notes.'],
    ];
    foreach ($faqs as $i => $f): ?>
      <div class="panel-in mb-1" data-reveal data-delay="<?= $i % 4 ?>">
        <strong><?= e($f[0]) ?></strong>
        <p class="small muted mt-1" style="margin:0"><?= e($f[1]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="sec" style="padding-top:0">
  <div class="shell">
    <div class="panel center" data-reveal style="padding:clamp(38px,6vw,64px);border-radius:var(--r-xl)">
      <h2 class="serif mb-2" style="font-size:clamp(1.7rem,3.6vw,2.6rem);font-weight:400">Ready to close the loop?</h2>
      <div class="flex" style="justify-content:center;flex-wrap:wrap">
        <a class="btn btn-primary btn-lg" href="<?= url('register.php') ?>">Create a free account</a>
        <a class="btn btn-lg" href="<?= url('marketplace.php') ?>">Browse the marketplace</a>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
