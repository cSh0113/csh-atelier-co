<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'about';
$pageTitle = 'About & our message';
$causes = db()->query('SELECT * FROM causes ORDER BY id')->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<section class="sec" style="padding-top:56px">
  <div class="shell" style="max-width:800px">
    <span class="mono" data-reveal>About CSH Atelier Co.</span>
    <h1 class="serif" data-reveal data-delay="1"
        style="font-size:clamp(2.2rem,5.4vw,4rem);font-weight:400;line-height:1.04;margin:16px 0 24px">
      Clothing is the loudest thing you wear.
    </h1>
    <p class="lede muted" data-reveal data-delay="2">
      We started CSH Atelier because a garment is one of the few things you can
      wear into a room that says something before you do. So we decided ours
      should say something worth hearing.
    </p>
  </div>
</section>

<section class="sec" style="padding-top:20px">
  <div class="shell split">
    <div data-reveal>
      <h2 class="serif mb-2" style="font-size:clamp(1.6rem,3.2vw,2.4rem);font-weight:400">The message</h2>
      <p class="muted mb-2">
        Every piece we design carries a message about something that matters in
        South Africa and beyond: gender based violence, mental health, the
        environment, and the right of every person to be seen and to rise.
      </p>
      <p class="muted mb-2">
        We don&rsquo;t print slogans for the sake of it. Each collection is built
        around a conversation we think people should be having, and the garment is
        just the thing that starts it. Somebody reads your shirt in a queue, and
        for a moment the subject is on the table.
      </p>
      <p class="muted">
        That&rsquo;s the point. Fashion gets a lot of criticism for being shallow.
        We think it can be the opposite, a way of carrying something
        important through a very ordinary day.
      </p>
    </div>
    <div class="causes" data-reveal data-delay="1">
      <?php foreach ($causes as $c): ?>
      <div class="cause">
        <h3><?= e($c['name']) ?></h3>
        <p><?= e($c['description']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec" style="background:var(--bg-sunken)">
  <div class="shell split">
    <div data-reveal>
      <span class="mono">The second problem</span>
      <h2 class="serif mt-1 mb-2" style="font-size:clamp(1.6rem,3.2vw,2.4rem);font-weight:400">
        Making clothing with a conscience isn&rsquo;t enough
      </h2>
      <p class="muted mb-2">
        You can print the most important message in the world on a t-shirt, but if
        that shirt is worn seven times and thrown away, you&rsquo;ve still added to
        the problem. Textile waste is one of the largest and least-discussed
        environmental burdens we have.
      </p>
      <p class="muted mb-2">
        So we built the rest of the business around keeping clothing alive. Members
        sell their preloved pieces to each other. Anything too worn to resell comes
        back to us, and we remake it into something new or recycle it responsibly.
        People earn credits for taking part.
      </p>
      <p class="muted">
        The result is a brand where buying something new is only one of the options,
        and honestly, not the one we push hardest.
      </p>
    </div>
    <div data-reveal data-delay="1">
      <div class="panel" style="padding:34px">
        <h3 class="mb-2">What makes us different</h3>
        <div class="spec"><span>We sell to you</span><strong>B2C</strong></div>
        <div class="spec"><span>You sell to each other</span><strong>C2C</strong></div>
        <div class="spec"><span>You sell back to us</span><strong>C2B</strong></div>
        <div class="spec"><span>We remake it</span><strong>Circular</strong></div>
        <p class="small muted mt-2" style="margin-bottom:0">
          Most clothing brands only do the first one. The other three are what
          keep a garment out of a landfill.
        </p>
      </div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="shell" style="max-width:800px">
    <span class="mono" data-reveal>The people</span>
    <h2 class="serif mt-1 mb-2" data-reveal data-delay="1"
        style="font-size:clamp(1.6rem,3.2vw,2.4rem);font-weight:400">A division of CSH Innovations Co.</h2>
    <p class="muted mb-2" data-reveal data-delay="2">
      CSH Atelier Co. is the fashion arm of CSH Innovations Co., a South African
      multisector company founded by <strong>Sibusiso Hector Chauke</strong>,
      working across technology, art, fashion, music and media.
    </p>
    <p class="muted" data-reveal data-delay="3">
      The thread running through all of it is the same: build things that create
      opportunity for people who don&rsquo;t usually get handed one, and do it in a
      way that leaves the place better than we found it.
    </p>

    <div class="panel center mt-3" data-reveal style="padding:44px;border-radius:var(--r-xl)">
      <h3 class="serif mb-2" style="font-size:1.6rem;font-weight:400">Join the loop</h3>
      <p class="muted small mb-3" style="max-width:44ch;margin-inline:auto">
        Wear the message. Then pass it on.
      </p>
      <div class="flex" style="justify-content:center;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?= url('marketplace.php') ?>">Shop the marketplace</a>
        <a class="btn" href="<?= url('donate.php') ?>">Donate clothing</a>
        <a class="btn" href="https://cshinnovations.com" target="_blank" rel="noopener noreferrer">Visit CSH Innovations Co. &rarr;</a>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
