/* Universal Driveshafts — smart phone fields.
   Upgrades every <input type="tel"> with a country / dial-code select (US +1 by default), formats the
   number as it is typed, and submits one international value in the original field name. */
(function () {
  var C = [ // iso, dial, flag, name  (priority first, then alphabetical)
    ['US','1','🇺🇸','United States'],['CA','1','🇨🇦','Canada'],['MX','52','🇲🇽','Mexico'],['PR','1','🇵🇷','Puerto Rico'],
    ['AR','54','🇦🇷','Argentina'],['AU','61','🇦🇺','Australia'],['AT','43','🇦🇹','Austria'],['BS','1','🇧🇸','Bahamas'],['BB','1','🇧🇧','Barbados'],['BE','32','🇧🇪','Belgium'],['BZ','501','🇧🇿','Belize'],['BO','591','🇧🇴','Bolivia'],['BR','55','🇧🇷','Brazil'],
    ['CL','56','🇨🇱','Chile'],['CN','86','🇨🇳','China'],['CO','57','🇨🇴','Colombia'],['CR','506','🇨🇷','Costa Rica'],['CU','53','🇨🇺','Cuba'],['CZ','420','🇨🇿','Czechia'],['DK','45','🇩🇰','Denmark'],['DO','1','🇩🇴','Dominican Republic'],
    ['EC','593','🇪🇨','Ecuador'],['EG','20','🇪🇬','Egypt'],['SV','503','🇸🇻','El Salvador'],['FI','358','🇫🇮','Finland'],['FR','33','🇫🇷','France'],['DE','49','🇩🇪','Germany'],['GR','30','🇬🇷','Greece'],['GT','502','🇬🇹','Guatemala'],['GY','592','🇬🇾','Guyana'],
    ['HT','509','🇭🇹','Haiti'],['HN','504','🇭🇳','Honduras'],['HK','852','🇭🇰','Hong Kong'],['HU','36','🇭🇺','Hungary'],['IS','354','🇮🇸','Iceland'],['IN','91','🇮🇳','India'],['ID','62','🇮🇩','Indonesia'],['IE','353','🇮🇪','Ireland'],['IL','972','🇮🇱','Israel'],['IT','39','🇮🇹','Italy'],
    ['JM','1','🇯🇲','Jamaica'],['JP','81','🇯🇵','Japan'],['KE','254','🇰🇪','Kenya'],['KR','82','🇰🇷','South Korea'],['LU','352','🇱🇺','Luxembourg'],['MY','60','🇲🇾','Malaysia'],['NL','31','🇳🇱','Netherlands'],['NZ','64','🇳🇿','New Zealand'],['NI','505','🇳🇮','Nicaragua'],['NG','234','🇳🇬','Nigeria'],['NO','47','🇳🇴','Norway'],
    ['PK','92','🇵🇰','Pakistan'],['PA','507','🇵🇦','Panama'],['PY','595','🇵🇾','Paraguay'],['PE','51','🇵🇪','Peru'],['PH','63','🇵🇭','Philippines'],['PL','48','🇵🇱','Poland'],['PT','351','🇵🇹','Portugal'],['QA','974','🇶🇦','Qatar'],['RO','40','🇷🇴','Romania'],['RU','7','🇷🇺','Russia'],
    ['SA','966','🇸🇦','Saudi Arabia'],['SG','65','🇸🇬','Singapore'],['ZA','27','🇿🇦','South Africa'],['ES','34','🇪🇸','Spain'],['SE','46','🇸🇪','Sweden'],['CH','41','🇨🇭','Switzerland'],['TW','886','🇹🇼','Taiwan'],['TH','66','🇹🇭','Thailand'],['TT','1','🇹🇹','Trinidad and Tobago'],['TR','90','🇹🇷','Turkey'],
    ['UA','380','🇺🇦','Ukraine'],['AE','971','🇦🇪','United Arab Emirates'],['GB','44','🇬🇧','United Kingdom'],['UY','598','🇺🇾','Uruguay'],['VE','58','🇻🇪','Venezuela'],['VN','84','🇻🇳','Vietnam'],['VI','1','🇻🇮','U.S. Virgin Islands']
  ];
  var NANP = { US:1, CA:1, PR:1, DO:1, JM:1, BS:1, BB:1, TT:1, VI:1 };
  var byIso = {}; C.forEach(function (c) { byIso[c[0]] = c; });
  function digits(s) { return String(s || '').replace(/\D+/g, ''); }
  function fmt(iso, d) { // national formatting: NANP (xxx) xxx-xxxx, elsewhere groups of 3
    d = d.slice(0, NANP[iso] ? 10 : 15);
    if (NANP[iso]) return d.length > 6 ? '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6) : d.length > 3 ? '(' + d.slice(0, 3) + ') ' + d.slice(3) : d;
    return d.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
  }
  function detect(full) { // "+52 55 1234 5678" -> {iso, rest}; longest dial code wins, +1 stays US
    var d = digits(full); if (!/^\s*\+/.test(full) && !(d.length === 11 && d[0] === '1')) return null;
    if (d[0] === '1' && d.length === 11) return { iso: 'US', rest: d.slice(1) };
    var best = null;
    C.forEach(function (c) { if (d.indexOf(c[1]) === 0 && (!best || c[1].length > byIso[best][1].length)) best = c[0]; });
    return best ? { iso: best, rest: d.slice(byIso[best][1].length) } : null;
  }
  // country names in the page's language (keeps the English list as the fallback)
  var LANG = (document.documentElement.lang || 'en').slice(0, 2), T = window.udsT || function (x) { return x; };
  if (LANG !== 'en' && window.Intl && Intl.DisplayNames) {
    try { var DN = new Intl.DisplayNames([LANG], { type: 'region' }); C.forEach(function (c) { var n = DN.of(c[0]); if (n && n !== c[0]) c[3] = n; }); } catch (e) {}
  }
  var FLAG = '/img/flags/';
  function flagImg(iso) { return '<img class="tel__flag" src="' + FLAG + iso.toLowerCase() + '.svg" alt="" width="20" height="15" loading="lazy">'; }
  var openMenu = null;
  function upgrade(input) {
    if (input.dataset.telReady || input.closest('.tel')) return; input.dataset.telReady = '1';
    var name = input.getAttribute('name'), form = input.form, iso = 'US';
    var wrap = document.createElement('div'); wrap.className = 'tel';
    // country picker: a button with the flag + dial code, and a searchable list (native <select> cannot show flags)
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'tel__cc'; btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false');
    var menu = document.createElement('div'); menu.className = 'tel__menu'; menu.hidden = true;
    menu.innerHTML = '<input type="search" class="tel__q" placeholder="' + T('Search country') + '" aria-label="' + T('Search country') + '" autocomplete="off"><ul class="tel__list" role="listbox"></ul>';
    var list = menu.querySelector('.tel__list'), q = menu.querySelector('.tel__q');
    var hidden = document.createElement('input'); hidden.type = 'hidden'; if (name) { hidden.name = name; input.removeAttribute('name'); }
    input.parentNode.insertBefore(wrap, input); wrap.appendChild(btn); wrap.appendChild(input); wrap.appendChild(hidden); wrap.appendChild(menu);
    input.setAttribute('inputmode', 'tel'); if (!input.getAttribute('autocomplete')) input.setAttribute('autocomplete', 'tel-national');
    function paint() { var c = byIso[iso]; btn.innerHTML = flagImg(iso) + '<span class="tel__code">+' + c[1] + '</span><span class="tel__caret" aria-hidden="true"></span>'; btn.setAttribute('aria-label', c[3] + ' +' + c[1] + T(', change country')); }
    function renderList(filter) {
      var f = (filter || '').toLowerCase();
      list.innerHTML = C.filter(function (c) { return !f || c[3].toLowerCase().indexOf(f) > -1 || c[0].toLowerCase() === f || ('+' + c[1]).indexOf(f) === 0 || c[1].indexOf(f) === 0; })
        .map(function (c) { return '<li role="option" data-iso="' + c[0] + '" aria-selected="' + (c[0] === iso) + '"' + (c[0] === iso ? ' class="is-on"' : '') + '>' + flagImg(c[0]) + '<span class="tel__name">' + c[3] + '</span><span class="tel__dial">+' + c[1] + '</span></li>'; }).join('') || '<li class="tel__none">No match</li>';
    }
    function open() { if (openMenu && openMenu !== close) openMenu(); menu.hidden = false; btn.setAttribute('aria-expanded', 'true'); q.value = ''; renderList(''); openMenu = close; setTimeout(function () { q.focus(); var on = list.querySelector('.is-on'); if (on) on.scrollIntoView({ block: 'center' }); }, 10); }
    function close() { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); if (openMenu === close) openMenu = null; }
    function choose(code, focusInput) { iso = code; paint(); input.value = fmt(iso, digits(input.value)); input.placeholder = NANP[iso] ? '(813) 524-6491' : T('Number without the country code'); sync(); close(); if (focusInput !== false) input.focus(); }
    btn.addEventListener('click', function () { menu.hidden ? open() : close(); });
    q.addEventListener('input', function () { renderList(q.value); });
    q.addEventListener('keydown', function (e) {
      var items = list.querySelectorAll('[data-iso]'), cur = list.querySelector('.is-hot'), i = Array.prototype.indexOf.call(items, cur);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (cur) cur.classList.remove('is-hot'); i = e.key === 'ArrowDown' ? Math.min(items.length - 1, i + 1) : Math.max(0, i - 1); if (items[i]) { items[i].classList.add('is-hot'); items[i].scrollIntoView({ block: 'nearest' }); } }
      else if (e.key === 'Enter') { e.preventDefault(); var pick = cur || items[0]; if (pick) choose(pick.dataset.iso); }
      else if (e.key === 'Escape') { close(); btn.focus(); }
    });
    list.addEventListener('click', function (e) { var li = e.target.closest('[data-iso]'); if (li) choose(li.dataset.iso); });
    document.addEventListener('click', function (e) { if (!menu.hidden && !wrap.contains(e.target)) close(); });
    // existing value (prefilled profile phone) -> split into country + national
    var pre = detect(input.value); if (pre) { iso = pre.iso; input.value = fmt(iso, pre.rest); } else input.value = fmt('US', digits(input.value));
    if (!input.placeholder || /mobile|phone/i.test(input.placeholder)) input.placeholder = '(813) 524-6491';
    paint();
    function sync() {
      var d = digits(input.value), c = byIso[iso];
      hidden.value = d ? '+' + c[1] + ' ' + (NANP[iso] ? d.replace(/(\d{3})(\d{3})(\d{0,4})/, '$1-$2-$3').replace(/-$/, '') : d) : '';
      if (d && NANP[iso] && d.length !== 10) input.setCustomValidity('Enter the 10-digit number, area code first.'); else input.setCustomValidity('');
    }
    input.addEventListener('input', function () {
      var v = input.value;
      if (/^\s*\+/.test(v)) { // typing or pasting an international number: keep the "+" until the dial code is known
        var hit = detect(v);
        if (hit && (hit.rest.length || digits(v).length > byIso[hit.iso][1].length)) { iso = hit.iso; paint(); input.value = fmt(iso, hit.rest); }
        else { input.value = '+' + digits(v); hidden.value = ''; return; }
      } else { var dd = digits(v); if (NANP[iso] && dd.length === 11 && dd[0] === '1') dd = dd.slice(1); input.value = fmt(iso, dd); }
      sync();
    });
    // follow a country select in the same form while the number is still empty
    var cs = form && (form.querySelector('select[name="shipping_country"]') || form.querySelector('select[name="country"]') || form.querySelector('select[name$="country"]'));
    if (cs) cs.addEventListener('change', function () { if (!digits(input.value) && byIso[cs.value]) choose(cs.value, false); });
    sync();
  }
  function scan(root) { (root || document).querySelectorAll('input[type="tel"]').forEach(upgrade); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(); }); else scan();
  new MutationObserver(function (m) { m.forEach(function (x) { x.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); }); }); }).observe(document.documentElement, { childList: true, subtree: true });
  window.udsPhone = { scan: scan };
})();
