/**
 * 1단계 스모크 — install.php 실행 → 사용자·관리자 로그인, 세션 분리, 가입, 접근 차단(403).
 * 실행: node doc/pw/auth-smoke.js
 */
'use strict';
const fs = require('fs');
const { BASE, OUT, startServer, resetDb, openBrowser, ok, done, login, status } = require('./lib');

(async () => {
  resetDb();
  const server = await startServer();
  const { browser, context, page } = await openBrowser({ viewport: { width: 390, height: 844 } });
  try {
    // 1) 설치
    await page.goto(`${BASE}/${process.env.BIG5_INSTALL_PATH || '1time/install.php'}`);
    const installText = await page.textContent('body');
    ok(installText.includes('설치 완료'), 'install.php 실행 → 설치 완료');
    ok(installText.includes('계정 sonbm 생성') && installText.includes('계정 user 생성'), '초기 계정 생성');

    // 2) 접근 차단 (운영 .htaccess 흉내)
    ok(await status(`${BASE}/includes/db_config.php`) === 403, 'includes/ 직접 접근 403');
    ok(await status(`${BASE}/data/.htaccess`) === 403, 'data/ 직접 접근 403');
    ok(await status(`${BASE}/CLAUDE.md`) === 403, 'CLAUDE.md 직접 접근 403');
    ok(await status(`${BASE}/doc/PLAN.md`) === 403, 'doc/ 직접 접근 403');
    for (const d of ['includes', 'data']) {
      ok(fs.readFileSync(`${__dirname}/../../${d}/.htaccess`, 'utf8').includes('Deny from all'), `${d}/.htaccess = Deny from all`);
    }

    // 3) 보호 화면은 로그인으로
    await page.goto(`${BASE}/admin/index.php`);
    ok(page.url().endsWith('/big5/admin/login.php'), '미로그인 관리자 화면 → admin/login.php');

    // 4) 사용자 로그인
    await login(page, 'user', 'visang');
    ok(page.url().endsWith('/big5/index.php'), 'user/visang 로그인 → 시작 화면');
    ok((await page.textContent('body')).includes('사용자님, 반갑습니다'), '시작 화면에 표시 이름');
    ok(await page.isVisible('a:has-text("로그아웃")'), '로그인 상태 메뉴');

    // 5) 사용자 세션만으로는 관리자 화면 불가 (세션 분리)
    await page.goto(`${BASE}/admin/index.php`);
    ok(page.url().endsWith('/admin/login.php'), '사용자 로그인만으로 관리자 화면 접근 불가');

    // 6) 일반 사용자는 관리자 로그인 불가
    await login(page, 'user', 'visang', true);
    ok((await page.textContent('body')).includes('맞지 않습니다'), '일반 계정 관리자 로그인 거부');

    // 7) 관리자 로그인
    await login(page, 'sonbm', 'visang', true);
    ok(page.url().endsWith('/big5/admin/index.php'), 'sonbm/visang 관리자 로그인 → 대시보드');
    ok((await page.textContent('h1')).includes('대시보드'), '대시보드 표시');

    // 8) 관리자 로그아웃해도 사용자 로그인은 유지
    await page.goto(`${BASE}/admin/logout.php`);
    await page.goto(`${BASE}/index.php`);
    ok((await page.textContent('body')).includes('사용자님, 반갑습니다'), '관리자 로그아웃 후 사용자 세션 유지');
    await page.goto(`${BASE}/logout.php`);
    ok(await page.isVisible('.topnav a:has-text("로그인")'), '사용자 로그아웃');

    // 9) 가입 (만 14세 미확인 → 거부, 확인 → 가입·자동 로그인)
    await page.goto(`${BASE}/register.php`);
    await page.fill('input[name=username]', 'tester1');
    await page.fill('input[name=password]', 'password1');
    await page.fill('input[name=password2]', 'password1');
    await page.fill('input[name=display_name]', '시험');
    await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
    ok((await page.textContent('.form-error')).includes('만 14세'), '만 14세 확인 없으면 가입 거부');
    await page.fill('input[name=password]', 'password1');
    await page.fill('input[name=password2]', 'password1');
    await page.check('input[name=age14]');
    await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
    ok((await page.textContent('body')).includes('가입이 끝났습니다'), '가입 후 자동 로그인');

    // 10) CSRF 없는 POST 거부
    const { request } = require('playwright');
    const anon = await request.newContext();
    const res = await anon.post(`${BASE}/login.php`, { form: { username: 'user', password: 'visang' }, maxRedirects: 0 });
    await anon.dispose();
    ok(res.status() === 400, 'CSRF 토큰 없는 POST → 400');

    // 11) 가로 넘침 없음 (모바일)
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    ok(!overflow, '모바일 390px 가로 넘침 없음');
    fs.mkdirSync(OUT, { recursive: true });
    await page.screenshot({ path: `${OUT}/stage1-home-mobile.png`, fullPage: true });
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    await browser.close();
    server.stop();
    done();
  }
})();
