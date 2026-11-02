/**
 * 전체 검증 — CLI(php tests/run.php) + 브라우저 스모크(doc/pw/*-smoke.js) 순서대로.
 * 실행: node doc/pw/run.js  (--cli 는 CLI 만, --pw 는 브라우저만)
 */
'use strict';
const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const ROOT = path.resolve(__dirname, '..', '..');
const arg = process.argv[2] || '';
let failed = 0;

if (arg !== '--pw') {
  const r = spawnSync('php', ['tests/run.php'], { cwd: ROOT, stdio: 'inherit' });
  if (r.status !== 0) failed++;
}
if (arg !== '--cli') {
  const smokes = fs.readdirSync(__dirname).filter((f) => f.endsWith('-smoke.js')).sort();
  for (const s of smokes) {
    console.log(`\n== ${s}`);
    const r = spawnSync('node', [path.join(__dirname, s)], { cwd: ROOT, stdio: 'inherit' });
    if (r.status !== 0) failed++;
  }
}
console.log(failed === 0 ? '\n== 전체 통과' : `\n== 실패 묶음 ${failed}개`);
process.exitCode = failed === 0 ? 0 : 1;
