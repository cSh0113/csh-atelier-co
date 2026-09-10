</main>

<div class="marquee" aria-hidden="true">
  <div data-marquee>
    <span>Reduce <em>&#8226;</em></span>
    <span>Reuse <em>&#8226;</em></span>
    <span>Recycle <em>&#8226;</em></span>
    <span>Rewear <em>&#8226;</em></span>
    <span>Remade <em>&#8226;</em></span>
    <span>Wear the message <em>&#8226;</em></span>
  </div>
</div>

<footer class="site-footer">
  <div class="shell">
    <div class="foot-grid">
      <div>
        <div class="logo mb-1">CSH <span>Atelier</span></div>
        <p class="muted small" style="max-width:36ch">
          Clothing that carries a message, and a second life. Buy, sell,
          donate and remake, so fewer garments end up in landfill.
        </p>
      </div>
      <div>
        <h4>Shop</h4>
        <a href="<?= url('marketplace.php') ?>">Marketplace</a>
        <a href="<?= url('shop.php') ?>">The Label</a>
        <a href="<?= url('shop.php?remade=1') ?>">Remade pieces</a>
      </div>
      <div>
        <h4>Circulate</h4>
        <a href="<?= url('sell.php') ?>">Sell an item</a>
        <a href="<?= url('donate.php') ?>">Donate &amp; earn credits</a>
        <a href="<?= url('how-it-works.php') ?>">How it works</a>
      </div>
      <div>
        <h4>Company</h4>
        <a href="<?= url('about.php') ?>">About &amp; our message</a>
        <a href="<?= url('account.php') ?>">My account</a>
        <a href="mailto:admin@cshinnovations.com">Contact</a>
        <a href="https://cshinnovations.com" target="_blank" rel="noopener noreferrer">CSH Innovations Co. &rarr;</a>
      </div>
    </div>
    <div class="newsletter-panel">
      <div><h4>Stay in the loop</h4><p class="small muted">New drops, circular fashion notes and community updates.</p></div>
      <form class="newsletter-form" method="post" action="<?= url('newsletter.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
        <label class="sr-only" for="footer-newsletter-email">Email address</label>
        <input id="footer-newsletter-email" type="email" name="email" placeholder="Your email address" required>
        <button class="btn btn-leaf" type="submit">Subscribe</button>
      </form>
    </div>
    <div class="foot-bottom">
      <span>&copy; <?= date('Y') ?> CSH Atelier Co., a division of CSH Innovations Co.</span>
      <span>Made in South Africa &#183; CSH Innovations Co.</span>
    </div>
  </div>
</footer>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
