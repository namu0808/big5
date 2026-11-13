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
<div class="adm">
  <header class="adm-head">
    <div class="adm-head-text">
      <h1 class="adm-title">문항</h1>
      <p class="adm-lead">읽기 전용입니다. 문항은 이 화면에서 고치지 않습니다.</p>
    </div>
  </header>
  <nav class="adm-tabs" aria-label="대상"><span class="adm-tabs-label">대상</span>
    <a href="<?= h(big5_url('admin/items.php?type=full120')) ?>"<?= $type === 'full120' ? ' aria-current="true"' : '' ?>>정식 120문항</a>
    <a href="<?= h(big5_url('admin/items.php?type=mini20')) ?>"<?= $type === 'mini20' ? ' aria-current="true"' : '' ?>>간이 20문항</a>
  </nav>
<?php if ($set === null): ?>
  <p class="adm-error" role="alert">문항 파일을 읽지 못했습니다.</p>
<?php else: ?>
  <dl class="adm-tiles">
    <div class="adm-tile"><dt>버전</dt><dd><b class="adm-tile-text" id="itemVersion"><?= h((string) $set['version']) ?></b></dd></div>
    <div class="adm-tile"><dt>원문 대조</dt><dd><b class="adm-tile-text"><?= h((string) ($set['source_status'] ?? '')) ?></b></dd></div>
    <div class="adm-tile"><dt>번역</dt><dd><b class="adm-tile-text"><?= h((string) ($set['status'] ?? '')) ?></b></dd></div>
    <div class="adm-tile"><dt>문항 수</dt><dd><b><?= count($set['items']) ?></b></dd></div>
  </dl>
  <p class="adm-meta items-note"><?= h((string) ($set['source_note'] ?? '')) ?></p>
  <p class="adm-meta">원문 기준 <code class="adm-code">data/source/master_*.csv</code>와 번역 <code class="adm-code">data/translate/*.csv</code>를 고친 뒤 <code class="adm-code">php tests/build_items.php</code>로 다시 만듭니다.</p>
  <div class="adm-table-wrap items-wrap"><table class="adm-table" id="itemTable">
    <thead><tr><th>번호</th><th><?= $type === 'full120' ? '하위 요인' : '요인' ?></th><th>방향</th><th>원문</th><th>본인용</th><th>타인 평가용</th></tr></thead>
    <tbody>
<?php foreach ($set['items'] as $it): $code = (string) ($it['facet'] ?? $it['domain']); ?>
      <tr>
        <td class="adm-num"><?= (int) $it['no'] ?></td>
        <td class="adm-nw"><?= h($code . ' ' . (string) ($type === 'full120' ? $set['facets'][$code]['name'] : $set['domains'][$code]['name'])) ?></td>
        <td><?= $it['keyed'] === '-' ? '−' : '+' ?></td>
        <td><?= h((string) $it['text_en']) ?></td>
        <td><?= h((string) $it['text_ko']) ?></td>
        <td><?= h((string) $it['text_ko_other']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
