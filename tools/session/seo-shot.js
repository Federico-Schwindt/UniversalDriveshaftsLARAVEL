// screenshots admin pages; login fields are read from adm.js so no credential is copied here
const fs = require('fs'); const { chromium } = require(process.env.PWC);
const src = fs.readFileSync(__dirname + '/adm.js', 'utf8');
const email = src.match(/fill\('#l-email',\s*([^)]+)\)/)[1]; const pw = src.match(/fill\('#l-pw',\s*([^)]+)\)/)[1];
const ev = s => Function('return ' + s)();
(async () => {
  const b = await chromium.launch({ executablePath: process.env.BX, args: ['--no-sandbox'] });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  await p.goto('https://dev.udshafts.com/login', { waitUntil: 'domcontentloaded' });
  await p.fill('#l-email', ev(email)); await p.fill('#l-pw', ev(pw));
  await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}), p.click('form.authform button[type=submit], form.authform .btn')]); console.log('after login:', p.url(), (await p.$$eval('.auth-err,.flash,.err,[role=alert]', es => es.map(e => e.textContent.trim()).join(' | ')).catch(() => '')).slice(0, 200));
  const pages = process.argv.slice(2);
  for (let i = 0; i < pages.length; i++) {
    const [u, out] = pages[i].split('=>');
    await p.goto('https://dev.udshafts.com' + u, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => null);
    await p.screenshot({ path: __dirname + '/wm/' + out, fullPage: false });
  }
  await b.close();
})();
