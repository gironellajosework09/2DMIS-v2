'use strict';
const fs = require('node:fs');
const R = 'C:/xampp/htdocs/2DMIS-v2';
const docs = [
  'docs/IMPLEMENTATION_LOG.md',
  'docs/SESSION_HANDOFF.md',
  'docs/UI_CSS_RETIREMENT_PLAN.md'
];
for (const d of docs) {
  const p = R + '/' + d;
  const buf = fs.readFileSync(p);
  const total = buf.length;
  const lines = [];
  let cur = '';
  let i = 0;
  const flush = (eol) => { lines.push({ text: cur, eol }); cur = ''; };
  while (i < total) {
    const b = buf[i];
    if (b === 0x0d && i + 1 < total && buf[i + 1] === 0x0a) { flush('\r\n'); i += 2; }
    else if (b === 0x0a) { flush('\n'); i += 1; }
    else if (b === 0x0d) { flush('\r'); i += 1; }
    else { cur += String.fromCodePoint(b); i += 1; }
  }
  if (cur !== '') { lines.push({ text: cur, eol: '' }); }
  const crlf = lines.filter((l) => l.eol === '\r\n').length;
  const lf = lines.filter((l) => l.eol === '\n').length;
  const bareCr = lines.filter((l) => l.eol === '\r').length;
  let stripped = 0;
  const outLines = lines.map((L) => {
    const t = L.text.replace(/[ \t]+$/, '');
    if (t !== L.text) stripped++;
    return { text: t, eol: L.eol };
  });
  const out = outLines.map((L) => L.text + L.eol).join('');
  fs.writeFileSync(p, out);
  console.log(
    d +
      ' eol=' + (crlf > lf && crlf > bareCr ? 'CRLF' : 'LF') +
      ' crlf=' + crlf + ' lf=' + lf + ' bareCr=' + bareCr +
      ' lines=' + lines.length +
      ' stripped=' + stripped +
      ' bytesBefore=' + total +
      ' bytesAfter=' + Buffer.byteLength(out, 'utf8')
  );
}
