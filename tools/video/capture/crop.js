// crop a tall PNG to the first N px of height; usage: node crop.js in.png out.png height
const { chromium } = require(process.env.PWC); const path = require('path'); const fs = require('fs');
(async () => {
  const [inp, out, h] = process.argv.slice(2); const html = path.join(path.dirname(path.resolve(inp)), '_crop.html');
  fs.writeFileSync(html, `<body style="margin:0"><img src="${path.basename(inp)}" style="display:block"></body>`);
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 2880, height: +h } });
  await p.goto('file://' + html); await p.waitForFunction(() => document.querySelector('img').complete && document.querySelector('img').naturalWidth > 0);
  const dim = await p.evaluate(() => { const i = document.querySelector('img'); return [i.naturalWidth, i.naturalHeight]; });
  await p.setViewportSize({ width: dim[0], height: +h }); await p.screenshot({ path: out, clip: { x: 0, y: 0, width: dim[0], height: +h } }); await b.close(); fs.unlinkSync(html); console.log('cropped', dim, '->', dim[0], h);
})();
