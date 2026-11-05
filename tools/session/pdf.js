const {chromium}=require(process.env.PWC);
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage();
const S='/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/';
await p.goto('file://'+S+'compare.html',{waitUntil:'load'});await p.waitForTimeout(500);
await p.pdf({path:S+'UDS-vs-DriveshaftParts-price-comparison.pdf',format:'Letter',printBackground:true,displayHeaderFooter:true,headerTemplate:'<div></div>',footerTemplate:'<div style="font-size:7px;color:#5c6879;width:100%;text-align:center">UDS vs DriveshaftParts.com · <span class="pageNumber"></span>/<span class="totalPages"></span></div>',margin:{top:'14mm',bottom:'14mm',left:'12mm',right:'12mm'}});
await p.setViewportSize({width:816,height:1056});await p.screenshot({path:S+'cmp1.png'});
await b.close();})();
