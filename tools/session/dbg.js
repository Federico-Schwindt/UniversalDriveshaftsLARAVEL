const { spawnSync } = require('child_process'); const path = require('path');
const FF = path.join(process.env.HOME, 'pti-work/reel/remotion/node_modules/@remotion/compositor-linux-x64-gnu/ffmpeg');
const r = spawnSync(FF, ['-v', 'error', '-nostdin', '-i', process.argv[2], '-ac', '1', '-ar', '11025', '-f', 'wav', '-acodec', 'pcm_f32le', '-'], { maxBuffer: 1 << 30 });
console.log('status', r.status, 'len', r.stdout.length, String(r.stderr).slice(0, 200));
const off = r.stdout.indexOf('data') + 8; console.log('off', off, r.stdout.slice(0, 12).toString('latin1'));
const pcm = new Float32Array(r.stdout.buffer.slice(r.stdout.byteOffset + off, r.stdout.byteOffset + r.stdout.length - ((r.stdout.length - off) % 4)));
let mx = 0, nan = 0; for (let i = 0; i < pcm.length; i++) { if (Number.isNaN(pcm[i])) nan++; else mx = Math.max(mx, Math.abs(pcm[i])); } console.log('n', pcm.length, 'max', mx, 'nan', nan);
