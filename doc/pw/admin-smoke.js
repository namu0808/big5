/**
 * 7단계 스모크 — 관리자 화면: 접근 차단, 대시보드(집계·품질·분포), 사용자 검색·사용 중지, 문항 조회,
 * 규준 잠금(300건 미만), 익명 CSV(아이디·이름 없음, 품질 플래그 제외 옵션).
 * 실행: node doc/pw/admin-smoke.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { ROOT, BASE, OUT, DB, DB_CONFIG, startServer, resetDb, openBrowser, ok, done, login, freshInstall, sql } = require('./lib');

const PAGES = ['index', 'users', 'items', 'content', 'norms', 'export'];

(async () => {
  resetDb();
  const server = await startServer();
  const { browser, context, page } = await openBrowser({ viewport: { width: 1280, height: 900 } });
  try {
    ok(await freshInstall(page), '설치');
    const env = { ...process.env, BIG5_DB_CONFIG: DB_CONFIG, BIG5_DEV_SQLITE: DB };
    execFileSync('php', [path.join(ROOT, 'doc', 'dev-seed-reports.php')], { env });

    // ── 일반 사용자·로그인 없음 → 모든 관리자 화면 차단
    await login(page, 'user', 'visang');
    let blocked = 0;
    for (const p of PAGES) {
      await page.goto(`${BASE}/admin/${p}.php`);
      if (page.url().endsWith('/admin/login.php')) blocked++;
    }
    ok(blocked === PAGES.length, `일반 사용자 → 관리자 화면 ${PAGES.length}개 모두 차단`);
    const anon = await chromium.launch();
    const ap = await (await anon.newContext()).newPage();
    const r = await ap.goto(`${BASE}/admin/export.php?download=1&kind=self&type=full120`);
    ok(ap.url().endsWith('/admin/login.php') && !(await ap.content()).includes('respondent'), '로그인 없이 CSV 내려받기 차단');
    await anon.close();

    await login(page, 'sonbm', 'visang', true);
    fs.mkdirSync(OUT, { recursive: true });

    // ── 대시보드
    await page.goto(`${BASE}/admin/index.php`);
    const stats = await page.$$eval('.stat', (els) => els.map((e) => e.textContent.replace(/\s+/g, ' ').trim()));
    ok(stats.some((t) => /^정식 검사 완료\s*4$/.test(t)) && stats.some((t) => /^간이 검사 완료\s*1$/.test(t)), '대시보드: 정식 4·간이 1');
    ok((await page.textContent('#flagRatio')).includes('%'), '대시보드: 품질 플래그 비율');
    ok((await page.$$('#distGrid svg.hist')).length === 5, '대시보드: 요인별 분포 5개');
    await page.screenshot({ path: `${OUT}/stage7-admin-dashboard.png`, fullPage: true });
    await page.click('a:has-text("품질 플래그 제외")');
    ok(page.url().includes('clean=1'), '분포: 품질 플래그 제외 보기');

    // ── 사용자: 검색·사용 중지
    await page.goto(`${BASE}/admin/users.php?q=use`);
    ok((await page.$$('#userTable tbody tr')).length === 1 && !!(await page.$('tr[data-username="user"]')), '사용자 검색');
    await page.goto(`${BASE}/admin/users.php?q=%25`);
    ok((await page.$$('#userTable tbody tr')).length === 0, "검색어 '%' 는 글자 그대로 (LIKE 이스케이프)");
    await page.goto(`${BASE}/admin/users.php`);
    ok(!(await page.$('tr[data-username="sonbm"] button')), '관리자 계정은 사용 중지 단추 없음');
    await Promise.all([page.waitForLoadState('load'), page.click('tr[data-username="user"] button')]);
    ok((await page.textContent('tr[data-username="user"]')).includes('중지'), '사용 중지');
    const ub = await chromium.launch();
    const up = await (await ub.newContext()).newPage();
    await login(up, 'user', 'visang');
    ok((await up.textContent('body')).includes('사용이 중지된 계정'), '중지된 사용자는 로그인 불가');
    await Promise.all([page.waitForLoadState('load'), page.click('tr[data-username="user"] button')]);
    await login(up, 'user', 'visang');
    ok(up.url().endsWith('/index.php'), '다시 사용 → 로그인 가능');
    await ub.close();

    // ── 문항
    await page.goto(`${BASE}/admin/items.php`);
    ok((await page.$$('#itemTable tbody tr')).length === 120 && (await page.textContent('#itemVersion')).includes('ipip-neo-120'), '문항: 정식 120, 버전');
    await page.goto(`${BASE}/admin/items.php?type=mini20`);
    ok((await page.$$('#itemTable tbody tr')).length === 20, '문항: 간이 20');

    // ── 규준 잠금
    await page.goto(`${BASE}/admin/norms.php`);
    ok(await page.isVisible('#normsLocked') && await page.isDisabled('#btnCompute'), '규준: 300건 미만이면 잠금·단추 비활성');
    const forced = await page.evaluate(async (url) => {
      const fd = new FormData();
      fd.append('csrf', document.querySelector('input[name=csrf]').value);
      fd.append('action', 'compute');
      return (await fetch(url, { method: 'POST', body: fd })).text();
    }, `${BASE}/admin/norms.php`);
    ok(forced.includes('300건 이상이어야'), '규준: 단추를 우회한 POST 도 거부');
    ok(sql('SELECT COUNT(*) AS n FROM big5_norms')[0].n === 0, '규준 표 비어 있음');

    // ── 익명 CSV
    const get = async (q) => {
      const res = await context.request.get(`${BASE}/admin/export.php?download=1&${q}`);
      return { type: res.headers()['content-type'], disp: res.headers()['content-disposition'], body: (await res.body()).toString('utf8') };
    };
    const all = await get('kind=self&type=full120');
    const lines = all.body.replace(/^﻿/, '').trim().split('\n');
    ok(all.type.startsWith('text/csv') && /attachment; filename="big5_self_full120_\d{8}\.csv"/.test(all.disp), 'CSV 내려받기 (text/csv, 파일 이름)');
    ok(all.body.startsWith('﻿'), 'CSV: 엑셀용 BOM');
    ok(lines.length === 5 && lines[0].startsWith('respondent,kind,relation,test_type'), 'CSV: 머리 줄 + 정식 4건');
    ok(!/\buser\b|sonbm|사용자|손병목|"display_name"|username/.test(all.body), 'CSV: 아이디·이름 없음');
    const hashes = new Set(lines.slice(1).map((l) => l.split(',')[0]));
    ok(hashes.size === 1 && /^[0-9a-f]{64}$/.test([...hashes][0]), 'CSV: 응답자는 64자 해시 하나 (같은 사람 = 같은 해시)');
    const flagged = sql("SELECT COUNT(*) AS n FROM big5_sessions WHERE test_type = 'full120' AND quality_flags IS NOT NULL AND quality_flags <> ''")[0].n;
    const clean = await get('kind=self&type=full120&clean=1');
    const cleanLines = clean.body.trim().split('\n');
    ok(cleanLines.length === 5 - flagged && flagged > 0, `CSV 품질 플래그 제외: ${4 - flagged}건 (플래그 ${flagged}건 제외)`);
    const mini = await get('kind=self&type=mini20');
    ok(mini.body.trim().split('\n').length === 2 && !mini.body.includes('pomp_C1'), '간이 CSV: 1건, 하위 요인 열 없음');
    await page.goto(`${BASE}/admin/export.php`);
    await page.screenshot({ path: `${OUT}/stage7-admin-export.png` });
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    await browser.close();
    server.stop();
    done();
  }
})();
