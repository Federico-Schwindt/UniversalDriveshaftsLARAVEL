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

  /* ---------- top bar shadow once the page scrolls under it ---------- */
  function initHeader() {
    var head = document.getElementById('uhead');
    if (!head) return;
    var ticking = false;
    function update() { head.classList.toggle('is-stuck', window.scrollY > 2); ticking = false; }
    window.addEventListener('scroll', function () {
      if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
    update();
  }

  /* ---------- sidebar menu (partials/sidebar): groups open and close, the desktop sidebar folds to an icon
     rail (remembered; the layout head applies it before paint), and below 1100px it is the off-canvas menu ---------- */
  function initSidebar() {
    var sb = document.getElementById('sb');
    if (!sb) return;
    var html = document.documentElement, burger = document.getElementById('uburger'), scrim = document.querySelector('.sb-scrim');
    var desk = window.matchMedia('(min-width:1100px)'), fold = sb.querySelector('[data-sb-fold]');
    var t = window.udsT || function (s) { return s; };
    function setRail(on) {
      html.classList.toggle('sb-rail', on);
      try { localStorage.setItem('uds.rail', on ? '1' : '0'); } catch (e) {}
      if (fold) { var l = on ? t('Expand menu') : t('Collapse menu'); fold.setAttribute('aria-label', l); fold.title = l; }
      window.dispatchEvent(new Event('resize'));   // the parts tables refit to the new width
    }
    if (fold && html.classList.contains('sb-rail')) { fold.setAttribute('aria-label', t('Expand menu')); fold.title = t('Expand menu'); }
    // focus moves only for keyboard users: a programmatic focus after a tap leaves a focus ring on phones
    function open(kbd) {
      html.classList.add('sb-open'); if (scrim) scrim.hidden = false;
      if (burger) burger.setAttribute('aria-expanded', 'true');
      html.style.overflow = 'hidden';
      var x = sb.querySelector('.sb__x'); if (x && kbd) x.focus();
    }
    function close() {
      if (!html.classList.contains('sb-open')) return;
      html.classList.remove('sb-open'); if (scrim) scrim.hidden = true;
      if (burger) burger.setAttribute('aria-expanded', 'false');
      html.style.overflow = '';
    }
    sb.addEventListener('click', function (e) {
      // the group names are links (Parts catalog → /shop, My account → the account…); the arrow beside each opens its list
      var b = e.target.closest('.sb__tog, .sb__subbtn');
      if (b) {
        var box = b.closest('.sb__grp, .sb__sub');
        var on = !box.classList.contains('is-open');
        box.classList.toggle('is-open', on); b.setAttribute('aria-expanded', on ? 'true' : 'false');
        place(box.closest('.sb__grp'));
        return;
      }
      if (e.target.closest('[data-sb-fold]')) { setRail(!html.classList.contains('sb-rail')); return; }
      if (e.target.closest('[data-sb-close]')) { close(); if (burger && e.detail === 0) burger.focus(); }
    });
    // folded rail: a group's links fly out beside its icon (CSS), lifted when they would run past the bottom of the window
    function place(g) {
      var l = g && g.querySelector('.sb__list');
      if (!l || !html.classList.contains('sb-rail')) return;
      l.style.top = '';
      var r = l.getBoundingClientRect();
      if (!r.height) return;
      var over = r.bottom - (window.innerHeight - 12);
      if (over > 0) l.style.top = -Math.min(over, r.top - 12) + 'px';
    }
    [].forEach.call(sb.querySelectorAll('.sb__grp'), function (g) {
      g.addEventListener('mouseenter', function () { place(g); });
      g.addEventListener('focusin', function () { place(g); });
    });
    if (burger) burger.addEventListener('click', function (e) { if (html.classList.contains('sb-open')) close(); else open(e.detail === 0); if (e.detail !== 0) burger.blur(); });
    if (scrim) scrim.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && html.classList.contains('sb-open')) { close(); if (burger) burger.focus(); } });
    (desk.addEventListener ? desk.addEventListener.bind(desk, 'change') : desk.addListener.bind(desk))(function () { if (desk.matches) close(); });
    // the current page's link in view in a long menu
    var cur = sb.querySelector('.sb__list a.is-on'), sc = sb.querySelector('.sb__scroll');
    if (cur && sc && cur.offsetTop > sc.clientHeight - 60) sc.scrollTop = cur.offsetTop - sc.clientHeight / 2;
  }

  /* ---------- language menus (partials/lang-switch): a <details> opens on its own; this closes the
     dropdowns on an outside click, Escape or tabbing away, keeps one open at a time, and lets the arrow
     keys move between the languages. The mobile-menu one is an accordion row and stays put. ---------- */
  function initLangMenus() {
    var menus = [].slice.call(document.querySelectorAll('[data-langsw]'));
    if (!menus.length) return;
    function dropdown(d) { return !d.classList.contains('langsw--drawer'); }
    menus.forEach(function (d) {
      var sum = d.querySelector('summary');
      d.addEventListener('toggle', function () {
        if (d.open) menus.forEach(function (o) { if (o !== d && dropdown(o)) o.open = false; });
      });
      d.addEventListener('keydown', function (e) {
        var links = [].slice.call(d.querySelectorAll('.langsw__menu a'));
        var i = links.indexOf(document.activeElement);
        if (e.key === 'Escape' && d.open) { d.open = false; sum.focus(); e.preventDefault(); }
        else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          d.open = true;
          links[e.key === 'ArrowDown' ? (i + 1) % links.length : (i <= 0 ? links.length - 1 : i - 1)].focus();
          e.preventDefault();
        }
      });
      if (dropdown(d)) d.addEventListener('focusout', function () {
        setTimeout(function () { if (d.open && !d.contains(document.activeElement)) d.open = false; }, 0);
      });
    });
    document.addEventListener('click', function (e) {
      menus.forEach(function (d) { if (d.open && dropdown(d) && !d.contains(e.target)) d.open = false; });
    });
  }

  /* ---------- pages fetched ahead of time (the layout's speculation rules) carry the cart as it was: putting the
     rules back throws those copies away, so this runs whenever something is added to the cart ---------- */
  function initPrefetch() {
    window.addEventListener('uds:cart', function () {
      [].forEach.call(document.querySelectorAll('script[type="speculationrules"]'), function (s) {
        var n = document.createElement('script'); n.type = 'speculationrules'; n.textContent = s.textContent;
        [].forEach.call(s.attributes, function (a) { n.setAttribute(a.name, a.value); });
        s.replaceWith(n);
      });
    });
  }

  /* ---------- lazy pictures start loading only when they are about to scroll in, and on a phone that is a blank
     square for a second or more (owner, 2026-11-12). Once the page has loaded, the small ones (lists, cards, 160 and
     320 px copies of a few KB each) are fetched in the background, so they are already there when the visitor scrolls ---------- */
  function initPicsAhead() {
    var c = navigator.connection;
    if (c && (c.saveData || /2g/.test(c.effectiveType || ''))) return;
    function go() {
      var imgs = [].filter.call(document.querySelectorAll('img[loading="lazy"]'), function (i) {
        return /\/media\/(160|320)\//.test(i.getAttribute('src') || '') || (+i.getAttribute('width') || 999) <= 400;
      });
      (function next() {
        imgs.splice(0, 12).forEach(function (i) { i.loading = 'eager'; });
        if (imgs.length) setTimeout(next, 120);
      })();
    }
    var idle = function () { (window.requestIdleCallback || function (f) { setTimeout(f, 200); })(go, { timeout: 1500 }); };
    if (document.readyState === 'complete') idle(); else window.addEventListener('load', idle);
  }

  /* ---------- "About …" popovers (.cinfo): hover and focus are CSS; a tap toggles them (iOS gives buttons no focus) ---------- */
  function initInfoPops() {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('.cinfo__btn'), cur = b && b.parentNode;
      document.querySelectorAll('.cinfo.is-open').forEach(function (c) { if (c !== cur) c.classList.remove('is-open'); });
      if (cur) cur.classList.toggle('is-open');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') document.querySelectorAll('.cinfo.is-open').forEach(function (c) { c.classList.remove('is-open'); });
    });
  }

  function boot() {
    initReveal(); initHeader(); initSidebar(); initInfoPops(); initLangMenus(); initPrefetch(); initPicsAhead();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else { boot(); }
})();
