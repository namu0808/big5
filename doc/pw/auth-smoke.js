/**
 * 1단계 스모크 — install.php 실행 → 사이트 공통 로그인(한 번 로그인)·관리자 로그인, 세션 분리, 가입, 접근 차단(403).
 * 실행: node doc/pw/auth-smoke.js
 */
'use strict';
const fs = require('fs');
const { BASE, ORIGIN, OUT, startServer, resetDb, openBrowser, ok, done, login, status, sql } = require('./lib');

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

    // 2-1) 검색 노출 — 시작 화면만 색인 허용, 나머지는 noindex
    await page.goto(`${BASE}/index.php`);
    ok((await page.getAttribute('meta[name=robots]', 'content')).startsWith('index'), '시작 화면 색인 허용');
    ok((await page.title()).includes('빅파이브(Big5)'), '시작 화면 제목에 빅파이브(Big5)');
    ok((await page.getAttribute('link[rel=canonical]', 'href')).endsWith('/big5/'), '시작 화면 canonical');
    ok((await page.getAttribute('meta[name=description]', 'content') || '').includes('초등 공부력'), '시작 화면 설명');
    ok(await page.isVisible('footer a[href="/"]'), '꼬리에 SBM 진단 검사 홈 링크');
    await page.goto(`${BASE}/admin/login.php`);
    ok((await page.getAttribute('meta[name=robots]', 'content')) === 'noindex', '관리자 화면 noindex');

    // 3) 보호 화면은 로그인으로 — 사용자 화면은 사이트 공통 로그인(돌아올 곳 유지), 관리자는 다이얼 관리자 로그인
    await page.goto(`${BASE}/admin/index.php`);
    ok(page.url().endsWith('/big5/admin/login.php'), '미로그인 관리자 화면 → admin/login.php');
    await page.goto(`${BASE}/history.php`);
    ok(page.url() === `${ORIGIN}/account/login.php?next=%2Fbig5%2Fhistory.php`, '미로그인 내 기록 → 공통 로그인(next=/big5/history.php)');
    await page.fill('input[name=username]', 'user');
    await page.fill('input[name=password]', 'visang');
    await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
    ok(page.url() === `${BASE}/history.php`, '공통 로그인 뒤 다이얼 화면으로 돌아옴');
    const ck = (await context.cookies()).find((c) => c.name === 'SBMSESSID');
    ok(!!ck && ck.path === '/', '세션 쿠키 SBMSESSID 경로 / (사이트 공통)');
    await page.goto(`${ORIGIN}/`);
    ok((await page.textContent('.gnb')).includes('내 계정'), '홈에서도 로그인 상태 (한 번 로그인)');
    await page.goto(`${BASE}/logout.php`);
    await page.goto(`${ORIGIN}/`);
    ok(!(await page.textContent('.gnb')).includes('내 계정'), '다이얼에서 로그아웃 → 홈도 로그아웃');
    await page.goto(`${BASE}/login.php`);
    ok(page.url().startsWith(`${ORIGIN}/account/login.php`), '예전 주소 /big5/login.php → 공통 로그인');

    // 4) 사용자 로그인
    await login(page, 'user', 'visang');
    ok(page.url().endsWith('/big5/index.php'), 'user/visang 공통 로그인 → 다이얼 시작 화면');
    ok((await page.textContent('body')).includes('사용자님, 반갑습니다'), '시작 화면에 표시 이름');
    // 상단(0.10.1) — 공부력 진단과 같은 모양: SBM 표식·Dial·'SBM 진단 검사 홈'·내 메뉴·[내 계정]
    ok((await page.getAttribute('.gnb a.brand-mark', 'href')) === '/', '상단: SBM 표식 → 사이트 홈(휴대폰에서도 홈으로 가는 길)');
    ok(await page.isVisible('.gnb .brand-mark') && (await page.textContent('.gnb .brand-name')).trim() === 'Dial' && (await page.getAttribute('.gnb-menu a:has-text("SBM 진단 검사 홈")', 'href')) === '/', '상단: SBM 표식·Dial·사이트 홈 링크');
    ok(await page.isVisible('.gnb-menu a:has-text("내 기록")') && (await page.getAttribute('.gnb a.gnb-cta', 'href')) === '/account/' && (await page.textContent('.gnb-user-name')).includes('사용자님'), '로그인 상태 상단: 내 기록·[내 계정]');

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
    ok(await page.isVisible('.gnb a.gnb-cta:has-text("로그인")') && (await page.getAttribute('.gnb a.gnb-cta', 'href')).startsWith('/account/login.php?next='), '사용자 로그아웃 → 상단 [로그인](공통 로그인)');
    ok((await page.getAttribute('.hero-sub a:has-text("익명으로 가입")', 'href')).startsWith('/account/register.php?next='), '시작 화면: 익명으로 가입 → 공통 가입');
    for (const w of [390, 768, 1280]) {
      await page.setViewportSize({ width: w, height: 900 });
      const g = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth - document.documentElement.clientWidth, cta: document.querySelector('.gnb-cta').getBoundingClientRect().height }));
      ok(g.sw <= 0 && g.cta >= 44, `${w}px 상단 가로 넘침 없음·버튼 44px`);
    }
    await page.setViewportSize({ width: 1280, height: 900 });

    // 9) 가입 (만 14세 미확인 → 거부, 확인 → 가입·자동 로그인 → 다이얼로 돌아옴)
    await page.goto(`${BASE}/register.php`);
    ok(page.url().startsWith(`${ORIGIN}/account/register.php`), '예전 주소 /big5/register.php → 공통 가입');
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
    ok(page.url() === `${BASE}/index.php` && (await page.textContent('body')).includes('시험님, 반갑습니다'), '가입 후 자동 로그인 → 다이얼 시작 화면');
    ok(sql("SELECT COUNT(*) AS n FROM sbm_users WHERE username = 'tester1'")[0].n === 1, '가입 계정은 공통 계정 테이블(sbm_users)');

    // 10) CSRF 없는 POST 거부
    const { request } = require('playwright');
    const anon = await request.newContext();
    const res = await anon.post(`${ORIGIN}/account/login.php`, { form: { username: 'user', password: 'visang' }, maxRedirects: 0 });
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
