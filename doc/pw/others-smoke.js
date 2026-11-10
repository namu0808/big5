/**
 * 6단계 스모크 — 초대 링크 → 다른 브라우저(로그인 없음)에서 평가 → 본인 리포트 9장 반영,
 * 만료·위조 링크 차단, 다른 사용자 리포트 차단, 내 기록·비교·리포트 10장, 내 데이터 전체 삭제.
 * 실행: node doc/pw/others-smoke.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { ROOT, BASE, ORIGIN, OUT, DB, DB_CONFIG, startServer, resetDb, openBrowser, ok, done, login, freshInstall, sql } = require('./lib');

/** 로그인 없는 새 브라우저로 평가 한 건 완료 */
async function rateOnce(link, pick) {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
  await ctx.route(/^https?:\/\/(?!127\.0\.0\.1)/, (r) => r.abort());
  const p = await ctx.newPage();
  const out = {};
  await p.goto(link);
  out.intro = await p.textContent('body');
  out.menuHidden = !(await p.$('.topbar'));
  await p.click('button:has-text("평가 시작")');
  out.needAgree = (await p.textContent('body')).includes('동의란에 표시해 주세요');
  await p.check('input[name=agree]');
  await Promise.all([p.waitForLoadState('load'), p.click('button:has-text("평가 시작")')]);
  out.guide = await p.textContent('.test-guide');
  out.firstText = await p.textContent('.q .q-text');
  for (let pg = 0; pg < 2; pg++) {
    for (const q of await p.$$('.q')) {
      const no = Number(await q.getAttribute('data-no'));
      await (await q.$(`.scale-btn[data-value="${pick(no)}"]`)).click();
    }
    if (pg === 0) {
      await p.click('#btnNext');
      await p.waitForFunction(() => document.querySelector('.q').getAttribute('data-no') === '11');
    }
  }
  await Promise.all([p.waitForURL(/done=1/, { timeout: 15000 }), p.click('#btnNext')]);
  out.done = await p.textContent('body');
  await browser.close();
  return out;
}

(async () => {
  resetDb();
  const server = await startServer();
  let { browser, page } = await openBrowser({ viewport: { width: 1100, height: 900 } });
  try {
    ok(await freshInstall(page), '설치');
    const env = { ...process.env, BIG5_DB_CONFIG: DB_CONFIG, BIG5_DEV_SQLITE: DB };
    const ids = JSON.parse(execFileSync('php', [path.join(ROOT, 'doc', 'dev-seed-reports.php')], { env }).toString());
    await login(page, 'user', 'visang');

    // ── 초대 만들기
    await page.goto(`${BASE}/invite.php`);
    ok((await page.textContent('body')).includes('평가해 준 사람이 한 명뿐이면'), '초대 화면: 한 명일 때 응답이 드러난다는 안내');
    await page.selectOption('select[name=relation]', 'colleague');
    await Promise.all([page.waitForLoadState('load'), page.click('button:has-text("링크 만들기")')]);
    const link = await page.inputValue('#inviteLink');
    ok(/rate\.php\?t=[0-9a-f]{64}$/.test(link), '초대 링크 생성 (64자 토큰)');
    const token = link.split('t=')[1];
    const rows = sql('SELECT token_hash FROM big5_invites');
    ok(rows.length === 1 && rows[0].token_hash !== token, 'DB 에는 토큰 원문이 없음');
    await page.reload();
    ok(!(await page.$('#inviteLink')), '새로고침하면 링크를 다시 보여 주지 않음');

    // ── 다른 브라우저(로그인 없음)에서 평가
    const r1 = await rateOnce(link, (no) => ((no * 3) % 5) + 1);
    ok(r1.intro.includes('사용자님이 평가를 부탁했어요') && r1.menuHidden, '평가 안내: 부탁한 사람 이름, 메뉴 없음');
    ok(r1.needAgree, '평가: 동의 없이 시작 불가');
    ok(r1.firstText.includes('이 사람은'), "평가 문항은 '이 사람은 ~' 문장");
    ok(r1.guide.includes('사용자님'), '평가 안내 줄에 대상 이름');
    ok(r1.done.includes('고맙습니다') && !/\d+\.\d\s*·\s*(낮은 쪽|가운데|높은 쪽)/.test(r1.done) && !r1.done.includes('외향성'), '완료 화면: 결과를 보여 주지 않음');

    // ── 본인 리포트 9장 (한 사람의 시선)
    const latest = Math.max(...Object.values(ids));
    await page.goto(`${BASE}/report.php?id=${latest}`);
    let othersText = await page.textContent('#others');
    ok(othersText.includes('한 사람의 시선') && othersText.includes('동료 1'), '리포트 9장: 한 사람의 시선, 관계 범주만');
    ok((await page.$$('#others .c-row')).length === 5, '리포트 9장: 다섯 다이얼 나란히');
    ok(!!(await page.$('a[href="#others"]')), '목차에 9장');

    // 두 번째 평가 → 평균
    await rateOnce(link, () => 5);
    await page.reload();
    othersText = await page.textContent('#others');
    ok(othersText.includes('다른 사람들의 평균') && othersText.includes('동료 2'), '평가 2건 → 평균 표시');
    ok(othersText.includes('내가 보는 나와 남이 보는 나가 다른 곳'), '15점 이상 차이 표시');
    fs.mkdirSync(OUT, { recursive: true });
    await (await page.$('#others')).screenshot({ path: `${OUT}/stage6-report-others.png` });
    await (await page.$('#retest')).screenshot({ path: `${OUT}/stage6-report-retest.png` });

    // ── 리포트 10장 (바로 앞 검사와 비교)
    ok(!!(await page.$('#retest')) && (await page.$$('#retest .c-row')).length === 5, '리포트 10장: 지난번과 비교');
    await page.goto(`${BASE}/report.php?id=${ids.all3}`);
    ok(!(await page.$('#retest')), '첫 검사 리포트에는 10장 없음');
    // 지난번 성장 행동 다시 보기
    sql('INSERT INTO big5_growth_choices (user_id, session_id, domain, direction, action_key, created_at) VALUES ((SELECT id FROM sbm_users WHERE username = ?), ?, ?, ?, ?, ?)', ['user', ids.random, 'C', 'grow', 'c_g_calendar', '2026-11-02 00:00:00']);
    await page.goto(`${BASE}/report.php?id=${ids.extreme}`);
    ok((await page.textContent('#growthRecall')).includes('달력에 넣고'), '리포트 10장: 지난번에 골라 둔 성장 행동');

    // ── 내 기록 → 비교
    await page.goto(`${BASE}/history.php`);
    ok((await page.$$('.h-item')).length === 5, '내 기록: 검사 5건');
    await page.check(`input[name="pick[]"][value="${ids.all3}"]`);
    await page.check(`input[name="pick[]"][value="${ids.extreme}"]`);
    await Promise.all([page.waitForLoadState('load'), page.click('button:has-text("비교하기")')]);
    ok(page.url().includes('compare.php') && (await page.$$('.c-row')).length === 35, '비교: 요인 5 + 하위 요인 30');
    ok((await page.textContent('body')).includes('측정 오차'), '비교: 고정 안내');
    await page.goto(`${BASE}/compare.php?pick[]=${ids.all3}&pick[]=${ids.mini}`);
    ok((await page.$$('.c-row')).length === 5 && (await page.textContent('body')).includes('종류가 달라'), '정식·간이 비교: 요인만 + 안내');

    // ── 만료·위조 링크
    await page.goto(`${BASE}/invite.php`);
    await Promise.all([page.waitForLoadState('load'), page.click('#inviteTable button:has-text("멈추기")')]);
    const b2 = await chromium.launch();
    const p2 = await (await b2.newContext()).newPage();
    let resp = await p2.goto(link);
    ok(resp.status() === 410 && (await p2.textContent('h1')).includes('기한이 지난'), '멈춘(만료) 링크 → 410 차단');
    resp = await p2.goto(`${BASE}/rate.php?t=${'a'.repeat(64)}`);
    ok(resp.status() === 404, '없는 토큰 → 404');
    resp = await p2.goto(`${BASE}/rate.php?t=../../etc`);
    ok(resp.status() === 404, '형식이 틀린 토큰 → 404');
    await b2.close();

    // ── 다른 사용자 리포트 id 차단
    const b3 = await chromium.launch();
    const p3 = await (await b3.newContext()).newPage();
    await login(p3, 'sonbm', 'visang');
    resp = await p3.goto(`${BASE}/report.php?id=${ids.extreme}`);
    ok(resp.status() === 404, '다른 사용자 리포트 id → 404');
    resp = await p3.goto(`${BASE}/compare.php?pick[]=${ids.all3}&pick[]=${ids.extreme}`);
    ok(resp.status() === 404, '다른 사용자 검사 비교 → 404');
    await b3.close();

    // ── 다이얼 기록만 삭제 (계정은 남는다)
    await page.goto(`${BASE}/account.php`);
    ok(await page.getAttribute('#sbmAccountLink', 'href') === '/account/', '계정 화면 → 사이트 공통 내 계정 링크');
    await page.fill('#deleteForm input[name=current]', 'visang');
    await page.fill('#deleteForm input[name=phrase]', '삭제');
    await Promise.all([page.waitForLoadState('load'), page.click('#deleteForm button')]);
    ok((await page.textContent('.danger-box')).includes('정확히 적어'), '삭제: 확인 문구가 틀리면 거부');
    await page.fill('#deleteForm input[name=current]', 'visang');
    await page.fill('#deleteForm input[name=phrase]', '모두 삭제');
    await Promise.all([page.waitForLoadState('load'), page.click('#deleteForm button')]);
    ok((await page.textContent('body')).includes('다이얼 검사 기록을 모두 지웠습니다'), '다이얼 기록 삭제 완료 → 시작 화면 안내');
    const left = sql(`SELECT
      (SELECT COUNT(*) FROM big5_sessions WHERE user_id = (SELECT id FROM sbm_users WHERE username = 'user')) AS s,
      (SELECT COUNT(*) FROM big5_invites WHERE user_id = (SELECT id FROM sbm_users WHERE username = 'user')) AS i,
      (SELECT COUNT(*) FROM big5_growth_choices WHERE user_id = (SELECT id FROM sbm_users WHERE username = 'user')) AS g`)[0];
    ok(Object.values(left).every((v) => v === 0), `내 다이얼 기록 0건 ${JSON.stringify(left)}`);
    // 실제로 지우지 않는다(2026-11-10) — 기록은 빈 은퇴 계정('~del…')으로 옮겨 남는다
    const kept = sql(`SELECT
      (SELECT COUNT(*) FROM big5_sessions s JOIN sbm_users u ON u.id = s.user_id WHERE u.username LIKE '~del%' AND u.is_active = 0) AS s,
      (SELECT COUNT(*) FROM big5_ratings) AS ra`)[0];
    ok(kept.s > 0 && kept.ra > 0, `지운 기록은 DB 에 보존 ${JSON.stringify(kept)}`);
    await page.goto(`${BASE}/history.php`);
    ok(await page.locator('a[href*="report.php?id="]').count() === 0, '지운 뒤 내 기록 화면에 옛 검사 없음');
    ok(sql("SELECT COUNT(*) AS n FROM sbm_users WHERE username = 'user'")[0].n === 1, '계정은 남음');
    await page.goto(`${BASE}/history.php`);
    ok(page.url().endsWith('/big5/history.php'), '기록 삭제 후에도 로그인 유지');

    // ── 사이트 공통 [내 계정]에서 계정 전체 삭제 → 다이얼 데이터와 계정 모두
    const sid = sql("SELECT id FROM sbm_users WHERE username = 'user'")[0].id;
    sql("INSERT INTO big5_sessions (user_id, test_type, item_version, status, started_at, consent_at) VALUES (?, 'mini20', 'v', 'in_progress', '2026-11-05 00:00:00', '2026-11-05 00:00:00')", [sid]);
    await page.goto(`${ORIGIN}/account/`);
    ok((await page.textContent('.svc-list')).includes('Dial'), '내 계정: 이용 중인 검사에 다이얼');
    await page.fill('#deleteForm input[name=current]', 'visang');
    await page.fill('#deleteForm input[name=phrase]', '모두 삭제');
    await Promise.all([page.waitForLoadState('load'), page.click('#deleteForm button')]);
    ok(page.url() === `${ORIGIN}/` && (await page.textContent('.flash')).includes('지웠습니다'), '계정 전체 삭제 → 홈 안내');
    const gone = sql(`SELECT (SELECT COUNT(*) FROM sbm_users WHERE username = 'user') AS u,
      (SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?) AS s`, [sid])[0];
    ok(gone.u === 0 && gone.s === 1, `계정은 은퇴(아이디 풀림)·다이얼 기록은 보존 ${JSON.stringify(gone)}`);
    await page.goto(`${BASE}/history.php`);
    ok(page.url().includes('/account/login.php'), '삭제 후 로그아웃 상태 → 공통 로그인');
    await login(page, 'user', 'visang');
    ok((await page.textContent('body')).includes('맞지 않습니다'), '삭제된 계정으로 로그인 불가');
    fs.mkdirSync(OUT, { recursive: true });
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    await browser.close();
    server.stop();
    done();
  }
})();
