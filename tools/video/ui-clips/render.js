// Frame-by-frame renderer: every frame is window.render(n) in headless Chromium, piped as PNG into ffmpeg (H.264).
// usage: node render.js <w> <h> <out.mp4>            full video
//        node render.js <w> <h> stills 100,400,...    PNGs into out/stills
const { chromium } = require(process.env.PWC); const { spawn } = require('child_process'); const fs = require('fs'); const path = require('path');
const FF = path.join(process.env.HOME, 'pti-work/reel/remotion/node_modules/@remotion/compositor-linux-x64-gnu/ffmpeg');
const [W, H, out, list] = [+process.argv[2], +process.argv[3], process.argv[4], process.argv[5]];
(async () => {
  const b = await chromium.launch({ args: ['--disable-web-security', '--allow-file-access-from-files'] });
  const p = await b.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
  await p.goto('file://' + path.join(__dirname, 'video/index.html') + `?w=${W}&h=${H}`); await p.evaluate(() => window.ready);
  const meta = await p.evaluate(() => window.META);
  if (out === 'stills') {
    fs.mkdirSync(path.join(__dirname, 'out/stills'), { recursive: true });
    for (const f of list.split(',').map(Number)) { await p.evaluate((f) => window.render(f), f); await p.screenshot({ path: path.join(__dirname, `out/stills/${W}x${H}-${f}.png`) }); }
    console.log('stills ok'); await b.close(); return;
  }
  const silent = out.replace(/\.mp4$/, '.video.mp4');
  const ff = spawn(FF, ['-v', 'error', '-y', '-f', 'image2pipe', '-framerate', String(meta.FPS), '-c:v', 'png', '-i', '-', '-c:v', 'libx264', '-preset', 'medium', '-crf', '16', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', silent], { stdio: ['pipe', 'inherit', 'inherit'] });
  const t0 = Date.now();
  for (let f = 0; f < meta.TOTAL; f++) {
    await p.evaluate((f) => window.render(f), f);
    const buf = await p.screenshot({ type: 'png' });
    if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r));
    if (f % 120 === 0) console.log(`frame ${f}/${meta.TOTAL} ${((Date.now() - t0) / 1000).toFixed(0)}s`);
  }
  ff.stdin.end(); await new Promise((r) => ff.on('close', r)); await b.close();
  // audio: music bed + every SFX at its frame, mixed without normalisation
  const D = meta.TOTAL / meta.FPS; const S = (n) => path.join(__dirname, 'video/sfx', n + '.mp3');
  const args = ['-v', 'error', '-y', '-i', silent, '-i', S('m51')]; meta.SFX.forEach((s) => args.push('-i', S(s.src)));
  let fl = `[1:a]atrim=0:${D},asetpts=PTS-STARTPTS,volume='if(lt(t,0.6),t/0.6,if(gt(t,${D - 2.2}),max(0,(${D}-t)/2.2),1))*0.5':eval=frame[m];`;
  meta.SFX.forEach((s, i) => { const ms = Math.round(s.t * 1000); fl += `[${i + 2}:a]atrim=0:${s.dur},asetpts=PTS-STARTPTS,volume=${s.vol},adelay=${ms}|${ms}[s${i}];`; });
  fl += `[m]${meta.SFX.map((_, i) => `[s${i}]`).join('')}amix=inputs=${meta.SFX.length + 1}:normalize=0:duration=first,atrim=0:${D},volume=1.6[a]`;
  args.push('-filter_complex', fl, '-map', '0:v', '-map', '[a]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-shortest', '-movflags', '+faststart', out);
  await new Promise((r, j) => { const a = spawn(FF, args, { stdio: 'inherit' }); a.on('close', (c) => (c ? j(new Error('audio mix failed ' + c)) : r())); });
  fs.unlinkSync(silent); console.log('done', out, ((Date.now() - t0) / 1000).toFixed(0) + 's');
})().catch((e) => { console.error('FAIL', e.message); process.exit(1); });
