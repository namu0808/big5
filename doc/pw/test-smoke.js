/**
 * 3단계 스모크 — 동의 → 검사(10문항/페이지, 자동 저장) → 브라우저 닫았다 열어 이어 하기 → 결과,
 * 미응답 시 [다음] 비활성, 남의 결과 접근 차단, 간이 검사·품질 안내, 모바일 가로 넘침.
 * 실행: node doc/pw/test-smoke.js
 */
'use strict';
const fs = require('fs');
const { chromium, request } = require('playwright');
const { BASE, OUT, startServer, resetDb, openBrowser, ok, done, login, sql, freshInstall } = require('./lib');

/** 문항 번호별로 고를 값 (고르게 섞인 응답) */
const pick = (no) => ((no * 7) % 5) + 1;

/** 현재 페이지의 문항에 모두 답한다 */
async function answerPage(page, valueOf) {
  const qs = await page.$$('.q');
  for (const q of qs) {
    const no = Number(await q.getAttribute('data-no'));
    await (await q.$(`.scale-btn[data-value="${valueOf(no)}"]`)).click();
  }
  return qs.length;
}

async function waitSaved(page) {
  await page.waitForFunction(() => document.getElementById('saveState').textContent === '자동 저장됨', null, { timeout: 10000 });
}

(async () => {
  resetDb();
  const server = await startServer();
  const mobile = { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true };
  let { browser, context, page } = await openBrowser(mobile);
  try {
    ok(await freshInstall(page), '설치');

    // ── 로그인 전: 검사 버튼 → 로그인 → 동의 화면으로 돌아옴
    await page.goto(`${BASE}/index.php`);
    await Promise.all([page.waitForLoadState('load'), page.click('a:has-text("정식 검사")')]);
    ok(page.url().endsWith('/login.php'), '로그인 전 검사 시작 → 로그인 화면');
    await page.fill('input[name=username]', 'user');
    await page.fill('input[name=password]', 'visang');
    await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
    ok(page.url().includes('/consent.php?type=full120'), '로그인 후 동의 화면으로 이어짐');

    // ── 동의
    const body = await page.textContent('body');
    ok(body.includes('진단 도구가 아닙니다') && body.includes('지우는 법') && body.includes('언제까지'), '동의 화면: 무엇이 아닌지·보관·삭제 안내');
    await Promise.all([page.waitForLoadState('load'), page.click('button:has-text("검사 시작")')]);
    ok((await page.textContent('.form-error')).includes('동의란'), '동의 없이 시작 불가');
    await page.check('input[name=agree]');
    await Promise.all([page.waitForURL(/test\.php\?id=\d+/), page.click('button:has-text("검사 시작")')]);
    const sid = Number(new URL(page.url()).searchParams.get('id'));
    ok(sid > 0, '검사 시작 → test.php');

    // ── 검사 화면 첫 페이지
    ok((await page.textContent('.test-guide')).includes('오늘의 기분보다 평소의 나를 떠올려 답해 주세요.'), '상단 고정 안내 한 줄');
    ok((await page.$$('.q')).length === 10, '한 화면 10문항');
    ok(await page.isDisabled('#btnNext'), '답하기 전 [다음] 비활성');
    const qs = await page.$$('.q');
    for (let i = 0; i < 9; i++) {
      const no = Number(await qs[i].getAttribute('data-no'));
      await (await qs[i].$(`.scale-btn[data-value="${pick(no)}"]`)).click();
    }
    ok(await page.isDisabled('#btnNext'), '9문항만 답하면 [다음] 여전히 비활성');
    const no10 = Number(await qs[9].getAttribute('data-no'));
    await (await qs[9].$(`.scale-btn[data-value="${pick(no10)}"]`)).click();
    ok(!(await page.isDisabled('#btnNext')), '10문항 모두 답하면 [다음] 활성');
    const btnBox = await (await qs[0].$('.scale-btn')).boundingBox();
    ok(btnBox.height >= 48 && btnBox.width >= 56, `선택지 버튼 엄지 크기 (${Math.round(btnBox.width)}×${Math.round(btnBox.height)})`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    ok(!overflow, '검사 화면 390px 가로 넘침 없음');
    fs.mkdirSync(OUT, { recursive: true });
    await page.screenshot({ path: `${OUT}/stage3-test-mobile.png` });

    // 2~5페이지 (50문항까지)
    for (let p = 2; p <= 5; p++) {
      await page.click('#btnNext');
      await page.waitForFunction((n) => document.querySelector('.q').getAttribute('data-no') === String(n), (p - 1) * 10 + 1);
      await answerPage(page, pick);
    }
    await waitSaved(page);
    ok((await page.textContent('#progressText')).trim() === '50 / 120', '진행 표시 50 / 120');
    // 이전 버튼으로 돌아가도 답이 남아 있음
    await page.click('#btnPrev');
    const checked = await page.$$eval('.scale-btn[aria-checked="true"]', (b) => b.length);
    ok(checked === 10, '[이전]으로 돌아가도 고른 답 유지');
    const saved = sql("SELECT COUNT(*) AS n FROM big5_responses WHERE owner_type = 'session' AND owner_id = ?", [sid]);
    ok(saved[0].n === 50, `DB 자동 저장 50건 (실제 ${saved[0].n})`);

    // ── 브라우저를 닫았다가 다시 열기 → 이어 하기
    await browser.close();
    ({ browser, context, page } = await openBrowser(mobile));
    await login(page, 'user', 'visang');
    await page.goto(`${BASE}/consent.php?type=full120`);
    ok((await page.textContent('.resume-box')).includes('50 / 120'), '다시 열면 이어 하기 안내 (50 / 120)');
    await Promise.all([page.waitForURL(/test\.php/), page.click('a:has-text("이어 하기")')]);
    ok((await page.$eval('.q', (e) => e.getAttribute('data-no'))) === '51', '이어 하기: 51번부터');
    ok((await page.textContent('#progressText')).trim() === '50 / 120', '이어 하기: 진행 50 / 120');

    // 나머지 7페이지
    for (let p = 6; p <= 12; p++) {
      if (p > 6) {
        await page.click('#btnNext');
        await page.waitForFunction((n) => document.querySelector('.q').getAttribute('data-no') === String(n), (p - 1) * 10 + 1);
      }
      await answerPage(page, pick);
    }
    ok((await page.textContent('#btnNext')).includes('결과 보기'), '마지막 페이지 버튼 = 결과 보기');
    await Promise.all([page.waitForURL(/report\.php\?id=/, { timeout: 15000 }), page.click('#btnNext')]);
    ok(page.url().includes(`report.php?id=${sid}`), '결과 보기 → 리포트');
    ok((await page.$$('.r-domain')).length === 5, '리포트에 다섯 다이얼');
    const r = sql("SELECT status, duration_sec, scoring_version FROM big5_sessions WHERE id = ?", [sid])[0];
    const nScores = sql("SELECT COUNT(*) AS n FROM big5_scores WHERE owner_type = 'session' AND owner_id = ?", [sid])[0].n;
    ok(r.status === 'completed' && r.scoring_version === '1.0' && nScores === 35, `완료 저장: 상태·채점 버전·점수 35행 (${nScores})`);
    await page.screenshot({ path: `${OUT}/stage3-report-mobile.png`, fullPage: true });

    // 끝난 검사의 test.php 는 리포트로
    await page.goto(`${BASE}/test.php?id=${sid}`);
    ok(page.url().includes(`report.php?id=${sid}`), '완료한 검사 test.php → 리포트로');

    // ── 간이 검사: 모두 3점, 빠르게 → 품질 안내
    await page.goto(`${BASE}/consent.php?type=mini20`);
    await page.check('input[name=agree]');
    await Promise.all([page.waitForURL(/test\.php/), page.click('button:has-text("검사 시작")')]);
    const mid = Number(new URL(page.url()).searchParams.get('id'));
    await answerPage(page, () => 3);
    await page.click('#btnNext');
    await page.waitForFunction(() => document.querySelector('.q').getAttribute('data-no') === '11');
    await answerPage(page, () => 3);
    await Promise.all([page.waitForURL(/report\.php/, { timeout: 15000 }), page.click('#btnNext')]);
    const qtext = await page.textContent('.quality-box');
    ok(qtext.includes('매우 짧았어요') && qtext.includes('같은 답을'), '간이 검사 모두 3점·빠름 → 품질 안내 2가지');
    const flags = sql('SELECT quality_flags FROM big5_sessions WHERE id = ?', [mid])[0].quality_flags;
    ok(flags === 'fast,straightline', `품질 플래그 DB 저장 (${flags})`);

    // ── 잘못된 응답 값 거부 (ajax)
    await page.goto(`${BASE}/consent.php?type=full120`);
    await page.check('input[name=agree]');
    await Promise.all([page.waitForURL(/test\.php/), page.click('button:has-text("검사 시작")')]);
    const tid = Number(new URL(page.url()).searchParams.get('id'));
    const bad = await page.evaluate(async (url) => {
      const fd = new FormData();
      fd.append('ajax', '1'); fd.append('csrf', document.getElementById('test').dataset.csrf);
      fd.append('action', 'answer'); fd.append('item_no', '1'); fd.append('value', '9');
      return (await fetch(url, { method: 'POST', body: fd })).json();
    }, `${BASE}/test.php?id=${tid}`);
    ok(bad.ok === 0 && bad.code === 'invalid', '범위 밖 응답 값(9) 거부');
    const early = await page.evaluate(async (url) => {
      const fd = new FormData();
      fd.append('ajax', '1'); fd.append('csrf', document.getElementById('test').dataset.csrf); fd.append('action', 'finish');
      return (await fetch(url, { method: 'POST', body: fd })).json();
    }, `${BASE}/test.php?id=${tid}`);
    ok(early.ok === 0 && early.code === 'missing' && early.missing.length === 120, '다 답하지 않고 finish → missing');

    // ── 남의 결과·검사 접근 차단 (다른 사용자)
    const other = await browser.newContext();
    const op = await other.newPage();
    await other.route(/^https?:\/\/(?!127\.0\.0\.1)/, (route) => route.abort());
    await login(op, 'sonbm', 'visang');
    const resp = await op.goto(`${BASE}/report.php?id=${sid}`);
    ok(resp.status() === 404 && (await op.textContent('h1')).includes('찾을 수 없습니다'), '다른 사용자 리포트 id 접근 → 404');
    const resp2 = await op.goto(`${BASE}/test.php?id=${sid}`);
    ok(resp2.status() === 404, '다른 사용자 검사 id 접근 → 404');
    const steal = await op.evaluate(async (url) => {
      const fd = new FormData(); fd.append('ajax', '1'); fd.append('action', 'answer'); fd.append('item_no', '1'); fd.append('value', '1');
      return (await fetch(url, { method: 'POST', body: fd })).json();
    }, `${BASE}/test.php?id=${tid}`);
    ok(steal.ok === 0 && steal.code === 'not_found', '다른 사용자 검사에 응답 저장 시도 → 거부');
    await other.close();

    // 로그인 없이 리포트 → 로그인 화면
    const anon = await request.newContext();
    const ar = await anon.get(`${BASE}/report.php?id=${sid}`, { maxRedirects: 0 });
    ok(ar.status() === 302 && (ar.headers().location || '').endsWith('/login.php'), '로그인 없이 리포트 → 로그인으로');
    await anon.dispose();
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    await browser.close();
    server.stop();
    done();
  }
})();
