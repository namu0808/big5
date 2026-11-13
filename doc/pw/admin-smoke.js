/**
 * 7단계 스모크 — 관리자 화면: 접근 차단, 한눈에 보기(집계)·분석(품질·분포), 사용자 검색·사용 중지, 문항 조회,
 * 규준 잠금(300건 미만), 익명 CSV(아이디·이름 없음, 품질 플래그 제외 옵션), 검사 기록·결과지 그대로 보기,
 * 관리자 화면 규격(sbm.ai.kr doc/ADMIN-UI.md — 머리 세 줄·메뉴 이름·공용 부품·44px·가로 넘침·끝낸 날 필터·최근 끝낸 검사).
 * 실행: node doc/pw/admin-smoke.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { ROOT, BASE, ORIGIN, OUT, DB, DB_CONFIG, startServer, resetDb, openBrowser, ok, done, login, isAdminLogin, freshInstall, sql } = require('./lib');

const PAGES = ['index', 'users', 'items', 'content', 'norms', 'export', 'results', 'report', 'analysis'];

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
      if (isAdminLogin(page.url())) blocked++;
    }
    ok(blocked === PAGES.length, `일반 사용자 → 관리자 화면 ${PAGES.length}개 모두 차단`);
    const anon = await chromium.launch();
    const ap = await (await anon.newContext()).newPage();
    const r = await ap.goto(`${BASE}/admin/export.php?download=1&kind=self&type=full120`);
    ok(isAdminLogin(ap.url()) && !(await ap.content()).includes('respondent'), '로그인 없이 CSV 내려받기 차단');
    await anon.close();

    await login(page, 'sonbm', 'visang', true);
    fs.mkdirSync(OUT, { recursive: true });

    // ── 한눈에 보기(숫자 칸) · 분석(응답 품질·요인별 분포 — 0.15.0 첫 화면에서 옮김)
    await page.goto(`${BASE}/admin/index.php`);
    const stats = await page.$$eval('.adm-tile', (els) => els.map((e) => e.textContent.replace(/\s+/g, ' ').trim()));
    ok(stats.length === 6 && stats.some((t) => /^정식 검사 완료\s*4$/.test(t)) && stats.some((t) => /^간이 검사 완료\s*1$/.test(t)), '한눈에 보기: 숫자 칸 6개, 정식 4·간이 1');
    ok(!(await page.$('#flagRatio')) && !(await page.$('#distGrid')) && !!(await page.$('.adm-links a[href$="admin/analysis.php"]')) && !!(await page.$('.adm-links a[href$="admin/results.php"]')) && !!(await page.$('.adm-links a[href$="admin/export.php"]')), '한눈에 보기: 품질·분포는 분석으로, 바로 가기(검사 기록·분석·CSV 내려받기)');
    await page.screenshot({ path: `${OUT}/stage7-admin-dashboard.png`, fullPage: true });
    await Promise.all([page.waitForLoadState('load'), page.click('.adm-links a:has-text("분석")')]);
    ok(page.url().endsWith('admin/analysis.php') && (await page.textContent('#flagRatio')).includes('%'), '분석: 품질 플래그 비율');
    const flagRows = (await page.textContent('.flag-table')).replace(/\s+/g, ' ');
    ok(['fast', 'straightline', 'inconsistent'].every((f) => flagRows.includes(f)), '분석: 품질 플래그 3종 건수');
    ok((await page.$$('#distGrid svg.hist')).length === 5, '분석: 요인별 분포 5개');
    const nAll = (await page.textContent('#distGrid figcaption')).match(/(\d+)건/)[1];
    await page.screenshot({ path: `${OUT}/admin-analysis.png`, fullPage: true });
    await Promise.all([page.waitForLoadState('load'), page.click('a:has-text("품질 플래그 제외")')]);
    const nClean = (await page.textContent('#distGrid figcaption')).match(/(\d+)건/)[1];
    ok(page.url().includes('admin/analysis.php?clean=1') && (await page.textContent('.adm-tabs a[aria-current]')).trim() === '품질 플래그 제외' && Number(nClean) < Number(nAll), `분포: 품질 플래그 제외 보기(${nAll}건 → ${nClean}건)`);
    await page.goto(`${BASE}/admin/index.php`);

    // ── 통합 관리자(0.12.0): 다이얼 관리자 머리에 메뉴 줄, 사이트 /admin/ 첫 화면에 다이얼 숫자, 사용자 화면은 통합 '회원 관리'로
    const bar = await page.$$eval('.adm-bar a', (els) => els.map((e) => e.textContent.trim()));
    ok(bar[0] === '통합 관리자' && bar[1] === '회원' && bar.includes('다이얼'), '다이얼 관리자: 통합 관리자 메뉴 줄 (' + bar.join(',') + ')');
    ok((await page.textContent('.adm-bar a[aria-current="page"]')).trim() === '다이얼', '메뉴 줄: 다이얼 표시');
    ok(!(await page.$('.adm-sub a:has-text("사용자")')) && !(await page.$('.adm-sub a:has-text("회원")')), '다이얼 메뉴에서 사용자 빠짐(통합 회원 관리로)');
    await page.click('.adm-bar a:has-text("통합 관리자")');
    await page.waitForLoadState('load');
    const dialCard = (await page.textContent('#adm-big5-1')).replace(/\s+/g, ' ');
    ok(page.url() === `${ORIGIN}/admin/` && dialCard.includes('다이얼') && /정식 검사 완료\(120문항\) 4/.test(dialCard) && /간이 검사 완료\(20문항\) 1/.test(dialCard), '통합 첫 화면: 다이얼 숫자(정식 4·간이 1)');
    const dialVer = (require('fs').readFileSync(require('path').join(__dirname, '..', '..', 'includes', 'version.php'), 'utf8').match(/BIG5_VERSION = '([^']+)'/) || [])[1];
    ok((await page.textContent('.adm-ver')).includes('다이얼 ' + dialVer), '통합 첫 화면: 다이얼 버전 ' + dialVer);
    ok(await page.$('#adm-big5-1 a[href="/big5/admin/"]') !== null, '통합 첫 화면: [다이얼 관리] 바로 가기');
    await page.goto(`${BASE}/admin/users.php?q=use`);
    ok(page.url() === `${ORIGIN}/admin/users.php?q=use&p=1`, '다이얼 사용자 화면 → 통합 회원 관리(검색어 유지) (' + page.url() + ')');
    ok((await page.textContent('tr[data-username="user"]')).includes('다이얼 완료한 검사'), '회원 관리: 다이얼 이용 요약');

    // ── 사용자: 검색·사용 중지 (통합 회원 관리 화면)
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

    // ── 결과지: 목록 → 회원이 보는 결과지 그대로(읽기 전용)
    await page.goto(`${BASE}/admin/results.php`);
    ok((await page.$$('#resultsTable tbody tr')).length === 5 && (await page.textContent('#resultsTotal')).trim() === '모두 5건', '검사 기록: 완료 검사 5건');
    ok(!!(await page.$('.topbar a[href$="admin/results.php"][aria-current="page"]')), "관리자 메뉴에 '검사 기록'");
    await page.screenshot({ path: `${OUT}/admin-results.png` });
    await page.goto(`${BASE}/admin/results.php?type=mini20`);
    ok((await page.$$('#resultsTable tbody tr')).length === 1, '결과지 목록: 간이만 거르기');
    await page.goto(`${BASE}/admin/results.php`);
    await Promise.all([page.waitForLoadState('load'), page.click('#resultsTable a:has-text("결과지 보기")')]);
    ok(page.url().includes('admin/report.php?id=') && (await page.textContent('#adminViewBar')).includes('읽기 전용'), '결과지 보기: 관리자 보기 표시');
    const rv = await page.evaluate(() => ({
      title: document.querySelector('.adm.adm-slim h1.adm-title').childNodes[0].textContent.trim(),
      crumb: document.querySelector('.adm-crumb').textContent.replace(/\s+/g, ' ').trim(),
      cur: [...document.querySelectorAll('.adm-sub a[aria-current=page]')].map((e) => e.textContent.trim()).join('|'),
      inReport: !!document.querySelector('.adm-report > #report') && !document.querySelector('.adm #report'),
      cover: getComputedStyle(document.querySelector('#report .r-cover h1')).fontSize,
      over: document.documentElement.scrollWidth - window.innerWidth,
    }));
    ok(rv.title === '결과지 그대로 보기' && rv.crumb === '검사 기록 › 결과지 그대로 보기' && rv.cur === '검사 기록', `결과지 보기: 제목·자리 표시(${rv.title} / ${rv.crumb} / ${rv.cur})`);
    ok(rv.inReport && rv.cover === '32px' && rv.over <= 0, `결과지 보기: 결과지는 .adm 밖(.adm-report)에 사용자 화면 모양 그대로(${rv.cover}, ${rv.over})`);
    ok(!!(await page.$('#report .r-cover')) && (await page.$$('#report .r-chapter')).length >= 10 && !(await page.$('#growthApp')), '결과지 보기: 회원 결과지 그대로, 성장 행동 고르기는 없음');
    const postRes = await page.request.post(page.url(), { form: { ajax: '1', action: 'growth_add' } });
    ok(postRes.status() === 405, '결과지 보기: 바꾸는 요청은 받지 않음(405)');
    ok((await page.goto(`${BASE}/admin/report.php?id=999999`)).status() === 404, '결과지 보기: 없는 검사 → 404');
    const ctx2 = await browser.newContext();
    const p2 = await ctx2.newPage();
    await login(p2, 'user', 'visang');
    await p2.goto(`${BASE}/admin/report.php?id=1`);
    ok(isAdminLogin(p2.url()), '일반 사용자는 관리자 결과지 보기에 들어가지 못함');
    await p2.goto(`${BASE}/admin/results.php`);
    ok(isAdminLogin(p2.url()), '일반 사용자는 결과지 목록에 들어가지 못함');
    await ctx2.close();

    // ── 관리자 화면 규격(sbm.ai.kr doc/ADMIN-UI.md): 머리 세 줄, 메뉴 이름 = 제목, 공용 부품만, 누르는 것 44px, 가로 넘침 없음
    const MENU = [['index.php', '한눈에 보기'], ['results.php', '검사 기록'], ['analysis.php', '분석'], ['items.php', '문항'], ['content.php', '결과 문장'], ['norms.php', '규준'], ['export.php', 'CSV 내려받기']];
    await page.goto(`${BASE}/admin/index.php`);
    ok((await page.locator('.adm-sub a').allInnerTexts()).join('|') === MENU.map((m) => m[1]).join('|'), '관리자 메뉴 이름·순서');
    ok((await page.locator('header.adm-top.topbar > .adm-top-inner, header.adm-top > nav.adm-bar, header.adm-top > nav.adm-sub').count()) === 3, '머리 세 줄(이름 줄·서비스 줄·앱 메뉴)');
    ok((await page.locator('.adm-bar a[aria-current=page]').innerText()) === '다이얼' && (await page.locator('.adm-brand').innerText()).replace(/\s+/g, ' ').trim() === '관리자 다이얼', '머리: 관리자 · 서비스 줄의 지금 자리');
    ok((await page.locator('.adm-top').innerText()).includes('손병목님') && await page.locator('.adm-top .adm-who a[href="/admin/logout.php"]').count() === 1 && await page.locator('.adm-sub a:has-text("로그아웃"), .adm-bar a:has-text("로그아웃")').count() === 0, '머리: 이름·로그아웃은 오른쪽(메뉴 밖)');
    const fontOk = await page.evaluate(() => getComputedStyle(document.querySelector('.adm-title')).fontFamily.includes('Pretendard') && getComputedStyle(document.querySelector('.adm-top')).position === 'static' && getComputedStyle(document.querySelector('.adm-tile b')).fontFamily.includes('IBM Plex Mono'));
    ok(fontOk, '공용 관리자 CSS 가 실림(측정 기록지 글꼴·머리는 붙박이 아님)');
    for (const [u, name] of MENU) {
      for (const w of [390, 768, 1280]) {
        await page.setViewportSize({ width: w, height: 900 });
        await page.goto(`${BASE}/admin/${u}`);
        const r = await page.evaluate(() => ({
          h1: [...document.querySelectorAll('.adm-top h1, .adm h1')].map((e) => e.className + ':' + e.childNodes[0].textContent.trim()).join('|'),
          cur: [...document.querySelectorAll('.adm-sub a[aria-current=page]')].map((e) => e.textContent.trim()).join('|'),
          // 옛 부품 — 결과 문장 미리보기(.adm-report 안, 사용자 결과지의 글 모양 그대로)는 빼고 센다
          old: [...document.querySelectorAll('main .stat, main .stat-grid, main table.table, main .table-wrap, main .btn, main .badge, main .notice-box, main .inline-form, main .form-card, main .form-error, main .pager, header.gnb, footer.site-footer')].filter((e) => !e.closest('.adm-report')).length,
          outside: [...document.querySelector('main').children].filter((e) => !e.matches('.adm, .adm-report, .flash')).length,
          small: [...document.querySelectorAll('.adm-top a, main .adm-btn, main .adm-tabs a, main .adm-pager a, main input:not([type=hidden]):not([type=checkbox]), main label.adm-check, main select, main summary')].filter((e) => e.offsetParent && e.getBoundingClientRect().height < 44).length,
          over: document.documentElement.scrollWidth - window.innerWidth,
          foot: document.querySelectorAll('footer.adm-foot').length,
        }));
        ok(r.h1 === 'adm-title:' + name && r.cur === name, `${u} ${w}px: 제목 = 메뉴 이름(${r.h1} / ${r.cur})`);
        ok(r.old === 0 && r.outside === 0 && r.foot === 1, `${u} ${w}px: 옛 부품 없음·관리자 꼬리 (${r.old}, ${r.outside})`);
        ok(r.small === 0 && r.over <= 0, `${u} ${w}px: 누르는 것 44px·가로 넘침 없음 (${r.small}, ${r.over})`);
      }
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto(`${BASE}/admin/report.php?id=1`);
    ok(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth) <= 0 && await page.locator('footer.adm-foot').count() === 1, '결과지 그대로 보기 390px: 가로 넘침 없음·관리자 꼬리');
    await page.setViewportSize({ width: 1280, height: 900 });
    // 결과 문장 미리보기는 사용자 결과지의 글 모양 그대로(공용 관리자 본문 규칙이 닿지 않음)
    await page.goto(`${BASE}/admin/content.php?d=N&b=high`);
    const pvStyle = await page.evaluate(() => { const p = document.querySelector('#preview p:not([class])'); const li = document.querySelector('#preview li'); return { inAdm: !!document.querySelector('.adm #preview'), mt: getComputedStyle(p).marginTop, fs: getComputedStyle(p).fontSize, li: getComputedStyle(li).marginTop, box: !!document.querySelector('#preview .notice-box') }; });
    ok(!pvStyle.inAdm && pvStyle.mt === '16px' && pvStyle.fs === '16px' && pvStyle.li !== '0px' && pvStyle.box, `결과 문장 미리보기: 결과지 글 모양 그대로(${pvStyle.mt}, ${pvStyle.fs})`);
    await page.screenshot({ path: `${OUT}/admin-content-preview.png`, fullPage: true });

    // 검사 기록: 건수·끝낸 날 필터(그날 건수·기간 밖 0건·틀린 값 무시)·쪽 나누기(50건씩)
    const numOf = async () => parseInt((await page.locator('#resultsTotal').innerText()).replace(/[^0-9]/g, ''), 10);
    await page.goto(`${BASE}/admin/results.php`);
    const total = await numOf();
    ok(total === sql("SELECT COUNT(*) AS n FROM big5_sessions WHERE status = 'completed'")[0].n && (await page.$$('#resultsTable tbody tr')).length === Math.min(50, total) && await page.locator('.adm-pager').count() === 0, `검사 기록: 모두 ${total}건(50건 이하면 쪽 나누기 없음)`);
    const day = sql("SELECT substr(MAX(completed_at), 1, 10) AS d FROM big5_sessions WHERE status = 'completed'")[0].d;
    const nDay = sql(`SELECT COUNT(*) AS n FROM big5_sessions WHERE status = 'completed' AND completed_at >= '${day} 00:00:00' AND completed_at <= '${day} 23:59:59'`)[0].n;
    await page.goto(`${BASE}/admin/results.php?from=${day}&to=${day}`);
    ok(await numOf() === nDay && nDay > 0 && await page.inputValue('input[name=from]') === day && await page.inputValue('input[name=to]') === day, `검사 기록: 끝낸 날 필터(${day} ${nDay}건)`);
    await page.goto(`${BASE}/admin/results.php?from=2000-01-01&to=2000-01-02`);
    ok((await page.locator('#resultsTotal').innerText()).trim() === '모두 0건' && (await page.locator('#resultsTable').innerText()).includes('조건에 맞는 검사가 없습니다'), '검사 기록: 기간 밖은 0건');
    await page.goto(`${BASE}/admin/results.php?from=${encodeURIComponent("x' OR 1=1")}&to=2026-13-40`);
    ok(await numOf() === total && await page.inputValue('input[name=from]') === '', '검사 기록: 틀린 날짜 값은 무시');
    await page.goto(`${BASE}/admin/results.php?type=mini20&owner=active&q=use&from=${day}&to=${day}`);
    ok(await numOf() === 1 && await page.inputValue('select[name=type]') === 'mini20' && await page.inputValue('select[name=owner]') === 'active' && await page.inputValue('input[name=q]') === 'use', '검사 기록: 대상·계정·아이디·끝낸 날 함께 거르기(값 유지)');
    // 쪽 나누기 — 완료 검사를 60건 더 넣어 본다(가장 최근 검사를 베낌: 응답·점수 없이 목록 줄만)
    const dbRun = (code) => execFileSync('php', ['-r', 'require $argv[1]; ' + code, DB_CONFIG], { env: { ...env, BIG5_DB_QUIET: '1' } }); // SQLite·MariaDB 공통
    dbRun(`$r = $pdo->query("SELECT user_id, test_type, item_version, scoring_version, started_at, consent_at FROM big5_sessions WHERE status = 'completed' AND test_type = 'full120' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC); $st = $pdo->prepare("INSERT INTO big5_sessions (user_id, test_type, item_version, scoring_version, status, started_at, completed_at, duration_sec, consent_at) VALUES (?, ?, ?, ?, 'completed', ?, ?, 600, ?)"); for ($i = 0; $i < 60; $i++) { $st->execute([$r['user_id'], $r['test_type'], $r['item_version'], $r['scoring_version'], $r['started_at'], '2020-05-05 10:00:00', $r['consent_at']]); }`);
    await page.goto(`${BASE}/admin/results.php?type=full120`);
    const totalFull = await numOf();
    ok(totalFull === total - 1 + 60 && (await page.$$('#resultsTable tbody tr')).length === 50 && (await page.locator('.adm-pager span').innerText()).trim() === '1 / 2' && await page.locator('.adm-pager a[rel=prev]').count() === 0, `검사 기록: 50건씩(${totalFull}건 → 1 / 2)`);
    await Promise.all([page.waitForLoadState('load'), page.click('.adm-pager a[rel=next]')]);
    ok(page.url().includes('p=2') && page.url().includes('type=full120') && await page.locator('.adm-pager a[rel=prev]').count() === 1 && await page.locator('.adm-pager a[rel=next]').count() === 0 && (await page.$$('#resultsTable tbody tr')).length === totalFull - 50, '검사 기록: 다음 쪽·앞 쪽(필터 유지)');
    await page.goto(`${BASE}/admin/results.php?from=2020-05-05&to=2020-05-05&p=2`);
    ok(await numOf() === 60 && (await page.$$('#resultsTable tbody tr')).length === 10 && (await page.getAttribute('.adm-pager a[rel=prev]', 'href')).includes('from=2020-05-05'), '검사 기록: 끝낸 날 필터도 쪽을 넘겨 유지');
    await page.screenshot({ path: `${OUT}/admin-results-pager.png` });
    dbRun(`$pdo->exec("DELETE FROM big5_sessions WHERE completed_at = '2020-05-05 10:00:00'");`);

    // 통합 관리자 첫 화면 '최근 끝낸 검사' — 다이얼 줄(결과지 보기), 아이디 없음
    await page.goto(`${ORIGIN}/admin/`);
    const rec = page.locator('#recentTable tr[data-svc=big5]');
    ok(await rec.count() === Math.min(10, total) && (await rec.first().locator('a').getAttribute('href')).startsWith('/big5/admin/report.php?id='), '통합 관리자: 최근 끝낸 검사에 다이얼(결과지 보기)');
    const recText = await page.locator('#adm-recent').innerText();
    ok(/정식 120문항/.test(recText) && /간이 20문항/.test(recText) && !/\buser\b/.test(recText) && !recText.includes('사용자'), '통합 관리자: 최근 끝낸 검사에 대상 이름, 아이디·이름 없음');
    ok(await page.$('#adm-big5-1 a[href="/big5/admin/results.php"]') !== null && await page.$('#adm-big5-1 a[href="/big5/admin/analysis.php"]') !== null && await page.$('#adm-big5-1 a[href="/big5/admin/export.php"]') !== null, '통합 첫 화면: 다이얼 바로 가기(검사 기록·분석·CSV 내려받기)');
    await Promise.all([page.waitForLoadState('load'), rec.first().locator('a').click()]);
    ok(!!(await page.$('#report .r-cover')) && (await page.locator('h1.adm-title').innerText()).startsWith('결과지 그대로 보기'), '최근 끝낸 검사 → 결과지 그대로 보기');
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    await browser.close();
    server.stop();
    done();
  }
})();
