const { registerCues, VIS } = await import(process.cwd() + '/src/v3/cues.ts'); const { FPS, ORDER, at, SEC } = await import(process.cwd() + '/src/v3/timeline.ts');
const ev = [...new Set([...registerCues().map((c) => c.f), ...VIS, ...ORDER.map((k) => at(k))])].sort((a, b) => a - b);
for (let i = 1; i < ev.length; i++) { const g = ev[i] - ev[i - 1]; if (g > 90) { const k = ORDER.find((k) => ev[i - 1] >= SEC[k][0] && ev[i - 1] < SEC[k][1]); console.log(`${(ev[i-1]/FPS).toFixed(2)}s → ${(ev[i]/FPS).toFixed(2)}s  gap ${(g/FPS).toFixed(2)}s  [${k}] local ${ev[i-1]-at(k)}..${ev[i]-at(k)}`); } }
