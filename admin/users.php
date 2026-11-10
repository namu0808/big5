<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/**
 * 사용자(사이트 공통 계정) 화면은 0.12.0부터 통합 관리자 '회원 관리'(사이트 /admin/users.php)로 옮겼다 — 검색어·쪽은 그대로 넘긴다.
 * 코어가 옛 판이면 통합 관리자가 없으므로 안내만.
 */
if (function_exists('sbm_admin_url')) {
    $q = big5_param('q');
    $p = (int) big5_param('p', '1');
    header('Location: ' . sbm_admin_url('users.php') . '?q=' . rawurlencode($q) . '&p=' . max(1, $p));
    exit;
}
http_response_code(404);
echo '회원 관리는 통합 관리자(/admin/users.php)로 옮겼습니다.';
