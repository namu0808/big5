<?php
/**
 * 관리자 전용 화면(/admin) — 관리자 로그인($_SESSION['admin_id'])이 없으면 통합 관리자 로그인(/admin/login.php?next=지금 화면)으로 보낸다(0.12.0).
 * 코어가 옛 판이라 sbm_admin_url() 이 없으면 예전처럼 다이얼 admin/login.php 로.
 * /admin 의 모든 파일은 declare 바로 다음 첫 문장으로 이 파일을 require 한다.
 * require 후 $big5Admin(현재 관리자 행)과 $pdo 를 쓸 수 있다.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($pdo === null) {
    http_response_code(503);
    require __DIR__ . '/db_down.php';
    exit;
}

$big5Admin = big5_current_admin($pdo);
if ($big5Admin === null) {
    if (function_exists('sbm_admin_url')) {
        header('Location: ' . sbm_admin_url('login.php', big5_current_path()));
        exit;
    }
    big5_redirect('admin/login.php');
}
