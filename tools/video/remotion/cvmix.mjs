// Sound mix for the CV reel → public/cv/mix.wav (music pre-cut on the beat + every cue from src/cv/cues.ts,
// music ducked -4 dB on impacts, echo tails on whooshes, two-pass loudnorm to -14 LUFS / -1 dBTP).
// The composition plays this file with <Audio>, so a Studio render already carries the sound.
// usage: node --experimental-strip-types cvmix.mjs
import { spawnSync } from 'child_process'; import path from 'path';
const { cues } = await import('./src/cv/cues.ts'); const { FPS, TOTAL } = await import('./src/cv/timeline.ts');
const FF = path.join(process.env.HOME, 'pti-work/reel/remotion/node_modules/@remotion/compositor-linux-x64-gnu/ffmpeg');
const D = TOTAL / FPS;
const all = cues().filter((c) => c.f >= 0 && c.f / FPS < D - 0.1);
const sfxPath = (n) => path.join('public/cv/sfx', n + '.mp3');
const run = (args) => { const r = spawnSync(FF, args, { encoding: 'utf8', maxBuffer: 1 << 28 }); if (r.status) { console.error(r.stderr.slice(-2000)); process.exit(1); } return r; };

const ducks = all.filter((c) => c.duck).map((c) => c.f / FPS);
const duckExpr = ducks.map((a) => `max(0,min(1,(t-${a.toFixed(3)})/0.02))*max(0,min(1,(${(a + 0.42).toFixed(3)}-t)/0.2))`).join('+');
const music = `[0:a]atrim=0:${D.toFixed(3)},asetpts=PTS-STARTPTS,volume='(if(lt(t,0.15),t/0.15,1)*if(gt(t,${(D - 0.45).toFixed(3)}),max(0,(${D.toFixed(3)}-t)/0.45),1))*0.7*(1-0.37*min(1,${duckExpr || '0'}))':eval=frame[m];`;

const inputs = ['-i', 'public/cv/music.wav']; let fl = music; const labels = [];
const ECHO = new Set(['1491', '1486', '3120', '2630', '2909', '788', '2908']);
all.forEach((c, i) => {
  const copies = ECHO.has(c.src) ? [[0, 1], [0.08, 0.38], [0.17, 0.18]] : [[0, 1]];
  copies.forEach(([d, g], j) => {
    const idx = inputs.length / 2; inputs.push('-i', sfxPath(c.src));
    const ms = Math.round((c.f / FPS + d) * 1000), off = c.off || 0, dur = c.dur || 2;
    const lab = `s${i}_${j}`; labels.push(lab);
    fl += `[${idx}:a]atrim=${off}:${off + dur},asetpts=PTS-STARTPTS,aresample=48000,volume=${(c.vol * g).toFixed(3)},adelay=${ms}|${ms}[${lab}];`;
  });
});
fl += `[m]${labels.map((l) => `[${l}]`).join('')}amix=inputs=${labels.length + 1}:normalize=0:duration=first,volume=0.6,atrim=0:${D.toFixed(3)}[pre]`;
console.log('cues', all.length, 'inputs', labels.length, 'ducks', ducks.length);
const tmp = 'out/cv/premix.wav';
run(['-v', 'error', '-nostdin', '-y', ...inputs, '-filter_complex', fl, '-map', '[pre]', '-c:a', 'pcm_s16le', '-ar', '48000', tmp]);
const m = run(['-v', 'info', '-nostdin', '-i', tmp, '-af', 'loudnorm=I=-14:TP=-1:LRA=11:print_format=json', '-f', 'null', '-']).stderr;
const pj = (t) => { const a = t.lastIndexOf('{'), b = t.indexOf('}', a); return JSON.parse(t.slice(a, b + 1)); };
const js = pj(m);
console.log('measured', js.input_i, 'LUFS', js.input_tp, 'dBTP');
const ln = `loudnorm=I=-14:TP=-1:LRA=11:measured_I=${js.input_i}:measured_TP=${js.input_tp}:measured_LRA=${js.input_lra}:measured_thresh=${js.input_thresh}:offset=${js.target_offset}:linear=true:print_format=json`;
const r2 = run(['-v', 'info', '-nostdin', '-y', '-i', tmp, '-af', ln, '-c:a', 'pcm_s16le', '-ar', '48000', 'public/cv/mix.wav']).stderr;
const js2 = pj(r2);
console.log('output', js2.output_i, 'LUFS', js2.output_tp, 'dBTP -> public/cv/mix.wav');
