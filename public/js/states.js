/* UDS state / province dropdowns (config/states.php, owner 2026-11-13: "añade lista para state también").
   A text field marked data-state-list="<id of its country select>" gets a dropdown whenever that country has a list.
   The text field stays the real form field (same id, name and data-geo), so everything that reads it, fills it
   (IP prefill, Google address suggestions, saved addresses) or listens to its change event keeps working: the
   dropdown only writes the picked code into it. Countries without a list show the plain text field.
   After filling an address from a script without events, call udsStates.sync(). */
(function () {
  var DATA = window.UDS_STATES || {}, LISTS = DATA.lists || {}, PICK = DATA.pick || {};
  var pairs = [];

  // "fl", "FL" or "Florida" -> "FL"; '' for empty; null when the text is not on the list
  function code(list, v) {
    v = String(v || '').trim();
    if (!v) return '';
    var up = v.toUpperCase();
    if (list[up]) return up;
    for (var k in list) if (list[k].toUpperCase() === up) return k;
    return null;
  }

  function fill(p, cc) {
    var list = LISTS[cc];
    p.sel.innerHTML = '';
    var first = document.createElement('option');
    first.value = ''; first.textContent = PICK[cc] || PICK.US || 'Select';
    p.sel.appendChild(first);
    Object.keys(list).forEach(function (k) {
      var o = document.createElement('option');
      o.value = k; o.textContent = list[k];
      p.sel.appendChild(o);
    });
    p.cc = cc;
  }

  // empty the field and say so, so whatever prices the address (checkout shipping) looks again
  function clear(p) {
    p.input.value = '';
    p.input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function sync(p, countryChanged) {
    var cc = ((p.country && p.country.value) || 'US').toUpperCase();
    var list = LISTS[cc];
    if (!list) {
      // a code from the list of the country picked before means nothing here
      if (countryChanged && p.cc && LISTS[p.cc][p.input.value]) clear(p);
      p.sel.style.display = 'none'; p.input.style.display = '';
      if (p.label) p.label.htmlFor = p.input.id;
      return;
    }
    if (p.cc !== cc) fill(p, cc);
    var c = code(list, p.input.value);
    if (c) { p.input.value = c; p.sel.value = c; }
    else {
      p.sel.value = '';
      // a state of the country picked before means nothing in the new one; text saved long ago stays until a pick
      if (countryChanged && c === null) clear(p);
    }
    p.input.style.display = 'none'; p.sel.style.display = '';   // inline style: the forms' CSS would beat [hidden]
    if (p.label) p.label.htmlFor = p.sel.id;
  }

  function setup(input) {
    var sel = document.createElement('select');
    sel.id = input.id + '-list';
    sel.className = input.className;
    var ac = input.getAttribute('autocomplete');
    if (ac) sel.setAttribute('autocomplete', ac);
    input.parentNode.insertBefore(sel, input.nextSibling);
    var p = { input: input, sel: sel, cc: null, country: document.getElementById(input.dataset.stateList),
      label: input.id ? document.querySelector('label[for="' + input.id + '"]') : null };
    sel.addEventListener('change', function () {
      input.value = sel.value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    input.addEventListener('change', function () { sync(p, false); });
    if (p.country) p.country.addEventListener('change', function () { sync(p, true); });
    pairs.push(p);
    sync(p, false);
  }

  function syncAll() { pairs.forEach(function (p) { sync(p, false); }); }

  document.querySelectorAll('input[data-state-list]').forEach(setup);
  document.addEventListener('uds:address-selected', syncAll);
  document.addEventListener('uds:geo', syncAll);
  window.udsStates = { sync: syncAll };
})();
