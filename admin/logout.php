<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/** 관리자 로그아웃 — 0.12.0부터 통합 관리자 로그아웃으로(세 관리자 화면이 함께 닫힘, 사용자 로그인은 유지) */
if (function_exists('sbm_admin_url')) {
    header('Location: ' . sbm_admin_url('logout.php'));
    exit;
}
big5_logout('admin');
big5_redirect('admin/login.php');
