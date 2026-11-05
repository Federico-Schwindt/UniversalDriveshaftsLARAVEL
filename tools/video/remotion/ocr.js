// OCR a list of PNGs with tesseract.js and report any "udshafts" hits. usage: node ocr.js out.json file1.png file2.png ...
const { createWorker } = require('tesseract.js'); const fs = require('fs');
(async () => {
  const [out, ...files] = process.argv.slice(2);
  const w = await createWorker('eng', 1, { cachePath: process.env.HOME + '/pti-work/reel/remotion/.tess', logger: () => {} });
  const res = {}; let hits = 0;
  for (const f of files) {
    const { data } = await w.recognize(f); const t = data.text.replace(/\s+/g, ' ');
    const hit = /udshafts|udshaft|dev\.uds/i.test(t); if (hit) hits++;
    res[f] = { hit, text: t }; console.log((hit ? 'HIT  ' : 'ok   ') + f + (hit ? '  => ' + (t.match(/.{0,30}udshaft.{0,30}/i) || [''])[0] : ''));
  }
  fs.writeFileSync(out, JSON.stringify(res, null, 1)); await w.terminate(); console.log('files', files.length, 'hits', hits);
})();
