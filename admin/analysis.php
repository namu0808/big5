<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/**
 * 분석(0.15.0 — 첫 화면에서 옮겨 옴) — 응답 품질(플래그 비율·종류별 건수)과 요인별 점수 분포.
 * ?clean=1 이면 품질 플래그가 붙은 응답을 분포에서 뺀다. 계산은 includes/admin_stats.php. 화면 규격은 sbm.ai.kr doc/ADMIN-UI.md
 */
require_once __DIR__ . '/../includes/admin_stats.php';
require_once __DIR__ . '/../includes/content_loader.php';

$clean = big5_param('clean') === '1';
$c = big5_admin_counts($pdo);
$dist = big5_admin_distribution($pdo, $clean);
$domains = big5_content()['domains'] ?? [];
$flagLabel = ['fast' => '너무 빠름', 'straightline' => '같은 답 반복', 'inconsistent' => '반대 문장에 같은 답'];

$page_title = '분석';
$layout_area = 'admin';
$nav_active = 'analysis';
require __DIR__ . '/../includes/header.php';
?>
<div class="adm">
  <header class="adm-head">
    <div class="adm-head-text">
      <h1 class="adm-title">분석</h1>
      <p class="adm-lead">끝낸 본인 검사(정식·간이)의 응답 품질과 요인별 점수 분포입니다.</p>
    </div>
  </header>
  <section class="adm-sec">
    <h2 class="adm-h">응답 품질</h2>
    <p>본인 검사 완료 가운데 품질 플래그가 붙은 응답: <strong id="flagRatio"><?= $c['flagged'] ?>건 (<?= h(number_format($c['flag_ratio'], 1)) ?>%)</strong></p>
    <div class="adm-table-wrap flag-table"><table class="adm-table">
      <thead><tr><th>품질 플래그</th><th>코드</th><th class="adm-num">건수</th></tr></thead>
      <tbody>
<?php foreach (BIG5_QUALITY_FLAGS as $f): ?>
        <tr><td><?= h($flagLabel[$f]) ?></td><td class="adm-code"><?= h($f) ?></td><td class="adm-num"><?= (int) ($c['by_flag'][$f] ?? 0) ?>건</td></tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <section class="adm-sec">
    <h2 class="adm-h">요인별 점수 분포</h2>
    <nav class="adm-tabs" aria-label="범위"><span class="adm-tabs-label">범위</span>
      <a href="<?= h(big5_url('admin/analysis.php')) ?>"<?= $clean ? '' : ' aria-current="true"' ?>>전체</a>
      <a href="<?= h(big5_url('admin/analysis.php?clean=1')) ?>"<?= $clean ? ' aria-current="true"' : '' ?>>품질 플래그 제외</a>
    </nav>
    <p class="adm-meta">정식·간이 본인 검사, 0~100 환산 점수, 10점 단위</p>
    <div class="dist-grid" id="distGrid">
<?php foreach ($dist as $d => $x): ?>
      <figure class="dist-card">
        <figcaption><b><?= h((string) ($domains[$d]['name'] ?? $d)) ?></b> <span class="adm-code"><?= $x['n'] ?>건 · 평균 <?= h(number_format($x['mean'], 1)) ?></span></figcaption>
        <?= big5_histogram_svg($x['bins'], (string) ($domains[$d]['color'] ?? '#888'), (string) ($domains[$d]['name'] ?? $d)) ?>
      </figure>
<?php endforeach; ?>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php';
