const { chromium } = require(process.env.PWC);
const OUT=process.env.OUT;
(async()=>{
 const b=await chromium.launch({headless:true});
 for (const lang of ['es','en']) for (const [w,h] of [[1280,900],[390,844]]) {
  const ctx=await b.newContext({viewport:{width:w,height:h}}); const p=await ctx.newPage();
  const errs=[]; p.on('pageerror',e=>errs.push(e.message)); p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
  const tag=`${lang}-${w}`; const out=[];
  const go=async(u)=>{ await p.goto('https://dev.udshafts.com'+u+(u.includes('?')?'&':'?')+'lang='+lang,{waitUntil:'domcontentloaded'}); await p.waitForTimeout(600); };
  const txt=async(n)=>{ out.push('['+n+'] '+(await p.evaluate(()=>document.querySelector('#dsg').innerText)).replace(/\s+/g,' ').slice(0,420)); await p.screenshot({path:`${OUT}/d-${tag}-${n}.png`,fullPage:false}); };
  await go('/driveshaft-designer'); await txt('0-style');
  await go('/driveshaft-designer/2'); await txt('1-front');
  const first=await p.getAttribute('.dsg__section','href'); await go(first); await txt('2-middle');
  const second=await p.getAttribute('.dsg__section','href'); await go(second);
  const third=await p.getAttribute('.dsg__section','href'); await go(third); await p.waitForTimeout(2500); await txt('3-sheet');
  const lab=await p.$('.dim-text'); if (lab) { await lab.click(); await p.waitForTimeout(300); out.push('placeholder: '+await p.getAttribute('#dsg-dim-input','placeholder')); }
  const over=await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
  console.log(tag,'errors:',errs.length?errs.slice(0,3):'none','| h-overflow:',over);
  require('fs').writeFileSync(`${OUT}/d-${tag}.txt`,out.join('\n')); await ctx.close();
 }
 await b.close();
})();
