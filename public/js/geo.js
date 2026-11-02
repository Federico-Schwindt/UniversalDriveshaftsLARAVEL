/* UDS geo + address autocomplete
   1) Silent IP prefill: fills empty [data-geo="city|state|zip|country"] fields from /geo (not inside [data-geo-noip]).
   2) Google Places Autocomplete on [data-gplaces] street inputs -> fills the address group
      (inputs sharing the same data-addr-group, keyed by data-geo + the street field itself). */
(function () {
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

  // ---- 2) Google Places Autocomplete ----
  function initPlaces() {
    if (!(window.google && google.maps && google.maps.places)) return;
    document.querySelectorAll('[data-gplaces]').forEach(function (input) {
      var ac = new google.maps.places.Autocomplete(input, {
        types: ['address'],
        fields: ['address_components'],
      });
      ac.addListener('place_changed', function () {
        var place = ac.getPlace();
        if (!place || !place.address_components) return;
        var parts = {};
        place.address_components.forEach(function (c) {
          if (c.types.indexOf('street_number') > -1) parts.num = c.long_name;
          if (c.types.indexOf('route') > -1) parts.route = c.long_name;
          if (c.types.indexOf('locality') > -1) parts.city = c.long_name;
          if (c.types.indexOf('sublocality_level_1') > -1 && !parts.city) parts.city = c.long_name;
          if (c.types.indexOf('administrative_area_level_1') > -1) parts.state = c.short_name;
          if (c.types.indexOf('postal_code') > -1) parts.zip = c.long_name;
          if (c.types.indexOf('country') > -1) { parts.country = c.short_name; parts.country_name = c.long_name; }
        });
        input.value = ((parts.num || '') + ' ' + (parts.route || '')).trim() || input.value;
        var group = input.dataset.addrGroup || '';
        var scope = group ? document.querySelectorAll('[data-addr-group="' + group + '"]') : [];
        // A picked place REPLACES the address, so every field in its group is set
        // from it — including clearing a field the place has no value for (a
        // broad landmark with no postal_code, say). Leaving an old value in
        // place used to strand e.g. a US zip code under a newly-picked
        // Argentina address, silently pricing shipping as domestic.
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
        // one event for the whole group, after every field has its final value —
        // callers that react to address changes (e.g. checkout's shipping-rate
        // fetch) get a single, complete, consistent snapshot instead of a burst
        // of partial ones that can resolve out of order.
        document.dispatchEvent(new CustomEvent('uds:address-selected', { detail: { group: group } }));
      });
    });
  }
  window.udsInitPlaces = initPlaces;
  if (window.google && window.google.maps) initPlaces();
})();
