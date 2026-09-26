'use strict';
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const R = 'C:/xampp/htdocs/2DMIS-v2';
const M = R + '/gate-marker.txt';
const out = [];

function run(cmd, args) {
  try {
    const r = execFileSync(cmd, args, {
      cwd: R,
      encoding: 'utf8',
      stdio: ['pipe', 'pipe', 'pipe'],
      windowsHide: true
    });
    return { ok: true, out: r };
  } catch (e) {
    return { ok: false, out: (e.stdout || '') + (e.stderr || '') };
  }
}

const d = run('git', ['diff', '--check']);
out.push('GIT_DIFF_CHECK ok=' + d.ok);
out.push('GIT_DIFF_CHECK_head=' + (d.out.split(/\r?\n/).slice(0, 5).join(' | ')));

const c = run('git', ['status', '--porcelain']);
const lines = (c.out || '').split(/\r?\n/).filter(Boolean);
out.push('GIT_PORCELAIN count=' + lines.length);
out.push('GIT_PORCELAIN_head=' + lines.slice(0, 4).join(' | '));

const suite = run('php', ['artisan', 'test']);
out.push('PHPUNIT ok=' + suite.ok);
const st = suite.out.match(/(\d+)\s+passed\s*\((\d+)\s+assertions\)/);
out.push('PHPUNIT_summary=' + (st ? st[1] + '/' + st[2] : 'parse-fail'));

const pint = run('C:/xampp/htdocs/2DMIS-v2/vendor/bin/pint', ['--test', '--dirty']);
out.push('PINT ok=' + pint.ok);

fs.writeFileSync(M, out.join('\n') + '\n', 'utf8');
console.log('MARKER_WRITTEN');
