const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch(); const p = await b.newPage();
  await p.goto('https://dev.udshafts.com/robots.txt').catch(()=>{});
  const r = await p.evaluate(async () => {
    const out = {};
    out.webcodecs = typeof VideoEncoder !== 'undefined';
    for (const codec of ['avc1.640028','avc1.4d0028','avc1.42E01F']) {
      try { const s = await VideoEncoder.isConfigSupported({ codec, width:1080, height:1920, bitrate:8e6, framerate:30, avc:{format:'avc'} }); out[codec]=s.supported; } catch(e){ out[codec]='err '+e.message; }
    }
    out.mr_mp4 = typeof MediaRecorder!=='undefined' && MediaRecorder.isTypeSupported('video/mp4;codecs=avc1');
    return out;
  });
  console.log(JSON.stringify(r)); await b.close();
})();
