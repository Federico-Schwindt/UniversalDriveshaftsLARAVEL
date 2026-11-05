const { chromium } = require(process.env.PWC); const ck = require('./cookie.json'); const OUT = process.env.HOME + '/pti-work/reel/code/video/shots/';
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  await ctx.addCookies([{ name: ck.name, value: ck.value, domain: 'dev.udshafts.com', path: '/', httpOnly: true, secure: true }, { name: 'uds_lang', value: 'en', domain: 'dev.udshafts.com', path: '/' }]);
  const p = await ctx.newPage(); p.setDefaultTimeout(8000);
  await p.goto('https://dev.udshafts.com/checkout', { waitUntil: 'load', timeout: 60000 }); await p.waitForTimeout(2500);
  await p.addStyleTag({ content: '.floaty,.ua,.ua-panel,.ua-launch{display:none!important} *{caret-color:transparent}' });
  const r = await p.evaluate(() => {
    const V = { email: 'alex@example.com', first: 'Alex', last: 'Morgan', company: 'Morgan Fleet Repair', address: '1200 Industrial Blvd', address2: 'Bay 4', city: 'Springfield', zip: '62704', postcode: '62704', state: 'IL', phone: '(813) 555-0142' };
    const out = [];
    document.querySelectorAll('input, textarea').forEach((i) => {
      if (['hidden', 'checkbox', 'radio', 'submit', 'button'].includes(i.type)) return;
      const key = ((i.name || '') + ' ' + (i.id || '') + ' ' + (i.autocomplete || '')).toLowerCase();
      let v = null;
      if (i.type === 'email' || /mail/.test(key)) v = V.email; else if (i.type === 'tel' || /phone|tel/.test(key)) v = V.phone;
      else if (/first|given/.test(key)) v = V.first; else if (/last|family/.test(key)) v = V.last; else if (/company|org/.test(key)) v = V.company;
      else if (/address.?2|line2|apt|suite/.test(key)) v = V.address2; else if (/address|street|line1/.test(key)) v = V.address; else if (/city|locality/.test(key)) v = V.city;
      else if (/zip|postal|postcode/.test(key)) v = V.zip; else if (/coupon|promo/.test(key)) v = ''; else if (i.value) v = '';
      if (v !== null) { i.value = v; out.push(key.trim() + '=' + v); }
    });
    document.querySelectorAll('select').forEach((s) => { const o = [...s.options].find((o) => /^(IL|Illinois)$/i.test(o.value) || /Illinois/.test(o.text)); if (o) s.value = o.value; });
    document.querySelectorAll('.tel__code').forEach((e) => { e.textContent = '+1'; });
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
    while ((n = w.nextNode())) n.nodeValue = n.nodeValue.replace(/federico schwindt/gi, 'Alex Morgan').replace(/owner@example\.com/gi, 'alex@example.com').replace(/federico|leonel/gi, 'Alex').replace(/schwindt/gi, 'Morgan');
    return out;
  });
  console.log(r.join(' | '));
  await p.mouse.move(1439, 899); await p.waitForTimeout(500);
  await p.screenshot({ path: OUT + 'checkout.png' });
  const body = await p.evaluate(() => document.body.innerText); (body.match(/.{0,60}(leonel|federico|schwindt).{0,60}/gi) || []).forEach(l => console.log('LEAK', l)); const vis = await p.evaluate(() => { const r=[]; document.querySelectorAll('*').forEach(e => { if (e.children.length===0 && /leonel|schwindt/i.test(e.textContent)) { const b=e.getBoundingClientRect(); r.push(e.tagName+' y='+(b.y|0)+' '+e.textContent.trim().slice(0,60)); } }); return r; }); console.log(vis.join('\n'));
  await b.close();
})();
