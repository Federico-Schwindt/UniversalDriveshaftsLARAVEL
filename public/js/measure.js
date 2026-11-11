/* How-to-measure drawings (App\Support\Measure).
   Inside a [data-md-scope], anything with data-md="pa_…" lights its dimension in the drawing, and pointing at a
   callout lights the matching row (category legend, product spec list). Table headers open the category drawing
   in a popover showing just that dimension: hover with a mouse, the letter button for touch and keyboard.
   The table is re-rendered by AJAX, so everything is delegated from document. */
(function () {
  'use strict';

  function light(scope, key) {
    scope.querySelectorAll('.md-svg').forEach(function (svg) {
      svg.classList.toggle('md-focus', !!key);
      svg.querySelectorAll('.md-dim').forEach(function (g) { g.classList.toggle('is-on', !!key && g.getAttribute('data-dim') === key); });
    });
    scope.querySelectorAll('[data-md]').forEach(function (el) { el.classList.toggle('is-md', !!key && el.getAttribute('data-md') === key); });
  }

  var lit = null, litKey = null;
  function track(t) {
    var scope = t && t.closest ? t.closest('[data-md-scope]') : null;
    var el = scope ? t.closest('[data-md], .md-dim') : null;
    var key = el ? (el.getAttribute('data-md') || el.getAttribute('data-dim')) : null;
    if (lit && lit !== scope) light(lit, null);
    if (scope && (scope !== lit || key !== litKey)) light(scope, key);
    lit = scope; litKey = key;
  }
  document.addEventListener('mouseover', function (e) { track(e.target); });
  document.addEventListener('focusin', function (e) { track(e.target); });

  /* product page: the drawing shows only the letters; the caption corner reads out the dimension under the pointer
     with this part's value ("E · CJ to flat · 2.205"). The spec list stays still (owner, 2026-11) */
  document.querySelectorAll('.pdp-md').forEach(function (fig) {
    var scope = fig.closest('[data-md-scope]'), svg = fig.querySelector('.md-svg'), read = fig.querySelector('.pdp-md__read');
    if (!scope || !svg) return;
    var info = {}, T = window.udsT || String;
    function yn(v) { return v === 'Y' ? T('Yes') : v === 'N' ? T('No') : v; }
    scope.querySelectorAll('[data-md]').forEach(function (row) {
      var dt = row.querySelector('dt'), dd = row.querySelector('dd'), chip = row.querySelector('.md-chip');
      if (!dt || !dd) return;
      info[row.getAttribute('data-md')] = {
        letter: chip ? chip.textContent.trim() : '',
        label: dt.textContent.replace(chip ? chip.textContent : '', '').trim(),
        value: yn(dd.textContent.trim())
      };
    });
    if (!read) return;
    var idle = read.textContent;
    function say(key) {
      var d = key && info[key];
      read.textContent = d ? [d.letter, d.label, d.value].filter(Boolean).join('  ·  ') : idle;
      fig.classList.toggle('is-reading', !!d);
    }
    scope.addEventListener('mouseover', function (e) { var el = e.target.closest('[data-md], .md-dim'); say(el ? (el.getAttribute('data-md') || el.getAttribute('data-dim')) : null); });
    scope.addEventListener('mouseleave', function () { say(null); });
    scope.addEventListener('focusin', function (e) { var el = e.target.closest('[data-md], .md-dim'); say(el ? (el.getAttribute('data-md') || el.getAttribute('data-dim')) : null); });
  });

  var pop = document.getElementById('md-pop');
  if (!pop) return;
  var psvg = pop.querySelector('.md-svg');
  var chip = pop.querySelector('.md-chip');
  var title = pop.querySelector('.md-pop__t span:last-child');
  var open = null, pinned = false;

  function expanded(th, v) { var b = th.querySelector('.th-md'); if (b) b.setAttribute('aria-expanded', v ? 'true' : 'false'); }

  function show(th) {
    var key = th.getAttribute('data-md');
    var btn = th.querySelector('.th-md');
    chip.textContent = btn ? btn.textContent : '';
    title.textContent = (th.querySelector('.th-sort') || th).textContent.trim();
    psvg.classList.add('md-focus');
    psvg.querySelectorAll('.md-dim').forEach(function (g) { g.classList.toggle('is-on', g.getAttribute('data-dim') === key); });
    // under the header cell, kept on screen; above it when there is no room below
    var r = th.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
    var x = Math.min(Math.max(12, r.left + r.width / 2 - w / 2), window.innerWidth - w - 12);
    var y = r.bottom + 8;
    if (y + h > window.innerHeight - 8 && r.top - h - 8 > 8) y = r.top - h - 8;
    pop.style.left = Math.round(x) + 'px';
    pop.style.top = Math.round(y) + 'px';
    if (open && open !== th) expanded(open, false);
    open = th;
    expanded(th, true);
    pop.classList.add('is-open');
    pop.setAttribute('aria-hidden', 'false');
  }

  function hide() {
    if (!open) return;
    expanded(open, false);
    open = null;
    pinned = false;
    pop.classList.remove('is-open');
    pop.setAttribute('aria-hidden', 'true');
  }

  document.addEventListener('mouseover', function (e) {
    if (pinned) return;
    var th = e.target.closest ? e.target.closest('th[data-md]') : null;
    if (th) { if (th !== open) show(th); } else hide();
  });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.th-md') : null;
    if (!btn) { if (pinned) hide(); return; }
    var th = btn.closest('th[data-md]');
    if (open === th && pinned) { hide(); return; }
    show(th);
    pinned = true;
  });
  document.addEventListener('focusin', function (e) {
    if (e.target.classList && e.target.classList.contains('th-md')) show(e.target.closest('th[data-md]'));
    else if (!pinned) hide();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
  window.addEventListener('scroll', hide, { passive: true, capture: true });
  window.addEventListener('resize', hide);
})();
