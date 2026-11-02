<?php
/**
 * 결과 리포트 — 3단계: 점수 요약만 보여 준다. 섹션 0~11 전체 리포트는 5단계에서 이 파일을 넓힌다.
 * 본인 검사만 열람 (id 만 바꿔 남의 결과를 볼 수 없다).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/test_session.php';
require_once __DIR__ . '/includes/not_found.php';

$id = (int) big5_param('id', '0');
$session = $id > 0 ? big5_session_get($pdo, $id, (int) $big5User['id']) : null;
if ($session === null) {
    big5_not_found();
}
if ($session['status'] !== 'completed') {
    big5_redirect('test.php?id=' . $id);
}
$items = big5_items((string) $session['test_type']);
$scores = big5_scores_load($pdo, 'session', $id);
$flags = big5_flags($session['quality_flags']);
$bandLabel = ['low' => '낮은 쪽', 'mid' => '가운데', 'high' => '높은 쪽'];

$page_title = '나의 다섯 다이얼';
require __DIR__ . '/includes/header.php';
?>
<article class="reading">
  <h1>나의 다섯 다이얼</h1>
  <p class="muted"><?= h($big5User['display_name']) ?> · <?= h(substr((string) $session['completed_at'], 0, 10)) ?> · <?= h(big5_test_label((string) $session['test_type'])) ?> · 문항 <?= h((string) $session['item_version']) ?></p>

<?php if ($flags !== []): ?>
  <div class="notice-box quality-box">
    <p><strong>응답에 대해 알려 드릴 점</strong></p>
    <ul>
<?php foreach ($flags as $f): ?>
      <li><?= h(big5_quality_message($f)) ?></li>
<?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

  <section class="dials" aria-label="다섯 다이얼 점수">
<?php foreach (big5_domain_order() as $d): $row = $scores['domains'][$d] ?? null; if ($row === null) { continue; } ?>
    <div class="dial">
      <div class="dial-head">
        <span class="dial-name" style="--c: var(--c-<?= h($d) ?>)"><?= h($items['domains'][$d]['name']) ?></span>
        <span class="dial-score"><?= h(number_format($row['pomp'], 1)) ?> · <?= h($bandLabel[$row['band']]) ?></span>
      </div>
      <div class="bar"><span class="bar-fill" style="width: <?= h((string) $row['pomp']) ?>%; background: var(--c-<?= h($d) ?>)"></span></div>
    </div>
<?php endforeach; ?>
  </section>
  <p class="form-help">0~100은 나의 응답을 환산한 위치이며, 다른 사람과 비교한 순위가 아닙니다. 높고 낮음은 좋고 나쁨이 아니라 방향입니다.</p>
  <p class="notice-box">자세한 해석 리포트는 준비 중입니다.</p>
</article>
<?php require __DIR__ . '/includes/footer.php';
