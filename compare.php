<?php
/** 지난번의 나와 비교 — 두 완료 검사(본인 것만)의 요인·하위 요인 변화. 나 vs 타인은 리포트 9장 (승인된 변경 11) */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/others.php';
require_once __DIR__ . '/includes/not_found.php';
require_once __DIR__ . '/includes/content_loader.php';
require_once __DIR__ . '/includes/report_charts.php';

$userId = (int) $big5User['id'];
$pick = $_GET['pick'] ?? [];
$ids = is_array($pick) ? array_values(array_unique(array_map('intval', $pick))) : [];
if (count($ids) !== 2) {
    big5_flash('비교할 검사를 두 개 골라 주세요.');
    big5_redirect('history.php');
}
$a = big5_session_get($pdo, $ids[0], $userId);
$b = big5_session_get($pdo, $ids[1], $userId);
if ($a === null || $b === null || $a['status'] !== 'completed' || $b['status'] !== 'completed') {
    big5_not_found();
}
if ((string) $a['completed_at'] > (string) $b['completed_at']) {
    [$a, $b] = [$b, $a]; // a = 이전, b = 지금
}
$content = big5_content();
$fx = $content['fixed'];
$cmp = big5_compare_scores(big5_scores_load($pdo, 'session', (int) $a['id']), big5_scores_load($pdo, 'session', (int) $b['id']));
$items = big5_items('full120');
$growthBefore = big5_growth_keys($pdo, $userId, (int) $a['id']);

$page_title = '지난번의 나와 비교';
$nav_active = 'history';
require __DIR__ . '/includes/header.php';

/** 변화 한 줄 */
function big5_cmp_row(string $label, array $r, string $color, string $size): void
{
    $sign = $r['diff'] > 0 ? '+' : '';
    echo '<div class="c-row' . ($r['flag'] ? ' is-flag' : '') . '"><div class="dial-head"><span class="dial-name" style="--c:' . h($color) . '">' . h($label) . '</span>'
        . '<span class="dial-score">' . h(number_format($r['before'], 1)) . ' → <strong>' . h(number_format($r['after'], 1)) . '</strong> (' . h($sign . number_format($r['diff'], 1)) . ')'
        . ($r['flag'] ? ' <span class="tag-near">10점 이상</span>' : '') . '</span></div>'
        . big5_bar_html($r['after'], $color, $label, $size, $r['before']) . '</div>';
}
?>
<article class="reading">
  <h1><?= h((string) $fx['retest']['title']) ?></h1>
  <p class="muted">이전: <?= h(substr((string) $a['completed_at'], 0, 10)) ?> <?= h(big5_test_label((string) $a['test_type'])) ?> → 지금: <?= h(substr((string) $b['completed_at'], 0, 10)) ?> <?= h(big5_test_label((string) $b['test_type'])) ?></p>
<?php foreach ($fx['retest']['guide'] as $g): ?>
  <p><?= h((string) $g) ?></p>
<?php endforeach; ?>
<?php if ($a['test_type'] !== $b['test_type']): ?>
  <p class="notice-box">두 검사의 종류가 달라(정식·간이) 문항이 다릅니다. 다이얼 다섯 개만 비교하며, 차이에는 검사 종류의 차이도 섞여 있어요.</p>
<?php endif; ?>
  <p class="form-help">막대의 짙은 세로선이 이전 위치, 동그라미가 지금 위치입니다.</p>
  <div class="c-list">
<?php foreach (big5_domain_order() as $d): if (!isset($cmp['domains'][$d])) { continue; } ?>
    <?php big5_cmp_row((string) $content['domains'][$d]['name'], $cmp['domains'][$d], (string) $content['domains'][$d]['color'], 'lg'); ?>
<?php endforeach; ?>
  </div>
<?php if ($cmp['facets'] !== []): ?>
  <h2>하위 요인</h2>
<?php foreach (big5_domain_order() as $d): ?>
  <h3><?= h((string) $content['domains'][$d]['name']) ?></h3>
  <div class="c-list">
<?php foreach ($cmp['facets'] as $code => $r): if ($code[0] !== $d) { continue; } ?>
    <?php big5_cmp_row($code . ' ' . (string) $items['facets'][$code]['name'], $r, (string) $content['domains'][$d]['color'], 'sm'); ?>
<?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php endif; ?>
  <h2><?= h((string) $fx['retest']['growth_recall']) ?></h2>
<?php if ($growthBefore === []): ?>
  <p class="muted">이전 검사에서 골라 둔 성장 행동이 없습니다.</p>
<?php else: ?>
  <ul>
<?php foreach ($growthBefore as $k): ?>
    <li><?= h(big5_growth_text($content['growth']['domains'], $k)) ?></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
  <p><a href="<?= h(big5_url('report.php?id=' . (int) $b['id'])) ?>">지금 리포트 보기</a> · <a href="<?= h(big5_url('history.php')) ?>">내 기록</a></p>
</article>
<?php require __DIR__ . '/includes/footer.php';
