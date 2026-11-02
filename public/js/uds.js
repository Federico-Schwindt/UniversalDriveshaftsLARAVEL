/* Universal Driveshafts — global UI behaviour (no dependencies) */
(function () {
  'use strict';
  var mm = window.matchMedia('(prefers-reduced-motion: reduce)');
  document.documentElement.classList.add('js');

  /* ---------- scroll reveal ---------- */
  function initReveal() {
    var els = document.querySelectorAll('.reveal, .reveal-stagger');
    if (!els.length) return;

    function showAll() {
      Array.prototype.forEach.call(els, function (el) { el.classList.add('is-in'); });
    }
    if (mm.matches || !('IntersectionObserver' in window)) { showAll(); return; }

    Array.prototype.forEach.call(els, function (el) {
      if (el.classList.contains('reveal-stagger')) {
        Array.prototype.forEach.call(el.children, function (c, i) {
          c.style.setProperty('--i', i);
        });
      }
    });

    // threshold 0: a grid taller than the viewport can never satisfy a
    // percentage threshold, which would leave it invisible forever.
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -40px 0px', threshold: 0 });
    Array.prototype.forEach.call(els, function (el) { io.observe(el); });

    // failsafe: nothing stays hidden for more than 1.2s whatever happens
    setTimeout(showAll, 1200);
  }

  /* ---------- sticky header shadow ---------- */
  function initHeader() {
    var head = document.getElementById('uhead');
    if (!head) return;
    var ticking = false;
    // stick only once the header actually reaches the top (below the promo bar), with hysteresis
    var promo = document.querySelector('.upromo');
    function update() {
      var offset = promo ? promo.offsetHeight : 0, y = window.scrollY, on = head.classList.contains('is-stuck');
      if (!on && y > offset + 2) head.classList.add('is-stuck');
      else if (on && y < Math.max(0, offset - 12)) head.classList.remove('is-stuck');
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
    update();
  }

  /* ---------- mobile drawer ---------- */
  function initDrawer() {
    var burger = document.getElementById('uburger');
    var drawer = document.getElementById('udrawer');
    if (!burger || !drawer) return;
    var closeBtn = drawer.querySelector('[data-drawer-close]');
    var scrim = drawer.querySelector('.udrawer__scrim');
    function open() {
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden', 'false');
      burger.setAttribute('aria-expanded', 'true');
      document.documentElement.style.overflow = 'hidden';
    }
    function close() {
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
      burger.setAttribute('aria-expanded', 'false');
      document.documentElement.style.overflow = '';
    }
    burger.addEventListener('click', function () {
      drawer.classList.contains('is-open') ? close() : open();
    });
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (scrim) scrim.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });
    drawer.addEventListener('click', function (e) {
      var a = e.target.closest('a[href]');
      if (a && !a.hasAttribute('data-drawer-close')) close();
    });
  }

  /* ---------- header dropdown menus (hover on desktop, click on touch, keyboard) ---------- */
  function initMenus() {
    var items = document.querySelectorAll('.umenu__item.has-menu');
    var touch = window.matchMedia('(hover: none)').matches || window.innerWidth <= 1240;
    items.forEach(function (item) {
      var trigger = item.querySelector('.umenu__link');
      var menu = item.querySelector('.umega');
      if (!trigger || !menu) return;
      trigger.setAttribute('aria-expanded', 'false');
      trigger.setAttribute('aria-haspopup', 'true');
      function setOpen(v) {
        menu.classList.toggle('is-open', v);
        trigger.setAttribute('aria-expanded', v ? 'true' : 'false');
      }
      if (touch && trigger.tagName === 'A') {
        trigger.addEventListener('click', function (e) {
          if (!menu.classList.contains('is-open')) { e.preventDefault(); setOpen(true); }
        });
      }
      trigger.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setOpen(true); var f = menu.querySelector('a'); if (f) f.focus(); }
        if (e.key === 'Escape') setOpen(false);
      });
      item.addEventListener('focusout', function () {
        setTimeout(function () { if (!item.contains(document.activeElement)) setOpen(false); }, 0);
      });
      document.addEventListener('click', function (e) {
        if (!item.contains(e.target)) setOpen(false);
      });
    });
  }

  /* ---------- mark the current top-level nav item ---------- */
  function initActiveNav() {
    var path = location.pathname.replace(/\/$/, '') || '/';
    document.querySelectorAll('.umenu__link[data-match]').forEach(function (link) {
      var pats = link.getAttribute('data-match').split(',');
      var hit = pats.some(function (p) {
        p = p.trim();
        return p === '/' ? path === '/' : (path === p || path.indexOf(p + '/') === 0);
      });
      if (hit) link.classList.add('is-active');
    });
  }

  /* ---------- "Our locations" store popover: was hover/focus-within only in
     CSS, so it never opened on tap (no hover on touch) and aria-expanded sat
     on "false" forever regardless of state. Click-driven now, same pattern as
     the header mega menus. ---------- */
  function initStoreMenu() {
    var wrap = document.querySelector('.upromo__stores');
    var trigger = wrap && wrap.querySelector('.upromo__storebtn');
    var menu = wrap && wrap.querySelector('.upromo__storemenu');
    if (!wrap || !trigger || !menu) return;
    trigger.setAttribute('aria-haspopup', 'true');
    function setOpen(v) {
      wrap.classList.toggle('is-open', v);
      trigger.setAttribute('aria-expanded', v ? 'true' : 'false');
    }
    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen(!wrap.classList.contains('is-open'));
    });
    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setOpen(false); trigger.blur(); }
    });
    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) setOpen(false);
    });
    wrap.addEventListener('focusout', function () {
      setTimeout(function () { if (!wrap.contains(document.activeElement)) setOpen(false); }, 0);
    });
  }

  function boot() {
    initReveal(); initHeader(); initDrawer(); initMenus(); initActiveNav(); initStoreMenu();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else { boot(); }
})();
