/* ================================================================
   OZONE — Shared Micro-Interaction Engine
   Loaded on every staff page via the sidebar includes.
   Pure vanilla JS, no dependencies. Self-initialising and idempotent
   (safe to load more than once). Touches DOM/UX only — never logic.
   ================================================================ */
(function () {
  'use strict';
  if (window.__ozoneInit) return;       // idempotent guard
  window.__ozoneInit = true;

  var reduceMotion = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------------
   * 1. RIPPLE — on .btn and [data-ripple]
   * ------------------------------------------------------------- */
  function attachRipple(e) {
    var el = e.target.closest('.btn, [data-ripple]');
    if (!el || el.disabled || reduceMotion) return;
    var rect = el.getBoundingClientRect();
    var size = Math.max(rect.width, rect.height);
    var span = document.createElement('span');
    span.className = 'ripple';
    span.style.width = span.style.height = size + 'px';
    span.style.left = (e.clientX - rect.left - size / 2) + 'px';
    span.style.top = (e.clientY - rect.top - size / 2) + 'px';
    el.appendChild(span);
    setTimeout(function () { span.remove(); }, 600);
  }
  document.addEventListener('click', attachRipple);

  /* ---------------------------------------------------------------
   * 2. ANIMATED COUNTERS — [data-count-to]
   *    Reads the element's numeric target, counts up on view.
   *    Preserves prefix/suffix via data-prefix / data-suffix.
   * ------------------------------------------------------------- */
  function animateCounter(el) {
    var target = parseFloat(el.getAttribute('data-count-to'));
    if (isNaN(target)) return;
    var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var prefix = el.getAttribute('data-prefix') || '';
    var suffix = el.getAttribute('data-suffix') || '';
    var dur = reduceMotion ? 0 : 1100;
    var start = performance.now();
    function frame(now) {
      var p = dur === 0 ? 1 : Math.min((now - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);          // easeOutCubic
      var val = (target * eased).toFixed(decimals);
      el.textContent = prefix + Number(val).toLocaleString(undefined, {
        minimumFractionDigits: decimals, maximumFractionDigits: decimals
      }) + suffix;
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  /* ---------------------------------------------------------------
   * 3. REVEAL-ON-SCROLL  (.reveal -> .visible)
   *    + counters + progress bars triggered when seen.
   * ------------------------------------------------------------- */
  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var t = entry.target;
      t.classList.add('visible');
      if (t.hasAttribute('data-count-to')) animateCounter(t);
      if (t.classList.contains('progress-bar') && t.dataset.value) {
        t.style.width = t.dataset.value + '%';
      }
      io.unobserve(t);
    });
  }, { threshold: 0.12 }) : null;

  function observeAll() {
    var nodes = document.querySelectorAll('.reveal, [data-count-to], .progress-bar[data-value]');
    nodes.forEach(function (n) {
      if (io) io.observe(n);
      else { n.classList.add('visible'); if (n.hasAttribute('data-count-to')) animateCounter(n); }
    });
  }

  /* ---------------------------------------------------------------
   * 4. SIDEBAR COLLAPSE  (remembers state in localStorage)
   * ------------------------------------------------------------- */
  function initSidebar() {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    if (!sidebar.querySelector('.sidebar-toggle')) {
      var btn = document.createElement('button');
      btn.className = 'sidebar-toggle';
      btn.setAttribute('aria-label', 'Toggle sidebar');
      btn.innerHTML = '<i class="ph-bold ph-caret-left"></i>';
      sidebar.appendChild(btn);
      btn.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('ozone_sidebar_collapsed', sidebar.classList.contains('collapsed'));
      });
    }
    if (localStorage.getItem('ozone_sidebar_collapsed') === 'true' && window.innerWidth > 1200) {
      sidebar.classList.add('collapsed');
    }
  }

  /* ---------------------------------------------------------------
   * 5. TOAST API  — window.ozoneToast(title, msg, type)
   *    type: 'success' | 'error' | 'info' | 'accent'
   * ------------------------------------------------------------- */
  var ICONS = {
    success: 'ph-check-circle', error: 'ph-warning-circle',
    info: 'ph-info', accent: 'ph-bell'
  };
  function getStack() {
    var s = document.querySelector('.toast-stack');
    if (!s) { s = document.createElement('div'); s.className = 'toast-stack'; document.body.appendChild(s); }
    return s;
  }
  window.ozoneToast = function (title, msg, type) {
    type = type || 'accent';
    var stack = getStack();
    var el = document.createElement('div');
    el.className = 'toast ' + (type === 'accent' ? '' : type);
    el.innerHTML =
      '<div class="toast-icon"><i class="ph-fill ' + (ICONS[type] || ICONS.accent) + '"></i></div>' +
      '<div class="toast-body"><div class="toast-title"></div>' +
      (msg ? '<div class="toast-msg"></div>' : '') + '</div>';
    el.querySelector('.toast-title').textContent = title || '';
    if (msg) el.querySelector('.toast-msg').textContent = msg;
    stack.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('show'); });
    setTimeout(function () {
      el.classList.add('hide');
      setTimeout(function () { el.remove(); }, 500);
    }, 4200);
  };

  /* Back-compat shim: the old iOS "Dynamic Island" API now routes to
     the luxury toast stack, so every existing triggerDynamicIsland()
     call site (live-order chime, greeting, dashboard cards) keeps
     working with the new look. */
  window.triggerDynamicIsland = function (title, message, type) {
    if (type === 'danger') type = 'error';
    window.ozoneToast(title, message, type || 'accent');
  };

  /* ---------------------------------------------------------------
   * 6. MODALS — [data-modal-open="id"], [data-modal-close]
   *    Esc to close, click backdrop to close, focus trap-lite.
   * ------------------------------------------------------------- */
  function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
    var f = m.querySelector('input, select, textarea, button');
    if (f) setTimeout(function () { f.focus(); }, 120);
  }
  function closeModal(m) {
    m.classList.remove('open');
    document.body.style.overflow = '';
  }
  window.ozoneModal = { open: openModal, close: function (id) { var m = document.getElementById(id); if (m) closeModal(m); } };

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) { e.preventDefault(); openModal(opener.getAttribute('data-modal-open')); return; }
    var closer = e.target.closest('[data-modal-close]');
    if (closer) { var ov = closer.closest('.modal-overlay'); if (ov) closeModal(ov); return; }
    if (e.target.classList && e.target.classList.contains('modal-overlay')) closeModal(e.target);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var open = document.querySelector('.modal-overlay.open');
      if (open) closeModal(open);
      var dd = document.querySelector('.dropdown.open');
      if (dd) dd.classList.remove('open');
    }
  });

  /* ---------------------------------------------------------------
   * 7. DROPDOWNS — [data-dropdown-toggle]
   * ------------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-dropdown-toggle]');
    document.querySelectorAll('.dropdown.open').forEach(function (d) {
      if (!toggle || d !== toggle.closest('.dropdown')) d.classList.remove('open');
    });
    if (toggle) { e.preventDefault(); var dd = toggle.closest('.dropdown'); if (dd) dd.classList.toggle('open'); }
  });

  /* ---------------------------------------------------------------
   * 8. BUTTON LOADING on form submit  ([data-loading])
   * ------------------------------------------------------------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var btn = form.querySelector('button[type="submit"][data-loading], .btn[data-loading]');
    if (btn) { btn.classList.add('is-loading'); btn.disabled = true; }
  }, true);

  /* ---------------------------------------------------------------
   * 9. SKELETON SWAP — hide [data-skeleton] once window loads
   * ------------------------------------------------------------- */
  function clearSkeletons() {
    document.querySelectorAll('[data-skeleton]').forEach(function (n) {
      n.style.transition = 'opacity .3s ease';
      n.style.opacity = '0';
      setTimeout(function () { n.remove(); }, 320);
    });
  }

  /* ---------------------------------------------------------------
   * INIT
   * ------------------------------------------------------------- */
  function init() {
    initSidebar();
    observeAll();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
  window.addEventListener('load', clearSkeletons);

  // Re-scan helper for AJAX-injected content
  window.ozoneRefresh = observeAll;
})();

/* ================================================================
   STAFF SHELL ADD-ONS  ·  live clock + mobile sidebar drawer
   Appended separately so the idempotent IIFE above is untouched.
   ================================================================ */
function initLiveClock() {
  var clocks = document.querySelectorAll('.live-clock');
  if (!clocks.length) return;
  var dateEls = document.querySelectorAll('.clock-date');
  function tick() {
    var now = new Date();
    var h = String(now.getHours() % 12 || 12).padStart(2, '0');
    var m = String(now.getMinutes()).padStart(2, '0');
    var s = String(now.getSeconds()).padStart(2, '0');
    var ampm = now.getHours() >= 12 ? 'PM' : 'AM';
    var markup = h + ':' + m +
      '<span class="clock-seconds">:' + s + '</span>' +
      '<span class="clock-ampm">' + ampm + '</span>';
    clocks.forEach(function (el) { el.innerHTML = markup; });
    if (dateEls.length) {
      var d = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
      dateEls.forEach(function (el) { el.textContent = d; });
    }
  }
  tick();
  setInterval(tick, 1000);
}

/* Mobile drawer: hamburger toggles body.kami-nav-open; backdrop closes it. */
function initStaffShell() {
  document.addEventListener('click', function (e) {
    if (e.target.closest('.kami-mobile-toggle')) {
      document.body.classList.toggle('kami-nav-open');
    } else if (e.target.closest('.kami-backdrop') ||
               (document.body.classList.contains('kami-nav-open') && e.target.closest('.kami-nav .nav-link'))) {
      document.body.classList.remove('kami-nav-open');
    }
  });
}

/* Mobile data-table rows: tap "Details" to reveal secondary columns (td.row-secondary). */
function initRowExpanders() {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.row-expand-btn');
    if (!btn) return;
    var row = btn.closest('tr');
    if (row) row.classList.toggle('row-expanded');
  });
}

(function () {
  function boot() { initLiveClock(); initStaffShell(); initRowExpanders(); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else { boot(); }
})();
