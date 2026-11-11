<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/**
 * 관리자 — 결과지 목록. 완료한 본인 검사를 최근 순으로 보여 주고, 한 건을 누르면 그 회원이 보는 결과지를 그대로 연다(admin/report.php).
 * 사용자가 지운 기록도 DB 에 남아 있으므로('삭제한 계정') 함께 보인다. 읽기 전용 — POST 없음.
 */
require_once __DIR__ . '/../includes/admin_stats.php';
require_once __DIR__ . '/../includes/content_loader.php';

$filter = ['type' => big5_param('type'), 'owner' => big5_param('owner'), 'q' => big5_param('q')];
$res = big5_admin_results($pdo, $filter, (int) big5_param('p', '1'));
$domains = big5_content()['domains'] ?? [];
$codes = ['C', 'E', 'A', 'O', 'N'];
$typeLabel = ['full120' => '정식 120', 'mini20' => '간이 20'];
$flagLabel = ['fast' => '너무 빠름', 'straightline' => '같은 답 반복', 'inconsistent' => '반대 문장에 같은 답'];
$link = static function (int $p) use ($filter): string {
    return big5_url('admin/results.php?' . http_build_query(array_filter($filter, static fn($v) => $v !== '') + ['p' => $p]));
};

$page_title = '결과지';
$layout_area = 'admin';
$nav_active = 'results';
require __DIR__ . '/../includes/header.php';
?>
<h1>결과지</h1>
<p>회원이 끝낸 검사의 실제 결과지를 봅니다. 이름·연락처를 받지 않는 익명 검사이며, 검사 개선에 씁니다. 사용자가 지운 기록도 DB 에 남아 있어 <strong>삭제한 계정</strong>으로 함께 보입니다.</p>
<form method="get" action="<?= h(big5_url('admin/results.php')) ?>" class="inline-form">
  <label>검사
    <select name="type">
      <option value="">전체</option>
<?php foreach ($typeLabel as $k => $v): ?>
      <option value="<?= h($k) ?>"<?= $filter['type'] === $k ? ' selected' : '' ?>><?= h($v) ?></option>
<?php endforeach; ?>
    </select>
  </label>
  <label>계정
    <select name="owner">
      <option value="">전체</option>
      <option value="active"<?= $filter['owner'] === 'active' ? ' selected' : '' ?>>쓰는 계정</option>
      <option value="deleted"<?= $filter['owner'] === 'deleted' ? ' selected' : '' ?>>삭제한 계정(보존 기록)</option>
    </select>
  </label>
  <label>아이디 <input class="input-sm" type="search" name="q" value="<?= h($filter['q']) ?>" maxlength="50"></label>
  <button type="submit" class="btn btn-outline btn-sm">찾기</button>
</form>
<p class="muted" id="resultsTotal">모두 <?= $res['total'] ?>건</p>
<div class="table-wrap">
<table class="table" id="resultsTable">
  <thead>
    <tr><th>검사</th><th>끝낸 때</th><th>계정</th><th>종류</th>
<?php foreach ($codes as $d): ?>
      <th><?= h((string) ($domains[$d]['name'] ?? $d)) ?></th>
<?php endforeach; ?>
      <th>받은 평가</th><th>응답 품질</th><th></th></tr>
  </thead>
  <tbody>
<?php foreach ($res['rows'] as $r): ?>
    <tr<?= $r['retired'] ? ' class="row-retired"' : '' ?>>
      <td>#<?= $r['id'] ?></td>
      <td><?= h(substr($r['completed_at'], 0, 16)) ?></td>
      <td><?= $r['retired'] ? '<span class="badge badge-warn" style="margin-left:0">삭제한 계정</span>' : h($r['username']) ?></td>
      <td><?= h($typeLabel[$r['test_type']] ?? $r['test_type']) ?></td>
<?php foreach ($codes as $d): ?>
      <td><?= isset($r['domains'][$d]) ? h(number_format($r['domains'][$d], 1)) : '–' ?></td>
<?php endforeach; ?>
      <td><?= $r['ratings'] > 0 ? $r['ratings'] . '건' : '–' ?></td>
      <td><?= $r['quality_flags'] === [] ? '–' : h(implode(', ', array_map(static fn($f) => $flagLabel[$f] ?? $f, $r['quality_flags']))) ?></td>
      <td><a href="<?= h(big5_url('admin/report.php?id=' . $r['id'])) ?>">결과지 보기</a></td>
    </tr>
<?php endforeach; ?>
<?php if ($res['rows'] === []): ?>
    <tr><td colspan="<?= 7 + count($codes) ?>" class="muted">해당하는 검사가 없습니다.</td></tr>
<?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($res['pages'] > 1): ?>
<p class="pager">
<?php for ($p = 1; $p <= $res['pages']; $p++): ?>
  <a href="<?= h($link($p)) ?>"<?= $p === $res['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a>
<?php endfor; ?>
</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
