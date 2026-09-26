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
  const hasCRLF = buf.includes(Buffer.from('\r\n'));
  const eol = hasCRLF ? '\r\n' : '\n';
  const text = buf.toString('utf8');
  const rawLines = text.split(/\r\n|\n/);
  let stripped = 0;
  let changedAny = false;
  const outLines = rawLines.map((ln) => {
    let n = ln;
    if (/[ \t]+$/.test(n)) {
      const pre = n;
      n = n.replace(/[ \t]+$/, '');
      if (n !== pre) { stripped++; changedAny = true; }
    }
    return n;
  });
  if (changedAny) {
    const out = outLines.join(eol);
    fs.writeFileSync(p, out);
  }
  console.log(d + ' eol=' + (hasCRLF ? 'CRLF' : 'LF') + ' lines=' + outLines.length +
    ' trailingWsStripped=' + stripped + ' bytesAfter=' + Buffer.byteLength(outLines.join(eol), 'utf8'));
}
