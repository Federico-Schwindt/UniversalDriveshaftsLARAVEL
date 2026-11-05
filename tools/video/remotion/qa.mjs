// Quality gate: 1) OCR one frame per second for "udshafts"; 2) per-second motion table from the composition's layer schedule
// and its cue list (every visual event is also a cue). usage: node --experimental-strip-types qa.mjs <final.mp4> <report.md>
import { spawnSync } from 'child_process'; import path from 'path'; import fs from 'fs';
const { registerCues, VIS } = await import('./src/v3/cues.ts'); const { FPS, TOTAL, SEC, ORDER, LAYERS, TRANS, at } = await import('./src/v3/timeline.ts');
const FF = path.join(process.env.HOME, 'pti-work/reel/remotion/node_modules/@remotion/compositor-linux-x64-gnu/ffmpeg');
const [video, report] = process.argv.slice(2); const dir = path.join(process.env.HOME, 'pti-work/reel/remotion/out/v3/qa'); fs.rmSync(dir, { recursive: true, force: true }); fs.mkdirSync(dir, { recursive: true });
spawnSync(FF, ['-v', 'error', '-nostdin', '-i', video, '-vf', 'fps=1,scale=1920:-1', path.join(dir, 'f%04d.png')]);
const frames = fs.readdirSync(dir).filter((f) => f.endsWith('.png')).sort().map((f) => path.join(dir, f));
const ocr = spawnSync('node', ['ocr.js', path.join(dir, 'ocr.json'), ...frames], { encoding: 'utf8', maxBuffer: 1 << 28 });
const ocrJson = JSON.parse(fs.readFileSync(path.join(dir, 'ocr.json'), 'utf8'));
const hits = Object.entries(ocrJson).filter(([, v]) => v.hit).map(([k]) => path.basename(k));
// motion table
const cues = registerCues(); const secOf = (f) => ORDER.find((k) => f >= SEC[k][0] && f < SEC[k][1]) || (f < SEC.open[0] ? 'open' : 'end');
const rows = []; let worst = 99; let longestGap = 0, gapAt = 0; let lastEvent = 0;
const events = [...cues.map((c) => c.f), ...VIS, ...ORDER.map((k) => at(k))].sort((a, b) => a - b);
events.forEach((e) => { if (e - lastEvent > longestGap) { longestGap = e - lastEvent; gapAt = lastEvent; } lastEvent = e; });
for (let s = 0; s < Math.ceil(TOTAL / FPS); s++) {
  const f0 = s * FPS, f1 = f0 + FPS, k = secOf(f0 + 30); const layers = LAYERS[k]; const ev = cues.filter((c) => c.f >= f0 && c.f < f1);
  const tr = ORDER.filter((kk) => at(kk) >= f0 && at(kk) < f1 && TRANS[kk] !== 'none');
  const what = [tr.length ? `transition → ${tr.join(',')} (${TRANS[tr[0]]})` : '', ev.length ? `${ev.length} events (${[...new Set(ev.map((c) => c.src))].slice(0, 3).join(',')})` : ''].filter(Boolean).join('; ');
  worst = Math.min(worst, layers.length); rows.push(`| ${String(s).padStart(3)} s | ${k} | ${layers.join(', ')}${what ? ' · ' + what : ''} | ${layers.length} |`);
}
const md = `# Quality gate — ${path.basename(video)}\n\n## OCR (1 frame / second, tesseract.js)\n\nframes: ${frames.length} · hits for "udshafts": **${hits.length}**${hits.length ? ' → ' + hits.join(', ') : ''}\n\n## Motion table\n\nMinimum independently moving layers in any second: **${worst}** (gate: ≥ 3). Longest stretch without a new visual/sound event: **${(longestGap / FPS).toFixed(2)} s** at ${(gapAt / FPS).toFixed(1)} s (gate: ≤ 1.5 s).\n\n| t | section | moving layers · events | n |\n|---|---|---|---|\n${rows.join('\n')}\n`;
fs.writeFileSync(report, md); console.log('OCR hits', hits.length, '| min layers', worst, '| longest gap', (longestGap / FPS).toFixed(2), 's at', (gapAt / FPS).toFixed(1), 's');
