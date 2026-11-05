/**
 * 5단계 스모크 — 샘플 3종(전부 3점·무작위·극단값)+간이 검사 리포트가 깨지지 않고 생성되는지,
 * 장 구성·고지·금지 표현, 성장 행동 저장(ajax), 인쇄(PDF) 장 나눔·차트·꼬리말, 모바일 가로 넘침.
 * 실행: node doc/pw/report-smoke.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { ROOT, BASE, OUT, DB, DB_CONFIG, startServer, resetDb, openBrowser, ok, done, login, freshInstall, sql } = require('./lib');

(async () => {
  resetDb();
  const server = await startServer();
  let { browser, context, page } = await openBrowser({ viewport: { width: 1100, height: 900 } });
  try {
    ok(await freshInstall(page), '설치');
    const env = { ...process.env, BIG5_DB_CONFIG: DB_CONFIG, BIG5_DEV_SQLITE: DB };
    const ids = JSON.parse(execFileSync('php', [path.join(ROOT, 'doc', 'dev-seed-reports.php')], { env }).toString());
    ok(ids.all3 && ids.random && ids.extreme && ids.mini && ids.nhigh, `샘플 검사 5건 생성 ${JSON.stringify(ids)}`);
    // 표시 이름에 따옴표·꺾쇠를 넣어 인쇄 꼬리말(CSS 문자열)·화면 이스케이프를 함께 확인한다
    sql('UPDATE sbm_users SET display_name = ? WHERE username = ?', ['김 "다이얼" </style>', 'user']);
    await login(page, 'user', 'visang');
    fs.mkdirSync(OUT, { recursive: true });

    for (const [name, id] of Object.entries(ids)) {
      const resp = await page.goto(`${BASE}/report.php?id=${id}`);
      ok(resp.status() === 200, `${name}: 리포트 200`);
      const chapters = await page.$$eval('.r-chapter', (els) => els.map((e) => e.id));
      ok(['guide', 'intro', 'cautions', 'glance', 'detail', 'combos', 'work', 'relations', 'growth', 'appendix'].every((c) => chapters.includes(c)), `${name}: 장 0~8·부록 모두 있음`);
      ok((await page.$$('#cautions .r-cautions li')).length === 7, `${name}: 일곱 가지 주의점 7개`);
      ok((await page.$$('.r-domain')).length === 5, `${name}: 다이얼별 상세 5개`);
      ok((await page.$$('svg.radar circle')).length === 5, `${name}: 레이더 SVG`);
      const combos = (await page.$$('.r-combo')).length;
      ok(combos >= 2 && combos <= 5, `${name}: 조합 카드 ${combos}개`);
      const text = await page.textContent('#report');
      ok(!/상위\s*\d|하위\s*\d|\d+\s*%|퍼센트|백분위|번째|타입/.test(text), `${name}: 백분위·순위·유형 표현 없음`);
      ok(!/(성실|외향|내향|친화|개방|신경|리더)형(입니다|이에요|이다|인|의)/.test(text), `${name}: 'OO형' 이름 없음`);
      ok(text.includes('진단 도구가 아니며 채용·평가에 사용하지 않습니다'), `${name}: 인쇄 꼬리말 고지`);
      if (name === 'mini') {
        ok((await page.$$('.r-facet')).length === 0 && text.includes('정식 검사(120문항)를 해 보세요'), 'mini: 하위 요인 대신 안내');
      } else {
        ok((await page.$$('.r-facet')).length === 30, `${name}: 하위 요인 30개`);
      }
      if (name === 'all3') ok(text.includes('다섯 다이얼이 모두 가운데 근처에 모여 있어'), 'all3: 모두 가운데 안내');
      if (name === 'nhigh') {
        ok(text.includes('1577-0199'), 'nhigh: 신경성 높은 쪽 도움 안내 한 줄');
      }
      ok(text.includes('김 "다이얼" </style>'), `${name}: 표시 이름 이스케이프되어 그대로 보임`);
      if (name === 'extreme') {
        ok((await page.$$('#glance .r-standout .r-card')).length === 2, 'extreme: 두드러진 다이얼 두 개');
        ok(text.includes('1577-0199') === false, 'extreme(신경성 낮음): 도움 안내는 신경성 높은 쪽에만');
      }
      await page.screenshot({ path: `${OUT}/stage5-report-${name}.png`, fullPage: false });
    }


    // ── 성장 행동 저장
    await page.goto(`${BASE}/report.php?id=${ids.random}`);
    await page.click('#growthPick button[data-domain="C"][data-dir="grow"]');
    const firstBox = await page.$('#growthList input[type=checkbox]');
    const key = await firstBox.getAttribute('data-key');
    await firstBox.check();
    await page.waitForFunction((k) => !!document.querySelector(`#growthSaved li[data-key="${k}"]`), key);
    await page.reload();
    ok(!!(await page.$(`#growthSaved li[data-key="${key}"]`)), '성장 행동 저장 → 새로고침 뒤에도 남음');
    await page.click('#growthPick button[data-domain="C"][data-dir="grow"]');
    await page.uncheck(`#growthList input[data-key="${key}"]`);
    await page.waitForFunction((k) => !document.querySelector(`#growthSaved li[data-key="${k}"]`), key);
    ok(true, '성장 행동 저장 취소');
    const bad = await page.evaluate(async (url) => {
      const fd = new FormData();
      fd.append('ajax', '1'); fd.append('csrf', document.getElementById('growthApp').dataset.csrf);
      fd.append('action', 'growth_add'); fd.append('domain', 'C'); fd.append('direction', 'grow'); fd.append('action_key', 'nope');
      return (await fetch(url, { method: 'POST', body: fd })).json();
    }, `${BASE}/report.php?id=${ids.random}`);
    ok(bad.ok === 0 && bad.code === 'invalid', '목록에 없는 행동 key 거부');

    // ── 인쇄 / PDF
    await page.goto(`${BASE}/report.php?id=${ids.extreme}`);
    await page.emulateMedia({ media: 'print' });
    ok(await page.isHidden('.topbar') && await page.isHidden('#btnPrint'), '인쇄: 메뉴·버튼 숨김');
    const brk = await page.$eval('#intro', (e) => getComputedStyle(e).breakBefore || getComputedStyle(e).pageBreakBefore);
    ok(brk === 'page' || brk === 'always', `인쇄: 장마다 새 페이지 (${brk})`);
    ok(await page.isVisible('svg.radar'), '인쇄: 차트 보임');
    const pdfPath = `${OUT}/stage5-report-extreme.pdf`;
    await page.pdf({ path: pdfPath, format: 'A4', printBackground: true });
    const pdf = fs.readFileSync(pdfPath).toString('latin1');
    const pages = (pdf.match(/\/Type\s*\/Page[^s]/g) || []).length;
    ok(pages >= 12, `PDF 생성 A4 ${pages}쪽`);
    const pdfText = execFileSync('pdftotext', ['-layout', pdfPath, '-']).toString();
    const footers = (pdfText.match(/진단 도구가 아니며 채용·평가에 사용하지 않습니다/g) || []).length;
    ok(footers >= pages, `PDF 쪽마다 꼬리말 고지 (${footers}회 / ${pages}쪽)`);
    ok(pdfText.includes('김 "다이얼" </style> · 검사일'), 'PDF 꼬리말에 이름·검사일');
    await page.screenshot({ path: `${OUT}/stage5-print-preview.png`, fullPage: false });
    await page.emulateMedia({ media: 'screen' });
    await browser.close();

    // ── 모바일
    ({ browser, context, page } = await openBrowser({ viewport: { width: 390, height: 844 }, isMobile: true }));
    await login(page, 'user', 'visang');
    await page.goto(`${BASE}/report.php?id=${ids.random}`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    ok(!overflow, '모바일 390px 리포트 가로 넘침 없음');
    await page.screenshot({ path: `${OUT}/stage5-report-mobile.png`, fullPage: false });
  } catch (e) {
    ok(false, `예외: ${e.message}`);
  } finally {
    await browser.close();
    server.stop();
    done();
  }
})();
