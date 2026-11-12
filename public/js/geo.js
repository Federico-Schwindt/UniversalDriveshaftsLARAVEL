/* UDS geo + address autocomplete
   1) Silent IP prefill: fills empty [data-geo="city|state|zip|country"] fields from /geo (not inside [data-geo-noip]).
   2) Address suggestions under [data-gplaces] street inputs -> a picked one fills the address group
      (inputs sharing the same data-addr-group, keyed by data-geo + the street field itself).
      The key comes from this script's own tag: <script src="/js/geo.js" data-gmaps-key="…">. */
(function () {
  var KEY = (document.currentScript && document.currentScript.getAttribute('data-gmaps-key')) || '';

  // ---- 1) IP-based silent prefill ----
  fetch('/geo').then(function (r) { return r.json(); }).then(function (g) {
    document.querySelectorAll('[data-geo]').forEach(function (el) {
      var v = g[el.dataset.geo] || '';
      if (!v || el.value || el.closest('[data-geo-noip]')) return;   // address-book forms: a saved address is not where the visitor is
      if (el.tagName === 'SELECT') {
        var has = Array.from(el.options).some(function (o) { return o.value === v; });
        if (!has) return;
      }
      el.value = v;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    document.dispatchEvent(new CustomEvent('uds:geo', { detail: g }));
  }).catch(function () {});

  // ---- 2) Address suggestions (Google Places) ----
  // The list is ours: Google's input widget (places.Autocomplete) is closed to new customers since March 2025, and on
  // a key it refuses it drew "This page can't load Google Maps correctly" over the checkout's street field. Predictions
  // come from the Autocomplete Data API; a project that only has the older Places API answers through the older
  // service. When Google refuses the key altogether the field stays a plain text field and nothing is shown.
  // Maps is fetched the first time a street field is focused, so the checkout does not wait for it.
  var TYPES = ['street_address', 'premise', 'subpremise', 'route'];   // addresses only, as the widget's types: ['address'] did
  var LOGO = 'https://maps.gstatic.com/mapfiles/api-3/images/powered-by-google-on-white3_hdpi.png';
  var lib = null, loading = null, dead = false, typed = true, legacy = false, svc = null, det = null, fields = [];

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  // Google said no (key, billing, network): every street field goes back to a plain text field
  function stop() { dead = true; fields.forEach(function (f) { f.off(); }); }

  function places() {
    if (loading) return loading;
    loading = new Promise(function (ok, no) {
      if (window.google && google.maps && google.maps.importLibrary) return ok();
      var was = window.gm_authFailure;   // Google calls this when it rejects the key
      window.gm_authFailure = function () { stop(); if (typeof was === 'function') was(); };
      window.__udsMaps = ok;
      var s = document.createElement('script');
      s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(KEY) + '&loading=async&libraries=places&callback=__udsMaps';
      s.async = true; s.onerror = no;
      document.head.appendChild(s);
    }).then(function () { return google.maps.importLibrary('places'); }).then(function (l) { lib = l; });
    loading.catch(stop);
    return loading;
  }

  // What Google suggests for the typed text: [{main, sub, pick}], pick() answering the address parts [{long, short, types}]
  function fromData(q, token, filter) {
    var req = { input: q, sessionToken: token };
    if (filter) req.includedPrimaryTypes = TYPES;
    return lib.AutocompleteSuggestion.fetchAutocompleteSuggestions(req).then(function (r) {
      return ((r && r.suggestions) || []).filter(function (s) { return s.placePrediction; }).map(function (s) {
        var p = s.placePrediction;
        return { main: (p.mainText || p.text || {}).text || '', sub: p.secondaryText ? p.secondaryText.text : '', pick: function () {
          var place = p.toPlace();   // carries the session: fetching its fields closes it
          return place.fetchFields({ fields: ['addressComponents'] }).then(function () {
            return (place.addressComponents || []).map(function (c) { return { long: c.longText, short: c.shortText, types: c.types || [] }; });
          });
        } };
      });
    });
  }
  function fromService(q, token) {
    return new Promise(function (ok, no) {
      svc = svc || new lib.AutocompleteService();
      svc.getPlacePredictions({ input: q, sessionToken: token, types: ['address'] }, function (list, status) {
        if (status !== 'OK' && status !== 'ZERO_RESULTS') return no(new Error(status));
        ok((list || []).map(function (p) {
          var f = p.structured_formatting || {};
          return { main: f.main_text || p.description || '', sub: f.secondary_text || '', pick: function () {
            return new Promise(function (done, fail) {
              det = det || new lib.PlacesService(document.createElement('div'));
              det.getDetails({ placeId: p.place_id, fields: ['address_components'], sessionToken: token }, function (place, st) {
                if (st !== 'OK' || !place) return fail(new Error(st));
                done((place.address_components || []).map(function (c) { return { long: c.long_name, short: c.short_name, types: c.types || [] }; }));
              });
            });
          } };
        }));
      });
    });
  }
  function suggest(q, token) {
    if (legacy) return fromService(q, token);
    return fromData(q, token, typed).catch(function (e) {
      if (!typed) throw e;
      // the type filter may be what was refused: ask once without it, and keep asking that way if that works
      return fromData(q, token, false).then(function (found) { typed = false; return found; });
    }).catch(function (e) {
      if (!lib.AutocompleteService) throw e;
      return fromService(q, token).then(function (found) { legacy = true; return found; });
    });
  }

  // A picked place REPLACES the address, so every field in its group is set from it — including clearing a field the
  // place has no value for (a broad landmark with no postal_code, say). Leaving an old value in place used to strand
  // e.g. a US zip code under a newly-picked Argentina address, silently pricing shipping as domestic.
  function fill(input, comps) {
    var parts = {};
    comps.forEach(function (c) {
      if (c.types.indexOf('street_number') > -1) parts.num = c.long;
      if (c.types.indexOf('route') > -1) parts.route = c.long;
      if (c.types.indexOf('locality') > -1) parts.city = c.long;
      if (c.types.indexOf('sublocality_level_1') > -1 && !parts.city) parts.city = c.long;
      if (c.types.indexOf('administrative_area_level_1') > -1) parts.state = c.short;
      if (c.types.indexOf('postal_code') > -1) parts.zip = c.long;
      if (c.types.indexOf('country') > -1) { parts.country = c.short; parts.country_name = c.long; }
    });
    input.value = ((parts.num || '') + ' ' + (parts.route || '')).trim() || input.value;
    var group = input.dataset.addrGroup || '';
    var scope = group ? document.querySelectorAll('[data-addr-group="' + group + '"]') : [];
    Array.prototype.forEach.call(scope, function (el) {
      if (el === input) return;
      var k = el.dataset.geo;
      if (!k) return;
      var v = parts[k] || '';
      if (el.tagName === 'SELECT') {
        var has = v && Array.from(el.options).some(function (o) { return o.value === v; });
        if (!has) return; // no matching option: leave the select as-is rather than blank it
      }
      el.value = v;
    });
    // one event for the whole group, after every field has its final value — callers that react to address changes
    // (e.g. checkout's shipping-rate fetch) get a single, complete, consistent snapshot instead of a burst of partial
    // ones that can resolve out of order.
    document.dispatchEvent(new CustomEvent('uds:address-selected', { detail: { group: group } }));
  }

  function attach(input, n) {
    var id = 'addrsug-' + n, box = document.createElement('div'), list = document.createElement('ul');
    var items = [], active = -1, seq = 0, timer = 0, token = null, armed = false, auto = input.getAttribute('autocomplete');
    box.className = 'addrsug'; box.hidden = true;
    list.id = id; list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', (input.labels && input.labels[0] ? input.labels[0].textContent : input.placeholder || '').replace(/\*/g, '').trim());
    box.appendChild(list);
    // Google's terms: its predictions shown without a map carry its logo
    box.insertAdjacentHTML('beforeend', '<p class="addrsug__by"><img src="' + LOGO + '" alt="Powered by Google" width="120" height="14" loading="lazy"></p>');
    input.parentNode.classList.add('addrsug-host');
    input.parentNode.appendChild(box);

    function arm() {
      if (armed || dead) return;
      armed = true;
      input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-controls', id); input.setAttribute('aria-expanded', 'false');
      input.setAttribute('autocomplete', 'off');   // the browser's own address list would cover ours
    }
    function off() {
      clearTimeout(timer); seq++; close();
      if (!armed) return;
      armed = false;
      ['role', 'aria-autocomplete', 'aria-controls', 'aria-expanded'].forEach(function (a) { input.removeAttribute(a); });
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
      items = found;
      list.innerHTML = found.map(function (s, i) {
        return '<li role="option" id="' + id + '-' + i + '" aria-selected="false"><span><b>' + esc(s.main) + '</b>' + (s.sub ? ' <small>' + esc(s.sub) + '</small>' : '') + '</span></li>';
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
      var s = items[i]; if (!s) return;
      seq++; close(); token = null;   // the place's details close the session: the next search opens a new one
      s.pick().then(function (comps) { fill(input, comps); }).catch(function () {});   // no details: what was typed stays
    }
    function search() {
      var q = input.value.trim(), mine = ++seq;
      if (dead || q.length < 3) return close();
      places().then(function () {
        arm();
        token = token || new lib.AutocompleteSessionToken();
        return suggest(q, token);
      }).then(function (found) {
        if (mine === seq && !dead && document.activeElement === input) show(found.slice(0, 5));   // an older answer is for older text
      }).catch(stop);
    }

    input.addEventListener('focus', function () { if (!dead) places().then(arm).catch(function () {}); });
    input.addEventListener('input', function () { clearTimeout(timer); if (!dead) timer = setTimeout(search, 220); });
    input.addEventListener('blur', function () { clearTimeout(timer); seq++; setTimeout(close, 150); });
    input.addEventListener('keydown', function (e) {
      if (box.hidden || e.isComposing) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); move(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(active < 0 ? -1 : active - 1); }
      else if (e.key === 'Enter' && active > -1) { e.preventDefault(); pick(active); }   // without a chosen line Enter stays the form's
      else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); seq++; close(); }
    });
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });   // the field keeps the focus while a line is clicked
    list.addEventListener('click', function (e) {
      var li = e.target.closest('li'); if (li) pick(Array.prototype.indexOf.call(list.children, li));
    });
    if (document.activeElement === input) places().then(arm).catch(function () {});
    return { off: off };
  }

  if (KEY) document.querySelectorAll('[data-gplaces]').forEach(function (input, n) { fields.push(attach(input, n)); });
})();
