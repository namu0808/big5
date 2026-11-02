<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/** 문항 조회 (읽기 전용) — 버전, 원문 대조 상태, 문항표. 문항은 tests/build_items.php 로만 바꾼다 */
require_once __DIR__ . '/../includes/items.php';
$type = big5_param('type', 'full120');
if (!in_array($type, BIG5_TEST_TYPES, true)) {
    $type = 'full120';
}
$set = big5_items($type);

$page_title = '문항';
$layout_area = 'admin';
$nav_active = 'items';
require __DIR__ . '/../includes/header.php';
?>
<h1>문항</h1>
<p>
  <a href="<?= h(big5_url('admin/items.php?type=full120')) ?>"<?= $type === 'full120' ? ' aria-current="page"' : '' ?>>정식 120문항</a> ·
  <a href="<?= h(big5_url('admin/items.php?type=mini20')) ?>"<?= $type === 'mini20' ? ' aria-current="page"' : '' ?>>간이 20문항</a>
</p>
<?php if ($set === null): ?>
<p class="form-error">문항 파일을 읽지 못했습니다.</p>
<?php else: ?>
<div class="stat-grid">
  <div class="stat"><span class="stat-label">버전</span><span class="stat-value small-value" id="itemVersion"><?= h((string) $set['version']) ?></span></div>
  <div class="stat"><span class="stat-label">원문 대조</span><span class="stat-value small-value"><?= h((string) ($set['source_status'] ?? '')) ?></span></div>
  <div class="stat"><span class="stat-label">번역</span><span class="stat-value small-value"><?= h((string) ($set['status'] ?? '')) ?></span></div>
  <div class="stat"><span class="stat-label">문항 수</span><span class="stat-value small-value"><?= count($set['items']) ?></span></div>
</div>
<p class="muted"><?= h((string) ($set['source_note'] ?? '')) ?></p>
<p class="muted">문항은 이 화면에서 고치지 않습니다. 원문 기준 <code>data/source/master_*.csv</code>와 번역 <code>data/translate/*.csv</code>를 고친 뒤 <code>php tests/build_items.php</code>로 다시 만듭니다.</p>
<div class="table-wrap"><table class="table" id="itemTable">
  <thead><tr><th>번호</th><th><?= $type === 'full120' ? '하위 요인' : '요인' ?></th><th>방향</th><th>원문</th><th>본인용</th><th>타인 평가용</th></tr></thead>
  <tbody>
<?php foreach ($set['items'] as $it): $code = (string) ($it['facet'] ?? $it['domain']); ?>
    <tr>
      <td><?= (int) $it['no'] ?></td>
      <td><?= h($code . ' ' . (string) ($type === 'full120' ? $set['facets'][$code]['name'] : $set['domains'][$code]['name'])) ?></td>
      <td><?= $it['keyed'] === '-' ? '−' : '+' ?></td>
      <td><?= h((string) $it['text_en']) ?></td>
      <td><?= h((string) $it['text_ko']) ?></td>
      <td><?= h((string) $it['text_ko_other']) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
