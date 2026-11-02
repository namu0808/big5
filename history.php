<?php
/** 내 기록 — 검사 목록(이어 하기·리포트), 두 결과를 골라 비교 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/others.php';

$userId = (int) $big5User['id'];
$st = $pdo->prepare('SELECT id, test_type, status, started_at, completed_at, quality_flags FROM big5_sessions WHERE user_id = ? ORDER BY id DESC');
$st->execute([$userId]);
$rows = $st->fetchAll();
$done = array_values(array_filter($rows, static fn($r) => $r['status'] === 'completed'));
$received = count(big5_ratings_received($pdo, $userId));

$page_title = '내 기록';
$nav_active = 'history';
require __DIR__ . '/includes/header.php';
?>
<article class="reading">
  <h1>내 기록</h1>
<?php if ($rows === []): ?>
  <p>아직 검사 기록이 없습니다.</p>
  <p><a class="btn btn-primary" href="<?= h(big5_url('index.php')) ?>">검사 시작하기</a></p>
<?php else: ?>
  <form method="get" action="<?= h(big5_url('compare.php')) ?>" id="historyForm">
    <ul class="h-list">
<?php foreach ($rows as $r): $doneRow = $r['status'] === 'completed'; ?>
      <li class="h-item">
<?php if ($doneRow): ?>
        <label class="h-pick"><input type="checkbox" name="pick[]" value="<?= (int) $r['id'] ?>" aria-label="비교할 검사로 고르기"></label>
<?php else: ?>
        <span class="h-pick"></span>
<?php endif; ?>
        <div class="h-body">
          <p class="h-title"><?= h(big5_test_label((string) $r['test_type'])) ?></p>
          <p class="muted"><?= $doneRow ? '검사일 ' . h(substr((string) $r['completed_at'], 0, 16)) : '진행 중 · 시작 ' . h(substr((string) $r['started_at'], 0, 16)) ?><?= ($doneRow && $r['quality_flags']) ? ' · 응답 안내 있음' : '' ?></p>
        </div>
        <a class="btn btn-outline btn-sm" href="<?= h(big5_url(($doneRow ? 'report.php' : 'test.php') . '?id=' . (int) $r['id'])) ?>"><?= $doneRow ? '리포트' : '이어 하기' ?></a>
      </li>
<?php endforeach; ?>
    </ul>
<?php if (count($done) >= 2): ?>
    <p class="form-help">완료한 검사 두 개를 골라 [비교하기]를 누르면 지난번과 지금을 나란히 볼 수 있어요.</p>
    <button type="submit" class="btn btn-primary">비교하기</button>
<?php endif; ?>
  </form>
<?php endif; ?>
  <h2>다른 사람이 본 나</h2>
  <p>지금까지 받은 타인 평가 <strong><?= $received ?>건</strong>. 결과는 각 리포트의 '다른 사람이 본 나' 장에서 볼 수 있어요. <a href="<?= h(big5_url('invite.php')) ?>">평가 부탁하기</a></p>
</article>
<?php require __DIR__ . '/includes/footer.php';
