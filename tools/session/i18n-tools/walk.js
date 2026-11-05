const { chromium } = require(process.env.PWC);
const OUT=process.env.OUT;
(async()=>{
 const b=await chromium.launch({headless:true});
 for (const lang of ['es','en']) for (const [w,h] of [[1280,900],[390,844]]) {
  const ctx=await b.newContext({viewport:{width:w,height:h}}); const p=await ctx.newPage();
  const errs=[]; p.on('pageerror',e=>errs.push(e.message)); p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
  const tag=`${lang}-${w}`;
  for(let i=0;i<12;i++){ await p.goto('https://dev.udshafts.com/online-builder?lang='+lang,{waitUntil:'domcontentloaded'}); const t=await p.textContent('h1'); if(lang==='en'||/Armador/.test(t)) break; await p.waitForTimeout(6000);}
  const vis=async()=>p.evaluate(()=>{const a=document.querySelector('.dsb-step--active');return a?a.innerText:''});
  const texts=[]; const snap=async(n)=>{texts.push('['+n+'] '+(await vis()).replace(/\s+/g,' ').slice(0,400)); await p.screenshot({path:`${OUT}/b-${tag}-${n}.png`});};
  await snap('0-mode');
  await p.click('.dsb-mode-card[data-mode=custom]'); await p.waitForTimeout(300); await snap('1-duty');
  await p.click('#dsb-duty-cards .dsb-card'); await p.waitForTimeout(300); await snap('2-series');
  await p.click('#dsb-series-cards .dsb-card'); await p.waitForTimeout(400); await snap('3-after-series');
  if (await p.isVisible('.dsb-mode-card[data-material=steel]')) { await p.click('.dsb-mode-card[data-material=steel]'); await p.waitForTimeout(300); await snap('3b-front'); }
  await p.click('#dsb-front-app-cards .dsb-app-card >> nth=0'); await p.waitForTimeout(300); await snap('4-rear');
  await p.click('#dsb-rear-app-cards .dsb-app-card >> nth=0'); await p.waitForTimeout(300); await snap('5-measure');
  await p.fill('#dsb-inp-a1','30'); await p.click('[data-next=measure]'); await p.waitForTimeout(500); await snap('6-yoke');
  for (let k=0;k<2;k++){ const no=await p.$('.dsb-step--active [data-yoke-answer$=":no"]'); if(no){ await no.click(); await p.waitForTimeout(400);} }
  await snap('7-review');
  // premade path: catalog text
  await p.goto('https://dev.udshafts.com/online-builder?lang='+lang,{waitUntil:'domcontentloaded'});
  await p.click('.dsb-mode-card[data-mode=premade]'); await p.click('#dsb-duty-cards .dsb-card'); await p.click('#dsb-series-cards .dsb-card'); await p.waitForTimeout(3500); await snap('8-premade');
  const over=await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
  console.log(tag,'errors:',errs.length?errs.slice(0,3):'none','| h-overflow:',over);
  require('fs').writeFileSync(`${OUT}/b-${tag}.txt`,texts.join('\n'));
  await ctx.close();
 }
 await b.close();
})();
