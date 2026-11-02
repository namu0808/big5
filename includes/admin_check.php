<?php
/**
 * 관리자 전용 화면(/admin) — 관리자 로그인($_SESSION['admin_id'])이 없으면 admin/login.php 로 보낸다.
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
    big5_redirect('admin/login.php');
}
