// Rasterize each designer section (everything drawn = barrier), flood the background from the four corners,
// and vectorize what is left (enclosed metal) into evenodd fill paths in the section's own viewBox units.
const { chromium } = require(process.env.PWC); const fs = require('fs'); const path = require('path');
const SRC = process.env.SRC, OUT = process.env.OUT, S = 24;
(async () => {
  const b = await chromium.launch({ headless: true }); const p = await b.newPage({ viewport: { width: 400, height: 400 } });
  await p.setContent('<html><body></body></html>');
  const files = fs.readdirSync(SRC).filter(f => f.endsWith('.svg')).sort();
  const report = {};
  for (const f of files) {
    const code = f.replace('.svg', ''); const svg = fs.readFileSync(path.join(SRC, f), 'utf8');
    const res = await p.evaluate(async ({ svg, S }) => { svg = String(svg);
      const vb = svg.match(/viewBox="([^"]+)"/)[1].split(/[\s,]+/).map(Number); const W = vb[2], H = vb[3];
      const style = '<style>[stroke]:not([stroke="none"]):not([stroke="null"]){stroke:#000!important}[style*="stroke: rgb"],[style*="stroke:rgb"],[style*="stroke: #"],[style*="stroke:#"]{stroke:#000!important}.points,.points *{display:none!important}[fill]:not([fill="none"]){fill:#000!important}[style*="fill: rgb(255"]{fill:#000!important}[style*="fill:rgb(255"]{fill:#000!important}[style*="fill: #fff"]{fill:#000!important}</style>';
      svg = svg.replace(/<rect\b[^>]*canvas_background[^>]*\/>/g, '').replace(/<rect\b[^>]*canvas_background[^>]*>\s*<\/rect>/g, '');
      const src = svg.replace(/(<svg\b[^>]*>)/, '$1' + style).replace(/<svg\b([^>]*)>/, (m, a) => '<svg' + a.replace(/\swidth="[^"]*"/, '').replace(/\sheight="[^"]*"/, '') + ` width="${W * S}" height="${H * S}">`);
      const img = new Image(); img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(src);
      await new Promise((ok, no) => { img.onload = ok; img.onerror = () => no(new Error('img')); });
      const cw = W * S, ch = H * S; const c = document.createElement('canvas'); c.width = cw; c.height = ch;
      const g = c.getContext('2d', { willReadFrequently: true }); g.fillStyle = '#fff'; g.fillRect(0, 0, cw, ch); g.drawImage(img, 0, 0, cw, ch);
      const d = g.getImageData(0, 0, cw, ch).data; const barrier = new Uint8Array(cw * ch);
      for (let i = 0; i < cw * ch; i++) barrier[i] = d[i * 4] < 160 ? 1 : 0;
      // flood the background from the corners
      const bg = new Uint8Array(cw * ch); const stack = [];
      for (const [x, y] of [[0, 0], [cw - 1, 0], [0, ch - 1], [cw - 1, ch - 1]]) { const i = y * cw + x; if (!barrier[i] && !bg[i]) { bg[i] = 1; stack.push(i); } }
      while (stack.length) { const i = stack.pop(); const x = i % cw, y = (i / cw) | 0;
        if (x > 0 && !barrier[i - 1] && !bg[i - 1]) { bg[i - 1] = 1; stack.push(i - 1); }
        if (x < cw - 1 && !barrier[i + 1] && !bg[i + 1]) { bg[i + 1] = 1; stack.push(i + 1); }
        if (y > 0 && !barrier[i - cw] && !bg[i - cw]) { bg[i - cw] = 1; stack.push(i - cw); }
        if (y < ch - 1 && !barrier[i + cw] && !bg[i + cw]) { bg[i + cw] = 1; stack.push(i + cw); } }
      // regions: label every open (non-barrier) component; propagate labels through the line pixels so
      // neighbouring regions are found; depth = how many outlines you cross from the outside.
      // odd depth = metal (filled), even depth = a hole / cavity drawn inside metal (left empty).
      const label = new Int32Array(cw * ch).fill(-1); let nlab = 0; const q = [];
      for (let i = 0; i < cw * ch; i++) if (!barrier[i] && label[i] < 0) { const L = nlab++; label[i] = L; q.length = 0; q.push(i);
        while (q.length) { const j = q.pop(); const x = j % cw, y = (j / cw) | 0;
          if (x > 0 && !barrier[j - 1] && label[j - 1] < 0) { label[j - 1] = L; q.push(j - 1); }
          if (x < cw - 1 && !barrier[j + 1] && label[j + 1] < 0) { label[j + 1] = L; q.push(j + 1); }
          if (y > 0 && !barrier[j - cw] && label[j - cw] < 0) { label[j - cw] = L; q.push(j - cw); }
          if (y < ch - 1 && !barrier[j + cw] && label[j + cw] < 0) { label[j + cw] = L; q.push(j + cw); } } }
      const isBg = new Uint8Array(nlab); for (let i = 0; i < cw * ch; i++) if (bg[i]) isBg[label[i]] = 1;
      // breadth-first front through the barrier pixels (nearest-region labelling)
      let front = []; for (let i = 0; i < cw * ch; i++) if (!barrier[i]) front.push(i);
      const adj = new Map(); const link = (a, b) => { if (a === b) return; if (!adj.has(a)) adj.set(a, new Set()); if (!adj.has(b)) adj.set(b, new Set()); adj.get(a).add(b); adj.get(b).add(a); };
      while (front.length) { const next = [];
        for (const j of front) { const L = label[j]; const x = j % cw, y = (j / cw) | 0;
          for (const k of [x > 0 ? j - 1 : -1, x < cw - 1 ? j + 1 : -1, y > 0 ? j - cw : -1, y < ch - 1 ? j + cw : -1]) { if (k < 0) continue;
            if (label[k] < 0) { label[k] = L; next.push(k); } else if (label[k] !== L) link(L, label[k]); } }
        front = next; }
      const depth = new Int32Array(nlab).fill(-1); const dq = [];
      for (let L = 0; L < nlab; L++) if (isBg[L]) { depth[L] = 0; dq.push(L); }
      while (dq.length) { const L = dq.shift(); for (const M of (adj.get(L) || [])) if (depth[M] < 0) { depth[M] = depth[L] + 1; dq.push(M); } }
      const inside = (x, y) => x >= 0 && y >= 0 && x < cw && y < ch && depth[label[y * cw + x]] % 2 === 1;
      // directed boundary edges, inside on the left, linked into loops
      const edges = new Map(); let count = 0;
      const add = (x1, y1, x2, y2) => { edges.set(x1 + ',' + y1 + '>' + count, [x1, y1, x2, y2]); count++; };
      const starts = new Map();
      const push = (x1, y1, x2, y2) => { const k = x1 + ',' + y1; if (!starts.has(k)) starts.set(k, []); starts.get(k).push([x2, y2]); };
      let ninside = 0;
      for (let y = 0; y < ch; y++) for (let x = 0; x < cw; x++) if (inside(x, y)) { ninside++;
        if (!inside(x, y - 1)) push(x, y, x + 1, y);           // top edge, left→right
        if (!inside(x + 1, y)) push(x + 1, y, x + 1, y + 1);   // right edge, down
        if (!inside(x, y + 1)) push(x + 1, y + 1, x, y + 1);   // bottom edge, right→left
        if (!inside(x - 1, y)) push(x, y + 1, x, y);           // left edge, up
      }
      const loops = [];
      for (const [k, list] of starts) { while (list.length) {
        const [sx, sy] = k.split(',').map(Number); let cx = sx, cy = sy; const ring = [[cx, cy]]; let nxt = list.pop();
        let guard = 0;
        while (nxt && !(nxt[0] === sx && nxt[1] === sy) && guard++ < 5e6) { ring.push(nxt); const l2 = starts.get(nxt[0] + ',' + nxt[1]); nxt = l2 && l2.length ? l2.pop() : null; }
        if (ring.length > 3) loops.push(ring);
      } }
      // Douglas–Peucker, tolerance 0.35 px, then to viewBox units
      const rdp = (pts, eps) => { if (pts.length < 3) return pts; const [ax, ay] = pts[0], [bx, by] = pts[pts.length - 1]; let idx = -1, dmax = 0;
        const L = Math.hypot(bx - ax, by - ay) || 1e-9;
        for (let i = 1; i < pts.length - 1; i++) { const [px, py] = pts[i]; const dd = Math.abs((bx - ax) * (ay - py) - (ax - px) * (by - ay)) / L; if (dd > dmax) { dmax = dd; idx = i; } }
        if (dmax > eps) { const a = rdp(pts.slice(0, idx + 1), eps), bb = rdp(pts.slice(idx), eps); return a.slice(0, -1).concat(bb); } return [pts[0], pts[pts.length - 1]]; };
      const out = []; let area = 0;
      for (const ring of loops) { let far = 0, fd = 0; for (let i = 1; i < ring.length; i++) { const dd = (ring[i][0] - ring[0][0]) ** 2 + (ring[i][1] - ring[0][1]) ** 2; if (dd > fd) { fd = dd; far = i; } }
        const closed = ring.concat([ring[0]]); const r = rdp(closed.slice(0, far + 1), 1.2).slice(0, -1).concat(rdp(closed.slice(far), 1.2)); if (r.length < 4) continue;
        let a = 0; for (let i = 0; i < r.length - 1; i++) a += r[i][0] * r[i + 1][1] - r[i + 1][0] * r[i][1]; area += a / 2;
        out.push('M' + r.slice(0, -1).map(([x, y]) => (x / S).toFixed(2) + ',' + (y / S).toFixed(2)).join('L') + 'Z'); }
      return { W, H, inside: ninside / (cw * ch), regions: nlab, loops: out.length, d: out.join('') };
    }, { svg, S });
    fs.writeFileSync(path.join(OUT, code + '.frag'), `<g class="uds-fill"><path fill-rule="evenodd" d="${res.d}"/></g>`);
    report[code] = { inside: +res.inside.toFixed(3), loops: res.loops, bytes: res.d.length };
    process.stdout.write(code + " loops=" + res.loops + " inside=" + res.inside + " ");
  }
  fs.writeFileSync(path.join(OUT, '_report.json'), JSON.stringify(report, null, 1)); console.log('\ndone', files.length);
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
