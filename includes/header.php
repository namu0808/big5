<?php
/**
 * 공통 머리 — 페이지가 설정하는 변수:
 *   $page_title(문자열), $layout_area('user'|'admin'), $nav_active(메뉴 키), $hide_nav(bool),
 *   $body_class(문자열), $extra_css(경로 배열, assets/ 기준), $extra_head(이미 이스케이프된 HTML — 쓰지 않는 것이 원칙)
 *   $page_indexable(bool, 검색 노출 허용 — 시작 화면만), $meta_description(문자열, 검색 결과 요약)
 */
declare(strict_types=1);

require_once __DIR__ . '/version.php';

$page_title = isset($page_title) && is_string($page_title) ? $page_title : '';
$layout_area = (isset($layout_area) && $layout_area === 'admin') ? 'admin' : 'user';
$nav_active = isset($nav_active) && is_string($nav_active) ? $nav_active : '';
$hide_nav = !empty($hide_nav);
$body_class = isset($body_class) && is_string($body_class) ? $body_class : '';
$extra_css = isset($extra_css) && is_array($extra_css) ? $extra_css : [];
$page_indexable = !empty($page_indexable) && $layout_area === 'user';
$meta_description = isset($meta_description) && is_string($meta_description) ? $meta_description : '';
$page_jsonld = isset($page_jsonld) && is_array($page_jsonld) ? $page_jsonld : null;   // 시작 화면 구조화 데이터(검색어 보강 2026-11-11)

$big5HeadUser = $layout_area === 'user' ? big5_current_user($pdo ?? null) : null;
$big5HeadAdmin = $layout_area === 'admin' ? big5_current_admin($pdo ?? null) : null;

$big5Nav = $layout_area === 'admin'
    ? [
        'dash' => ['admin/index.php', '대시보드'],
        'results' => ['admin/results.php', '결과지'],
        'items' => ['admin/items.php', '문항'],
        'content' => ['admin/content.php', '해석 문구'],
        'norms' => ['admin/norms.php', '규준'],
        'export' => ['admin/export.php', '내보내기'],
    ]
    : [
        'history' => ['history.php', '내 기록'],
        'invite' => ['invite.php', '타인 평가'],
        'account' => ['account.php', '다이얼 기록'],
    ];
?><!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($page_indexable): ?>
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= h(big5_origin() . big5_url('')) ?>">
<?php else: ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<?php if ($meta_description !== ''): ?>
<meta name="description" content="<?= h($meta_description) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="SBM 진단 검사">
<meta property="og:title" content="<?= h($page_title !== '' ? $page_title : 'Dial — 빅파이브(Big5) 성격 검사') ?>">
<meta property="og:description" content="<?= h($meta_description) ?>">
<meta property="og:url" content="<?= h(big5_origin() . big5_url('')) ?>">
<meta property="og:image" content="<?= h(big5_origin() . '/assets/img/og.jpg') ?>">
<?php endif; ?>
<meta name="referrer" content="same-origin">
<title><?= h($page_title !== '' ? $page_title . ' · 다이얼' : 'Dial — 빅파이브(Big5) 성격 검사 | SBM 진단 검사') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@600;700&display=swap">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= h(big5_url('assets/style.css')) ?>?v=<?= h(BIG5_VERSION) ?>">
<?php if ($layout_area === 'admin'): ?>
<link rel="stylesheet" href="<?= h(big5_url('assets/admin.css')) ?>?v=<?= h(BIG5_VERSION) ?>">
<?php endif; ?>
<?php foreach ($extra_css as $css): ?>
<link rel="stylesheet" href="<?= h(big5_url('assets/' . $css)) ?>?v=<?= h(BIG5_VERSION) ?>">
<?php endforeach; ?>
<?php if (is_array($page_jsonld)): /* 검색 엔진용 구조화 데이터(시작 화면, 검색어 보강 2026-11-11) */ ?>
<script type="application/ld+json"><?= json_encode($page_jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
</head>
<body class="<?= h(trim('area-' . $layout_area . ' ' . $body_class)) ?>" data-base="<?= h(big5_base_url()) ?>">
<?php if (!$hide_nav): ?>
<?php /* 상단 — 공부력 진단(sbmtest includes/header.php)과 같은 모양: SBM 표식 · 서비스 이름 · 'SBM 진단 검사 홈' · 내 계정/로그인. .topbar 는 인쇄 숨김(print.css)용 */ ?>
<header class="gnb topbar">
  <div class="gnb-inner">
    <span class="brand">
      <a class="brand-mark" href="/" aria-label="SBM 진단 검사 홈" title="SBM 진단 검사 홈">SBM</a>
      <a class="brand-text" href="<?= h(big5_url($layout_area === 'admin' ? 'admin/index.php' : 'index.php')) ?>" aria-label="다이얼 시작 화면">
        <span class="brand-name">Dial<?= $layout_area === 'admin' ? ' 관리자' : '' ?></span>
        <span class="brand-sub">빅파이브(Big5) 성격 검사</span>
      </a>
    </span>
    <nav class="gnb-menu" aria-label="주 메뉴">
<?php if ($layout_area === 'admin'): ?>
<?php if ($big5HeadAdmin !== null): ?>
<?php foreach ($big5Nav as $big5NavKey => [$big5NavHref, $big5NavLabel]): ?>
      <a href="<?= h(big5_url($big5NavHref)) ?>"<?= $big5NavKey === $nav_active ? ' aria-current="page"' : '' ?>><?= h($big5NavLabel) ?></a>
<?php endforeach; ?>
      <a href="<?= h(function_exists('sbm_admin_url') ? sbm_admin_url('logout.php') : big5_url('admin/logout.php')) ?>">로그아웃</a>
<?php endif; ?>
<?php else: ?>
      <a href="/">SBM 진단 검사 홈</a>
<?php if ($big5HeadUser !== null): ?>
<?php foreach ($big5Nav as $big5NavKey => [$big5NavHref, $big5NavLabel]): ?>
      <a href="<?= h(big5_url($big5NavHref)) ?>"<?= $big5NavKey === $nav_active ? ' aria-current="page"' : '' ?>><?= h($big5NavLabel) ?></a>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
    </nav>
<?php if ($layout_area === 'user'): ?>
<?php if ($big5HeadUser !== null): ?>
    <span class="gnb-user"><span class="gnb-user-name"><?= h((string) $big5HeadUser['display_name']) ?>님</span><a class="gnb-cta" href="/account/">내 계정</a></span>
<?php else: ?>
    <a class="gnb-cta" href="<?= h(big5_account_url('login.php', big5_current_path())) ?>">로그인</a>
<?php endif; ?>
<?php endif; ?>
  </div>
<?php /* 통합 관리자 메뉴 줄(0.12.0) — 홈 /admin/ 과 같은 목록(sbm.ai.kr core sbm_admin_links). 코어가 옛 판이면 줄을 그리지 않는다 */ ?>
<?php if ($layout_area === 'admin' && $big5HeadAdmin !== null && function_exists('sbm_admin_links')): ?>
  <nav class="adm-bar" aria-label="통합 관리자 메뉴">
    <div class="adm-bar-inner">
<?php foreach (sbm_admin_links() as $big5AdmL): ?>
      <a href="<?= h($big5AdmL['url']) ?>"<?= $big5AdmL['id'] === 'big5' ? ' aria-current="page"' : '' ?>><?= h($big5AdmL['name']) ?></a>
<?php endforeach; ?>
    </div>
  </nav>
<?php endif; ?>
</header>
<?php endif; ?>
<main class="page">
<?php $big5Flash = big5_flash(); if ($big5Flash !== null): ?>
<p class="flash" role="status"><?= h($big5Flash) ?></p>
<?php endif; ?>
