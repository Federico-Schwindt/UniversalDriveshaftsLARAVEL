const { chromium } = require(process.env.PWC || 'playwright');
(async () => {
  const b = await chromium.launch({ executablePath: process.env.BX });
  const p = await b.newPage({ viewport: { width: 390, height: 1400 }, deviceScaleFactor: 2 });
  await p.goto('https://dev.udshafts.com/order-received/UDS-261030-I6H2?email=owner%40example.com', { waitUntil: 'networkidle' });
  await p.addStyleTag({ content: '.uds-asst,.fab,[class*=whats],[class*=assistant]{display:none!important}' });
  const el = await p.$('.orc'); await el.screenshot({ path: process.argv[2] });
  await b.close();
})();
