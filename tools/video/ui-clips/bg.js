const { chromium } = require(process.env.PWC); const path = require('path');
const BG = {
  'bg-1-night-steel': `background:#0b1a33;background-image:radial-gradient(60% 70% at 50% 42%,rgba(29,79,184,.55),rgba(11,26,51,0) 70%),radial-gradient(40% 40% at 85% 95%,rgba(207,49,48,.18),rgba(0,0,0,0) 70%),linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(180deg,#0b1a33,#081327);background-size:auto,auto,64px 64px,64px 64px,auto;`,
  'bg-2-blueprint': `background:#0d1f3e;background-image:radial-gradient(50% 60% at 18% 85%,rgba(207,49,48,.22),rgba(0,0,0,0) 70%),radial-gradient(70% 60% at 75% 20%,rgba(22,87,207,.35),rgba(0,0,0,0) 70%),linear-gradient(rgba(140,180,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(140,180,255,.06) 1px,transparent 1px),linear-gradient(rgba(140,180,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(140,180,255,.03) 1px,transparent 1px);background-size:auto,auto,120px 120px,120px 120px,24px 24px,24px 24px;`,
  'bg-3-paper': `background:#eef2f8;background-image:radial-gradient(45% 55% at 20% 30%,rgba(22,87,207,.16),rgba(0,0,0,0) 70%),radial-gradient(40% 50% at 85% 80%,rgba(207,49,48,.10),rgba(0,0,0,0) 70%),linear-gradient(rgba(18,70,171,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(18,70,171,.06) 1px,transparent 1px);background-size:auto,auto,48px 48px,48px 48px;`,
};
const blueprint = `<svg width="1920" height="1080" style="position:absolute;inset:0;opacity:.16" fill="none" stroke="#9cc0ff" stroke-width="1.5"><g transform="translate(260 640) rotate(-8)"><rect x="140" y="-30" width="1100" height="60"/><rect x="40" y="-44" width="100" height="88" rx="10"/><rect x="1240" y="-44" width="100" height="88" rx="10"/><circle cx="90" cy="0" r="22"/><circle cx="1290" cy="0" r="22"/><path d="M40 -120H1340M40 -135V-105M1340 -135V-105M140 -80H1240"/><text x="690" y="-132" fill="#9cc0ff" stroke="none" font-family="monospace" font-size="26" text-anchor="middle">C1 52.25</text><text x="690" y="-90" fill="#9cc0ff" stroke="none" font-family="monospace" font-size="22" text-anchor="middle">W1 48.5</text></g></svg>`;
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1920, height: 1080 } });
  for (const [n, css] of Object.entries(BG)) {
    await p.setContent(`<body style="margin:0"><div style="position:relative;width:1920px;height:1080px;${css}">${n.includes('blueprint') ? blueprint : ''}<div style="position:absolute;inset:0;background:radial-gradient(120% 90% at 50% 50%,rgba(0,0,0,0) 60%,rgba(0,0,0,${n.includes('paper') ? '.06' : '.45'}))"></div></div></body>`);
    await p.screenshot({ path: path.join(__dirname, 'inputs', n + '.png') });
  }
  await b.close(); console.log('bg ok');
})();
