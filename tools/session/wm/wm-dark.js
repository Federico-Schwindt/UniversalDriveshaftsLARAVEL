// Do the faint marks on dark driveshaft tubes follow the PTI parts template grid? Correlates the lightening on
// dark areas (J - local min) with the parts template at offset 0 and at the best offset in one tile period.
// usage: node wm-dark.js <template.png> <scale> <list.txt> <n>
const fs = require('fs');
const { chromium } = require(process.env.PWC);
const [tpl, scale, listFile, n] = process.argv.slice(2);
const files = fs.readFileSync(listFile, 'utf8').split('\n').filter(Boolean).filter((f, i) => i % Math.floor(15000 / +n) === 0).slice(0, +n);
const blank = '/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/wm/blank2.html';
fs.writeFileSync(blank, '<html><body></body></html>');
(async () => {
  const b = await chromium.launch({ executablePath: process.env.BX, args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const p = await b.newPage(); await p.goto('file://' + blank);
  const res = await p.evaluate(async ([tpl, scale, files]) => {
    const S = 500;
    const load = async (f) => { const im = new Image(); im.src = 'file://' + f; await im.decode(); const c = new OffscreenCanvas(im.width, im.height); const x = c.getContext('2d', { willReadFrequently: true }); x.fillStyle = '#fff'; x.fillRect(0, 0, im.width, im.height); x.drawImage(im, 0, 0); return { w: im.width, h: im.height, d: x.getImageData(0, 0, im.width, im.height).data }; };
    const t = await load(tpl); const M = new Float32Array(S * S); for (let i = 0; i < S * S; i++) M[i] = t.d[i * 4] / scale;
    const box = (L, w, h, r, f) => { const a = new Float32Array(L.length), o = new Float32Array(L.length); for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { let v = L[y * w + x]; for (let k = Math.max(0, x - r); k <= Math.min(w - 1, x + r); k++) v = f(v, L[y * w + k]); a[y * w + x] = v; } for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { let v = a[y * w + x]; for (let k = Math.max(0, y - r); k <= Math.min(h - 1, y + r); k++) v = f(v, a[k * w + x]); o[y * w + x] = v; } return o; };
    const corr = (D, w, h, dx, dy) => { let sdm = 0, smm = 0, sdd = 0; for (let y = 0; y < h; y += 2) for (let x = 0; x < w; x += 2) { const d = D[y * w + x]; if (d < 0) continue; const yy = y + dy, xx = x + dx; if (yy < 0 || xx < 0 || yy >= S || xx >= S) continue; const m = M[yy * S + xx]; sdm += d * m; smm += m * m; sdd += d * d; } return smm && sdd ? sdm / Math.sqrt(smm * sdd) : 0; };
    const out = [];
    for (const f of files) {
      let o; try { o = await load(f); } catch (e) { continue; } if (o.w > S || o.h > S) continue;
      const L = new Float32Array(o.w * o.h); for (let i = 0; i < L.length; i++) L[i] = (o.d[i * 4] + o.d[i * 4 + 1] + o.d[i * 4 + 2]) / 3;
      const mn = box(L, o.w, o.h, 7, Math.min), D = new Float32Array(L.length);
      let dark = 0; for (let i = 0; i < L.length; i++) { const d = L[i] - mn[i]; D[i] = mn[i] < 80 && d >= 0 && d < 60 ? d : -1; if (D[i] >= 0) dark++; }
      if (dark < 3000) continue;
      const s0 = corr(D, o.w, o.h, 0, 0);
      let best = { s: -1, dx: 0, dy: 0 };
      for (let dy = -44; dy <= 44; dy += 4) for (let dx = -44; dx <= 44; dx += 4) { const s = corr(D, o.w, o.h, dx, dy); if (s > best.s) best = { s, dx, dy }; }
      out.push({ f: f.split('/').slice(-2).join('/'), s0: +s0.toFixed(3), best });
    }
    return out;
  }, [tpl, +scale, files]);
  res.forEach(r => console.log(r.f, 's0', r.s0, 'best', r.best.s.toFixed(3), r.best.dx, r.best.dy));
  const s0 = res.map(r => r.s0).sort((a, b) => a - b);
  console.log('n', res.length, 'median s0', s0[Math.floor(s0.length / 2)], 'best at (0,0):', res.filter(r => r.best.dx === 0 && r.best.dy === 0).length);
  await b.close();
})();
