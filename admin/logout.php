<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/** 관리자 로그아웃 (사용자 로그인은 유지) */
big5_logout('admin');
big5_redirect('admin/login.php');
