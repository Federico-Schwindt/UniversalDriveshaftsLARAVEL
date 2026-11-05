const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport:{width:1080,height:1920}, recordVideo:{ dir: process.cwd(), size:{width:1080,height:1920} } });
  const p = await ctx.newPage();
  await p.goto('file://'+process.cwd()+'/sample.html');
  await p.waitForTimeout(2000);
  await ctx.close(); await b.close();
  console.log('recorded');
})();
