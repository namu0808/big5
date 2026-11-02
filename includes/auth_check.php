<?php
/**
 * 사용자 전용 화면 — 미로그인·비활성 계정이면 login.php 로 보낸다.
 * require 후 $big5User(현재 사용자 행)와 $pdo(PDO) 를 쓸 수 있다.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($pdo === null) {
    http_response_code(503);
    require __DIR__ . '/db_down.php';
    exit;
}

$big5User = big5_current_user($pdo);
if ($big5User === null) {
    $next = $_SERVER['REQUEST_URI'] ?? '';
    $_SESSION['big5_next'] = is_string($next) ? $next : '';
    big5_redirect('login.php');
}
