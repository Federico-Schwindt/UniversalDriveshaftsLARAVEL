// Local PTI watermark removal test (reverse alpha blending). Reads copies only; writes results to ./out.
// usage: node dewm.js <pti-dir> <outdir> <test files...>
const fs = require('fs'), path = require('path');
const { chromium } = require(process.env.PWC);
const [dir, outDir, ...tests] = process.argv.slice(2);
fs.mkdirSync(outDir, { recursive: true });
const all = fs.readdirSync(dir).filter(f => /\.jpe?g$/i.test(f)).sort();
// estimation set: every 2nd file (≈670 photos), never the test photos
const est = all.filter((f, i) => i % 2 === 0 && !tests.includes(f));
const url = f => 'data:image/jpeg;base64,' + fs.readFileSync(path.join(dir, f)).toString('base64');

(async () => {
  const b = await chromium.launch({ executablePath: process.env.BX, args: ['--no-sandbox'] });
  const p = await b.newPage();
  p.on('console', m => console.log('[page]', m.text()));
  await p.setContent('<html><body></body></html>');
  await p.evaluate(() => {
    const S = 500, BINS = 96;
    window.S = S;
    window.hist = new Uint16Array(S * S * BINS);
    window.load = async (u) => { const im = new Image(); im.src = u; await im.decode(); const c = new OffscreenCanvas(im.width, im.height); const x = c.getContext('2d'); x.drawImage(im, 0, 0); return { w: im.width, h: im.height, d: x.getImageData(0, 0, im.width, im.height).data }; };
    window.lum = (o) => { const L = new Float32Array(o.w * o.h); for (let i = 0; i < L.length; i++) L[i] = (o.d[i * 4] + o.d[i * 4 + 1] + o.d[i * 4 + 2]) / 3; return L; };
    // separable box max / min, radius r
    window.boxOp = (L, w, h, r, max) => {
      const t = new Float32Array(L.length), o = new Float32Array(L.length), f = max ? Math.max : Math.min;
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { let v = L[y * w + x]; for (let k = Math.max(0, x - r); k <= Math.min(w - 1, x + r); k++) v = f(v, L[y * w + k]); t[y * w + x] = v; }
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { let v = t[y * w + x]; for (let k = Math.max(0, y - r); k <= Math.min(h - 1, y + r); k++) v = f(v, t[k * w + x]); o[y * w + x] = v; }
      return o;
    };
    window.accumulate = async (u) => {
      const o = await load(u); if (o.w > S || o.h > S) return;
      const L = lum(o), mx = boxOp(L, o.w, o.h, 7, true);
      for (let y = 0; y < o.h; y++) for (let x = 0; x < o.w; x++) {
        const i = y * o.w + x; if (mx[i] < 247) continue;      // only on white background
        const d = Math.round(mx[i] - L[i]); if (d < 0 || d >= BINS) continue;
        hist[(y * S + x) * BINS + d]++;
      }
    };
    window.finish = () => {
      const M = new Float32Array(S * S), N = new Uint16Array(S * S);
      for (let i = 0; i < S * S; i++) {
        let n = 0; for (let k = 0; k < BINS; k++) n += hist[i * BINS + k];
        N[i] = n; if (n < 8) continue;
        let c = 0, half = n / 2; for (let k = 0; k < BINS; k++) { c += hist[i * BINS + k]; if (c >= half) { M[i] = k; break; } }
      }
      window.M = M; window.N = N;
      let strong = 0; for (let i = 0; i < M.length; i++) if (M[i] > 15) strong++;
      let mm = 0; for (let i = 0; i < M.length; i++) if (M[i] > mm) mm = M[i]; return { strong, maxM: mm };
    };
    window.mapPng = () => { const c = new OffscreenCanvas(S, S), x = c.getContext('2d'), im = x.createImageData(S, S); for (let i = 0; i < S * S; i++) { const v = 255 - Math.min(255, M[i] * 4); im.data.set([v, v, v, 255], i * 4); } x.putImageData(im, 0, 0); return c.convertToBlob({ type: 'image/png' }).then(bl => new Promise(r => { const fr = new FileReader(); fr.onload = () => r(fr.result); fr.readAsDataURL(bl); })); };
    // reverse alpha blend with watermark grey Wg: I = (J - a*Wg) / (1 - a), a = M / (255 - Wg)
    // where this photo sits on the 500x500 watermark canvas: best match of its logo shadows on white areas
    window.align = (o) => {
      const L = lum(o), mx = boxOp(L, o.w, o.h, 7, true), D = new Float32Array(L.length);
      for (let i = 0; i < L.length; i++) D[i] = mx[i] >= 247 ? Math.max(0, mx[i] - L[i]) : -1;
      let best = { dx: 0, dy: 0, score: -1 };
      for (let dy = 0; dy <= S - o.h; dy++) for (let dx = 0; dx <= S - o.w; dx++) {
        let sdm = 0, smm = 0, sdd = 0;
        for (let y = 0; y < o.h; y += 2) for (let x = 0; x < o.w; x += 2) { const d = D[y * o.w + x]; if (d < 0) continue; const m = M[(y + dy) * S + x + dx]; sdm += d * m; smm += m * m; sdd += d * d; }
        const sc = smm && sdd ? sdm / Math.sqrt(smm * sdd) : 0;
        if (sc > best.score) best = { dx, dy, score: sc };
      }
      return best;
    };
    window.unblend = (o, Wg, off) => {
      const out = new Uint8ClampedArray(o.d.length), ox = off ? off.dx : 0, oy = off ? off.dy : 0;
      for (let y = 0; y < o.h; y++) for (let x = 0; x < o.w; x++) {
        const i = y * o.w + x, m = M[(y + oy) * S + x + ox], a = m > 1.5 ? Math.min(0.9, m / (255 - Wg)) : 0;
        for (let c = 0; c < 3; c++) out[i * 4 + c] = a ? (o.d[i * 4 + c] - a * Wg) / (1 - a) : o.d[i * 4 + c];
        out[i * 4 + 3] = 255;
      }
      return out;
    };
    // residual logo contrast on dark/mid areas for a candidate grey: mean(I' on logo) - mean(I' around it, off-logo)
    window.residual = async (us, Wg) => {
      let s = 0, n = 0;
      for (const u of us) {
        const o = await load(u); if (o.w > S || o.h > S) continue;
        const r = unblend(o, Wg), w = o.w, h = o.h, R = 6;
        const L = new Float32Array(w * h); for (let i = 0; i < L.length; i++) L[i] = (r[i * 4] + r[i * 4 + 1] + r[i * 4 + 2]) / 3;
        for (let y = R; y < h - R; y += 2) for (let x = R; x < w - R; x += 2) {
          const m = M[y * S + x]; if (m < 18 || L[y * w + x] > 170) continue;   // strong logo pixel on a non-white part
          let bs = 0, bn = 0, lo = 255, hi = 0;
          for (let dy = -R; dy <= R; dy += 2) for (let dx = -R; dx <= R; dx += 2) { const j = (y + dy) * w + x + dx; if (M[(y + dy) * S + x + dx] < 2) { bs += L[j]; bn++; lo = Math.min(lo, L[j]); hi = Math.max(hi, L[j]); } }
          if (bn < 6 || hi - lo > 30) continue;                                   // flat surroundings only
          s += L[y * w + x] - bs / bn; n++;
        }
      }
      return { e: n ? s / n : 0, n };
    };
    // JPEG ringing along the logo outline: 3x3 median only inside the (slightly grown) logo area, then pure white on white backgrounds
    window.polish = (o, data, off) => {
      const w = o.w, h = o.h, ox = off ? off.dx : 0, oy = off ? off.dy : 0, out = new Uint8ClampedArray(data);
      const band = new Uint8Array(w * h);
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
        let on = 0; for (let dy = -1; dy <= 1 && !on; dy++) for (let dx = -1; dx <= 1; dx++) { const yy = y + dy + oy, xx = x + dx + ox; if (yy >= 0 && yy < S && xx >= 0 && xx < S && M[yy * S + xx] > 2) { on = 1; break; } }
        band[y * w + x] = on;
      }
      const mx = boxOp(lum(o), w, h, 7, true), v = new Array(9);
      for (let y = 1; y < h - 1; y++) for (let x = 1; x < w - 1; x++) {
        const i = y * w + x; if (!band[i]) continue;
        for (let c = 0; c < 3; c++) { let k = 0; for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) v[k++] = data[((y + dy) * w + x + dx) * 4 + c]; v.sort((a, b) => a - b); out[i * 4 + c] = v[4]; }
        if (mx[i] >= 247 && Math.min(out[i * 4], out[i * 4 + 1], out[i * 4 + 2]) >= 232) { out[i * 4] = out[i * 4 + 1] = out[i * 4 + 2] = 255; }
      }
      return out;
    };
    window.encode = (o, data) => { const c = new OffscreenCanvas(o.w, o.h), x = c.getContext('2d'); x.putImageData(new ImageData(data, o.w, o.h), 0, 0); return c.convertToBlob({ type: 'image/png' }).then(bl => new Promise(r => { const fr = new FileReader(); fr.onload = () => r(fr.result); fr.readAsDataURL(bl); })); };
  });

  console.log('estimating from', est.length, 'photos');
  for (let i = 0; i < est.length; i++) { await p.evaluate(u => accumulate(u), url(est[i])); if (i % 100 === 0) console.log(' ', i); }
  console.log('map', await p.evaluate(() => finish()));
  fs.writeFileSync(path.join(outDir, 'watermark-map.png'), Buffer.from((await p.evaluate(() => mapPng())).split(',')[1], 'base64'));

  // grey of the watermark: the value that leaves no logo contrast on flat non-white areas
  const fitSet = est.filter((f, i) => i % 3 === 0).slice(0, 120).map(url);
  let best = null;
  if (process.env.GREY) best = { Wg: +process.env.GREY, e: 0, n: 0 }; else {
  for (let Wg = 40; Wg <= 220; Wg += 10) {
    const r = await p.evaluate(([us, g]) => residual(us, g), [fitSet, Wg]);
    console.log('W', Wg, 'residual', r.e.toFixed(2), 'n', r.n);
    if (r.n > 50 && (!best || Math.abs(r.e) < Math.abs(best.e))) best = { Wg, ...r };
  }
  for (let Wg = best.Wg - 9; Wg <= best.Wg + 9; Wg += 3) {
    const r = await p.evaluate(([us, g]) => residual(us, g), [fitSet, Wg]);
    if (r.n > 50 && Math.abs(r.e) < Math.abs(best.e)) best = { Wg, ...r };
  }
  }
  console.log('chosen grey', best.Wg, 'residual', best.e.toFixed(2));

  for (const f of tests) {
    const res = await p.evaluate(async ([u, g]) => { const o = await load(u); const off = align(o); return { png: await encode(o, polish(o, unblend(o, g, off), off)), off }; }, [url(f), best.Wg]);
    console.log(f, 'offset', JSON.stringify(res.off));
    fs.writeFileSync(path.join(outDir, f.replace(/\.jpe?g$/i, '-clean.png')), Buffer.from(res.png.split(',')[1], 'base64'));
    fs.copyFileSync(path.join(dir, f), path.join(outDir, f));
  }
  fs.writeFileSync(path.join(outDir, 'params.json'), JSON.stringify({ grey: best.Wg, residual: best.e, estimation_photos: est.length }, null, 1));
  await b.close();
})();
