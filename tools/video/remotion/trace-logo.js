// Traces the three colour masks of the UDS logo with potrace and stacks them into one SVG (781x125 viewBox).
const { Potrace } = require('potrace'); const fs = require('fs');
const dir = process.env.HOME + '/pti-work/logo/';
const [BLUE, RED] = process.argv.slice(2);
const trace = (file, color) => new Promise((ok, bad) => {
  const p = new Potrace({ threshold: 128, turdSize: 20, optTolerance: 0.25, alphaMax: 1.0, turnPolicy: Potrace.TURNPOLICY_MINORITY, color, background: 'transparent' });
  p.loadImage(dir + file, (err) => err ? bad(err) : ok(p.getPathTag(color)));
});
(async () => {
  const layers = [await trace('m-blue.png', BLUE), await trace('m-white.png', '#ffffff'), await trace('m-red.png', RED)];
  // masks are 4x: scale the paths back to the logo's own 781x125 size
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 781 125" width="781" height="125" role="img" aria-label="Universal Driveshafts">\n<title>Universal Driveshafts</title>\n<g transform="scale(0.25)">\n${layers.join('\n')}\n</g>\n</svg>\n`;
  fs.writeFileSync(dir + 'uds-logo.svg', svg);
  console.log('svg bytes', svg.length);
})().catch((e) => { console.error(e); process.exit(1); });
