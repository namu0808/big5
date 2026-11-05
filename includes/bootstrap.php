<?php
/**
 * 모든 화면의 첫 require — DB 로더, 도우미, 공통 계정 코어, 세션, CSRF, 인증 함수를 싣고 세션을 연다.
 * 로그인이 필요한 화면은 auth_check.php(사용자) 또는 admin_check.php(관리자)를 대신 require 한다.
 */
declare(strict_types=1);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/core_link.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

// 화면에 오류를 내보내지 않는다 (error_log 만)
ini_set('display_errors', '0');

big5_session_begin();
