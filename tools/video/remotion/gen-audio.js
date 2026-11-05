// Synthesised audio for the launch video (no samples, no licences): a 50 s music bed + click + pop, 44.1 kHz stereo WAV.
const fs = require('fs');
const SR = 44100;
const wav = (file, L, R) => {
  const n = L.length, buf = Buffer.alloc(44 + n * 4);
  buf.write('RIFF', 0); buf.writeUInt32LE(36 + n * 4, 4); buf.write('WAVE', 8); buf.write('fmt ', 12); buf.writeUInt32LE(16, 16);
  buf.writeUInt16LE(1, 20); buf.writeUInt16LE(2, 22); buf.writeUInt32LE(SR, 24); buf.writeUInt32LE(SR * 4, 28); buf.writeUInt16LE(4, 32); buf.writeUInt16LE(16, 34);
  buf.write('data', 36); buf.writeUInt32LE(n * 4, 40);
  for (let i = 0; i < n; i++) { buf.writeInt16LE(Math.max(-1, Math.min(1, L[i])) * 32767 | 0, 44 + i * 4); buf.writeInt16LE(Math.max(-1, Math.min(1, R[i])) * 32767 | 0, 46 + i * 4); }
  fs.writeFileSync(file, buf);
};
const midi = (m) => 440 * Math.pow(2, (m - 69) / 12);
let seed = 7; const rnd = () => ((seed = (seed * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff) * 2 - 1;

// ---- music bed ----
const DUR = 50.5, N = Math.floor(DUR * SR), BPM = 112, BEAT = 60 / BPM, BAR = BEAT * 4;
const L = new Float32Array(N), R = new Float32Array(N);
const chords = [[57, 60, 64], [53, 57, 60], [48, 52, 55], [55, 59, 62]]; // Am F C G
const roots = [45, 41, 36, 43];
for (let i = 0; i < N; i++) {
  const t = i / SR, bar = Math.floor(t / BAR) % 4, inBar = t % BAR, beat = t % BEAT;
  // energy: soft intro, full from the product scenes, gentle outro
  const energy = Math.min(1, t / 8) * (t > 46 ? Math.max(0, (DUR - t) / 4.5) : 1);
  const lift = t > 10 ? 1 : 0.55;
  // warm pad: detuned sines + slow swell per bar
  let pad = 0;
  for (const m of chords[bar]) { const fq = midi(m); pad += Math.sin(2 * Math.PI * fq * t) + 0.5 * Math.sin(2 * Math.PI * fq * 1.004 * t) + 0.25 * Math.sin(2 * Math.PI * fq * 2 * t); }
  pad *= 0.045 * (0.75 + 0.25 * Math.sin(Math.PI * inBar / BAR));
  // sub bass on every beat
  const bf = midi(roots[bar]); const bEnv = Math.exp(-beat * 3.2);
  const bass = 0.20 * Math.sin(2 * Math.PI * bf * t) * (0.4 + 0.6 * bEnv) * lift;
  // soft kick on 1 and 3
  const kb = t % (BEAT * 2); const kEnv = Math.exp(-kb * 22);
  const kick = t > 4 ? 0.42 * Math.sin(2 * Math.PI * (48 + 110 * Math.exp(-kb * 35)) * kb) * kEnv : 0;
  // closed hats on the off-beats
  const hb = (t + BEAT / 2) % BEAT; const hEnv = Math.exp(-hb * 90);
  const hat = t > 10 ? 0.05 * rnd() * hEnv : 0;
  // gentle arpeggio in the product section
  const step = BEAT / 2, ai = Math.floor(t / step) % 3, ab = t % step;
  const arp = t > 10 && t < 46 ? 0.05 * Math.sin(2 * Math.PI * midi(chords[bar][ai] + 12) * t) * Math.exp(-ab * 7) : 0;
  const s = (pad + bass + kick + arp) * energy;
  L[i] = s + hat * energy * 0.8; R[i] = s + hat * energy * 1.2;
}
// light stereo widening on the pad via a tiny delay on the right channel
for (let i = N - 1; i > 300; i--) R[i] = R[i] * 0.85 + L[i - 300] * 0.15;
wav('public/launch-music.wav', L, R);

// ---- click: short filtered noise + high tick ----
const cN = Math.floor(0.05 * SR), cL = new Float32Array(cN), cR = new Float32Array(cN);
for (let i = 0; i < cN; i++) { const t = i / SR; const v = (0.35 * rnd() * Math.exp(-t * 260) + 0.5 * Math.sin(2 * Math.PI * 2600 * t) * Math.exp(-t * 180)); cL[i] = v; cR[i] = v; }
wav('public/launch-click.wav', cL, cR);

// ---- pop: soft rising blip for checkmarks ----
const pN = Math.floor(0.18 * SR), pL = new Float32Array(pN), pR = new Float32Array(pN);
for (let i = 0; i < pN; i++) { const t = i / SR; const fq = 620 + 520 * Math.min(1, t * 12); const v = 0.42 * Math.sin(2 * Math.PI * fq * t) * Math.exp(-t * 18) * Math.min(1, t * 400); pL[i] = v; pR[i] = v; }
wav('public/launch-pop.wav', pL, pR);
console.log('audio ok');
