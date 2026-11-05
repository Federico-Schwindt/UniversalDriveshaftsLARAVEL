const {chromium}=require(process.env.PWC);const S=process.env.S;
(async()=>{const b=await chromium.launch({args:['--no-sandbox']});
for(const [vn,w,h,mob,name,path,scroll] of [['mobile',375,812,true,'category-vp','/shop/driveshaft-components/u-joints',0],['mobile',375,812,true,'category-vp2','/shop/driveshaft-components/u-joints',900],['desktop',1440,900,false,'home-vp','/',0],['desktop',1440,900,false,'category-vp','/shop/driveshaft-components/u-joints',0],['mobile',375,812,true,'home-vp','/',0],['mobile',375,812,true,'checkout-vp','/checkout',0]]){
 const ctx=await b.newContext({viewport:{width:w,height:h},isMobile:mob,hasTouch:mob,deviceScaleFactor:1});const pg=await ctx.newPage();
 await pg.goto('https://dev.udshafts.com'+path,{waitUntil:'networkidle',timeout:60000});if(scroll){await pg.evaluate(s=>window.scrollTo(0,s),scroll);await pg.waitForTimeout(500);}
 const info=await pg.evaluate(()=>({rows:document.querySelectorAll('table tbody tr').length,h:document.documentElement.scrollHeight}));console.log(vn,name,JSON.stringify(info));
 await pg.screenshot({path:`${S}/shots/${vn}-${name}.png`});await ctx.close();}
await b.close();})();
