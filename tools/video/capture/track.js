// green-screen corner tracker: decode at 540x960, mask green, PCA-orient, extremes -> 4 corners (in 1080x1920 coords), temporal smoothing
const { spawn } = require('child_process'); const fs = require('fs');
const [,, src, out, mode] = process.argv; const W = 540, H = 960, S = 2;
const ff = spawn('npx', ['remotion', 'ffmpeg', '-v', 'error', '-i', src, '-vf', `scale=${W}:${H}`, '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-'], { cwd: process.env.HOME + '/pti-work/reel/remotion' });
let buf = Buffer.alloc(0); const FS = W * H * 3; const frames = [];
const isKey = mode === 'blue' ? (r, g, b) => b > 120 && b > r + 50 && b > g + 30 : (r, g, b) => g > 90 && g > r + 40 && g > b + 30;
function corners(d) {
  let n = 0, sx = 0, sy = 0; const pts = [];
  for (let y = 0; y < H; y += 1) for (let x = 0; x < W; x += 1) { const i = (y * W + x) * 3; if (isKey(d[i], d[i+1], d[i+2])) { pts.push(x, y); sx += x; sy += y; n++; } }
  if (n < 500) return null;
  const cx = sx / n, cy = sy / n; let a = 0, b = 0, c = 0;
  for (let k = 0; k < pts.length; k += 2) { const dx = pts[k] - cx, dy = pts[k+1] - cy; a += dx*dx; b += dx*dy; c += dy*dy; }
  const th = 0.5 * Math.atan2(2*b, a - c); // major axis angle
  // axis u = minor (screen width dir), v = major (screen height dir)
  const vx = Math.cos(th), vy = Math.sin(th); const ux = -vy, uy = vx;
  let best = [[-1e9],[-1e9],[-1e9],[-1e9]];
  for (let k = 0; k < pts.length; k += 2) { const dx = pts[k] - cx, dy = pts[k+1] - cy; const u = dx*ux + dy*uy, v = dx*vx + dy*vy;
    const sc = [-u - v, u - v, u + v, -u + v]; for (let q = 0; q < 4; q++) if (sc[q] > best[q][0]) best[q] = [sc[q], pts[k], pts[k+1]]; }
  let cs = best.map(p => [p[1] * S, p[2] * S]);
  // make order TL,TR,BR,BL in image space: sort by angle around centre starting top-left
  const C = [cx * S, cy * S]; cs.sort((p, q) => Math.atan2(p[1]-C[1], p[0]-C[0]) - Math.atan2(q[1]-C[1], q[0]-C[0])); // -pi..pi : TL first-ish
  // rotate so first = min(x+y)
  let i0 = 0; cs.forEach((p, i) => { if (p[0] + p[1] < cs[i0][0] + cs[i0][1]) i0 = i; }); cs = cs.slice(i0).concat(cs.slice(0, i0));
  return cs.flat();
}
ff.stdout.on('data', (ch) => { buf = Buffer.concat([buf, ch]); while (buf.length >= FS) { frames.push(corners(buf.subarray(0, FS))); buf = buf.subarray(FS); } });
ff.on('close', () => {
  // fill gaps + smooth (5-tap)
  for (let i = 0; i < frames.length; i++) if (!frames[i]) frames[i] = frames[i-1] || frames.find(Boolean);
  const sm = frames.map((f, i) => f.map((_, j) => { let s = 0, w = 0; for (let k = -2; k <= 2; k++) { const g = frames[i+k]; if (g) { const ww = 3 - Math.abs(k); s += g[j] * ww; w += ww; } } return +(s / w).toFixed(2); }));
  fs.writeFileSync(out, JSON.stringify(sm)); console.log(src.split('/').pop(), 'frames', sm.length, 'f0', sm[0].map(v => v|0).join(','));
});
