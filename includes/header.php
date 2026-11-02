<?php
/**
 * 공통 머리 — 페이지가 설정하는 변수:
 *   $page_title(문자열), $layout_area('user'|'admin'), $nav_active(메뉴 키), $hide_nav(bool),
 *   $body_class(문자열), $extra_css(경로 배열, assets/ 기준), $extra_head(이미 이스케이프된 HTML — 쓰지 않는 것이 원칙)
 */
declare(strict_types=1);

require_once __DIR__ . '/version.php';

$page_title = isset($page_title) && is_string($page_title) ? $page_title : '';
$layout_area = (isset($layout_area) && $layout_area === 'admin') ? 'admin' : 'user';
$nav_active = isset($nav_active) && is_string($nav_active) ? $nav_active : '';
$hide_nav = !empty($hide_nav);
$body_class = isset($body_class) && is_string($body_class) ? $body_class : '';
$extra_css = isset($extra_css) && is_array($extra_css) ? $extra_css : [];

$big5HeadUser = $layout_area === 'user' ? big5_current_user($pdo ?? null) : null;
$big5HeadAdmin = $layout_area === 'admin' ? big5_current_admin($pdo ?? null) : null;

$big5Nav = $layout_area === 'admin'
    ? [
        'dash' => ['admin/index.php', '대시보드'],
        'users' => ['admin/users.php', '사용자'],
        'items' => ['admin/items.php', '문항'],
        'content' => ['admin/content.php', '해석 문구'],
        'norms' => ['admin/norms.php', '규준'],
        'export' => ['admin/export.php', '내보내기'],
    ]
    : [
        'history' => ['history.php', '내 기록'],
        'invite' => ['invite.php', '타인 평가'],
        'account' => ['account.php', '계정'],
    ];
?><!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h($page_title !== '' ? $page_title . ' · 다이얼' : '다이얼 — 빅파이브 성격 검사') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<link rel="stylesheet" href="<?= h(big5_url('assets/style.css')) ?>?v=<?= h(BIG5_VERSION) ?>">
<?php if ($layout_area === 'admin'): ?>
<link rel="stylesheet" href="<?= h(big5_url('assets/admin.css')) ?>?v=<?= h(BIG5_VERSION) ?>">
<?php endif; ?>
<?php foreach ($extra_css as $css): ?>
<link rel="stylesheet" href="<?= h(big5_url('assets/' . $css)) ?>?v=<?= h(BIG5_VERSION) ?>">
<?php endforeach; ?>
</head>
<body class="<?= h(trim('area-' . $layout_area . ' ' . $body_class)) ?>" data-base="<?= h(big5_base_url()) ?>">
<?php if (!$hide_nav): ?>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= h(big5_url($layout_area === 'admin' ? 'admin/index.php' : 'index.php')) ?>">다이얼<?= $layout_area === 'admin' ? ' <span class="brand-sub">관리자</span>' : '' ?></a>
    <nav class="topnav" aria-label="주 메뉴">
<?php if ($layout_area === 'admin'): ?>
<?php if ($big5HeadAdmin !== null): ?>
<?php foreach ($big5Nav as $key => [$href, $label]): ?>
      <a href="<?= h(big5_url($href)) ?>"<?= $key === $nav_active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
<?php endforeach; ?>
      <a href="<?= h(big5_url('admin/logout.php')) ?>">로그아웃</a>
<?php endif; ?>
<?php elseif ($big5HeadUser !== null): ?>
<?php foreach ($big5Nav as $key => [$href, $label]): ?>
      <a href="<?= h(big5_url($href)) ?>"<?= $key === $nav_active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
<?php endforeach; ?>
      <a href="<?= h(big5_url('logout.php')) ?>">로그아웃</a>
<?php else: ?>
      <a href="<?= h(big5_url('login.php')) ?>"<?= $nav_active === 'login' ? ' aria-current="page"' : '' ?>>로그인</a>
      <a href="<?= h(big5_url('register.php')) ?>"<?= $nav_active === 'register' ? ' aria-current="page"' : '' ?>>가입</a>
<?php endif; ?>
    </nav>
  </div>
</header>
<?php endif; ?>
<main class="page">
<?php $big5Flash = big5_flash(); if ($big5Flash !== null): ?>
<p class="flash" role="status"><?= h($big5Flash) ?></p>
<?php endif; ?>
