const { chromium } = require(process.env.PWC); const fs=require('fs');
(async () => { const url=fs.readFileSync(process.env.HOME+'/pti-work/trkurl.txt','utf8').trim().split('\n').pop(); const b = await chromium.launch();
 for (const [w,f] of [[1280,'trk-d'],[390,'trk-m']]) { const p = await b.newPage({ viewport: { width: w, height: 900 } }); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  const r=await p.goto(url, { waitUntil: 'networkidle' }); console.log(w, r.status(), errs.join('|')||'no js errors');
  const el = p.locator('.ost-track'); await el.screenshot({ path: process.env.HOME + '/pti-work/' + f + '.png' }); }
 await b.close(); })();
