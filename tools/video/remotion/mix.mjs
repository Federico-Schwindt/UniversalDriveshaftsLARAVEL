// Sound mix for the launch video: music bed cut to the beat grid + every cue from src/v3/cues.ts, music ducked -4 dB for 300 ms
// on each impact, reverb tails on whooshes (delayed copies), then a two-pass loudnorm to -14 LUFS / -1 dBTP and mux.
// usage: node --experimental-strip-types mix.mjs <silent.mp4> <out.mp4>
import { spawnSync } from 'child_process'; import path from 'path'; import fs from 'fs';
const { registerCues } = await import('./src/v3/cues.ts'); const { FPS, TOTAL } = await import('./src/v3/timeline.ts');
const FF = path.join(process.env.HOME, 'pti-work/reel/remotion/node_modules/@remotion/compositor-linux-x64-gnu/ffmpeg');
const [video, out] = process.argv.slice(2); const D = TOTAL / FPS;
const cues = registerCues().filter((c) => c.f >= 0 && c.f / FPS < D - 0.2);
const sfxPath = (n) => path.join('public/v3/sfx', n + '.mp3');
const run = (args) => { const r = spawnSync(FF, args, { encoding: 'utf8', maxBuffer: 1 << 28 }); if (r.status) { console.error(r.stderr.slice(-2000)); process.exit(1); } return r; };

// ---- ducking envelope on the music: 20 ms attack, 300 ms hold, 220 ms release, -4 dB (gain 0.63)
const ducks = cues.filter((c) => c.duck).map((c) => c.f / FPS);
const duckExpr = ducks.map((a) => `max(0,min(1,(t-${a.toFixed(3)})/0.02))*max(0,min(1,(${(a + 0.52).toFixed(3)}-t)/0.22))`).join('+');
const music = `[1:a]atrim=0:${D.toFixed(3)},asetpts=PTS-STARTPTS,volume='(if(lt(t,1.2),t/1.2,1)*if(gt(t,${(D - 2.6).toFixed(3)}),max(0,(${D.toFixed(3)}-t)/2.6),1))*0.62*(1-0.37*min(1,${duckExpr || '0'}))':eval=frame[m];`;

// ---- SFX inputs: one ffmpeg input per cue, whooshes/risers get three echo copies for a reverb tail
const inputs = ['-i', video, '-i', 'public/v3/music/m846.mp3']; let fl = music; const labels = [];
const ECHO = { s1492: 1, s1490: 1, s1486: 1, s1489: 1, s1714: 1, s2908: 1, s2900: 1, s2902: 1, s498: 1 };
cues.forEach((c, i) => {
  const copies = ECHO[c.src] ? [[0, 1], [0.07, 0.42], [0.15, 0.22], [0.26, 0.1]] : [[0, 1]];
  copies.forEach(([d, g], j) => {
    const idx = inputs.length / 2; inputs.push('-i', sfxPath(c.src));
    const ms = Math.round((c.f / FPS + d) * 1000), off = c.off || 0, dur = c.dur || 2;
    const lab = `s${i}_${j}`; labels.push(lab);
    fl += `[${idx}:a]atrim=${off}:${off + dur},asetpts=PTS-STARTPTS,aresample=48000,volume=${(c.vol * g).toFixed(3)},adelay=${ms}|${ms}[${lab}];`;
  });
});
fl += `[m]${labels.map((l) => `[${l}]`).join('')}amix=inputs=${labels.length + 1}:normalize=0:duration=first,volume=0.6,atrim=0:${D.toFixed(3)}[pre]`;
console.log('cues', cues.length, 'inputs', labels.length, 'ducks', ducks.length);
const tmp = path.join(process.env.HOME, 'pti-work/reel/remotion/out/v3/premix.wav');
run(['-v', 'error', '-y', ...inputs, '-filter_complex', fl, '-map', '[pre]', '-c:a', 'pcm_s16le', '-ar', '48000', tmp]);
// ---- loudness: pass 1 measure, pass 2 linear normalisation to -14 LUFS / -1 dBTP
const m = run(['-v', 'info', '-nostdin', '-i', tmp, '-af', 'loudnorm=I=-14:TP=-1:LRA=11:print_format=json', '-f', 'null', '-']).stderr;
const pj = (t) => { const a = t.lastIndexOf('{'), b = t.indexOf('}', a); return JSON.parse(t.slice(a, b + 1)); };
const js = pj(m);
console.log('measured', js.input_i, 'LUFS', js.input_tp, 'dBTP');
const ln = `loudnorm=I=-14:TP=-1:LRA=11:measured_I=${js.input_i}:measured_TP=${js.input_tp}:measured_LRA=${js.input_lra}:measured_thresh=${js.input_thresh}:offset=${js.target_offset}:linear=true:print_format=json`;
const r2 = run(['-v', 'info', '-nostdin', '-y', '-i', video, '-i', tmp, '-map', '0:v', '-map', '1:a', '-af', ln, '-c:v', 'copy', '-c:a', 'aac', '-b:a', '256k', '-shortest', '-movflags', '+faststart', out]).stderr;
const js2 = pj(r2);
console.log('output', js2.output_i, 'LUFS', js2.output_tp, 'dBTP', '->', out);
fs.unlinkSync(tmp);
