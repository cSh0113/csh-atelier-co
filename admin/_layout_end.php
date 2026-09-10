</div><!-- /.adm-content -->
</main>

<script>
(function(){
  var b = document.querySelector('[data-adm-burger]');
  var s = document.querySelector('[data-adm-side]');
  if (b && s) b.addEventListener('click', function(){ s.classList.toggle('is-open'); });

  // confirm destructive actions
  document.querySelectorAll('[data-confirm]').forEach(function(el){
    el.addEventListener('submit', function(ev){
      if (!confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  // soft reveal for cards. Threshold is tiny on purpose: a percentage
  // threshold like .12 needs that fraction of the element's own height
  // visible at once, and a long table (products, with no pagination)
  // can be taller than a phone screen many times over, so it would
  // never clear a large threshold and would stay invisible forever.
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(en){
      en.forEach(function(x){ if (x.isIntersecting){ x.target.classList.add('in'); io.unobserve(x.target); } });
    }, { threshold: 0 });
    document.querySelectorAll('.adm-card, .adm-stat').forEach(function(el){ io.observe(el); });
  } else {
    document.querySelectorAll('.adm-card, .adm-stat').forEach(function(el){ el.classList.add('in'); });
  }
})();
</script>
</body>
</html>