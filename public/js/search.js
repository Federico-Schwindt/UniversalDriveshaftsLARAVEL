/* Universal Driveshafts — catalog search (grouped by category, keyboard driven): a panel under the header field on
   desktop, a white sheet on phones. */
(function () {
  var DSICON = '<svg viewBox="0 0 64 40" width="34" height="34" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linejoin="round"><circle cx="12" cy="20" r="7"/><circle cx="52" cy="20" r="7"/><rect x="19" y="16.5" width="26" height="7" rx="2"/></svg>';
  var ARROW = '<svg class="i" viewBox="0 0 256 256" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"/></svg>';
  var CART = '<svg class="i" viewBox="0 0 256 256" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="M104,216a16,16,0,1,1-16-16A16,16,0,0,1,104,216Zm88-16a16,16,0,1,0,16,16A16,16,0,0,0,192,200ZM239.71,74.14l-25.64,92.28A24.06,24.06,0,0,1,191,184H92.16A24.06,24.06,0,0,1,69,166.42L33.92,40H16a8,8,0,0,1,0-16H40a8,8,0,0,1,7.71,5.86L57.19,64H232a8,8,0,0,1,7.71,10.14ZM221.47,80H61.64l22.81,82.14A8,8,0,0,0,92.16,168H191a8,8,0,0,0,7.71-5.86Z"/></svg>';
  var T = window.udsT || function (x, r) { if (r) for (var k in r) x = x.split(':' + k).join(r[k]); return x; };
  var SUGGEST = ['1310', '1350', '1410', 'flange yoke', 'slip yoke', 'center support bearing', 'weld yoke', 'tubing'];
  function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function (c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]; }); }
  function csrf(){ var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function money(n){ return (n == null || !(Number(n) > 0)) ? T('Call for price') : '$' + Number(n).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  function mark(text, q){ // highlight the query inside a result name: match on the raw text, escape each piece after
    var t = String(text == null ? '' : text); if (!q) return esc(t);
    try {
      return t.split(new RegExp('(' + String(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'i'))
        .map(function(part, i){ return i % 2 ? '<mark>' + esc(part) + '</mark>' : esc(part); }).join('');
    } catch (err) { return esc(t); }
  }
  function recent(){ try { return JSON.parse(localStorage.getItem('uds.recent') || '[]'); } catch (e) { return []; } }
  function remember(q){ try { var r = recent().filter(function(x){ return x.toLowerCase() !== q.toLowerCase(); }); r.unshift(q); localStorage.setItem('uds.recent', JSON.stringify(r.slice(0, 6))); } catch (e) {} }
  function forget(){ try { localStorage.removeItem('uds.recent'); } catch (e) {} }

  // ---- build the overlay once ----
  var ov = document.createElement('div');
  ov.className = 'uds-search'; ov.setAttribute('aria-hidden', 'true');
  ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-labelledby', 'us-title');
  ov.innerHTML =
    '<h2 id="us-title" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap">' + T('Search parts') + '</h2>' +
    '<div class="us-top"><div class="us-topin">' +
      '<div class="us-inputwrap"><span class="us-mag"><svg class="i" viewBox="0 0 256 256" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="M229.66,218.34l-50.07-50.06a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z"/></svg></span>' +
        '<input type="search" class="us-input" placeholder="' + T('Part number, name or series — 5-1310X, flange yoke, U2594-173') + '" aria-label="' + T('Search parts') + '" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="us-results" aria-autocomplete="list">' +
        '<button class="us-clear" type="button" hidden aria-label="' + T('Clear search') + '"><svg class="i" viewBox="0 0 256 256" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button>' +
        '<kbd class="us-kbd" aria-hidden="true">Esc</kbd>' +
        '<button class="us-go" type="button">' + T('Search') + '</button>' +
      '</div>' +
      '<button class="us-close" type="button" aria-label="' + T('Close search') + '">' + T('Cancel') + '</button>' +
    '</div></div>' +
    '<div class="us-body"><div class="us-inner">' +
      '<div class="us-hint"></div>' +
      '<div class="us-results" id="us-results" role="listbox"></div>' +
      '<div class="us-foot" hidden></div>' +
    '</div></div>';
  document.body.appendChild(ov);

  var input = ov.querySelector('.us-input');
  var results = ov.querySelector('.us-results');
  var hint = ov.querySelector('.us-hint');
  var foot = ov.querySelector('.us-foot');
  var clearBtn = ov.querySelector('.us-clear');
  var lastQ = '', t, items = [], active = -1, seq = 0, opener = null;

  // desktop: the panel hangs from the header field, which keeps its place and look; phones (no header field): a sheet
  var head = document.querySelector('.tb__search');
  function place(){
    var r = head && head.offsetParent !== null ? head.getBoundingClientRect() : null;
    ov.classList.toggle('is-drop', !!(r && r.width));
    if (!r || !r.width) return;
    ov.style.setProperty('--us-x', r.left + 'px'); ov.style.setProperty('--us-y', Math.max(12, r.top) + 'px');
    ov.style.setProperty('--us-w', r.width + 'px'); ov.style.setProperty('--us-h', r.height + 'px');
    var hi = head.querySelector('input'); if (hi && hi.placeholder) input.placeholder = hi.placeholder;
  }
  window.addEventListener('resize', function(){ if (ov.classList.contains('on')) place(); });

  function open(prefill){
    opener = document.activeElement;
    place();
    var dr=document.getElementById('mob-drawer'); if(dr){dr.classList.remove('on');dr.setAttribute('aria-hidden','true');document.documentElement.classList.remove('md-lock');var bb=document.getElementById('mob-burger');if(bb)bb.setAttribute('aria-expanded','false');}
    ov.classList.add('on'); ov.setAttribute('aria-hidden', 'false');
    document.documentElement.style.overflow = 'hidden';
    if (prefill != null) input.value = prefill;
    setTimeout(function(){ input.focus(); run(true); }, 40);
  }
  function close(){
    ov.classList.remove('on'); ov.setAttribute('aria-hidden', 'true');
    document.documentElement.style.overflow = '';
    // focus goes back to what opened the overlay (header boxes blur themselves on focus — skip those)
    if (opener && opener.focus && !(opener.closest && opener.closest('form.search'))) { try { opener.focus(); } catch (e) {} }
    opener = null;
  }
  function goPage(q){ if (q) remember(q); location.href = '/search?q=' + encodeURIComponent(q); }

  function idle(){
    items = []; active = -1; results.innerHTML = ''; foot.hidden = true; hint.hidden = false;
    var r = recent(), html = '';
    if (r.length) html += '<div class="us-chips"><span class="us-chips__l">' + T('Recent') + '</span>' + r.map(function(q){ return '<button type="button" class="us-chip" data-q="' + esc(q) + '">' + esc(q) + '</button>'; }).join('') + '<button type="button" class="us-chip us-chip--x" data-forget>' + T('Clear') + '</button></div>';
    html += '<div class="us-chips"><span class="us-chips__l">' + T('Popular') + '</span>' + SUGGEST.map(function(q){ return '<button type="button" class="us-chip" data-q="' + esc(q) + '">' + esc(q) + '</button>'; }).join('') + '</div>';
    html += '<p class="us-tip">' + T('Type a part number, a series like <b>1310</b> or a name. Press <kbd>Enter</kbd> for the full results page, <kbd>↑</kbd><kbd>↓</kbd> to move, <kbd>Esc</kbd> to close.') + '</p>';
    hint.innerHTML = html;
  }
  // answers already seen are shown again at once (backspace, retyping); a newer keystroke cancels the request before it;
  // "Searching…" appears only when an answer is slow, so a fast one never flickers
  var memo = {}, memoN = 0, ctl = null;
  function run(now){
    var q = input.value.trim();
    clearBtn.hidden = !q;
    if (q.length < 2){ lastQ = ''; idle(); return; }
    clearTimeout(t);
    t = setTimeout(function(){
      if (q === lastQ) return; lastQ = q;
      var my = ++seq;
      if (ctl) { try { ctl.abort(); } catch (e) {} }
      if (memo[q]) { render(memo[q], q); return; }
      ctl = window.AbortController ? new AbortController() : null;
      var busy = setTimeout(function(){ if (my === seq){ hint.hidden = false; hint.innerHTML = '<span class="us-busy">' + T('Searching…') + '</span>'; } }, 180);
      fetch('/api/search?q=' + encodeURIComponent(q), {headers:{'Accept':'application/json'}, signal: ctl ? ctl.signal : undefined}).then(function(r){ return r.json(); })
        .then(function(d){ clearTimeout(busy); if (memoN++ > 60){ memo = {}; memoN = 0; } memo[q] = d; if (my === seq) render(d, q); })
        .catch(function(e){ clearTimeout(busy); if (e && e.name === 'AbortError') return; if (my === seq){ hint.hidden = false; hint.innerHTML = T('Search failed — try again.'); } });
    }, now ? 0 : 110);
  }
  // the full results page is fetched while the shopper reads the list, so Enter or "See all" opens it at once
  var specT = null, specEl = null;
  function prefetchPage(q){
    clearTimeout(specT);
    if (!(window.HTMLScriptElement && HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules'))) return;
    specT = setTimeout(function(){
      if (q !== input.value.trim()) return;
      if (specEl) specEl.remove();
      specEl = document.createElement('script'); specEl.type = 'speculationrules';
      specEl.textContent = JSON.stringify({prefetch: [{source: 'list', urls: ['/search?q=' + encodeURIComponent(q)]}]});
      document.head.appendChild(specEl);
    }, 500);
  }
  function render(d, q){
    var groups = d.groups || []; items = []; active = -1;
    if (!groups.length){
      results.innerHTML = ''; foot.hidden = true; hint.hidden = false;
      hint.innerHTML = '<div class="us-none">' + T('<b>No parts match “:q”.</b> Check the number against the one stamped on the part, try the series only, or <a href="/catalog/driveshafts">find a complete driveshaft by vehicle</a>.', {q: esc(q)}) + '</div>';
      return;
    }
    hint.hidden = true;
    // the query was not taken as typed (a typo fixed, a word no part holds left out): say how it was read
    var rd = d.read, read = rd ? '<p class="us-read">' + (rd.fixed ? T('Showing results for :q', {q: '<b>“' + esc(rd.fixed) + '”</b>'}) : '') +
      (rd.dropped && rd.dropped.length ? (rd.fixed ? ' · ' : '') + esc(T('Left out, no part mentions it: :words', {words: rd.dropped.join(', ')})) : '') + '</p>' : '';
    results.innerHTML = read + groups.map(function(g){
      return '<section class="us-group"><header class="us-ghead"><h3>' + esc(g.label) + '</h3><a href="' + esc(g.url) + '">' + (g.total > g.items.length ? T('All :n', {n: g.total}) : (g.key === 'xref' || g.key === 'exact' ? T('See all results') : T('Open category'))) + ' ' + ARROW + '</a></header>' +
        g.items.map(function(p){
          var idx = items.push(p) - 1;
          var img = p.image ? '<img src="' + esc(p.image) + '" alt="" loading="lazy">' : '<span class="us-ic">' + DSICON + '</span>';
          return '<a class="us-card" role="option" data-i="' + idx + '" href="' + esc(p.url) + '">' +
            '<span class="us-cimg">' + img + '</span>' +
            '<span class="us-cbody"><b class="us-cname">' + mark(p.name, q) + '</b><span class="us-csku">' + mark(p.sku, q) + '</span>' + (p.xref ? '<span class="us-xref">' + T('Replaces') + ' <b>' + mark(p.xref, q) + '</b></span>' : '') + '</span>' +
            '<span class="us-cprice' + (p.price == null ? ' is-call' : '') + '">' + money(p.price) + '</span>' +
            (p.add && Number(p.price) > 0 ? '<button class="us-cadd" type="button" data-add-sku="' + esc(p.add) + '" aria-label="' + T('Add :sku to cart', {sku: esc(p.sku)}) + '" title="' + T('Add to cart') + '">' + CART + '<span>' + T('Add') + '</span></button>' : '<span class="us-cgo" aria-hidden="true">' + ARROW + '</span>') +
          '</a>';
        }).join('') + '</section>';
    }).join('');
    prefetchPage(q);
    foot.hidden = false;
    foot.innerHTML = '<a class="us-all" href="/search?q=' + encodeURIComponent(q) + '">' + T((d.total || items.length) === 1 ? 'See :n result for “:q”' : 'See all :n results for “:q”', {n: (d.total || items.length), q: esc(q)}) + ARROW + '</a>';
    input.setAttribute('aria-expanded', 'true');
    results.querySelectorAll('.us-cadd').forEach(function(b){
      b.onclick = function(e){
        e.preventDefault(); e.stopPropagation();
        var sku = b.getAttribute('data-add-sku'); if (!sku) return;
        b.classList.add('is-busy');
        fetch('/quick-order/add', {method:'POST', headers:{'X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({lines: JSON.stringify([{sku:sku, qty:1}])})})
          .then(function(r){
            if (r.status === 419) throw new Error(T('Your session expired — reload the page.'));
            return r.json().catch(function(){ return {}; });
          }).then(function(d){
            b.classList.remove('is-busy');
            // success only when the server really added the part (a $0 / unknown SKU adds nothing)
            if (!(d.added > 0)) throw new Error(d.error || T('Something went wrong. Try again.'));
            b.classList.add('is-done');
            document.querySelectorAll('[data-cart-count]').forEach(function(c){ if (d.count){ c.textContent = d.count; c.hidden = false; } });
            setTimeout(function(){ b.classList.remove('is-done'); }, 1200);
          }).catch(function(err){
            b.classList.remove('is-busy'); b.classList.add('is-err'); b.title = err.message; b.setAttribute('aria-label', err.message);
            hint.hidden = false; hint.innerHTML = '<div class="us-none" role="alert">' + esc(err.message) + '</div>';
            setTimeout(function(){ b.classList.remove('is-err'); }, 2400);
          });
      };
    });
  }
  function setActive(i){
    var cards = results.querySelectorAll('.us-card'); if (!cards.length) return;
    active = (i + cards.length) % cards.length;
    cards.forEach(function(c, k){ c.classList.toggle('is-active', k === active); });
    cards[active].scrollIntoView({block:'nearest'});
  }

  input.addEventListener('input', function(){ run(false); });
  clearBtn.onclick = function(){ input.value = ''; lastQ=''; run(true); input.focus(); };
  ov.querySelector('.us-close').onclick = close;
  ov.querySelector('.us-go').onclick = function(){ var q = input.value.trim(); if (q.length >= 2) goPage(q); else input.focus(); };
  ov.addEventListener('click', function(e){ if (e.target === ov || e.target.classList.contains('us-body')) close(); });
  hint.addEventListener('click', function(e){
    var c = e.target.closest('[data-q]'); if (c){ input.value = c.getAttribute('data-q'); run(true); input.focus(); return; }
    if (e.target.closest('[data-forget]')){ forget(); idle(); }
  });
  results.addEventListener('mousemove', function(e){ var c = e.target.closest('.us-card'); if (c) setActive(+c.getAttribute('data-i')); });
  results.addEventListener('click', function(e){ var c = e.target.closest('.us-card'); if (c && !e.target.closest('.us-cadd')) remember(input.value.trim()); });
  document.addEventListener('keydown', function(e){
    if (!ov.classList.contains('on')) return;
    if (e.key === 'Escape'){ close(); return; }
    if (e.key === 'Tab'){ // keep focus inside the dialog
      var f = Array.prototype.filter.call(ov.querySelectorAll('a[href],button:not([disabled]),input,[tabindex]:not([tabindex="-1"])'), function(el){ return el.offsetParent !== null && !el.hidden; });
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      else if (!ov.contains(document.activeElement)) { e.preventDefault(); f[0].focus(); }
      return;
    }
    if (e.key === 'ArrowDown'){ e.preventDefault(); setActive(active + 1); return; }
    if (e.key === 'ArrowUp'){ e.preventDefault(); setActive(active - 1); return; }
    if (e.key === 'Enter'){
      e.preventDefault();
      var q = input.value.trim();
      if (active >= 0 && items[active]){ remember(q); location.href = items[active].url; return; }
      if (q.length >= 2) goPage(q);
    }
  });

  // ---- public API: any [data-open-search] control opens the overlay ----
  window.UDSSearch = { open: open, close: close };
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open-search]');
    if (t) { e.preventDefault(); open(''); }
  });
  // header / drawer search boxes open the overlay; a submit with text goes straight to the results page
  document.querySelectorAll('form.search').forEach(function(form){
    var i = form.querySelector('input');
    form.addEventListener('submit', function(e){ e.preventDefault(); var v = (i || {}).value || ''; if (v.trim().length >= 2) goPage(v.trim()); else open(v); });
    if (i){ i.addEventListener('focus', function(){ open(i.value); i.blur(); }); }
  });
  // global keyboard shortcut: "/" opens search
  document.addEventListener('keydown', function(e){
    if (e.key === '/' && !/(input|textarea|select)/i.test((e.target.tagName||'')) && !ov.classList.contains('on')){ e.preventDefault(); open(''); }
  });
})();
