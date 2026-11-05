const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto('https://dev.udshafts.com/shop',{waitUntil:'networkidle'});await p.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));await p.waitForTimeout(500);await p.evaluate(()=>window.scrollTo(0,0));await p.screenshot({path:process.env.SP+'/pg-shop2.png',fullPage:true});
console.log('shop ok');await b.close();})();
