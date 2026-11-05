// Estimates a watermark template (median darkening on white backgrounds, 500x500 canvas anchored top-left),
// fits the watermark grey, and scores every listed photo for how strongly it carries that template.
// usage: node wm-template.js <list.txt> <out-prefix> [sampleEvery]
const fs = require('fs');
const { chromium } = require(process.env.PWC);
const [listFile, outPrefix, every = '2'] = process.argv.slice(2);
const files = fs.readFileSync(listFile, 'utf8').split('\n').filter(Boolean);
const sample = files.filter((f, i) => i % +every === 0);
const blank = outPrefix + '-blank.html';
fs.writeFileSync(blank, '<html><body></body></html>');

(async () => {
  const b = await chromium.launch({ executablePath: process.env.BX, args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const p = await b.newPage();
  await p.goto('file://' + blank);
  await p.evaluate(() => {
    const S = 500, BINS = 96;
    window.S = S; window.hist = new Uint16Array(S * S * BINS);
    window.load = async (f) => { const im = new Image(); im.src = 'file://' + f; await im.decode(); const c = new OffscreenCanvas(im.width, im.height); const x = c.getContext('2d', { willReadFrequently: true }); x.fillStyle = '#fff'; x.fillRect(0, 0, im.width, im.height); x.drawImage(im, 0, 0); return { w: im.width, h: im.height, d: x.getImageData(0, 0, im.width, im.height).data }; };
    window.lum = (o) => { const L = new Float32Array(o.w * o.h); for (let i = 0; i < L.length; i++) L[i] = (o.d[i * 4] + o.d[i * 4 + 1] + o.d[i * 4 + 2]) / 3; return L; };
    window.boxMax = (L, w, h, r) => {
      const t = new Float32Array(L.length), o = new Float32Array(L.length);
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { let v = 0; const a = Math.max(0, x - r), z = Math.min(w - 1, x + r); for (let k = a; k <= z; k++) { const q = L[y * w + k]; if (q > v) v = q; } t[y * w + x] = v; }
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { let v = 0; const a = Math.max(0, y - r), z = Math.min(h - 1, y + r); for (let k = a; k <= z; k++) { const q = t[k * w + x]; if (q > v) v = q; } o[y * w + x] = v; }
      return o;
    };
    window.accumulate = async (f) => {
      let o; try { o = await load(f); } catch (e) { return 0; }
      if (o.w > S || o.h > S) return 0;
      const L = lum(o), mx = boxMax(L, o.w, o.h, 7);
      for (let y = 0; y < o.h; y++) for (let x = 0; x < o.w; x++) { const i = y * o.w + x; if (mx[i] < 247) continue; const d = Math.round(mx[i] - L[i]); if (d >= 0 && d < BINS) hist[(y * S + x) * BINS + d]++; }
      return 1;
    };
    window.finish = () => {
      const M = new Float32Array(S * S);
      for (let i = 0; i < S * S; i++) { let n = 0; for (let k = 0; k < BINS; k++) n += hist[i * BINS + k]; if (n < 8) continue; let c = 0; for (let k = 0; k < BINS; k++) { c += hist[i * BINS + k]; if (c >= n / 2) { M[i] = k; break; } } }
      window.M = M; window.hist = null;
      let strong = 0, mm = 0; for (let i = 0; i < M.length; i++) { if (M[i] > 10) strong++; if (M[i] > mm) mm = M[i]; }
      return { strong, maxM: mm };
    };
    window.score = async (f) => {
      let o; try { o = await load(f); } catch (e) { return null; }
      if (o.w > S || o.h > S) return { s: -1, w: o.w, h: o.h };
      const L = lum(o), mx = boxMax(L, o.w, o.h, 7);
      let sdm = 0, smm = 0, sdd = 0;
      for (let y = 0; y < o.h; y += 2) for (let x = 0; x < o.w; x += 2) { const i = y * o.w + x; if (mx[i] < 247) continue; const d = Math.max(0, mx[i] - L[i]), m = M[y * S + x]; sdm += d * m; smm += m * m; sdd += d * d; }
      return { s: smm && sdd ? sdm / Math.sqrt(smm * sdd) : 0, w: o.w, h: o.h };
    };
    // grey that leaves no logo contrast on flat non-white areas
    window.residual = async (fs_, Wg) => {
      let s = 0, n = 0;
      for (const f of fs_) {
        let o; try { o = await load(f); } catch (e) { continue; } if (o.w > S || o.h > S) continue;
        const w = o.w, h = o.h, R = 6, L = new Float32Array(w * h);
        for (let i = 0; i < w * h; i++) { const m = M[Math.floor(i / w) * S + (i % w)], a = m > 1.5 ? Math.min(0.9, m / (255 - Wg)) : 0; L[i] = ((o.d[i * 4] + o.d[i * 4 + 1] + o.d[i * 4 + 2]) / 3 - a * Wg) / (1 - a); }
        for (let y = R; y < h - R; y += 2) for (let x = R; x < w - R; x += 2) {
          const m = M[y * S + x]; if (m < 0.6 * window.MAXM || L[y * w + x] > 170) continue;
          let bs = 0, bn = 0, lo = 255, hi = 0;
          for (let dy = -R; dy <= R; dy += 2) for (let dx = -R; dx <= R; dx += 2) { if (M[(y + dy) * S + x + dx] < 1) { const v = L[(y + dy) * w + x + dx]; bs += v; bn++; if (v < lo) lo = v; if (v > hi) hi = v; } }
          if (bn < 6 || hi - lo > 30) continue;
          s += L[y * w + x] - bs / bn; n++;
        }
      }
      return { e: n ? s / n : 0, n };
    };
    window.templatePng = (scale) => { const c = new OffscreenCanvas(S, S), x = c.getContext('2d'), im = x.createImageData(S, S); for (let i = 0; i < S * S; i++) { const v = Math.min(255, Math.round(M[i] * scale)); im.data.set([v, v, v, 255], i * 4); } x.putImageData(im, 0, 0); return c.convertToBlob({ type: 'image/png' }).then(bl => new Promise(r => { const fr = new FileReader(); fr.onload = () => r(fr.result); fr.readAsDataURL(bl); })); };
  });

  console.log('sample', sample.length, 'of', files.length);
  let used = 0;
  for (let i = 0; i < sample.length; i++) { used += await p.evaluate(f => accumulate(f), sample[i]); }
  const fin = await p.evaluate(() => finish());
  console.log('template', fin, 'from', used, 'photos');
  await p.evaluate(m => { window.MAXM = m; }, fin.maxM);

  const fit = sample.filter((f, i) => i % 4 === 0).slice(0, 150);
  let best = null;
  for (let Wg = 0; Wg <= 200; Wg += 10) { const r = await p.evaluate(([f, g]) => residual(f, g), [fit, Wg]); console.log(' grey', Wg, r.e.toFixed(2), r.n); if (r.n > 30 && (!best || Math.abs(r.e) < Math.abs(best.e))) best = { Wg, ...r }; }
  if (best) for (let Wg = Math.max(0, best.Wg - 8); Wg <= best.Wg + 8; Wg += 2) { const r = await p.evaluate(([f, g]) => residual(f, g), [fit, Wg]); if (r.n > 30 && Math.abs(r.e) < Math.abs(best.e)) best = { Wg, ...r }; }
  console.log('grey', best);

  const scale = Math.max(1, Math.floor(250 / Math.max(1, fin.maxM)));
  fs.writeFileSync(outPrefix + '.png', Buffer.from((await p.evaluate(s => templatePng(s), scale)).split(',')[1], 'base64'));
  const scores = {};
  for (let i = 0; i < files.length; i++) { scores[files[i]] = await p.evaluate(f => score(f), files[i]); if (i % 2000 === 0) console.log(' scored', i); }
  fs.writeFileSync(outPrefix + '.json', JSON.stringify({ grey: best ? best.Wg : null, residual: best ? best.e : null, scale, maxM: fin.maxM, size: 500, anchor: 'top-left', from: used }, null, 1));
  fs.writeFileSync(outPrefix + '-scores.json', JSON.stringify(scores));
  const vals = Object.values(scores).filter(Boolean).map(v => v.s);
  const buckets = [-1, 0, 0.05, 0.1, 0.15, 0.2, 0.3, 0.4, 1.01];
  for (let k = 0; k < buckets.length - 1; k++) console.log(`score ${buckets[k]}..${buckets[k + 1]}: ${vals.filter(v => v >= buckets[k] && v < buckets[k + 1]).length}`);
  await b.close();
})();
