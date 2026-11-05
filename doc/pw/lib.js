/**
 * Playwright 스모크 공통 도우미 — 개발 서버를 띄우고(이미 떠 있으면 그대로) 브라우저를 연다.
 * 서버: BIG5_DB_CONFIG=doc/dev-db-sqlite.php php -S 127.0.0.1:8093 doc/dev-router.php
 * 앱 주소: http://127.0.0.1:8093/big5/  (홈·공통 계정 /, /account/ 는 옆 sbm.ai.kr 저장소 — 라우터가 넘긴다)
 */
'use strict';
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const http = require('http');
const { chromium } = require('playwright');

const ROOT = path.resolve(__dirname, '..', '..');
const PORT = Number(process.env.BIG5_PORT || 8093);
const ORIGIN = `http://127.0.0.1:${PORT}`;
const BASE = `${ORIGIN}/big5`;
const DB = process.env.BIG5_DEV_SQLITE || path.join(ROOT, 'doc', 'pw-smoke.sqlite');
// BIG5_TEST_DB=mysql 이면 로컬 MariaDB(doc/dev-db-mysql.php)로 검증한다 (운영과 같은 엔진)
const USE_MYSQL = process.env.BIG5_TEST_DB === 'mysql';
const DB_CONFIG = path.join(ROOT, 'doc', USE_MYSQL ? 'dev-db-mysql.php' : 'dev-db-sqlite.php');
const OUT = path.join(ROOT, 'doc', 'pw', 'out');

function ping() {
  return new Promise((resolve) => {
    const req = http.get(`${BASE}/index.php`, (res) => { res.resume(); resolve(true); });
    req.on('error', () => resolve(false));
    req.setTimeout(1000, () => { req.destroy(); resolve(false); });
  });
}

/** 개발 서버 시작 — { stop() } 반환 */
async function startServer() {
  if (await ping()) return { stop() {} };
  const env = { ...process.env, BIG5_DB_CONFIG: DB_CONFIG, BIG5_DEV_SQLITE: DB };
  const proc = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.join(ROOT, 'doc', 'dev-router.php')], { cwd: ROOT, env, stdio: ['ignore', 'ignore', 'pipe'] });
  let errLog = '';
  proc.stderr.on('data', (d) => { errLog += d.toString(); });
  for (let i = 0; i < 50; i++) {
    if (await ping()) break;
    await new Promise((r) => setTimeout(r, 100));
  }
  return {
    stop() { proc.kill(); },
    log() { return errLog; },
  };
}

/** 개발 DB 를 지운다 (install.php 검증 전) — SQLite 는 파일 삭제, MariaDB 는 big5_·sbm_ 테이블 전부 삭제 */
function resetDb() {
  if (USE_MYSQL) {
    const { execFileSync } = require('child_process');
    const code = 'require $argv[1]; $pdo->exec("SET FOREIGN_KEY_CHECKS = 0"); foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) { if (str_starts_with((string) $t, "big5_") || str_starts_with((string) $t, "sbm_")) { $pdo->exec("DROP TABLE `" . str_replace("`", "", (string) $t) . "`"); } } $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");';
    execFileSync('php', ['-r', code, DB_CONFIG], { env: { ...process.env, BIG5_DB_QUIET: '1' } });
    return;
  }
  for (const f of [DB, DB + '-journal']) if (fs.existsSync(f)) fs.unlinkSync(f);
}

async function openBrowser(opts = {}) {
  const browser = await chromium.launch();
  const context = await browser.newContext(opts);
  const page = await context.newPage();
  // CDN(웹폰트)이 막힌 환경에서도 기다리지 않게 외부 요청은 바로 끊는다
  await context.route(/^https?:\/\/(?!127\.0\.0\.1)/, (route) => route.abort());
  return { browser, context, page };
}

let pass = 0;
let fail = 0;
function ok(cond, name) {
  if (cond) { pass++; console.log(`  PASS  ${name}`); } else { fail++; console.log(`  FAIL  ${name}`); }
}
function done() {
  console.log(`\n결과: PASS ${pass} / FAIL ${fail}`);
  process.exitCode = fail === 0 ? 0 : 1;
}

/** 로그인 — 사용자는 사이트 공통 /account/login.php(돌아올 곳 /big5/index.php), 관리자는 /big5/admin/login.php */
async function login(page, username, password, admin = false) {
  await page.goto(admin ? `${BASE}/admin/login.php` : `${ORIGIN}/account/login.php?next=%2Fbig5%2Findex.php`);
  await page.fill('input[name=username]', username);
  await page.fill('input[name=password]', password);
  await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
}

async function status(url) {
  return new Promise((resolve) => {
    http.get(url, (res) => { res.resume(); resolve(res.statusCode); }).on('error', () => resolve(0));
  });
}

/** 개발 DB 에 SQL 조회 (php PDO sqlite) — 결과 행 배열 */
function sql(query, params = []) {
  const { execFileSync } = require('child_process');
  const code = 'require $argv[1]; $st = $pdo->prepare($argv[2]); $st->execute(json_decode($argv[3], true)); echo json_encode($st->fetchAll());';
  const env = { ...process.env, BIG5_DEV_SQLITE: DB, BIG5_DB_QUIET: '1' };
  const out = execFileSync('php', ['-r', code, DB_CONFIG, query, JSON.stringify(params)], { env });
  return JSON.parse(out.toString());
}

/** 새 DB 에 install.php 실행 */
async function freshInstall(page) {
  // 배포 묶음 검증 때는 운영처럼 앱 루트의 install.php (BIG5_INSTALL_PATH=install.php)
  await page.goto(`${BASE}/${process.env.BIG5_INSTALL_PATH || '1time/install.php'}`);
  return (await page.textContent('body')).includes('설치 완료');
}

module.exports = { ROOT, BASE, ORIGIN, OUT, DB, DB_CONFIG, USE_MYSQL, startServer, resetDb, openBrowser, ok, done, login, status, sql, freshInstall };
