<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/** 관리자 첫 화면 '한눈에 보기' — 응답 수(정식/간이/타인)와 바로 가기. 응답 품질·요인별 분포는 '분석'(admin/analysis.php). 화면 규격은 sbm.ai.kr doc/ADMIN-UI.md */
require_once __DIR__ . '/../includes/admin_stats.php';

$c = big5_admin_counts($pdo);

$page_title = '한눈에 보기';
$layout_area = 'admin';
$nav_active = 'dash';
require __DIR__ . '/../includes/header.php';
?>
<div class="adm">
  <header class="adm-head">
    <div class="adm-head-text">
      <h1 class="adm-title">한눈에 보기</h1>
      <p class="adm-lead">다이얼(빅파이브 성격 검사)의 숫자입니다. 이름·연락처를 받지 않는 익명 검사입니다.</p>
    </div>
  </header>
  <dl class="adm-tiles">
    <div class="adm-tile"><dt>사용자</dt><dd><b><?= $c['users'] ?></b><small>사용 중지 <?= $c['users_inactive'] ?> · 삭제한 계정 <?= $c['users_retired'] ?>(기록 보존)</small></dd></div>
    <div class="adm-tile"><dt>정식 검사 완료</dt><dd><b><?= $c['full'] ?></b></dd></div>
    <div class="adm-tile"><dt>간이 검사 완료</dt><dd><b><?= $c['mini'] ?></b></dd></div>
    <div class="adm-tile"><dt>타인 평가 완료</dt><dd><b><?= $c['ratings'] ?></b></dd></div>
    <div class="adm-tile"><dt>진행 중</dt><dd><b><?= $c['in_progress'] ?></b></dd></div>
    <div class="adm-tile"><dt>최근 7일 완료</dt><dd><b><?= $c['last7'] ?></b></dd></div>
  </dl>
  <p class="adm-links">
    <a class="adm-btn adm-btn-sm adm-btn-main" href="<?= h(big5_url('admin/results.php')) ?>">검사 기록</a>
    <a class="adm-btn adm-btn-sm" href="<?= h(big5_url('admin/analysis.php')) ?>">분석</a>
    <a class="adm-btn adm-btn-sm" href="<?= h(big5_url('admin/items.php')) ?>">문항</a>
    <a class="adm-btn adm-btn-sm" href="<?= h(big5_url('admin/content.php')) ?>">결과 문장</a>
    <a class="adm-btn adm-btn-sm" href="<?= h(big5_url('admin/norms.php')) ?>">규준</a>
    <a class="adm-btn adm-btn-sm" href="<?= h(big5_url('admin/export.php')) ?>">CSV 내려받기</a>
  </p>
  <p class="adm-meta">검사 기록에서 끝낸 검사의 실제 결과지를 봅니다(사용자가 지운 보존 기록 포함). 응답 품질과 요인별 점수 분포는 분석에 있습니다.</p>
  <p class="adm-ver">앱 <?= h(BIG5_VERSION) ?></p>
</div>
<?php require __DIR__ . '/../includes/footer.php';
