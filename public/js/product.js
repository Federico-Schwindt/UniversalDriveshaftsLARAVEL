/* Universal Driveshafts — product detail page */
(function () {
  'use strict';
  var root = document.querySelector('[data-pdp]');
  if (!root) return;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var T = window.udsT || function (x) { return x; };
  // a visible, announced line under the buy box for failures (419 / 429 / call-for-price) instead of a silent reset
  function say(msg) {
    var box = add && add.closest('.pdp__cart'); if (!box) return;
    var m = box.querySelector('.pdp__msg');
    if (!m) { m = document.createElement('p'); m.className = 'pdp__msg'; m.setAttribute('role', 'alert'); m.style.cssText = 'margin:.5rem 0 0;color:var(--danger,#b42318);font-size:.9rem;flex-basis:100%'; box.appendChild(m); }
    m.textContent = msg || ''; m.hidden = !msg;
  }

  /* ---- gallery ---- */
  var stage = root.querySelector('.pdp__stage');
  var main = document.getElementById('pdp-img');
  root.querySelectorAll('.pdp__thumbs button').forEach(function (b) {
    b.addEventListener('click', function () {
      main.src = b.dataset.full;
      root.querySelectorAll('.pdp__thumbs button').forEach(function (o) { o.setAttribute('aria-current', 'false'); });
      b.setAttribute('aria-current', 'true');
    });
  });
  if (stage && main && window.matchMedia('(hover: hover)').matches) {
    stage.addEventListener('mousemove', function (e) {
      var r = stage.getBoundingClientRect();
      main.style.transformOrigin =
        Math.min(100, Math.max(0, (e.clientX - r.left) / r.width * 100)) + '% ' +
        Math.min(100, Math.max(0, (e.clientY - r.top) / r.height * 100)) + '%';
      main.style.transform = 'scale(2.3)';
      stage.classList.add('is-zoom');
    });
    stage.addEventListener('mouseleave', function () {
      main.style.transform = ''; stage.classList.remove('is-zoom');
    });
  }

  /* ---- quantity ---- */
  var qty = document.getElementById('pdp-qty');
  root.querySelectorAll('[data-qty]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = parseInt(qty.value, 10) || 1;
      qty.value = Math.max(1, v + (b.dataset.qty === 'up' ? 1 : -1));
    });
  });

  /* ---- add to cart: one handler shared by the main button and the sticky
     mirror, so BOTH show their own Adding/Added state. Before, the sticky
     button forwarded its click to the (now off-screen) main button — the main
     button silently went through Adding/Added while the sticky one the
     customer actually looked at never changed, which read as "did nothing". */
  var add = document.getElementById('pdp-add');
  var stickyAdd = document.getElementById('pdp-sticky-add');
  var buttons = [add, stickyAdd].filter(Boolean);
  var busy = false;

  var originalLabel = add ? add.querySelector('span').textContent : 'Add to cart';

  function wireAddButton(btn) {
    btn.addEventListener('click', function () {
      if (busy) return;
      busy = true;
      buttons.forEach(function (b) {
        b.disabled = true; b.classList.add('is-busy');
        var s = b.querySelector('span'); if (s) s.textContent = T('Adding…');
      });

      fetch('/shop/catalog/add', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ id: btn.dataset.add, qty: (qty && qty.value) || 1 })
      }).then(function (r) {
        if (r.status === 419) return { success: false, error: T('Session expired — reload the page and try again.') };
        if (r.status === 429) return { success: false, error: T('Too many tries — wait a minute and try again.') };
        return r.json().catch(function () { return { success: false }; });
      }).then(function (d) {
        busy = false; say(d.success ? '' : (d.error || T('Could not add to cart. Please try again.')));
        buttons.forEach(function (b) { b.disabled = false; b.classList.remove('is-busy'); });
        if (d.success) {
          buttons.forEach(function (b) { b.classList.add('is-done'); var s = b.querySelector('span'); if (s) s.textContent = T('Added ✓'); });
          var badge = document.querySelector('[data-cart-count]');
          if (badge) { badge.textContent = d.count; badge.hidden = false; badge.classList.remove('bump'); void badge.offsetWidth; badge.classList.add('bump'); }
          setTimeout(function () {
            buttons.forEach(function (b) { b.classList.remove('is-done'); var s = b.querySelector('span'); if (s) s.textContent = originalLabel; });
          }, 1600);
        } else {
          buttons.forEach(function (b) { var s = b.querySelector('span'); if (s) s.textContent = originalLabel; });
        }
      }).catch(function () {
        busy = false; say(T('Network error. Please try again.'));
        buttons.forEach(function (b) { b.disabled = false; b.classList.remove('is-busy'); var s = b.querySelector('span'); if (s) s.textContent = originalLabel; });
      });
    });
  }
  buttons.forEach(wireAddButton);

  /* ---- wishlist toggle: there was no way to add anything to the wishlist
     anywhere on the site before this button existed. ---- */
  var wish = document.getElementById('pdp-wish');
  if (wish) {
    wish.addEventListener('click', function () {
      if (wish.classList.contains('is-busy')) return;
      var on = wish.classList.contains('is-on');
      wish.classList.add('is-busy');
      fetch(on ? '/wishlist/remove' : '/wishlist/add', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ product_id: wish.dataset.wish })
      }).then(function (r) { return r.ok ? r.json() : { success: false }; }).then(function (d) {
        wish.classList.remove('is-busy');
        if (!d.success) return;
        wish.classList.toggle('is-on', !on);
        wish.setAttribute('aria-pressed', (!on) ? 'true' : 'false');
        wish.setAttribute('aria-label', (!on) ? T('Remove from wishlist') : T('Add to wishlist'));
        wish.title = wish.getAttribute('aria-label');
        var b = document.querySelector('[data-wish-count]');
        if (b) { b.textContent = d.count; b.hidden = d.count === 0; }
      }).catch(function () { wish.classList.remove('is-busy'); });
    });
  }

  /* ---- sticky buy bar: shown whenever the main buy box is off screen. On phones that includes the first screen,
     where the photo pushes the price and button below the fold; on desktop only once it has scrolled past. ---- */
  var sticky = document.getElementById('pdp-sticky');
  if (sticky && add) {
    var buyBox = add.closest('.pdp__cart');
    var phone = window.matchMedia ? window.matchMedia('(max-width: 760px)') : { matches: false };
    if (buyBox && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        var e = entries[0];
        var show = !e.isIntersecting && (e.boundingClientRect.top < 0 || phone.matches);
        sticky.classList.toggle('is-on', show);
        sticky.setAttribute('aria-hidden', show ? 'false' : 'true');
      }, { threshold: 0 }).observe(buyBox);
    }
  }

  /* ---- interchange / alternates ---- */
  var alts = document.getElementById('pdp-alts');
  if (alts && alts.dataset.id) {
    fetch('/shop/alternates/' + alts.dataset.id)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var list = (d && d.alternates) || [];
        if (!list.length) return;
        alts.querySelector('.ualts__grid').innerHTML = list.slice(0, 8).map(function (a) {
          return '<a class="ualt" href="/parts/' + encodeURIComponent(a.sku) + '">' +
            '<span class="ualt__sku">' + esc(a.sku) + '</span>' +
            '<span class="ualt__name">' + esc(a.name || '') + '</span>' +
            '<span class="ualt__price">' + (Number(a.price) > 0 ? '$' + Number(a.price).toFixed(2) : esc(T('Call for price'))) + '</span></a>';
        }).join('');
        alts.hidden = false;
      }).catch(function () {});
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }
})();
