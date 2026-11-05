<?php
/** 가입 — 사이트 공통 가입(/account/register.php)으로 넘긴다 (예전 주소·즐겨찾기용) */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Location: ' . big5_account_url('register.php'));
exit;
