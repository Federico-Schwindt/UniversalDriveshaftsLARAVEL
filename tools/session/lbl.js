const { chromium } = require(process.env.PWC);
(async()=>{
  const b=await chromium.launch({headless:true});
  for (const [w,h] of [[1280,720],[390,760]]) {
    const p=await (await b.newContext({viewport:{width:w,height:h}})).newPage();
    const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
    await p.goto('file://'+process.cwd()+'/lbl.html'); await p.waitForTimeout(1500);
    await p.click('[data-modal=label]'); await p.waitForTimeout(1500);
    await p.screenshot({path:`lbl-${w}.png`});
    const r=await p.evaluate(()=>{const body=document.querySelector('.modal__body'),foot=document.querySelector('.modal__foot'),btn=document.querySelector('[data-label-buy]');const fr=foot.getBoundingClientRect(),box=document.querySelector('.modal__box').getBoundingClientRect();return{scrollable:body.scrollHeight>body.clientHeight,footInBox:fr.bottom<=box.bottom+1&&fr.bottom<=innerHeight+1,btnDisabled:btn.disabled,sum:document.querySelector('[data-label-sum]').textContent,checked:(document.querySelector('[name=service_code]:checked')||{}).value}});
    console.log(w,JSON.stringify(r),errs);
    await p.evaluate(()=>document.querySelector('.modal__body').scrollTo(0,9999)); await p.waitForTimeout(300);
    await p.screenshot({path:`lbl-${w}-b.png`});
  }
  await b.close();
})();
