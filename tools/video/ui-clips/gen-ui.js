// Generates the vector UI for the launch video: one self-contained SVG per screen (fonts by name, photos embedded),
// plus video/ui.js (same SVGs inline) and the click targets the cursor needs.
const fs = require('fs'); const path = require('path');
const ROOT = __dirname, A = path.join(ROOT, 'inputs/assets');
const img = (f) => { const b = fs.readFileSync(path.join(A, f)); const ext = f.split('.').pop(); return `data:image/${ext === 'svg' ? 'svg+xml' : ext};base64,${b.toString('base64')}`; };
const LOGO = img('../logo.svg');

const C = { steel: '#0b1a33', steel7: '#16294a', blue: '#1246ab', blue6: '#1657cf', blue050: '#eef3fc', blue100: '#dce7f8', red: '#cf3130', ink: '#141b28', ink2: '#3a4557', ink3: '#5c6879', paper: '#ffffff', paper2: '#f6f8fc', paper3: '#eef2f8', line: '#e4e8f0', lineS: '#d4dae6', teal: '#1f9e88', ok: '#1a7f4b', okbg: '#e7f5ec' };
const F = { h: "Poppins, sans-serif", b: "'Hanken Grotesk', sans-serif", m: "'IBM Plex Mono', monospace" };
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const T = (x, y, s, { size = 16, fam = F.b, w = 400, fill = C.ink, anchor = 'start', ls = 0, id = '', op = 1 } = {}) =>
  `<text${id ? ` id="${id}"` : ''} x="${x}" y="${y}" font-family="${fam}" font-size="${size}" font-weight="${w}" fill="${fill}" text-anchor="${anchor}" letter-spacing="${ls}"${op !== 1 ? ` opacity="${op}"` : ''}>${esc(s)}</text>`;
const R = (x, y, w, h, { r = 0, fill = 'none', stroke = 'none', sw = 1, id = '', op = 1, filter = '' } = {}) =>
  `<rect${id ? ` id="${id}"` : ''} x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}"${op !== 1 ? ` opacity="${op}"` : ''}${filter ? ` filter="url(#${filter})"` : ''}/>`;
const svg = (w, h, body, extraDefs = '') => `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}">
<defs>
  <filter id="sh" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="10" stdDeviation="14" flood-color="#0b1a33" flood-opacity=".14"/></filter>
  <filter id="sh2" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#0b1a33" flood-opacity=".10"/></filter>
  <radialGradient id="heroGlow" cx="78%" cy="40%" r="60%"><stop offset="0" stop-color="#1d4fb8" stop-opacity=".75"/><stop offset="1" stop-color="#0b1a33" stop-opacity="0"/></radialGradient>
  <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" fill="none" stroke="#ffffff" stroke-opacity=".05"/></pattern>
  <pattern id="gridL" width="24" height="24" patternUnits="userSpaceOnUse"><path d="M24 0H0V24" fill="none" stroke="#1246ab" stroke-opacity=".07"/></pattern>
  ${extraDefs}
</defs>
${body}
</svg>`;
const TARGETS = {}; // click targets in screen coordinates
const tgt = (screen, key, x, y) => { (TARGETS[screen] ||= {})[key] = [x, y]; };

// ---- icons (24px stroke icons, drawn at x,y top-left)
const ic = {
  search: (x, y, c = C.ink, s = 1) => `<g transform="translate(${x} ${y}) scale(${s})" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></g>`,
  user: (x, y, c = C.ink) => `<g transform="translate(${x} ${y})" fill="none" stroke="${c}" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></g>`,
  heart: (x, y, c = C.ink) => `<g transform="translate(${x} ${y})" fill="none" stroke="${c}" stroke-width="2" stroke-linejoin="round"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></g>`,
  cart: (x, y, c = C.ink) => `<g transform="translate(${x} ${y})" fill="none" stroke="${c}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"><path d="M3 4h2.5l2.2 10.5h10.6L20.5 7H7"/><circle cx="9.5" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></g>`,
  truck: (x, y, c = C.teal) => `<g transform="translate(${x} ${y}) scale(.75)" fill="none" stroke="${c}" stroke-width="2" stroke-linejoin="round"><path d="M2 6h12v10H2zM14 9h4l3 3v4h-7z"/><circle cx="6" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></g>`,
  check: (x, y, c = '#fff') => `<path d="M${x - 6} ${y} l4 4 l8 -9" fill="none" stroke="${c}" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>`,
  plus: (x, y, c = C.blue) => `<path d="M${x - 7} ${y}h14M${x} ${y - 7}v14" stroke="${c}" stroke-width="2" stroke-linecap="round"/>`,
};

// ---- shared desktop chrome (inside the browser viewport, 1440 wide)
const strip = () => `${R(0, 0, 1440, 40, { fill: C.steel })}${ic.truck(118, 11)}${T(146, 26, 'U.S. free shipping on orders over $1,000 (excluding tubes and driveshafts)', { size: 14, fill: '#dfe7f5' })}
${T(1060, 26, 'Track order & returns', { size: 14, fill: '#c9d4e8' })}${T(1210, 26, 'Wholesale', { size: 14, fill: '#c9d4e8' })}
${R(1288, 9, 32, 22, { r: 11, fill: '#fff' })}${T(1304, 25, 'EN', { size: 11, fam: F.m, w: 600, fill: C.steel, anchor: 'middle' })}${T(1336, 25, 'ES', { size: 11, fam: F.m, fill: '#8fa0bf', anchor: 'middle' })}${T(1362, 25, 'FR', { size: 11, fam: F.m, fill: '#8fa0bf', anchor: 'middle' })}`;
const header = (screen, active = '') => {
  tgt(screen, 'cart', 1134, 80); tgt(screen, 'search', 1020, 80);
  const nav = ['Catalog', 'Driveshafts', 'Services', 'About']; let x = 430;
  const items = nav.map((n) => { const s = T(x, 86, n, { size: 17, fam: F.h, w: 500, fill: n === active ? C.blue : C.ink2 }) + (n === 'Catalog' || n === 'Driveshafts' ? `<path d="M${x + n.length * 9.4 + 6} 79l4 4 4-4" fill="none" stroke="${n === active ? C.blue : C.ink2}" stroke-width="1.6"/>` : ''); x += n.length * 9.4 + 46; return s; }).join('');
  return `${R(0, 40, 1440, 82, { fill: '#fff' })}<line x1="0" y1="121.5" x2="1440" y2="121.5" stroke="${C.line}"/>
<image href="${LOGO}" x="120" y="60" width="262" height="42"/>${items}
${ic.search(1008, 68, C.ink2)}${ic.user(1050, 68, C.ink2)}${ic.heart(1088, 68, C.ink2)}${ic.cart(1124, 68, C.ink2)}
<g id="cartBadge"><circle cx="1150" cy="68" r="10" fill="${C.teal}"/><text id="cartCount" x="1150" y="72.5" font-family="${F.m}" font-size="12" font-weight="600" fill="#fff" text-anchor="middle">0</text></g>
${R(1180, 57, 150, 46, { r: 23, fill: C.blue6 })}${T(1255, 86, 'Online builder', { size: 16, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}`;
};

// ---- vector driveshaft (one piece), centred on cx,cy with length L
const shaft = (cx, cy, L, s = 1) => {
  const x0 = cx - L / 2, x1 = cx + L / 2, th = 26 * s;
  const yoke = (x, dir) => `<g transform="translate(${x} ${cy}) scale(${dir * s} ${s})">
    <rect x="0" y="-17" width="26" height="34" rx="4" fill="#cfd6e2" stroke="#5c6879" stroke-width="1.5"/>
    <path d="M26 -21 h22 a6 6 0 0 1 6 6 v30 a6 6 0 0 1 -6 6 h-22z" fill="#e3e8f0" stroke="#5c6879" stroke-width="1.5"/>
    <circle cx="44" cy="0" r="9" fill="#9aa6b8" stroke="#5c6879" stroke-width="1.5"/><rect x="54" y="-24" width="12" height="48" rx="3" fill="#b9c2d0" stroke="#5c6879" stroke-width="1.5"/></g>`;
  return `<g><rect x="${x0 + 30 * s}" y="${cy - th / 2}" width="${L - 60 * s}" height="${th}" fill="url(#tubeG)" stroke="#5c6879" stroke-width="1.5"/>${yoke(x0 + 30 * s, -1)}${yoke(x1 - 30 * s, 1)}</g>`;
};
const tubeDefs = `<linearGradient id="tubeG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#eef2f7"/><stop offset=".45" stop-color="#c2cad8"/><stop offset="1" stop-color="#8794a8"/></linearGradient>`;

// =============================================================== HOME + search dropdown
const home = () => {
  const s = 'home';
  tgt(s, 'searchBox', 300, 481); tgt(s, 'row0', 520, 596);
  const items = [
    ['fy0.webp', '2-2-329 – Flange Yoke – 1310 Series', '2-2-0329', '$20.49'],
    ['fy1.webp', 'Flange Yoke 1310 series, 6 x 0.441 holes', '2-2-1339', '$18.31'],
    ['fy2.webp', 'Flange Yoke 1310 series, 3.937 BC', '2-2-1989', '$24.26'],
    ['fy3.webp', 'Flange Yoke 1310 Series, 4 x 0.380', '2-2-349', '$20.65'],
    ['fy4.webp', 'Flange Yoke 1310 series, 4 x 0.438', '2-2-479', '$14.52'],
  ];
  const rows = items.map(([im, n, sku, p], i) => { const y = 566 + i * 62; return `<g id="drop-r${i}">
    ${i ? `<line x1="136" y1="${y - 4}" x2="724" y2="${y - 4}" stroke="${C.line}"/>` : ''}
    ${R(140, y + 4, 48, 48, { r: 10, fill: C.paper3 })}<image href="${img(im)}" x="144" y="${y + 8}" width="40" height="40" preserveAspectRatio="xMidYMid meet"/>
    ${T(204, y + 24, n, { size: 15, fam: F.h, w: 600 })}${T(204, y + 44, sku, { size: 12, fam: F.m, fill: C.ink3 })}
    ${T(640, y + 34, p, { size: 16, fam: F.m, w: 600, anchor: 'end' })}${R(660, y + 12, 34, 34, { r: 9, fill: '#fff', stroke: C.blue6, sw: 1.5 })}${ic.plus(677, y + 29, C.blue6)}</g>`; }).join('');
  const cats = [['U-joints', '1310 · 1350 · 1410 · 1480'], ['Yokes', 'End · weld · slip · flange'], ['Center bearings', 'Bolt-on · two-piece'], ['Flanges', 'Companion · hub'], ['Tubing', 'DOM · seamless']];
  const catCards = cats.map(([n, d], i) => { const x = 120 + i * 244; return `${R(x, 700, 224, 150, { r: 16, fill: '#fff', stroke: C.line, filter: 'sh2' })}${R(x + 20, 720, 44, 44, { r: 12, fill: C.blue050 })}<circle cx="${x + 42}" cy="742" r="9" fill="none" stroke="${C.blue6}" stroke-width="2.4"/>${T(x + 20, 800, n, { size: 18, fam: F.h, w: 600 })}${T(x + 20, 824, d, { size: 13, fam: F.m, fill: C.ink3 })}`; }).join('');
  return svg(1440, 900, `${R(0, 0, 1440, 900, { fill: C.paper2 })}${strip()}${header(s)}
${R(0, 122, 1440, 520, { fill: C.steel })}${R(0, 122, 1440, 520, { fill: 'url(#heroGlow)' })}${R(0, 122, 1440, 520, { fill: 'url(#grid)' })}
<image href="${img('hero-ujoint.webp')}" x="860" y="150" width="470" height="451"/>
<line x1="120" y1="222" x2="142" y2="222" stroke="${C.red}" stroke-width="2"/>${T(152, 227, 'DRIVESHAFT COMPONENTS · COMPLETE DRIVESHAFTS', { size: 13, fam: F.m, w: 600, fill: '#ff6b66', ls: 2.5 })}
${T(120, 300, 'The driveshaft parts you need,', { size: 58, fam: F.h, w: 800, fill: '#fff', ls: -1.5 })}
${T(120, 368, 'on the shelf today.', { size: 58, fam: F.h, w: 800, fill: C.red, ls: -1.5 })}
${T(120, 414, 'U-joints, yokes, center bearings, flanges and tubing, 1310 to 1810 series.', { size: 19, fill: '#b9c6dd' })}
<g id="searchBox">${R(120, 450, 620, 62, { r: 31, fill: '#fff', stroke: '#fff', sw: 2, id: 'searchRing' })}${ic.search(146, 469, C.ink3)}
${T(182, 488, 'Part number, e.g. 5-1310XU', { size: 18, fill: '#8a95a8', id: 'searchPh' })}${T(182, 488, '', { size: 19, fam: F.m, w: 500, fill: C.ink, id: 'searchTxt' })}
<rect id="caret" x="182" y="468" width="2" height="26" fill="${C.blue6}" opacity="0"/>${R(632, 457, 100, 48, { r: 24, fill: C.blue6 })}${T(682, 487, 'Search', { size: 16, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}</g>
<g id="heroBtns">${R(120, 548, 230, 54, { r: 27, fill: C.blue6 })}${T(235, 581, 'Shop components  →', { size: 17, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}
${R(366, 548, 210, 54, { r: 27, fill: 'none', stroke: '#c9d4e8', sw: 1.5 })}${T(471, 581, 'Find by vehicle', { size: 17, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}</g>
${T(120, 680, 'Shop by part', { size: 26, fam: F.h, w: 700 })}${catCards}
<g id="drop" opacity="0">${R(120, 520, 620, 364, { r: 18, fill: '#fff', stroke: C.line, filter: 'sh' })}
${T(144, 551, 'FLANGE YOKES', { size: 12, fam: F.m, w: 600, fill: C.ink2, ls: 2.5 })}${T(716, 551, 'All 70 →', { size: 14, fam: F.h, w: 600, fill: C.blue6, anchor: 'end' })}${rows}</g>`, tubeDefs);
};

// =============================================================== FINDER
const finder = () => {
  const s = 'finder';
  const grids = {
    make: ['Ford', 'Chevrolet', 'Dodge', 'GMC', 'Jeep', 'RAM', 'Toyota', 'Nissan'],
    year: ['2018', '2017', '2016', '2015', '2014', '2013', '2012', '2011'],
    model: ['E350', 'Escape', 'Expedition', 'Explorer', 'F150', 'F250 SD', 'F350 SD', 'Ranger'],
    drive: ['AWD/4WD', 'RWD/2WD'],
  };
  const titles = { make: 'Choose make', year: 'Choose year', model: 'Choose model', drive: 'Choose drive' };
  const tiles = Object.entries(grids).map(([k, list]) => `<g id="tiles-${k}" opacity="${k === 'make' ? 1 : 0}">${T(120, 372, titles[k], { size: 22, fam: F.h, w: 700, ls: -0.3 })}${list.map((v, i) => {
    const col = i % 4, row = Math.floor(i / 4), x = 120 + col * 186, y = 396 + row * 104; tgt(s, `${k}:${v}`, x + 85, y + 44);
    return `<g id="tile-${k}-${v.replace(/\W/g, '')}">${R(x, y, 170, 88, { r: 14, fill: '#fff', stroke: C.line, filter: 'sh2' })}${T(x + 85, y + 52, v, { size: v.length > 8 ? 17 : 20, fam: F.h, w: 700, fill: C.blue, anchor: 'middle' })}</g>`; }).join('')}</g>`).join('');
  const chips = [['make', 'MAKE', 'Ford', 120], ['year', 'YEAR', '2015', 252], ['model', 'MODEL', 'F150', 384], ['drive', 'DRIVE', 'AWD/4WD', 516]].map(([k, l, v, x]) =>
    `<g id="chip-${k}" opacity="0">${T(x + 2, 286, l, { size: 11, fam: F.m, w: 600, fill: C.ink3, ls: 1.5 })}${R(x, 294, 120, 40, { r: 10, fill: '#fff', stroke: C.lineS })}${T(x + 14, 320, v, { size: 16, fam: F.h, w: 600 })}${T(x + 104, 320, '×', { size: 17, w: 700, fill: C.red, anchor: 'middle' })}</g>`).join('');
  tgt(s, 'add', 1128, 778);
  return svg(1440, 900, `${R(0, 0, 1440, 900, { fill: C.paper2 })}${strip()}${header(s, 'Driveshafts')}
${T(120, 162, 'Home   ›   Catalog   ›   Complete driveshafts', { size: 14, fill: C.ink3 })}
${T(120, 220, 'Complete driveshafts', { size: 44, fam: F.h, w: 800, ls: -1 })}${T(120, 252, 'Balanced, ready-to-install driveshafts by year, make and model.', { size: 17, fill: C.ink3 })}
${chips}${tiles}
<g id="result" opacity="0">${R(900, 280, 420, 560, { r: 20, fill: '#fff', stroke: C.line, filter: 'sh' })}
${R(924, 304, 64, 40, { r: 20, fill: '#1e3f8f' })}${T(956, 330, 'Ford', { size: 15, fam: F.h, w: 700, fill: '#fff', anchor: 'middle' })}
${T(1004, 322, 'FORD F150', { size: 22, fam: F.h, w: 800 })}${T(1004, 344, '2015 · AWD/4WD', { size: 14, fill: C.ink3 })}
${R(924, 370, 372, 150, { r: 14, fill: C.paper2 })}${shaft(1110, 445, 330, 0.9)}
${T(924, 556, 'U3194-2200 ↗', { size: 20, fam: F.m, w: 600, fill: C.blue })}${T(1296, 556, '$456.28', { size: 24, fam: F.h, w: 700, anchor: 'end' })}
${T(924, 580, 'FORD FRONT DRIVESHAFT', { size: 13, fam: F.m, fill: C.ink3, ls: 1 })}
${[['MODEL', 'F150', 924, 622], ['DRIVE', 'AWD/4WD', 1110, 622], ['LOC', 'Front', 924, 680], ['ENGINE', '5.0L', 1110, 680]].map(([l, v, x, y]) => `${T(x, y, l, { size: 11, fam: F.m, w: 600, fill: C.ink3, ls: 1.5 })}${T(x, y + 24, v, { size: 17, fam: F.h, w: 600 })}`).join('')}
<line x1="924" y1="728" x2="1296" y2="728" stroke="${C.line}"/>
<g id="addBtn">${R(924, 750, 372, 56, { r: 14, fill: C.blue6 })}${T(1110, 785, '+  Add to cart', { size: 18, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}</g>
<g id="added" opacity="0">${R(924, 750, 372, 56, { r: 14, fill: C.ok })}${ic.check(1050, 778)}${T(1120, 785, 'Added to cart', { size: 18, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}</g></g>`, tubeDefs);
};

// =============================================================== DESIGNER
const designer = () => {
  const s = 'designer';
  const X0 = 250, X1 = 1190, steps = [['1', 'Style', 'One piece'], ['2', 'Front section', '01'], ['3', 'Rear section', '01R'], ['4', 'Measurements', 'Click a label']];
  const stepPills = steps.map(([n, l, v], i) => { const x = 120 + i * 300, act = i === 3; return `${R(x, 266, 284, 74, { r: 14, fill: act ? C.blue050 : '#fff', stroke: act ? C.blue6 : C.line, sw: act ? 2 : 1 })}<circle cx="${x + 26}" cy="292" r="12" fill="${act ? C.blue6 : C.ok}"/>${act ? T(x + 26, 297, n, { size: 13, fam: F.m, w: 600, fill: '#fff', anchor: 'middle' }) : ic.check(x + 26, 291)}${T(x + 48, 297, l, { size: 15, fam: F.h, w: 600, fill: act ? C.blue : C.ink })}${T(x + 20, 326, v, { size: 14, fill: act ? C.blue6 : C.ink3 })}`; }).join('');
  const dim = (k, y, xa, xb, label) => { tgt(s, k, (xa + xb) / 2, y - 18); return `<g><line x1="${xa}" y1="${y}" x2="${xb}" y2="${y}" stroke="${C.blue6}" stroke-width="2"/><path d="M${xa} ${y}l12 -6v12zM${xb} ${y}l-12 -6v12z" fill="${C.blue6}"/>
    <line x1="${xa}" y1="${y - 14}" x2="${xa}" y2="${y + 14}" stroke="${C.blue6}" stroke-width="1.5"/><line x1="${xb}" y1="${y - 14}" x2="${xb}" y2="${y + 14}" stroke="${C.blue6}" stroke-width="1.5"/>
    ${R((xa + xb) / 2 - 70, y - 44, 140, 40, { r: 8, fill: '#fff' })}${T((xa + xb) / 2, y - 14, label, { size: 30, fam: F.m, w: 600, fill: C.blue, anchor: 'middle', id: 'lab-' + k })}${T((xa + xb) / 2, y - 14, '', { size: 32, fam: F.m, w: 600, fill: C.ink, anchor: 'middle', id: 'val-' + k })}</g>`; };
  tgt(s, 'T1', 300, 600);
  const rows = [['C1', 'Overall length, end to end'], ['W1', 'Tube length, weld to weld'], ['T1', 'Tube outside diameter']].map(([k, d], i) => { const y = 742 + i * 30; return `${T(150, y, k, { size: 14, fam: F.m, w: 600, fill: C.blue })}${T(200, y, d, { size: 14, fill: C.ink2 })}${T(560, y, '', { size: 14, fam: F.m, w: 600, anchor: 'end', id: 'tv-' + k })}<line x1="150" y1="${y + 10}" x2="560" y2="${y + 10}" stroke="${C.line}"/>`; }).join('');
  return svg(1440, 900, `${R(0, 0, 1440, 900, { fill: C.paper2 })}${strip()}${header(s, 'Driveshafts')}
${T(120, 162, 'Home   ›   Driveshafts   ›   Driveshaft designer', { size: 14, fill: C.ink3 })}
${T(120, 222, 'Driveshaft designer', { size: 44, fam: F.h, w: 800, ls: -1 })}${T(120, 254, 'Draw it, dimension it, print it or send it to our shop.', { size: 17, fill: C.ink3 })}
${stepPills}
${R(120, 366, 1200, 500, { r: 18, fill: '#fff', stroke: C.line, filter: 'sh2' })}${R(121, 367, 1198, 498, { r: 18, fill: 'url(#gridL)' })}
${R(120, 366, 1200, 46, { r: 18, fill: '#fff' })}<line x1="120" y1="412" x2="1320" y2="412" stroke="${C.line}"/>
${T(146, 395, 'DRIVESHAFT DRAWING', { size: 13, fam: F.m, w: 600, ls: 2 })}${T(330, 395, '01+01R', { size: 13, fam: F.m, w: 600, fill: C.blue6, ls: 1 })}${T(1296, 395, 'One piece · NTS · inches', { size: 13, fam: F.m, fill: C.ink3, anchor: 'end' })}
${dim('C1', 478, X0, X1, '{C1}')}${dim('W1', 548, X0 + 70, X1 - 70, '{W1}')}
<g>${shaft((X0 + X1) / 2, 624, X1 - X0 + 20, 1.25)}</g>
<g><line x1="${X0 + 100}" y1="607" x2="${X0 + 100}" y2="641" stroke="${C.blue6}" stroke-width="2"/><path d="M${X0 + 100} 607l-5 10h10zM${X0 + 100} 641l-5 -10h10z" fill="${C.blue6}"/>${T(720, 634, '{T1}', { size: 26, fam: F.m, w: 600, fill: C.blue, anchor: 'middle', id: 'lab-T1' })}${T(720, 634, '', { size: 28, fam: F.m, w: 600, fill: C.ink, anchor: 'middle', id: 'val-T1' })}</g>
<line x1="120" y1="700" x2="1320" y2="700" stroke="${C.line}"/>${T(150, 724, 'MEASUREMENTS', { size: 12, fam: F.m, w: 600, ls: 2, fill: C.ink3 })}${T(560, 724, 'INCHES', { size: 12, fam: F.m, w: 600, ls: 2, fill: C.ink3, anchor: 'end' })}${rows}
${T(640, 724, 'LEGEND', { size: 12, fam: F.m, w: 600, ls: 2, fill: C.ink3 })}${T(640, 752, 'C  Overall length, end to end', { size: 14, fill: C.ink2 })}${T(640, 780, 'W  Tube length, weld to weld', { size: 14, fill: C.ink2 })}${T(640, 808, 'T  Tube outside diameter', { size: 14, fill: C.ink2 })}
${R(1060, 744, 236, 54, { r: 14, fill: C.blue6 })}${T(1178, 777, 'Print the sheet', { size: 17, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}
<g id="inBox" opacity="0">${R(0, 0, 170, 52, { r: 10, fill: '#fff', stroke: C.blue6, sw: 2.5, filter: 'sh2' })}${T(85, 35, '', { size: 26, fam: F.m, w: 600, anchor: 'middle', id: 'inTxt' })}</g>`, tubeDefs);
};

// =============================================================== ASSISTANT panel (460 × 860)
const assistant = () => {
  const s = 'assistant';
  const agents = [['Fitment check', '1350 series · lifted Jeep'], ['Part search', '5-1350XU · 1350TST · 5-7166XU'], ['Stock & price', 'In stock · from $14.66'], ['Shipping quote', 'UPS Ground · ships today']];
  const glyph = [
    (x, y) => `<path d="M${x - 8} ${y + 6}a9 9 0 1 1 16 0" fill="none" stroke="${C.blue6}" stroke-width="2.2" stroke-linecap="round"/><path d="M${x} ${y + 2}l5 -6" stroke="${C.blue6}" stroke-width="2.2" stroke-linecap="round"/>`,
    (x, y) => ic.search(x - 11, y - 11, C.blue6, 0.9),
    (x, y) => `<path d="M${x - 8} ${y - 6}h9l8 8-8 8-9-9z" fill="none" stroke="${C.blue6}" stroke-width="2.2" stroke-linejoin="round"/><circle cx="${x - 4}" cy="${y - 2}" r="1.6" fill="${C.blue6}"/>`,
    (x, y) => ic.truck(x - 9, y - 9, C.blue6),
  ];
  const cards = agents.map(([t, d], i) => { const y = 214 + i * 86; return `<g id="ag${i}"><g id="agBody${i}">${R(20, y, 420, 74, { r: 14, fill: '#fff', stroke: C.line, filter: 'sh2', id: 'agBox' + i })}
    ${R(36, y + 15, 44, 44, { r: 12, fill: C.blue050 })}${glyph[i](58, y + 37)}${T(94, y + 33, t, { size: 16, fam: F.h, w: 600 })}${T(94, y + 54, d, { size: 12.5, fam: F.m, fill: C.ink3 })}
    ${R(94, y + 62, 300, 4, { r: 2, fill: C.paper3 })}${R(94, y + 62, 300, 4, { r: 2, fill: C.blue6, id: 'agf' + i })}
    <g id="ags${i}"><circle cx="412" cy="${y + 37}" r="10" fill="none" stroke="${C.blue100}" stroke-width="3"/><path d="M412 ${y + 27}a10 10 0 0 1 10 10" fill="none" stroke="${C.blue6}" stroke-width="3" stroke-linecap="round"/></g>
    <g id="agc${i}" opacity="0"><circle cx="412" cy="${y + 37}" r="13" fill="${C.ok}"/>${ic.check(412, y + 37)}</g></g></g>`; }).join('');
  const prods = [['uj0.webp', '5-1350XU', '$16.40'], ['uj2.webp', '1350TST', '$58.92'], ['uj1.webp', '5-7166XU', '$14.66']].map(([im, sku, p], i) => { const x = 20 + i * 142; return `<g id="pc${i}" opacity="0">${R(x, 640, 132, 150, { r: 12, fill: '#fff', stroke: C.line })}${R(x + 1, 641, 130, 74, { r: 11, fill: C.paper2 })}<image href="${img(im)}" x="${x + 34}" y="648" width="64" height="62"/>${T(x + 10, 736, sku, { size: 12, fam: F.m, w: 600, fill: C.blue })}${T(x + 10, 762, p, { size: 16, fam: F.h, w: 700 })}<circle cx="${x + 82}" cy="757" r="3.5" fill="${C.ok}"/>${T(x + 90, 761, 'In stock', { size: 10.5, fill: C.ink3 })}</g>`; }).join('');
  return svg(460, 860, `${R(0, 0, 460, 860, { r: 26, fill: C.paper2 })}
<path d="M26 0h408a26 26 0 0 1 26 26v62H0V26A26 26 0 0 1 26 0z" fill="${C.steel}"/>
${R(20, 22, 44, 44, { r: 12, fill: C.blue6 })}<path d="M42 31l3 8 8 3-8 3-3 8-3-8-8-3 8-3z" fill="#fff"/>${T(76, 44, 'UDS Assistant', { size: 19, fam: F.h, w: 700, fill: '#fff' })}${R(222, 30, 44, 18, { r: 9, fill: C.blue6 })}${T(244, 43, 'BETA', { size: 10, fam: F.m, w: 600, fill: '#fff', anchor: 'middle', ls: 1 })}${T(76, 64, 'Parts, fitment, shipping & your orders', { size: 13, fill: '#a9b8d4' })}
<g id="u1" opacity="0">${R(96, 110, 344, 82, { r: 18, fill: C.blue6 })}${T(116, 142, 'I need a 1350 u-joint for my lifted', { size: 15.5, fill: '#fff' })}${T(116, 166, 'Jeep. Can you ship it today?', { size: 15.5, fill: '#fff' })}</g>
${cards}
<g id="b1" opacity="0">${R(20, 562, 420, 64, { r: 16, fill: '#fff', stroke: C.line })}${T(38, 589, '3 joints in stock. The 1350TST is built', { size: 15, fill: C.ink })}${T(38, 611, 'for lifted builds and ships today.', { size: 15, fill: C.ink })}</g>
${prods}
${R(20, 800, 420, 46, { r: 23, fill: '#fff', stroke: C.blue6, sw: 1.5 })}${T(44, 829, 'Ask about a part, your truck or an order', { size: 14, fill: '#8a95a8', id: 'aPh' })}${T(44, 829, '', { size: 14, fill: C.ink, id: 'aTxt' })}<circle cx="416" cy="823" r="16" fill="${C.blue6}"/><path d="M409 823h13m-5 -5 5 5-5 5" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>`);
};

// =============================================================== MY ACCOUNT (mobile, 402 × 874)
const account = () => {
  const steps = ['Placed', 'Paid', 'Shipped', 'Delivered'];
  const track = steps.map((l, i) => { const x = 58 + i * 96; return `<g id="tn${i}"><circle cx="${x}" cy="560" r="14" fill="#fff" stroke="${C.lineS}" stroke-width="2"/><g id="tnOn${i}" opacity="0"><circle cx="${x}" cy="560" r="14" fill="${C.ok}"/>${ic.check(x, 560)}</g>${T(x, 594, l, { size: 13, fill: C.ink3, anchor: 'middle', id: 'tl' + i })}</g>`; }).join('');
  return svg(402, 874, `${R(0, 0, 402, 874, { fill: C.paper2 })}
${R(0, 0, 402, 104, { fill: '#fff' })}<line x1="0" y1="104" x2="402" y2="104" stroke="${C.line}"/>${T(30, 34, '9:41', { size: 15, fam: F.h, w: 600 })}
<image href="${LOGO}" x="18" y="56" width="170" height="27"/>${ic.search(272, 58, C.ink2)}${ic.cart(310, 58, C.ink2)}<circle cx="336" cy="58" r="8" fill="${C.teal}"/>${T(336, 62, '1', { size: 10, fam: F.m, w: 600, fill: '#fff', anchor: 'middle' })}${R(352, 52, 36, 36, { r: 10, fill: C.blue6 })}<path d="M361 63h18M361 70h18M361 77h18" stroke="#fff" stroke-width="2"/>
<circle cx="46" cy="152" r="26" fill="${C.blue6}"/>${T(46, 159, 'AM', { size: 17, fam: F.h, w: 700, fill: '#fff', anchor: 'middle' })}${T(84, 148, 'Alex Morgan', { size: 20, fam: F.h, w: 700 })}${T(84, 170, 'alex@example.com', { size: 13, fill: C.ink3 })}${R(210, 156, 70, 20, { r: 10, fill: C.okbg })}${T(245, 170, '✓ Verified', { size: 11, fill: C.ok, anchor: 'middle' })}
${T(20, 230, 'Welcome back, Alex', { size: 24, fam: F.h, w: 800, ls: -0.5 })}${T(20, 254, 'Your orders, addresses and saved builds.', { size: 13.5, fill: C.ink3 })}
${[['1', 'Open order'], ['4', 'Orders placed'], ['1', 'Saved address']].map(([n, l], i) => `${R(20 + i * 124, 274, 114, 74, { r: 12, fill: '#fff', stroke: C.line })}${T(34 + i * 124, 306, n, { size: 24, fam: F.h, w: 700 })}${T(34 + i * 124, 332, l, { size: 11.5, fill: C.ink3 })}`).join('')}
${R(20, 368, 362, 310, { r: 16, fill: '#fff', stroke: C.line, filter: 'sh2' })}
${T(40, 402, 'Latest order', { size: 17, fam: F.h, w: 700 })}${T(362, 402, 'Invoice · Returns', { size: 12.5, fam: F.h, w: 600, fill: C.blue6, anchor: 'end' })}
${T(40, 438, 'UDS-261025-KX4R', { size: 15, fam: F.m, w: 600 })}${T(362, 438, '$456.28', { size: 18, fam: F.h, w: 700, anchor: 'end' })}
${T(40, 460, 'Oct 25, 2026 · U3194-2200 · Ford front driveshaft', { size: 11.5, fill: C.ink3 })}
${R(40, 474, 322, 44, { r: 10, fill: C.paper2 })}${shaft(201, 496, 250, 0.55)}
<line x1="58" y1="560" x2="346" y2="560" stroke="${C.line}" stroke-width="4"/><line id="tline" x1="58" y1="560" x2="346" y2="560" stroke="${C.ok}" stroke-width="4"/>${track}
${T(40, 636, 'UPS 1Z97Y41003 · Out for delivery', { size: 12, fam: F.m, fill: C.ink2, id: 'trkLine' })}
${R(20, 700, 362, 54, { r: 14, fill: C.blue6, id: 'buyAgain' })}${T(201, 733, 'Buy again', { size: 17, fam: F.h, w: 600, fill: '#fff', anchor: 'middle' })}
${R(20, 766, 362, 54, { r: 14, fill: '#fff', stroke: C.lineS })}${T(201, 799, 'Track package', { size: 17, fam: F.h, w: 600, fill: C.ink, anchor: 'middle' })}`, tubeDefs);
};

// =============================================================== PROBLEM: three supplier pages (640 × 400)
const supplier = (i) => {
  const page = [
    `${R(0, 0, 640, 400, { fill: '#525659' })}${R(120, 24, 400, 376, { fill: '#fff' })}${Array.from({ length: 14 }, (_, k) => R(150, 60 + k * 22, 160 + ((k * 73) % 180), 8, { r: 3, fill: '#d6d9de' })).join('')}${R(360, 60, 130, 90, { fill: '#e9ebee' })}${T(320, 392, 'Page 214 of 380', { size: 12, fill: '#888', anchor: 'middle' })}`,
    `${R(0, 0, 640, 400, { fill: '#fff' })}${R(40, 40, 230, 170, { r: 8, fill: '#f0f1f3' })}${T(300, 70, 'Front driveshaft, 4WD', { size: 22, fam: F.h, w: 700 })}${T(300, 98, 'Fits 2015 Ford F-150', { size: 15, fill: C.ink3 })}${R(300, 120, 150, 36, { r: 18, fill: '#fde8e8' })}${T(375, 144, 'Out of stock', { size: 16, fam: F.h, w: 700, fill: C.red, anchor: 'middle' })}${T(300, 186, 'Backorder: 3–5 weeks', { size: 15, fill: C.ink2 })}${R(40, 240, 560, 10, { r: 4, fill: '#eee' })}${R(40, 264, 420, 10, { r: 4, fill: '#eee' })}`,
    `${R(0, 0, 640, 400, { fill: '#fff' })}${T(40, 70, 'Driveline parts', { size: 22, fam: F.h, w: 700 })}${R(40, 96, 560, 120, { r: 10, fill: '#f6f6f6' })}${T(64, 140, 'Price:', { size: 16, fill: C.ink3 })}${T(120, 140, 'Call for quote', { size: 20, fam: F.h, w: 700, fill: C.ink })}${T(64, 180, '☎  Hold time ≈ 25 min', { size: 16, fill: C.red })}${R(40, 240, 560, 10, { r: 4, fill: '#eee' })}${R(40, 264, 380, 10, { r: 4, fill: '#eee' })}`,
  ][i];
  return svg(640, 400, page);
};

// =============================================================== write files
const out = { home: home(), finder: finder(), designer: designer(), assistant: assistant(), account: account(), sup0: supplier(0), sup1: supplier(1), sup2: supplier(2) };
const names = { home: '01-home-search', finder: '02-finder', designer: '03-designer', assistant: '04-assistant', account: '05-my-account-mobile', sup0: '00-problem-pdf', sup1: '00-problem-out-of-stock', sup2: '00-problem-call-for-price' };
for (const [k, v] of Object.entries(out)) fs.writeFileSync(path.join(ROOT, 'inputs/ui', names[k] + '.svg'), v);
fs.writeFileSync(path.join(ROOT, 'video/ui.js'), `window.UI=${JSON.stringify(out)};window.TARGETS=${JSON.stringify(TARGETS)};window.LOGO=${JSON.stringify(LOGO)};`);
console.log('ok', Object.keys(out).map((k) => `${k}:${(out[k].length / 1024).toFixed(0)}k`).join(' '));
