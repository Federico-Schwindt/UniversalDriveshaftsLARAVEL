// energy curve + BPM estimate of an mp3: decode to mono 11025 Hz PCM via ffmpeg, onset envelope, autocorrelation 70-180 BPM
const { spawnSync } = require('child_process'); const path = require('path');
const FF = path.join(process.env.HOME, 'pti-work/reel/remotion/node_modules/@remotion/compositor-linux-x64-gnu/ffmpeg');
const SR = 11025;
for (const file of process.argv.slice(2)) {
  const r = spawnSync(FF, ['-v', 'error', '-nostdin', '-i', file, '-ac', '1', '-ar', String(SR), '-f', 'wav', '-acodec', 'pcm_s16le', '-'], { maxBuffer: 1 << 30 });
  const off = r.stdout.indexOf('data') + 8; const i16 = new Int16Array(r.stdout.buffer.slice(r.stdout.byteOffset + off, r.stdout.byteOffset + r.stdout.length - ((r.stdout.length - off) % 2))); const pcm = Float32Array.from(i16, (v) => v / 32768); if (!pcm.length) { console.log('decode failed', String(r.stderr)); continue; }
  const hop = 256, n = Math.floor(pcm.length / hop), env = new Float32Array(n);
  for (let i = 0; i < n; i++) { let e = 0; for (let j = 0; j < hop; j++) e += pcm[i * hop + j] ** 2; env[i] = Math.sqrt(e / hop); }
  const onset = new Float32Array(n); for (let i = 1; i < n; i++) onset[i] = Math.max(0, env[i] - env[i - 1]);
  const fps = SR / hop; let best = 0, bestBpm = 0;
  for (let bpm = 70; bpm <= 180; bpm += 0.25) { const lag = Math.round((60 / bpm) * fps); let s = 0; for (let i = lag; i < n; i++) s += onset[i] * onset[i - lag]; s /= (n - lag); if (s > best) { best = s; bestBpm = bpm; } }
  // first strong beat: earliest onset peak above 60% of the max in the first 8 s
  const lag = (60 / bestBpm) * fps; let mx = 0; for (let i = 0; i < Math.min(n, fps * 8); i++) mx = Math.max(mx, onset[i]);
  let first = 0; for (let i = 0; i < n; i++) if (onset[i] > mx * 0.6) { first = i / fps; break; }
  // phase: which offset within a beat period maximises summed onset
  let bestPh = 0, bestS = 0; for (let ph = 0; ph < lag; ph += 0.5) { let s = 0; for (let k = ph; k < n; k += lag) s += onset[Math.round(k)] || 0; if (s > bestS) { bestS = s; bestPh = ph; } }
  // energy per 5 s
  const sec = Math.floor(n / fps), bands = []; for (let t = 0; t < sec; t += 5) { let e = 0, c = 0; for (let i = Math.floor(t * fps); i < Math.min(n, Math.floor((t + 5) * fps)); i++) { e += env[i]; c++; } bands.push((e / c * 100).toFixed(1)); }
  console.log(path.basename(file), `dur=${(pcm.length / SR).toFixed(1)}s bpm=${bestBpm} phase=${(bestPh / fps).toFixed(3)}s firstStrong=${first.toFixed(2)}s`); console.log('  energy/5s:', bands.join(' '));
}
