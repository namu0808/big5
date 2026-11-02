<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/** 관리자 대시보드 — 1단계: 기본 집계. 분포 차트·품질 비율은 7단계에서 채운다 */
$counts = ['users' => 0, 'full' => 0, 'mini' => 0, 'ratings' => 0];
try {
    $counts['users'] = (int) $pdo->query("SELECT COUNT(*) FROM big5_users WHERE role = 'user'")->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND test_type = ?");
    $st->execute(['full120']);
    $counts['full'] = (int) $st->fetchColumn();
    $st->execute(['mini20']);
    $counts['mini'] = (int) $st->fetchColumn();
    $counts['ratings'] = (int) $pdo->query("SELECT COUNT(*) FROM big5_ratings WHERE status = 'completed'")->fetchColumn();
} catch (Throwable $e) {
    error_log('다이얼 관리자 대시보드 집계 오류: ' . $e->getMessage());
}

$page_title = '대시보드';
$layout_area = 'admin';
$nav_active = 'dash';
require __DIR__ . '/../includes/header.php';
?>
<h1>대시보드</h1>
<p class="muted"><?= h($big5Admin['display_name']) ?>님, 관리자 화면입니다.</p>
<div class="stat-grid">
  <div class="stat"><span class="stat-label">사용자</span><span class="stat-value"><?= $counts['users'] ?></span></div>
  <div class="stat"><span class="stat-label">정식 검사 완료</span><span class="stat-value"><?= $counts['full'] ?></span></div>
  <div class="stat"><span class="stat-label">간이 검사 완료</span><span class="stat-value"><?= $counts['mini'] ?></span></div>
  <div class="stat"><span class="stat-label">타인 평가 완료</span><span class="stat-value"><?= $counts['ratings'] ?></span></div>
</div>
<?php require __DIR__ . '/../includes/footer.php';
