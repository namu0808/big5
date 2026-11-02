/**
 * 8단계 스모크 — 모바일(390)·태블릿(768)·PC(1280) 폭에서 주요 화면이 가로로 넘치지 않는지, 검사 화면 버튼 크기.
 * 실행: node doc/pw/responsive-smoke.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { ROOT, BASE, OUT, DB, DB_CONFIG, startServer, resetDb, openBrowser, ok, done, login, freshInstall, sql } = require('./lib');

const WIDTHS = [390, 768, 1280];

(async () => {
  resetDb();
  const server = await startServer();
  const setup = await openBrowser();
  let browser = setup.browser;
  try {
    ok(await freshInstall(setup.page), '설치');
    const env = { ...process.env, BIG5_DB_CONFIG: DB_CONFIG, BIG5_DEV_SQLITE: DB };
    const ids = JSON.parse(execFileSync('php', [path.join(ROOT, 'doc', 'dev-seed-reports.php')], { env }).toString());
    await setup.browser.close();
    fs.mkdirSync(OUT, { recursive: true });

    for (const w of WIDTHS) {
      browser = await chromium.launch();
      const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 500, hasTouch: w < 1000 });
      await ctx.route(/^https?:\/\/(?!127\.0\.0\.1)/, (r) => r.abort());
      const page = await ctx.newPage();
      await login(page, 'user', 'visang');
      // 검사 하나 시작
      await page.goto(`${BASE}/consent.php?type=full120`);
      await page.check('input[name=agree]');
      await Promise.all([page.waitForURL(/test\.php/), page.click('.consent-form button[type=submit]')]);
      const testUrl = page.url();
      const userPages = {
        '시작': `${BASE}/index.php`, '동의': `${BASE}/consent.php?type=mini20`, '검사': testUrl,
        '리포트': `${BASE}/report.php?id=${ids.extreme}`, '내 기록': `${BASE}/history.php`, '초대': `${BASE}/invite.php`, '계정': `${BASE}/account.php`,
      };
      const over = [];
      for (const [name, url] of Object.entries(userPages)) {
        await page.goto(url);
        const o = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (o > 1) over.push(`${name}(+${o}px)`);
      }
      ok(over.length === 0, `${w}px 사용자 화면 ${Object.keys(userPages).length}개 가로 넘침 없음${over.length ? ' — ' + over.join(', ') : ''}`);
      await page.goto(testUrl);
      const box = await (await page.$('.scale-btn')).boundingBox();
      ok(box.height >= 48 && box.width >= 48, `${w}px 검사 선택지 버튼 ${Math.round(box.width)}×${Math.round(box.height)}`);
      await page.screenshot({ path: `${OUT}/stage8-${w}-test.png` });
      await page.goto(userPages['리포트']);
      await page.screenshot({ path: `${OUT}/stage8-${w}-report.png` });

      await login(page, 'sonbm', 'visang', true);
      const adminOver = [];
      for (const p of ['index', 'users', 'items', 'content', 'norms', 'export']) {
        await page.goto(`${BASE}/admin/${p}.php`);
        const o = await page.evaluate(() => {
          // 표는 .table-wrap 안에서 가로로 스크롤되므로 페이지 자체가 넘치는지만 본다
          return document.documentElement.scrollWidth - window.innerWidth;
        });
        if (o > 1) adminOver.push(`${p}(+${o}px)`);
      }
      ok(adminOver.length === 0, `${w}px 관리자 화면 6개 가로 넘침 없음${adminOver.length ? ' — ' + adminOver.join(', ') : ''}`);
      await browser.close();
    }
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    try { await browser.close(); } catch (e) { /* 이미 닫힘 */ }
    server.stop();
    done();
  }
})();
