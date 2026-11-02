/**
 * Playwright 스모크 공통 도우미 — 개발 서버를 띄우고(이미 떠 있으면 그대로) 브라우저를 연다.
 * 서버: BIG5_DB_CONFIG=doc/dev-db-sqlite.php php -S 127.0.0.1:8093 doc/dev-router.php
 * 앱 주소: http://127.0.0.1:8093/big5/
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
  const env = { ...process.env, BIG5_DB_CONFIG: path.join(ROOT, 'doc', 'dev-db-sqlite.php'), BIG5_DEV_SQLITE: DB };
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

/** 개발 DB 를 지운다 (install.php 검증 전) */
function resetDb() {
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

async function login(page, username, password, admin = false) {
  await page.goto(`${BASE}/${admin ? 'admin/' : ''}login.php`);
  await page.fill('input[name=username]', username);
  await page.fill('input[name=password]', password);
  await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
}

async function status(url) {
  return new Promise((resolve) => {
    http.get(url, (res) => { res.resume(); resolve(res.statusCode); }).on('error', () => resolve(0));
  });
}

module.exports = { ROOT, BASE, ORIGIN, OUT, DB, startServer, resetDb, openBrowser, ok, done, login, status };
