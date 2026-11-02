/**
 * 4단계 스모크 — 관리자 '해석 문구' 화면: 점검표 통과, 위반 0, 미리보기, 일반 사용자 접근 차단.
 * 실행: node doc/pw/admin-content-smoke.js
 */
'use strict';
const fs = require('fs');
const { BASE, OUT, startServer, resetDb, openBrowser, ok, done, login, freshInstall } = require('./lib');

(async () => {
  resetDb();
  const server = await startServer();
  const { browser, page } = await openBrowser({ viewport: { width: 1280, height: 900 } });
  try {
    ok(await freshInstall(page), '설치');

    // 일반 사용자는 관리자 화면 불가
    await login(page, 'user', 'visang');
    await page.goto(`${BASE}/admin/content.php`);
    ok(page.url().endsWith('/admin/login.php'), '일반 사용자 → 관리자 로그인으로');

    await login(page, 'sonbm', 'visang', true);
    await page.goto(`${BASE}/admin/content.php`);
    ok((await page.textContent('h1')).includes('해석 문구'), '관리자 해석 문구 화면');
    const rows = await page.$$eval('#contentCheck tbody tr', (trs) => trs.map((t) => t.lastElementChild.textContent.trim()));
    ok(rows.length >= 15 && rows.every((r) => r === '통과'), `누락 점검표 ${rows.length}행 모두 통과`);
    ok(await page.isVisible('#violationsNone'), '문구 원칙 위반 0건');
    ok((await page.textContent('h2:has-text("누락 점검표") .badge')).includes('통과'), '점검표 배지 통과');

    // 미리보기: 신경성 높은 쪽 → 도움 안내
    await page.selectOption('select[name=d]', 'N');
    await page.selectOption('select[name=b]', 'high');
    await Promise.all([page.waitForLoadState('load'), page.click('button:has-text("보기")')]);
    const pv = await page.textContent('#preview');
    ok(pv.includes('신경성 · 높은 쪽') && pv.includes('1577-0199'), '미리보기: 신경성 높은 쪽 + 도움 안내');
    ok(pv.includes('N1 불안') && pv.includes('N6 취약성'), '미리보기: 하위 요인 여섯 개');
    // 잘못된 매개변수는 기본값으로
    await page.goto(`${BASE}/admin/content.php?d=<script>&b=x`);
    ok((await page.textContent('#preview h3')).includes('성실성 · 높은 쪽'), '잘못된 매개변수 → 기본값(성실성·높은 쪽)');
    fs.mkdirSync(OUT, { recursive: true });
    await page.goto(`${BASE}/admin/content.php`);
    await page.screenshot({ path: `${OUT}/stage4-admin-content.png`, fullPage: false });
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    await browser.close();
    server.stop();
    done();
  }
})();
