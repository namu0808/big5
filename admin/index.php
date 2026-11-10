<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/** 관리자 대시보드 — 응답 수(정식/간이/타인), 품질 플래그 비율, 요인별 점수 분포 */
require_once __DIR__ . '/../includes/admin_stats.php';
require_once __DIR__ . '/../includes/content_loader.php';

$clean = big5_param('clean') === '1';
$c = big5_admin_counts($pdo);
$dist = big5_admin_distribution($pdo, $clean);
$domains = big5_content()['domains'] ?? [];
$flagLabel = ['fast' => '너무 빠름', 'straightline' => '같은 답 반복', 'inconsistent' => '반대 문장에 같은 답'];

$page_title = '대시보드';
$layout_area = 'admin';
$nav_active = 'dash';
require __DIR__ . '/../includes/header.php';
?>
<h1>대시보드</h1>
<p class="muted"><?= h($big5Admin['display_name']) ?>님, 관리자 화면입니다.</p>
<div class="stat-grid">
  <div class="stat"><span class="stat-label">사용자</span><span class="stat-value"><?= $c['users'] ?></span><span class="muted small">사용 중지 <?= $c['users_inactive'] ?> · 삭제한 계정 <?= $c['users_retired'] ?>(기록 보존)</span></div>
  <div class="stat"><span class="stat-label">정식 검사 완료</span><span class="stat-value"><?= $c['full'] ?></span></div>
  <div class="stat"><span class="stat-label">간이 검사 완료</span><span class="stat-value"><?= $c['mini'] ?></span></div>
  <div class="stat"><span class="stat-label">타인 평가 완료</span><span class="stat-value"><?= $c['ratings'] ?></span></div>
  <div class="stat"><span class="stat-label">진행 중</span><span class="stat-value"><?= $c['in_progress'] ?></span></div>
  <div class="stat"><span class="stat-label">최근 7일 완료</span><span class="stat-value"><?= $c['last7'] ?></span></div>
</div>

<h2>응답 품질</h2>
<p>본인 검사 완료 가운데 품질 플래그가 붙은 응답: <strong id="flagRatio"><?= $c['flagged'] ?>건 (<?= h(number_format($c['flag_ratio'], 1)) ?>%)</strong></p>
<ul>
<?php foreach (BIG5_QUALITY_FLAGS as $f): ?>
  <li><?= h($flagLabel[$f]) ?> (<?= h($f) ?>): <?= (int) ($c['by_flag'][$f] ?? 0) ?>건</li>
<?php endforeach; ?>
</ul>

<h2>요인별 점수 분포</h2>
<p>
  <a href="<?= h(big5_url('admin/index.php')) ?>"<?= $clean ? '' : ' aria-current="page"' ?>>전체</a> ·
  <a href="<?= h(big5_url('admin/index.php?clean=1')) ?>"<?= $clean ? ' aria-current="page"' : '' ?>>품질 플래그 제외</a>
  <span class="muted">(정식·간이 본인 검사, 0~100 환산 점수, 10점 단위)</span>
</p>
<div class="dist-grid" id="distGrid">
<?php foreach ($dist as $d => $x): ?>
  <div class="stat">
    <span class="stat-label"><?= h((string) ($domains[$d]['name'] ?? $d)) ?> · <?= $x['n'] ?>건 · 평균 <?= h(number_format($x['mean'], 1)) ?></span>
    <?= big5_histogram_svg($x['bins'], (string) ($domains[$d]['color'] ?? '#888'), (string) ($domains[$d]['name'] ?? $d)) ?>
  </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
