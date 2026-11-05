// bundle once, render many stills: node stills3.mjs out/v3/stills 100,250,...  [scale]
import { bundle } from '@remotion/bundler'; import { renderStill, selectComposition } from '@remotion/renderer'; import fs from 'fs'; import path from 'path';
const [dir, list, scale = '0.5'] = process.argv.slice(2); fs.mkdirSync(dir, { recursive: true });
const serveUrl = await bundle({ entryPoint: path.resolve('src/index.ts'), publicDir: path.resolve('public') });
const composition = await selectComposition({ serveUrl, id: 'Launch3', browserExecutable: process.env.BX, chromiumOptions: { gl: 'swangle' } });
const t0 = Date.now();
for (const f of list.split(',').map(Number)) {
  await renderStill({ composition, serveUrl, frame: f, output: path.join(dir, `f${String(f).padStart(5, '0')}.png`), scale: +scale, browserExecutable: process.env.BX, chromiumOptions: { gl: 'swangle' }, timeoutInMilliseconds: 120000, logLevel: 'error' });
  console.log('still', f, ((Date.now() - t0) / 1000).toFixed(1) + 's');
}
