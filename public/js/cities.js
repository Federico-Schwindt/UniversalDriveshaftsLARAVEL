/* UDS city suggestions + ZIP lookup (owner, 2026-11-13: city "Sugerencias + ZIP").
   A city field marked data-city-state / data-city-country / data-city-zip (ids of its state, country and ZIP fields)
   gets, for United States addresses:
   - suggestions while typing: the chosen state's cities, A to Z, those starting with what was typed first; with no
     state yet, every state's ("Tampa, FL"), and picking one sets the state too;
   - from a 5-digit ZIP: the state, and the city when the field is empty or was filled this way.
   Any city can still be typed. Data (static, cached): /data/cities/<ST>.json, /data/cities/all/<letter>.json and
   /data/zip/<first 3 digits>.json, built from the GeoNames postal code files (CC BY 4.0, credited on /copyright).
   The list reuses geo.js's .addrsug look. */
(function () {
  var cache = {};
  function load(url) {
    if (!cache[url]) cache[url] = fetch(url).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { delete cache[url]; return null; });
    return cache[url];
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function fold(s) { return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function byId(id) { return id ? document.getElementById(id) : null; }
  function isUS(country) { var c = ((country && country.value) || 'US').toUpperCase(); return c === 'US'; }
  function stateCode(stateEl) { var v = String((stateEl && stateEl.value) || '').trim().toUpperCase(); return /^[A-Z]{2}$/.test(v) ? v : ''; }
  function setState(stateEl, st) {
    if (!stateEl || stateCode(stateEl) === st) return;
    stateEl.value = st;
    stateEl.dispatchEvent(new Event('change', { bubbles: true }));   // states.js shows it, the checkout re-prices
    if (window.udsStates) window.udsStates.sync();
  }

  function attach(input, n) {
    var stateEl = byId(input.dataset.cityState), country = byId(input.dataset.cityCountry), zip = byId(input.dataset.cityZip);
    var id = 'citysug-' + n, box = document.createElement('div'), list = document.createElement('ul');
    var items = [], active = -1, seq = 0, timer = 0, auto = input.getAttribute('autocomplete'), armed = false;
    box.className = 'addrsug'; box.hidden = true;
    list.id = id; list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', (input.labels && input.labels[0] ? input.labels[0].textContent : 'City').replace(/\*/g, '').trim());
    box.appendChild(list);
    input.parentNode.classList.add('addrsug-host');
    input.parentNode.appendChild(box);

    function arm() {   // while our list shows, the browser's own list would cover it
      if (armed) return; armed = true;
      input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-controls', id); input.setAttribute('autocomplete', 'off');
    }
    function disarm() {
      if (!armed) return; armed = false;
      ['role', 'aria-autocomplete', 'aria-controls', 'aria-expanded', 'aria-activedescendant'].forEach(function (a) { input.removeAttribute(a); });
      if (auto === null) input.removeAttribute('autocomplete'); else input.setAttribute('autocomplete', auto);
    }
    function close() {
      items = []; active = -1; box.hidden = true; list.innerHTML = '';
      input.removeAttribute('aria-activedescendant');
      if (armed) input.setAttribute('aria-expanded', 'false');
    }
    function show(found) {
      close();
      if (!found.length) return;
      arm(); items = found;
      list.innerHTML = found.map(function (c, i) {
        return '<li role="option" id="' + id + '-' + i + '" aria-selected="false"><span><b>' + esc(c.name) + '</b>' + (c.st ? ' <small>' + esc(c.st) + '</small>' : '') + '</span></li>';
      }).join('');
      box.style.top = (input.offsetTop + input.offsetHeight) + 'px';
      box.style.left = input.offsetLeft + 'px'; box.style.width = input.offsetWidth + 'px';
      box.hidden = false; input.setAttribute('aria-expanded', 'true');
    }
    function move(i) {
      active = (i + items.length) % items.length;
      Array.prototype.forEach.call(list.children, function (li, k) { li.setAttribute('aria-selected', k === active ? 'true' : 'false'); });
      input.setAttribute('aria-activedescendant', id + '-' + active);
    }
    function pick(i) {
      var c = items[i]; if (!c) return;
      seq++; close();
      input.value = c.name; delete input.dataset.cityAuto;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      if (c.st) setState(stateEl, c.st);
    }
    function search() {
      var q = fold(input.value.trim()), mine = ++seq;
      if (q.length < 2 || !isUS(country)) return close();
      var st = stateCode(stateEl);
      var url = st ? '/data/cities/' + st + '.json' : '/data/cities/all/' + input.value.trim().charAt(0).toUpperCase() + '.json';
      load(url).then(function (data) {
        if (mine !== seq || document.activeElement !== input || !data) return;
        var starts = [], contains = [];
        data.forEach(function (row) {
          var name = st ? row : row[0], f = fold(name), at = f.indexOf(q);
          if (at < 0) return;
          (at === 0 ? starts : contains).push({ name: name, st: st ? '' : row[1] });
        });
        show(starts.concat(contains).slice(0, 8));   // the files are A to Z already
      });
    }

    input.addEventListener('input', function () { delete input.dataset.cityAuto; clearTimeout(timer); timer = setTimeout(search, 120); });
    input.addEventListener('blur', function () { clearTimeout(timer); seq++; setTimeout(function () { close(); disarm(); }, 150); });
    input.addEventListener('keydown', function (e) {
      if (box.hidden || e.isComposing) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); move(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(active < 0 ? -1 : active - 1); }
      else if (e.key === 'Enter' && active > -1) { e.preventDefault(); pick(active); }
      else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); seq++; close(); }
    });
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });   // the field keeps the focus while a line is clicked
    list.addEventListener('click', function (e) {
      var li = e.target.closest('li'); if (li) pick(Array.prototype.indexOf.call(list.children, li));
    });

    // the ZIP decides the state, and fills the city when the customer has not typed one
    var lastZip = '';
    function fromZip() {
      var z = String((zip && zip.value) || '').trim().slice(0, 5);
      if (!/^\d{5}$/.test(z) || z === lastZip || !isUS(country)) return;
      lastZip = z;
      load('/data/zip/' + z.slice(0, 3) + '.json').then(function (map) {
        var hit = map && map[z];
        if (!hit || String(zip.value).trim().slice(0, 5) !== z) return;
        if (!input.value.trim() || input.dataset.cityAuto === input.value) {
          if (input.value !== hit[0]) { input.value = hit[0]; input.dispatchEvent(new Event('change', { bubbles: true })); }
          input.dataset.cityAuto = hit[0];
        }
        setState(stateEl, hit[1]);
      });
    }
    if (zip) {
      zip.addEventListener('input', fromZip);
      zip.addEventListener('change', fromZip);   // the IP prefill and address suggestions fire change
    }
    if (country) country.addEventListener('change', function () { lastZip = ''; close(); });
  }

  document.querySelectorAll('input[data-city-state]').forEach(attach);
})();
