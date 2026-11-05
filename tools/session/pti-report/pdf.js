const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); const p = await b.newPage();
  await p.goto('file://' + process.env.D + '/new-parts.html'); 
  await p.pdf({ path: process.env.D + '/UDS-new-parts-Oct-2026.pdf', format: 'Letter', printBackground: true, displayHeaderFooter: true, headerTemplate: '<span></span>',
    footerTemplate: '<div style="font-size:7pt;color:#5c6879;width:100%;text-align:center">Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>', margin: { top: '14mm', bottom: '16mm', left: '12mm', right: '12mm' } });
  await b.close(); })();
