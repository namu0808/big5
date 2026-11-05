<?php
/**
 * SBM 공통 계정 코어 연결 — 다이얼은 사이트 공통 계정(sbm_users)·세션(SBMSESSID)·CSRF 를 쓴다.
 * 코어 위치(sbm.ai.kr 저장소 core/):
 *   1) 환경 변수 SBM_CORE_DIR
 *   2) {big5 상위}/core          — 운영: {홈}/www/core
 *   3) {big5 상위}/sbm.ai.kr/core — 개발: 두 저장소를 나란히 클론
 * 못 찾으면 503 안내(화면 출력 없이 error_log).
 */
declare(strict_types=1);

/** 코어 폴더 후보 (순수 함수) */
function big5_core_candidates(string $appRoot): array
{
    $list = [];
    $env = getenv('SBM_CORE_DIR');
    if (is_string($env) && $env !== '') {
        $list[] = rtrim($env, '/');
    }
    $list[] = dirname($appRoot) . '/core';
    $list[] = dirname($appRoot) . '/sbm.ai.kr/core';
    return $list;
}

if (!function_exists('sbm_authenticate')) {
    $big5CoreFound = false;
    foreach (big5_core_candidates(dirname(__DIR__)) as $big5CoreDir) {
        if (is_file($big5CoreDir . '/core.php')) {
            require_once $big5CoreDir . '/core.php';
            $big5CoreFound = true;
            break;
        }
    }
    if (!$big5CoreFound) {
        error_log('다이얼: SBM 공통 계정 코어(core/core.php)를 찾지 못했습니다 — ' . implode(' | ', big5_core_candidates(dirname(__DIR__))));
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "SBM 코어를 찾지 못했습니다. sbm.ai.kr 저장소를 옆에 클론하거나 SBM_CORE_DIR 을 지정하세요.\n");
            exit(1);
        }
        http_response_code(503);
        require __DIR__ . '/db_down.php';
        exit;
    }
    unset($big5CoreFound, $big5CoreDir);
}
