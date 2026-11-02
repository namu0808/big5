<?php
/**
 * DB 설정 로더(심) — 실제 접속 정보는 웹루트(www) 밖 config 폴더의 big5_db.php.
 * 이 파일에는 비밀정보가 없다. 접속 정보를 이 파일에 넣지 말 것.
 *
 * 앱이 놓인 깊이({홈}/www/big5/ 등)에 상관없이 동작하도록
 * 가까운 상위 폴더부터 config/big5_db.php 를 찾는다.
 * 파일명이 다이얼 전용(big5_db.php)이라 같은 서버의 다른 앱 설정과 충돌하지 않는다.
 *
 * 외부 설정 파일 계약 (config/big5_db.php — 견본 doc/big5_db.sample.php):
 *   define('BIG5_DB_HOST', 'localhost'); define('BIG5_DB_NAME', '...');
 *   define('BIG5_DB_USER', '...');      define('BIG5_DB_PASS', '...');
 *   define('BIG5_EXPORT_SALT', '긴 무작위 문자열');   // 관리자 CSV 익명 해시용
 *   (선택) define('BIG5_BASE_URL', '/big5');           // 설치 경로, 기본 /big5
 *   $pdo = new PDO('mysql:host=...;dbname=...;charset=utf8mb4', ..., [
 *       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 *       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
 *       PDO::ATTR_EMULATE_PREPARES => false,
 *   ]);
 *   (실패 시 error_log 후 $pdo = null — 앱은 "DB 없음" 안내로 강등된다)
 *
 * 계약: require 후 전역 $pdo 는 항상 PDO 또는 null.
 */

declare(strict_types=1);

// 한국 시간 기준 — 검사일·만료일 계산을 호스팅 기본 시간대에 맡기지 않는다.
date_default_timezone_set('Asia/Seoul');

/** 설정 파일 후보 경로 — 환경변수 우선, 그다음 가까운 상위 config/ (순수 함수) */
function big5_db_config_candidates(string $includesDir): array
{
    $list = [];
    // 개발 환경: 환경변수로 설정 파일을 직접 지정 (SQLite 스모크 등)
    $env = getenv('BIG5_DB_CONFIG');
    if (is_string($env) && $env !== '') {
        $list[] = $env;
    }
    for ($i = 2; $i <= 6; $i++) {
        // i=2: {홈}/www/includes → {홈}/config
        // i=3: {홈}/www/big5/includes → {홈}/config (sonbm.com/big5/)
        $list[] = dirname($includesDir, $i) . '/config/big5_db.php';
    }
    return $list;
}

/** 드라이버 판별 — MariaDB 전용 구문 분기용 */
function big5_db_is_mysql(?PDO $pdo): bool
{
    if ($pdo === null) {
        return false;
    }
    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    } catch (Throwable $e) {
        return false;
    }
}

/** 현재 시각 문자열 (DB 함수 NOW() 대신 — SQLite 호환) */
function big5_now(int $offsetSec = 0): string
{
    return date('Y-m-d H:i:s', time() + $offsetSec);
}

/** @var PDO|null */
$pdo = null;

$big5DbCandidates = big5_db_config_candidates(__DIR__);
$big5DbLoaded = false;
foreach ($big5DbCandidates as $big5DbConfigFile) {
    if (@is_file($big5DbConfigFile)) {
        require_once $big5DbConfigFile; // 전역 스코프 — $pdo, BIG5_DB_* 정의
        $big5DbLoaded = true;
        break;
    }
}
if (!$big5DbLoaded && !(PHP_SAPI === 'cli' && getenv('BIG5_DB_QUIET') === '1')) {
    error_log('다이얼 DB 설정 파일 없음 — 탐색한 경로: ' . implode(' | ', $big5DbCandidates));
}

if (!($pdo instanceof PDO)) {
    $pdo = null; // 우아한 실패 계약
}
unset($big5DbCandidates, $big5DbConfigFile, $big5DbLoaded);
