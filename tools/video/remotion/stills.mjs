import { bundle } from '@remotion/bundler';
import { renderStill, selectComposition } from '@remotion/renderer';
import path from 'path';
const frames = (process.env.FRAMES || '150,415,800,1200,1590,1950,2240,2660,2900').split(',').map(Number);
const comps = (process.env.COMPS || 'LaunchWide,LaunchTall,LaunchSquare').split(',');
const serveUrl = await bundle({ entryPoint: path.resolve('src/index.ts') });
for (const id of comps) {
  const composition = await selectComposition({ serveUrl, id, browserExecutable: process.env.BX, timeoutInMilliseconds: 90000 });
  for (const frame of frames) {
    await renderStill({ serveUrl, composition, frame, output: `out/launch/${id}-${frame}.png`, browserExecutable: process.env.BX, timeoutInMilliseconds: 90000 });
  }
  console.log(id, 'done');
}
