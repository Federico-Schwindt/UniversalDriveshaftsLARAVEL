import * as pdfjs from './pdfjs/node_modules/pdfjs-dist/legacy/build/pdf.mjs';
import fs from 'fs';
const data = new Uint8Array(fs.readFileSync(process.argv[2]));
const doc = await pdfjs.getDocument({ data, useSystemFonts: true }).promise;
let out = `PAGES ${doc.numPages}\n`;
for (let i = 1; i <= doc.numPages; i++) {
  const pg = await doc.getPage(i); const tc = await pg.getTextContent();
  let last = null, line = '';
  for (const it of tc.items) { const y = Math.round(it.transform[5]); if (last !== null && Math.abs(y - last) > 3) { out += line + '\n'; line = ''; } line += (line && !line.endsWith(' ') ? ' ' : '') + it.str; last = y; }
  out += line + `\n=== end page ${i}\n`;
}
fs.writeFileSync(process.argv[3], out);
