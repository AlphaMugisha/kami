/* ============================================================================
   OZONE · public-fx.js
   Site-wide micro-interactions & animations for the PUBLIC website only
   (storefront, menu, cart, marketing pages). Not loaded in the staff system.

   Self-contained: injects its own CSS, wires everything on DOMContentLoaded,
   respects prefers-reduced-motion, and fails safe (content is never left
   hidden if the browser lacks IntersectionObserver).
   ========================================================================== */
(function () {
  'use strict';

  var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var CAN_HOVER = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ---- 1. Inject stylesheet ------------------------------------------- */
  var css = '\
  .kfx-progress{position:fixed;top:0;left:0;height:3px;width:0;z-index:99999;\
    background:linear-gradient(90deg,#fcd34d,#f59e0b,#B8860B);box-shadow:0 0 12px rgba(245,158,11,.5);\
    transition:width .1s linear;pointer-events:none}\
  .kfx-reveal{opacity:0;transform:translateY(28px);\
    transition:opacity .8s cubic-bezier(.16,1,.3,1),transform .8s cubic-bezier(.16,1,.3,1)}\
  .kfx-reveal.kfx-in{opacity:1;transform:none}\
  .kfx-tilt{transform-style:preserve-3d;will-change:transform;transition:transform .3s cubic-bezier(.16,1,.3,1)}\
  .btn{position:relative;overflow:hidden}\
  .kfx-ripple{position:absolute;border-radius:50%;transform:scale(0);pointer-events:none;\
    background:rgba(255,255,255,.45);animation:kfxRipple .6s ease-out;mix-blend-mode:overlay}\
  @keyframes kfxRipple{to{transform:scale(2.6);opacity:0}}\
  a,button,.btn,.product-card,.bento-card,.footer-links a,.nav-links a,.logos i,.shop-filters li{\
    transition-property:color,background-color,border-color,box-shadow,transform,opacity,filter;\
    transition-duration:.3s;transition-timing-function:cubic-bezier(.16,1,.3,1)}\
  .magnetic{will-change:transform}\
  :focus-visible{outline:2px solid var(--accent-main,#B8860B);outline-offset:3px;border-radius:4px}\
  .kfx-up{display:inline-flex;transition:transform .25s cubic-bezier(.16,1,.3,1)}\
  a:hover>.kfx-up,button:hover>.kfx-up,.btn:hover .kfx-up{transform:translateX(4px)}\
  @media (prefers-reduced-motion: reduce){\
    .kfx-reveal{opacity:1!important;transform:none!important;transition:none!important}\
    .kfx-tilt{transition:none}\
  }';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {

    /* ---- 2. Scroll progress bar ------------------------------------- */
    var bar = document.createElement('div');
    bar.className = 'kfx-progress';
    document.body.appendChild(bar);
    function onScroll() {
      var h = document.documentElement;
      var max = (h.scrollHeight - h.clientHeight) || 1;
      bar.style.width = (Math.min(1, h.scrollTop / max) * 100) + '%';
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---- 3. Scroll-reveal ------------------------------------------- */
    // If the page already animates its own content, don't double up.
    var pageAnimates = document.querySelector('.fade-up, .reveal');
    if (!pageAnimates && !REDUCED && 'IntersectionObserver' in window) {
      var targets = [];
      document.querySelectorAll(
        'section > *, .collection-grid > *, .bento-grid > *, .footer-grid > *, .product-card, .bento-card'
      ).forEach(function (el) {
        if (el.closest('nav')) return;
        if (!targets.includes(el)) targets.push(el);
      });
      var stagger = new WeakMap();
      targets.forEach(function (el) { el.classList.add('kfx-reveal'); });
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('kfx-in');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
      targets.forEach(function (el, i) {
        el.style.transitionDelay = (Math.min(i % 6, 5) * 0.06) + 's';
        io.observe(el);
      });
      // Failsafe: reveal everything after 1.4s no matter what.
      setTimeout(function () {
        targets.forEach(function (el) { el.classList.add('kfx-in'); });
      }, 1400);
    }

    /* ---- 4. Magnetic buttons + ripple ------------------------------- */
    document.querySelectorAll('.btn').forEach(function (btn) {
      // ripple
      btn.addEventListener('click', function (e) {
        var rect = btn.getBoundingClientRect();
        var size = Math.max(rect.width, rect.height);
        var rip = document.createElement('span');
        rip.className = 'kfx-ripple';
        rip.style.width = rip.style.height = size + 'px';
        rip.style.left = (e.clientX - rect.left - size / 2) + 'px';
        rip.style.top = (e.clientY - rect.top - size / 2) + 'px';
        btn.appendChild(rip);
        setTimeout(function () { rip.remove(); }, 600);
      });
      // magnetic pull
      if (CAN_HOVER && !REDUCED) {
        btn.classList.add('magnetic');
        btn.addEventListener('mousemove', function (e) {
          var r = btn.getBoundingClientRect();
          var mx = e.clientX - r.left - r.width / 2;
          var my = e.clientY - r.top - r.height / 2;
          btn.style.transform = 'translate(' + (mx * 0.25) + 'px,' + (my * 0.35) + 'px)';
        });
        btn.addEventListener('mouseleave', function () { btn.style.transform = ''; });
      }
    });

    /* ---- 5. 3D tilt on cards ---------------------------------------- */
    if (CAN_HOVER && !REDUCED) {
      document.querySelectorAll('[data-tilt], .bento-card, .mockup-window, .testimonial').forEach(function (card) {
        card.classList.add('kfx-tilt');
        card.addEventListener('mousemove', function (e) {
          var r = card.getBoundingClientRect();
          var px = (e.clientX - r.left) / r.width - 0.5;
          var py = (e.clientY - r.top) / r.height - 0.5;
          card.style.transform = 'perspective(900px) rotateX(' + (-py * 5) + 'deg) rotateY(' + (px * 6) + 'deg) translateY(-4px)';
        });
        card.addEventListener('mouseleave', function () { card.style.transform = ''; });
      });
    }

    /* ---- 6. Count-up numbers (e.g. features metrics) ---------------- */
    if (!REDUCED && 'IntersectionObserver' in window) {
      var nums = document.querySelectorAll('.metric-item h4, [data-count]');
      var co = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          co.unobserve(entry.target);
          var el = entry.target;
          var raw = el.getAttribute('data-count') || el.textContent;
          var m = String(raw).match(/([^\d]*)([\d.,]+)(.*)/);
          if (!m) return;
          var prefix = m[1], suffix = m[3];
          var target = parseFloat(m[2].replace(/,/g, ''));
          if (isNaN(target)) return;
          var decimals = (m[2].split('.')[1] || '').length;
          var start = performance.now(), dur = 1400;
          (function tick(now) {
            var p = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            var val = (target * eased).toFixed(decimals);
            el.textContent = prefix + Number(val).toLocaleString() + suffix;
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = prefix + target.toLocaleString() + suffix;
          })(start);
        });
      }, { threshold: 0.6 });
      nums.forEach(function (el) { co.observe(el); });
    }

    /* ---- 7. Smooth in-page anchor scrolling ------------------------- */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var id = a.getAttribute('href');
        if (id.length < 2) return;
        var t = document.querySelector(id);
        if (!t) return;
        e.preventDefault();
        var y = t.getBoundingClientRect().top + window.pageYOffset - 80;
        window.scrollTo({ top: y, behavior: REDUCED ? 'auto' : 'smooth' });
      });
    });

  });
})();
