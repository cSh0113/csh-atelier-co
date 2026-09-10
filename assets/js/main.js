/* ============================================================
   CSH ATELIER CO. front end behaviour

   Everything the pages do without reloading: the scroll reveals, the
   counting numbers on the home page, the light and dark switch, the
   mobile menu, and adding to cart in the background.
   One file, wrapped in a closure so nothing leaks onto the page.
   ============================================================ */
(function () {
  'use strict';

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Light and dark. I save the choice so it sticks between visits. */
  var root = document.documentElement;
  var saved = null;
  try { saved = localStorage.getItem('csh-theme'); } catch (e) {}
  if (saved) root.setAttribute('data-theme', saved);

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-theme-toggle]');
    if (!t) return;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('csh-theme', next); } catch (e) {}
  });

  /* The slide out menu on phones, plus the dark scrim behind it. */
  var mnav  = document.querySelector('[data-mnav]');
  var scrim = document.querySelector('[data-scrim]');
  function closeNav() {
    if (mnav) mnav.classList.remove('open');
    if (scrim) scrim.classList.remove('on');
  }
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-menu-open]')) {
      if (mnav) mnav.classList.add('open');
      if (scrim) scrim.classList.add('on');
    }
    if (ev.target.closest('[data-menu-close]') || ev.target.closest('[data-scrim]')) {
      closeNav();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeNav();
  });

  /* ---------- SCROLL PROGRESS ---------- */
  var bar = document.querySelector('[data-progress]');
  function progress() {
    if (!bar) return;
    var d = document.documentElement;
    var max = (d.scrollHeight - d.clientHeight) || 1;
    bar.style.width = ((d.scrollTop / max) * 100) + '%';
  }

  /* ---------- SCROLL REVEAL ---------- */
  var revealEls = document.querySelectorAll('[data-reveal]');
  if (reduce) {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  } else if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---------- ANIMATED COUNTERS ---------- */
  function runCounter(el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var dur = 1400, t0 = null;
    var suffix = el.getAttribute('data-suffix') || '';
    function tick(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      var val = target * eased;
      el.textContent = formatNum(val, target) + suffix;
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  function formatNum(v, target) {
    if (target >= 1000000) return (v / 1000000).toFixed(1) + 'M';
    if (target >= 1000)    return Math.round(v).toLocaleString();
    return Math.round(v).toString();
  }
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    if (reduce) {
      counters.forEach(function (el) {
        var t = parseFloat(el.getAttribute('data-count')) || 0;
        el.textContent = formatNum(t, t) + (el.getAttribute('data-suffix') || '');
      });
    } else if ('IntersectionObserver' in window) {
      var cio = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { runCounter(en.target); cio.unobserve(en.target); }
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { cio.observe(el); });
    } else {
      counters.forEach(runCounter);
    }
  }

  /* ---------- MARQUEE (duplicate for seamless loop) ---------- */
  var mq = document.querySelector('[data-marquee]');
  if (mq && mq.parentNode) {
    mq.parentNode.appendChild(mq.cloneNode(true));
  }

  /* ---------- AJAX ADD TO CART ---------- */
  function toast(msg, type) {
    var wrap = document.querySelector('[data-flash-wrap]');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'flash-wrap';
      wrap.setAttribute('data-flash-wrap', '');
      document.body.appendChild(wrap);
    }
    var el = document.createElement('div');
    el.className = 'flash ' + (type || '');
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .4s, transform .4s';
      el.style.opacity = '0';
      el.style.transform = 'translateX(24px)';
      setTimeout(function () { el.remove(); }, 400);
    }, 2600);
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-add-cart]');
    if (!btn) return;
    ev.preventDefault();

    var type = btn.getAttribute('data-type') || 'product';
    var id   = btn.getAttribute('data-id');
    if (!id) return;

    btn.disabled = true;
    var original = btn.textContent;
    btn.textContent = 'Adding...';

    var body = new URLSearchParams();
    body.append('action', 'add');
    body.append('type', type);
    body.append('id', id);
    body.append('csrf_token', window.CSH_CSRF || '');

    fetch(window.CSH_BASE + '/api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        btn.disabled = false;
        btn.textContent = original;
        if (data && data.success) {
          updateBadge(data.count);
          toast(data.message || 'Added to your bag');
        } else {
          toast((data && data.message) || 'Could not add that item', 'error');
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = original;
        toast('Network error - please try again', 'error');
      });
  });

  function updateBadge(count) {
    var link = document.querySelector('a[href$="cart.php"]');
    if (!link) return;
    var badge = link.querySelector('[data-cart-badge]');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'badge';
      badge.setAttribute('data-cart-badge', '');
      link.appendChild(badge);
    }
    badge.textContent = count;
    badge.style.transform = 'scale(1.4)';
    setTimeout(function () { badge.style.transform = 'scale(1)'; }, 200);
  }

  /* ---------- CREDIT SLIDER (checkout) ---------- */
  var slider = document.querySelector('[data-credit-slider]');
  if (slider) {
    var out   = document.querySelector('[data-credit-out]');
    var rand  = document.querySelector('[data-credit-rand]');
    var payEl = document.querySelector('[data-pay-total]');
    var rate  = parseFloat(slider.getAttribute('data-rate')) || 10;
    var subtotal = parseFloat(slider.getAttribute('data-subtotal')) || 0;
    var shipping = parseFloat(slider.getAttribute('data-shipping')) || 0;

    var sync = function () {
      var c = parseInt(slider.value, 10) || 0;
      var value = c / rate;
      var delivery = document.querySelector('input[name="delivery_method"]:checked');
      var freight = delivery && delivery.value === 'collection' ? 0 : shipping;
      if (out)  out.textContent  = c.toLocaleString();
      if (rand) rand.textContent = 'R' + value.toFixed(2);
      if (payEl) payEl.textContent = 'R' + Math.max(0, subtotal + freight - value).toFixed(2);
    };
    slider.addEventListener('input', sync);
    document.querySelectorAll('input[name="delivery_method"]').forEach(function (radio) {
      radio.addEventListener('change', sync);
    });
    sync();
  }

  /* ---------- LIVE FILTER (marketplace) ---------- */
  var search = document.querySelector('[data-live-search]');
  if (search) {
    var cards = Array.prototype.slice.call(
      document.querySelectorAll('[data-searchable]')
    );
    search.addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      var shown = 0;
      cards.forEach(function (c) {
        var hay = (c.getAttribute('data-searchable') || '').toLowerCase();
        var hit = !q || hay.indexOf(q) > -1;
        c.style.display = hit ? '' : 'none';
        if (hit) shown++;
      });
      var none = document.querySelector('[data-no-results]');
      if (none) none.style.display = shown ? 'none' : '';
    });
  }

  /* ---------- MARKETPLACE VIEW SWITCHER ---------- */
  var viewGrid = document.querySelector('[data-view-grid]');
  var viewButtons = document.querySelectorAll('[data-view]');
  if (viewGrid && viewButtons.length) {
    var allowedViews = ['grid-4', 'grid-3', 'grid-2', 'list'];
    var savedView = null;
    try { savedView = localStorage.getItem('csh-marketplace-view'); } catch (e) {}

    function applyView(view) {
      if (allowedViews.indexOf(view) === -1) view = 'grid-4';
      viewGrid.classList.remove('grid-4', 'grid-3', 'grid-2', 'list');
      viewGrid.classList.add(view);
      viewButtons.forEach(function (button) {
        var active = button.getAttribute('data-view') === view;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      try { localStorage.setItem('csh-marketplace-view', view); } catch (e) {}
    }

    viewButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        applyView(button.getAttribute('data-view'));
      });
    });
    applyView(savedView || 'grid-4');
  }

  /* ---------- IMAGE PREVIEW ON UPLOAD ---------- */
  document.addEventListener('change', function (ev) {
    var input = ev.target.closest('[data-preview]');
    if (!input || !input.files || !input.files[0]) return;
    var target = document.querySelector(input.getAttribute('data-preview'));
    if (!target) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      target.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
    };
    reader.readAsDataURL(input.files[0]);
  });

  /* ---------- SCROLL LISTENER ---------- */
  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () { progress(); ticking = false; });
  }, { passive: true });
  progress();
})();

/* ---------- scroll reveals: [data-rise] and the circular loop ---------- */
(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var rise = document.querySelectorAll('[data-rise]');
  var loops = document.querySelectorAll('[data-loop]');

  if (reduce || !('IntersectionObserver' in window)) {
    rise.forEach(function(el){ el.classList.add('in'); });
    loops.forEach(function(el){ el.classList.add('in'); });
    return;
  }

  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(en){
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    });
  }, { threshold: 0.18 });

  rise.forEach(function(el){ io.observe(el); });
  loops.forEach(function(el){ io.observe(el); });

  /* Any number tagged with data-count rolls up from zero when it scrolls
     into view. Only fires once, otherwise it replays on every scroll. */
  var cio = new IntersectionObserver(function(entries){
    entries.forEach(function(en){
      if (!en.isIntersecting) return;
      var el = en.target, target = parseFloat(el.getAttribute('data-count')) || 0, t0 = null;
      function step(ts){
        if (!t0) t0 = ts;
        var k = Math.min(1, (ts - t0) / 1400), eased = 1 - Math.pow(1 - k, 3);
        el.textContent = Math.round(target * eased).toLocaleString();
        if (k < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
      cio.unobserve(el);
    });
  }, { threshold: 0.5 });
  document.querySelectorAll('[data-count]').forEach(function(el){ cio.observe(el); });
})();
