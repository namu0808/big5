<?php
/** 사용자 로그아웃 (관리자 로그인은 유지) */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

big5_logout('user');
big5_redirect('index.php');
