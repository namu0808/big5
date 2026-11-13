<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/**
 * 결과 문장(해석 문구) 조회 (읽기 전용) — 파일별 상태, 누락 점검표, 문구 원칙 위반 목록, 요인·구간별 미리보기.
 * 문구 수정은 data/content/*.json 에서 하고, 이 화면은 점검과 확인만 한다.
 */
require_once __DIR__ . '/../includes/items.php';
require_once __DIR__ . '/../includes/content_check.php';

$content = big5_content();
$items = big5_items('full120');
$check = big5_content_check($content, $items !== null ? array_keys($items['facets']) : []);

$bandLabel = ['low' => '낮은 쪽', 'mid' => '가운데', 'high' => '높은 쪽'];
$d = big5_param('d', 'C');
$b = big5_param('b', 'high');
if (!in_array($d, BIG5_DOMAINS, true)) {
    $d = 'C';
}
if (!in_array($b, BIG5_BANDS, true)) {
    $b = 'high';
}
$entry = $content['domains'][$d][$b] ?? null;
$about = $content['fixed']['domain_about'][$d] ?? null;

$page_title = '결과 문장';
$layout_area = 'admin';
$nav_active = 'content';
require __DIR__ . '/../includes/header.php';
?>
<div class="adm adm-before-report">
  <header class="adm-head">
    <div class="adm-head-text">
      <h1 class="adm-title">결과 문장</h1>
      <p class="adm-lead">결과지에 나가는 해석 문구입니다. 문구는 <code class="adm-code">data/content/*.json</code>에서 고칩니다. 이 화면은 읽기 전용이에요. 검수본은 <code class="adm-code">php tests/build_review_content.php</code>로 만듭니다.</p>
    </div>
  </header>
  <section class="adm-sec">
    <h2 class="adm-h">파일</h2>
    <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>파일</th><th>status</th><th>version</th></tr></thead>
      <tbody>
<?php foreach (BIG5_CONTENT_FILES as $f): ?>
        <tr>
          <td class="adm-code"><?= h($f) ?>.json</td>
          <td><?= h(is_array($content[$f]) ? (string) ($content[$f]['status'] ?? '?') : '읽기 실패') ?></td>
          <td class="adm-code"><?= h(is_array($content[$f]) ? (string) ($content[$f]['version'] ?? '') : '') ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
  <section class="adm-sec">
    <h2 class="adm-h">누락 점검표 <span class="adm-badge <?= $check['ok'] ? 'adm-badge-ok' : 'adm-badge-warn' ?>"><?= $check['ok'] ? '통과' : '확인 필요' ?></span></h2>
    <div class="adm-table-wrap">
    <table class="adm-table" id="contentCheck">
      <thead><tr><th>항목</th><th>기대</th><th>실제</th><th>결과</th></tr></thead>
      <tbody>
<?php foreach ($check['rows'] as $r): ?>
        <tr<?= $r['ok'] ? '' : ' class="adm-row-warn"' ?>>
          <td><?= h($r['label']) ?></td>
          <td><?= h($r['expected']) ?></td>
          <td><?= h($r['actual']) ?></td>
          <td><?= $r['ok'] ? '통과' : '<strong>확인</strong>' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php if ($check['missing'] !== []): ?>
    <h3 class="adm-h3">누락 항목</h3>
    <ul>
<?php foreach ($check['missing'] as $m): ?>
      <li><?= h($m) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <h3 class="adm-h3">문구 원칙 위반 (금지 표현·유형 이름·백분위·병리 용어)</h3>
<?php if ($check['violations'] === []): ?>
    <p id="violationsNone">위반 0건</p>
<?php else: ?>
    <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>파일</th><th>위치</th><th>규칙</th><th>문구 조각</th></tr></thead>
      <tbody>
<?php foreach ($check['violations'] as $v): ?>
        <tr class="adm-row-warn"><td><?= h($v['file']) ?></td><td class="adm-code"><?= h($v['path']) ?></td><td><?= h($v['rule']) ?></td><td><?= h($v['fragment']) ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php endif; ?>
  </section>
  <section class="adm-sec">
    <h2 class="adm-h">미리보기</h2>
    <form method="get" action="<?= h(big5_url('admin/content.php')) ?>" class="adm-filter">
      <label>다이얼
        <select name="d">
<?php foreach (BIG5_DOMAINS as $code): ?>
          <option value="<?= h($code) ?>"<?= $code === $d ? ' selected' : '' ?>><?= h((string) ($content['domains'][$code]['name'] ?? $code)) ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <label>구간
        <select name="b">
<?php foreach (BIG5_BANDS as $code): ?>
          <option value="<?= h($code) ?>"<?= $code === $b ? ' selected' : '' ?>><?= h($bandLabel[$code]) ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="adm-btn adm-btn-sm">보기</button>
    </form>
<?php if (is_array($entry)): ?>
    <p class="adm-meta">아래는 사용자 결과지에 보이는 글 모양 그대로입니다.</p>
<?php endif; ?>
  </section>
</div>
<?php if (is_array($entry)): /* 미리보기는 결과지의 글 모양 그대로 — 공용 관리자 본문 규칙이 닿지 않게 .adm 밖(.adm-report)에 둔다 */ ?>
<div class="adm-report">
<article class="reading preview" id="preview">
  <h3><?= h((string) $content['domains'][$d]['name']) ?> · <?= h($bandLabel[$b]) ?></h3>
<?php if (is_array($about)): ?>
  <p class="muted"><strong>이 다이얼은 무엇을 재나</strong> <?= h((string) $about['about']) ?></p>
<?php endif; ?>
  <p class="summary-line"><?= h((string) $entry['summary_line']) ?></p>
<?php foreach ((array) $entry['body'] as $p): ?>
  <p><?= h_lines((string) $p) ?></p>
<?php endforeach; ?>
  <h4>강점</h4>
  <ul><?php foreach ((array) $entry['strengths'] as $s): ?><li><?= h((string) $s) ?></li><?php endforeach; ?></ul>
  <h4>주의할 점</h4>
  <ul><?php foreach ((array) $entry['cautions'] as $s): ?><li><?= h((string) $s) ?></li><?php endforeach; ?></ul>
  <p><strong>한 줄로 남기면</strong> <?= h((string) $entry['closing']) ?></p>
<?php if (!empty($entry['help_line'])): ?>
  <p class="notice-box"><?= h((string) $entry['help_line']) ?></p>
<?php endif; ?>
  <h4>하위 요인 (<?= h($bandLabel[$b]) ?>)</h4>
  <ul>
<?php foreach (($content['facets']['facets'] ?? []) as $code => $f): if ($code[0] !== $d) { continue; } ?>
    <li><strong><?= h($code . ' ' . $f['name']) ?></strong> — <?= h((string) $f[$b]) ?></li>
<?php endforeach; ?>
  </ul>
</article>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
