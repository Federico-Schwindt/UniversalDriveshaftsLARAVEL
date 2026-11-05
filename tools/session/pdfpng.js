const { chromium } = require(process.env.PWC); const fs=require('fs');
(async () => {
  const b64 = fs.readFileSync(process.argv[2]).toString('base64');
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1300,height:1700} });
  p.on('pageerror', e => console.log('pageerror', e.message));
  await p.setContent(`<html><body style="margin:0;background:#fff"><canvas id="c"></canvas>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script></body></html>`, { waitUntil:'load' });
  const dims = await p.evaluate(async (b64) => {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    const doc = await pdfjsLib.getDocument({ data: bytes }).promise;
    const page = await doc.getPage(1); const vp = page.getViewport({ scale: 1.6 });
    const c = document.getElementById('c'); c.width = vp.width; c.height = vp.height;
    await page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
    return { w: vp.width, h: vp.height, pages: doc.numPages };
  }, b64);
  console.log('rendered', JSON.stringify(dims));
  await (await p.$('#c')).screenshot({ path: process.argv[3] }); await b.close();
})();
