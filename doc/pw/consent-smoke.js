/**
 * 학술 연구 동의는 이용 조건(홈 0.16.0, 대표 결정 2026-11-21) — 동의하지 않은 계정은 도구 화면에서
 * 공통 동의 화면(/account/consent.php)으로 가고, 동의하면 돌아온다. 관리자는 막지 않는다.
 * 실행: node doc/pw/consent-smoke.js
 */
'use strict';
const L = require('./lib');

(async () => {
  L.resetDb();
  const srv = await L.startServer();
  const { browser, page } = await L.openBrowser();
  try {
    L.ok(await L.freshInstall(page), 'install.php 설치 완료');
    const target = `${L.BASE}/history.php`;
    const v = () => L.sql("SELECT research_consent AS v FROM sbm_users WHERE username = 'user'")[0].v;
    L.ok(Number(v()) === 1, '개발 계정은 동의함(초기 계정)');
    await L.login(page, 'user', 'visang');
    await page.goto(target);
    L.ok(new URL(page.url()).pathname === new URL(target).pathname, '동의한 계정은 도구 화면 그대로');

    L.sql("UPDATE sbm_users SET research_consent = NULL WHERE username = 'user'");
    await page.goto(target);
    L.ok(new URL(page.url()).pathname === '/account/consent.php', '동의하지 않은 계정 → 동의 화면 (' + page.url() + ')');
    await page.click('button[type=submit]');
    await page.waitForLoadState('load');
    L.ok(new URL(page.url()).pathname === '/account/consent.php', '표시 없이 누르면 그대로');
    await page.check('input[name=research]');
    await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
    L.ok(new URL(page.url()).pathname === new URL(target).pathname && Number(v()) === 1, '동의하면 도구 화면으로 돌아옴 · DB 동의');

    L.sql("UPDATE sbm_users SET research_consent = 0 WHERE username = 'sonbm'");
    await page.context().clearCookies();
    await L.login(page, 'sonbm', 'visang');
    await page.goto(target);
    L.ok(new URL(page.url()).pathname !== '/account/consent.php', '관리자 계정은 막지 않음');
  } catch (e) {
    L.ok(false, '예외: ' + e.message);
  } finally {
    await browser.close();
    srv.stop ? srv.stop() : srv.kill();
  }
  L.done('consent');
})();
