<?php
/**
 * 결과 리포트 (PLAN 5장) — 한 페이지 안에서 위에서 아래로 읽는다. 상단 목차, 인쇄 시 장마다 새 페이지.
 *  표지 · 0 읽는 법 · 1 빅파이브란 · 2 일곱 가지 · 3 한눈에 · 4 다이얼별 · 5 조합 · 6 일터 · 7 관계 · 8 성장 · 부록
 *  9 다른 사람이 본 나(받은 타인 평가가 있을 때) · 10 지난번과 비교(앞선 완료 검사가 있을 때)
 * 본인 검사만 열람 — id 만 바꿔 남의 결과를 볼 수 없다.
 * 새로고침 없는 동작(ajax=1 POST): action=growth_add | growth_remove (domain, direction, action_key)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/test_session.php';
require_once __DIR__ . '/includes/not_found.php';
require_once __DIR__ . '/includes/content_loader.php';
require_once __DIR__ . '/includes/report_builder.php';
require_once __DIR__ . '/includes/report_charts.php';
require_once __DIR__ . '/includes/others.php';

const BIG5_GROWTH_MAX = 9; // 검사 한 번에 저장할 수 있는 성장 행동 수

$id = (int) big5_param('id', '0');
$userId = (int) $big5User['id'];
$session = $id > 0 ? big5_session_get($pdo, $id, $userId) : null;
$isAjax = big5_is_post() && ($_POST['ajax'] ?? '') === '1';

if ($session === null) {
    if ($isAjax) {
        big5_json(['ok' => 0, 'code' => 'not_found', 'error' => '검사를 찾을 수 없습니다.']);
    }
    big5_not_found();
}
if ($session['status'] !== 'completed') {
    if ($isAjax) {
        big5_json(['ok' => 0, 'code' => 'not_completed', 'error' => '아직 끝나지 않은 검사입니다.']);
    }
    big5_redirect('test.php?id=' . $id);
}

$content = big5_content();
$growthContent = $content['growth']['domains'] ?? [];

/** 이 검사에 저장한 성장 행동 key 목록 */
function big5_growth_saved(PDO $pdo, int $userId, int $sessionId): array
{
    $st = $pdo->prepare('SELECT domain, direction, action_key FROM big5_growth_choices WHERE user_id = ? AND session_id = ? ORDER BY id');
    $st->execute([$userId, $sessionId]);
    return $st->fetchAll();
}

if ($isAjax) {
    big5_csrf_check(true);
    $action = big5_param('action');
    $domain = big5_param('domain');
    $direction = big5_param('direction');
    $key = big5_param('action_key');
    $valid = false;
    foreach ((array) ($growthContent[$domain][$direction]['actions'] ?? []) as $a) {
        $valid = $valid || ($a['key'] ?? '') === $key;
    }
    if (!in_array($direction, ['grow', 'shadow'], true) || !$valid) {
        big5_json(['ok' => 0, 'code' => 'invalid', 'error' => '알 수 없는 행동입니다.']);
    }
    try {
        if ($action === 'growth_add') {
            $saved = big5_growth_saved($pdo, $userId, $id);
            if (!in_array($key, array_column($saved, 'action_key'), true)) {
                if (count($saved) >= BIG5_GROWTH_MAX) {
                    big5_json(['ok' => 0, 'code' => 'limit', 'error' => '성장 행동은 ' . BIG5_GROWTH_MAX . '개까지 저장할 수 있어요.']);
                }
                $pdo->prepare('INSERT INTO big5_growth_choices (user_id, session_id, domain, direction, action_key, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$userId, $id, $domain, $direction, $key, big5_now()]);
            }
        } elseif ($action === 'growth_remove') {
            $pdo->prepare('DELETE FROM big5_growth_choices WHERE user_id = ? AND session_id = ? AND action_key = ?')->execute([$userId, $id, $key]);
        } else {
            big5_json(['ok' => 0, 'code' => 'action', 'error' => '알 수 없는 요청입니다.']);
        }
        big5_json(['ok' => 1, 'saved' => array_column(big5_growth_saved($pdo, $userId, $id), 'action_key')]);
    } catch (Throwable $e) {
        error_log('다이얼 성장 행동 저장 오류: ' . $e->getMessage());
        big5_json(['ok' => 0, 'code' => 'server', 'error' => '저장하지 못했습니다. 잠시 뒤 다시 눌러 주세요.']);
    }
}

$items = big5_items((string) $session['test_type']);
$scores = big5_scores_load($pdo, 'session', $id);
$R = big5_report_build($session, $scores, $items, $content);
$fx = $content['fixed'];
$D = $R['domains'];

// 성장 섹션 데이터 (JS 가 textContent 로 그린다)
$growthPayload = ['domains' => [], 'saved' => array_column(big5_growth_saved($pdo, $userId, $id), 'action_key'), 'max' => BIG5_GROWTH_MAX];
foreach ($D as $d => $x) {
    foreach (['grow', 'shadow'] as $dir) {
        $g = $growthContent[$d][$dir] ?? ['label' => '', 'actions' => []];
        $growthPayload['domains'][$d][$dir] = ['label' => (string) $g['label'], 'actions' => array_values($g['actions'])];
    }
    $growthPayload['domains'][$d]['name'] = $x['name'];
    $growthPayload['domains'][$d]['band'] = $x['band'];
}

// 9장: 받은 타인 평가 (요인 단위, 평가자는 관계 범주로만)
$others = big5_others_section(array_map(static fn($x) => $x['pomp'], $D), big5_ratings_received($pdo, $userId));
// 10장: 바로 앞 완료 검사와 비교
$prev = big5_session_previous($pdo, $session);
$retest = null;
if ($prev !== null) {
    $retest = [
        'prev' => $prev,
        'cmp' => big5_compare_scores(big5_scores_load($pdo, 'session', (int) $prev['id']), $scores),
        'growth' => array_values(array_filter(array_map(static fn($k) => big5_growth_text($growthContent, $k), big5_growth_keys($pdo, $userId, (int) $prev['id'])))),
    ];
}

// 장 번호 (9·10장은 데이터가 있을 때만 끼어든다)
$chapters = [
    'guide' => [0, $fx['reading_guide']['title']],
    'intro' => [1, $fx['intro']['title']],
    'cautions' => [2, $fx['seven_cautions']['title']],
    'glance' => [3, $fx['glance']['title']],
    'detail' => [4, $fx['detail']['title']],
    'combos' => [5, $fx['combos']['title']],
    'work' => [6, $fx['work']['title']],
    'relations' => [7, $fx['relations']['title']],
    'growth' => [8, $fx['growth']['title']],
];
if ($others !== null) {
    $chapters['others'] = [9, $fx['others']['title']];
}
if ($retest !== null) {
    $chapters['retest'] = [10, $fx['retest']['title']];
}
$chapters['appendix'] = [11, $fx['appendix']['title']];
$hd = static fn(string $k): string => $chapters[$k][0] . '. ' . $chapters[$k][1];

$radar = big5_radar_svg(array_values(array_map(static fn($x) => ['label' => $x['name'], 'value' => $x['pomp'], 'color' => $x['color']], $D)));

$page_title = '나의 다섯 다이얼';
$body_class = 'page-report';
$extra_css = ['print.css'];
$extra_js = ['report.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="report" id="report">

<!-- 표지 -->
<section class="r-cover" id="cover">
  <p class="r-kicker">빅파이브 성격 리포트</p>
  <h1><?= h((string) $fx['report_title']) ?></h1>
  <p class="r-meta"><strong><?= h((string) $big5User['display_name']) ?></strong> · 검사일 <?= h($R['date']) ?> · <?= h($R['test_label']) ?> · 문항 <?= h($R['item_version']) ?></p>
  <div class="r-mini">
<?php foreach ($D as $d => $x): ?>
    <div class="r-mini-row">
      <span class="r-mini-name" style="--c: <?= h($x['color']) ?>"><?= h($x['name']) ?></span>
      <?= big5_bar_html($x['pomp'], $x['color'], $x['name'], 'sm') ?>
      <span class="r-mini-val"><?= h(number_format($x['pomp'], 1)) ?></span>
    </div>
<?php endforeach; ?>
  </div>
<?php if ($R['quality'] !== []): ?>
  <div class="notice-box quality-box">
    <p><strong>응답에 대해 알려 드릴 점</strong></p>
    <ul>
<?php foreach ($R['quality'] as $f): ?>
      <li><?= h(big5_quality_message($f)) ?></li>
<?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
  <p class="r-actions no-print">
    <a class="btn btn-outline" href="#cautions">먼저 읽기: 꼭 알아야 할 일곱 가지</a>
    <button type="button" class="btn btn-primary" id="btnPrint">인쇄 / PDF 저장</button>
  </p>
  <nav class="r-toc no-print" aria-label="리포트 목차">
    <p class="r-toc-title">목차</p>
    <ol>
<?php foreach ($chapters as $k => [$no, $title]): ?>
      <li><a href="#<?= h($k) ?>"><?= h($no . '. ' . $title) ?></a></li>
<?php endforeach; ?>
    </ol>
  </nav>
</section>

<!-- 0. 읽는 법 -->
<section class="r-chapter" id="guide">
  <h2><?= h($hd('guide')) ?></h2>
<?php foreach ($fx['reading_guide']['paragraphs'] as $p): ?>
  <p><?= h_lines((string) $p) ?></p>
<?php endforeach; ?>
</section>

<!-- 1. 빅파이브란 -->
<section class="r-chapter" id="intro">
  <h2><?= h($hd('intro')) ?></h2>
<?php foreach ($fx['intro']['sections'] as $sec): ?>
  <h3><?= h((string) $sec['heading']) ?></h3>
<?php foreach ($sec['paragraphs'] as $p): ?>
  <p><?= h_lines((string) $p) ?></p>
<?php endforeach; ?>
<?php endforeach; ?>
  <h3><?= h((string) $fx['intro']['domain_table_title']) ?></h3>
  <div class="table-wrap"><table class="r-table">
    <tbody>
<?php foreach ($fx['intro']['domain_table'] as $row): ?>
      <tr><th scope="row"><span class="dot" style="--c: var(--c-<?= h((string) $row['code']) ?>)"></span><?= h((string) $row['name']) ?></th><td><?= h((string) $row['line']) ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
  <h3><?= h((string) $fx['intro']['mbti']['heading']) ?></h3>
  <p><?= h_lines((string) $fx['intro']['mbti']['paragraph']) ?></p>
  <div class="table-wrap"><table class="r-table r-table-compare">
    <thead><tr><?php foreach ($fx['intro']['mbti']['table']['head'] as $c): ?><th><?= h((string) $c) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
<?php foreach ($fx['intro']['mbti']['table']['rows'] as $row): ?>
      <tr><?php foreach ($row as $i => $c): ?><?= $i === 0 ? '<th scope="row">' . h((string) $c) . '</th>' : '<td>' . h((string) $c) . '</td>' ?><?php endforeach; ?></tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<!-- 2. 일곱 가지 -->
<section class="r-chapter" id="cautions">
  <h2><?= h($hd('cautions')) ?></h2>
  <ol class="r-cautions">
<?php foreach ($fx['seven_cautions']['items'] as $it): ?>
    <li><strong><?= h((string) $it['title']) ?></strong> <?= h((string) $it['text']) ?></li>
<?php endforeach; ?>
  </ol>
</section>

<!-- 3. 한눈에 -->
<section class="r-chapter" id="glance">
  <h2><?= h($hd('glance')) ?></h2>
  <div class="r-glance">
    <div class="r-bars">
<?php foreach ($D as $d => $x): ?>
      <div class="r-bar-row">
        <div class="dial-head">
          <span class="dial-name" style="--c: <?= h($x['color']) ?>"><?= h($x['name']) ?></span>
          <span class="dial-score"><?= h(number_format($x['pomp'], 1)) ?> · <?= h($x['band_label']) ?></span>
        </div>
        <?= big5_bar_html($x['pomp'], $x['color'], $x['name']) ?>
        <p class="r-summary"><?= h((string) $x['entry']['summary_line']) ?></p>
      </div>
<?php endforeach; ?>
      <div class="r-scale-legend" aria-hidden="true"><span>0</span><span>35</span><span>65</span><span>100</span></div>
    </div>
    <div class="r-radar"><?= $radar /* 서버에서 만든 SVG, 글자는 h() 로 이스케이프됨 */ ?></div>
  </div>
  <p class="form-help"><?= h((string) $fx['glance']['band_note']) ?></p>
  <h3><?= h((string) $fx['glance']['standout_title']) ?></h3>
<?php if ($R['all_mid']): ?>
  <p><?= h((string) $fx['glance']['all_mid_note']) ?></p>
<?php else: ?>
  <p class="muted"><?= h((string) $fx['glance']['standout_intro']) ?></p>
  <div class="r-standout">
<?php foreach ($R['standout'] as $d): $x = $D[$d]; ?>
    <div class="r-card" style="--c: <?= h($x['color']) ?>">
      <p class="r-card-title"><?= h($x['name']) ?> <span><?= h(number_format($x['pomp'], 1)) ?> · <?= h($x['band_label']) ?></span></p>
      <p><?= h((string) $x['entry']['summary_line']) ?></p>
    </div>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</section>

<!-- 4. 다이얼별 상세 -->
<section class="r-chapter" id="detail">
  <h2><?= h($hd('detail')) ?></h2>
<?php $h = $fx['detail']['headings']; $i = 0; foreach ($D as $d => $x): $i++; $e = $x['entry']; ?>
  <article class="r-domain" id="detail-<?= h($d) ?>" style="--c: <?= h($x['color']) ?>">
    <h3 class="r-domain-title"><span class="r-domain-no">4-<?= $i ?></span> <?= h($x['name']) ?> <small><?= h($x['tagline']) ?></small></h3>
    <h4><?= h((string) $h['about']) ?></h4>
    <p><?= h_lines($x['about']) ?></p>
    <h4><?= h((string) $h['position']) ?></h4>
    <div class="dial-head">
      <span class="dial-name" style="--c: <?= h($x['color']) ?>"><?= h($x['name']) ?></span>
      <span class="dial-score"><?= h(number_format($x['pomp'], 1)) ?> · <?= h($x['band_label']) ?><?php if ($x['near'] !== null): ?> · <span class="tag-near"><?= h((string) $fx['detail']['near_boundary_label']) ?></span><?php endif; ?></span>
    </div>
    <?= big5_bar_html($x['pomp'], $x['color'], $x['name']) ?>
    <p class="r-summary"><strong><?= h((string) $e['summary_line']) ?></strong></p>
<?php if ($x['near'] !== null): ?>
    <div class="r-near">
      <p><?= h((string) $fx['detail']['near_boundary_note']) ?></p>
      <p><strong><?= h($x['near']['band_label']) ?> 쪽이라면</strong> <?= h($x['near']['summary_line']) ?></p>
    </div>
<?php endif; ?>
    <h4><?= h((string) $h['body']) ?></h4>
<?php foreach ($e['body'] as $p): ?>
    <p><?= h_lines((string) $p) ?></p>
<?php endforeach; ?>
    <div class="r-two">
      <div><h4><?= h((string) $h['strengths']) ?></h4><ul><?php foreach ($e['strengths'] as $s): ?><li><?= h((string) $s) ?></li><?php endforeach; ?></ul></div>
      <div><h4><?= h((string) $h['cautions']) ?></h4><ul><?php foreach ($e['cautions'] as $s): ?><li><?= h((string) $s) ?></li><?php endforeach; ?></ul></div>
    </div>
<?php if (!empty($e['help_line'])): ?>
    <p class="notice-box r-help"><?= h((string) $e['help_line']) ?></p>
<?php endif; ?>
    <h4><?= h((string) $h['facets']) ?></h4>
<?php if ($R['is_full']): ?>
<?php if ($x['facet_spread']): ?>
    <p class="r-near"><?= h((string) $fx['detail']['facet_spread_note']) ?></p>
<?php endif; ?>
    <div class="r-facets">
<?php foreach ($x['facets'] as $f): ?>
      <div class="r-facet">
        <div class="dial-head"><span class="r-facet-name"><?= h($f['code'] . ' ' . $f['name']) ?></span><span class="dial-score"><?= h(number_format($f['pomp'], 1)) ?> · <?= h($f['band_label']) ?></span></div>
        <?= big5_bar_html($f['pomp'], $x['color'], $f['name'], 'sm') ?>
        <p class="r-facet-text"><span class="muted"><?= h($f['definition']) ?></span> <?= h($f['text']) ?></p>
      </div>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="muted"><?= h((string) $fx['detail']['mini_no_facets']) ?></p>
<?php endif; ?>
    <h4><?= h((string) $h['misconceptions']) ?></h4>
    <ul class="r-myths">
<?php foreach ($x['misconceptions'] as $m): ?>
      <li><strong><?= h((string) $m['title']) ?></strong> <?= h((string) $m['text']) ?></li>
<?php endforeach; ?>
    </ul>
    <h4><?= h((string) $h['closing']) ?></h4>
    <p class="r-closing"><?= h((string) $e['closing']) ?></p>
  </article>
<?php endforeach; ?>
</section>

<!-- 5. 조합 -->
<section class="r-chapter" id="combos">
  <h2><?= h($hd('combos')) ?></h2>
  <p><?= h((string) $fx['combos']['intro']) ?></p>
<?php foreach ($R['combos'] as $c): $rule = $c['rule']; ?>
  <div class="r-combo">
    <p class="r-combo-cond">
<?php foreach ($rule['when'] as $d => $b): if (!isset($D[$d])) { continue; } ?>
      <span class="chip" style="--c: <?= h($D[$d]['color']) ?>"><?= h($D[$d]['name'] . ' ' . $fx['band_labels'][$b]) ?></span>
<?php endforeach; ?>
<?php if (!$c['matched']): ?>
      <span class="chip chip-near">가까운 조합</span>
<?php endif; ?>
    </p>
    <h3><?= h((string) $rule['title']) ?></h3>
<?php foreach ($rule['body'] as $p): ?>
    <p><?= h_lines((string) $p) ?></p>
<?php endforeach; ?>
  </div>
<?php endforeach; ?>
</section>

<!-- 6. 일터 -->
<section class="r-chapter" id="work">
  <h2><?= h($hd('work')) ?></h2>
  <p><?= h((string) $fx['work']['intro']) ?></p>
<?php foreach (['style', 'team', 'pressure'] as $k): $sec = $R['work'][$k]; ?>
  <h3><?= h($sec['title']) ?></h3>
<?php foreach ($sec['rows'] as $row): ?>
  <p><span class="chip" style="--c: <?= h($D[$row['code']]['color']) ?>"><?= h($row['name'] . ' ' . $row['band_label']) ?></span> <?= h($row['text']) ?></p>
<?php endforeach; ?>
<?php if ($k === 'style'): $en = $R['work']['energy']; ?>
  <h3><?= h((string) $en['title']) ?></h3>
  <p class="form-help"><?= h((string) $en['note']) ?></p>
  <div class="table-wrap"><table class="r-table r-energy">
    <thead><tr><th>다이얼</th><th><?= h((string) $en['gain_label']) ?></th><th><?= h((string) $en['drain_label']) ?></th></tr></thead>
    <tbody>
<?php foreach ($en['rows'] as $row): ?>
      <tr>
        <th scope="row"><span class="dot" style="--c: <?= h($D[$row['code']]['color']) ?>"></span><?= h($row['name']) ?><br><small class="muted"><?= h($row['band_label']) ?></small></th>
        <td><ul><?php foreach ($row['gain'] as $g): ?><li><?= h((string) $g) ?></li><?php endforeach; ?></ul></td>
        <td><ul><?php foreach ($row['drain'] as $g): ?><li><?= h((string) $g) ?></li><?php endforeach; ?></ul></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
<?php endforeach; ?>
  <h3><?= h($R['work']['leader']['title']) ?></h3>
  <p class="muted"><?= h($R['work']['leader']['intro']) ?></p>
<?php foreach ($R['work']['leader']['rows'] as $row): ?>
  <p><span class="chip" style="--c: <?= h($D[$row['code']]['color']) ?>"><?= h($row['name'] . ' ' . $row['band_label']) ?></span> <?= h($row['text']) ?></p>
<?php endforeach; ?>
</section>

<!-- 7. 관계 -->
<section class="r-chapter" id="relations">
  <h2><?= h($hd('relations')) ?></h2>
  <p><?= h((string) $fx['relations']['intro']) ?></p>
<?php foreach ($R['relations'] as $sec): ?>
  <h3><?= h($sec['title']) ?></h3>
<?php foreach ($sec['rows'] as $row): ?>
  <p><span class="chip" style="--c: <?= h($D[$row['code']]['color']) ?>"><?= h($row['name'] . ' ' . $row['band_label']) ?></span> <?= h($row['text']) ?></p>
<?php endforeach; ?>
<?php endforeach; ?>
</section>

<!-- 8. 성장 -->
<section class="r-chapter" id="growth">
  <h2><?= h($hd('growth')) ?></h2>
<?php foreach ($fx['growth']['paragraphs'] as $p): ?>
  <p><?= h_lines((string) $p) ?></p>
<?php endforeach; ?>
  <p class="muted no-print"><?= h((string) $fx['growth']['how_to']) ?></p>
  <div class="r-growth no-print" id="growthApp"
       data-growth-b64="<?= h(base64_encode((string) json_encode($growthPayload, JSON_UNESCAPED_UNICODE))) ?>"
       data-csrf="<?= h(big5_csrf_token()) ?>"
       data-post="<?= h(big5_url('report.php?id=' . $id)) ?>"
       data-grow-label="<?= h((string) $fx['growth']['grow_label']) ?>"
       data-shadow-label="<?= h((string) $fx['growth']['shadow_label']) ?>">
    <div class="r-growth-pick" id="growthPick"></div>
    <div class="r-growth-list" id="growthList" aria-live="polite"></div>
    <p class="form-error" id="growthError" hidden></p>
  </div>
  <div class="r-growth-saved">
    <h3>저장한 성장 행동</h3>
    <ul id="growthSaved">
<?php
$savedKeys = $growthPayload['saved'];
$savedText = [];
foreach ($growthContent as $d => $dirs) {
    foreach (['grow', 'shadow'] as $dir) {
        foreach ($dirs[$dir]['actions'] ?? [] as $a) {
            if (in_array($a['key'], $savedKeys, true)) {
                $savedText[$a['key']] = (string) $a['text'];
            }
        }
    }
}
foreach ($savedKeys as $k): if (!isset($savedText[$k])) { continue; } ?>
      <li data-key="<?= h($k) ?>"><?= h($savedText[$k]) ?></li>
<?php endforeach; ?>
    </ul>
    <p class="muted" id="growthEmpty"<?= $savedText === [] ? '' : ' hidden' ?>>아직 저장한 행동이 없습니다.</p>
  </div>
</section>

<?php if ($others !== null): ?>
<!-- 9. 다른 사람이 본 나 -->
<section class="r-chapter" id="others">
  <h2><?= h($hd('others')) ?></h2>
<?php foreach ($fx['others']['guide'] as $g): ?>
  <p><?= h((string) $g) ?></p>
<?php endforeach; ?>
  <p class="r-others-rel">받은 평가 <?= (int) $others['count'] ?>건 ·
<?php $relTxt = []; foreach ($others['relations'] as $label => $cnt) { $relTxt[] = $label . ' ' . $cnt; } echo h(implode(', ', $relTxt)); ?>
  </p>
  <p class="r-legend"><span><b></b>나</span><span><i></i><?= h((string) ($others['single'] ? $fx['others']['single_rater_label'] : $fx['others']['average_label'])) ?></span></p>
  <div class="c-list">
<?php foreach ($others['rows'] as $d => $row): $x = $D[$d]; ?>
    <div class="c-row<?= $row['flag'] ? ' is-flag' : '' ?>">
      <div class="dial-head">
        <span class="dial-name" style="--c: <?= h($x['color']) ?>"><?= h($x['name']) ?></span>
        <span class="dial-score">나 <?= h(number_format($row['self'], 1)) ?> · <?= h((string) ($others['single'] ? '한 사람' : '평균')) ?> <strong><?= h(number_format($row['others'], 1)) ?></strong><?php if ($row['flag']): ?> <span class="tag-near"><?= h((string) $fx['others']['gap_label']) ?></span><?php endif; ?></span>
      </div>
      <?= big5_bar_html($row['self'], $x['color'], $x['name'], 'lg', $row['others']) ?>
    </div>
<?php endforeach; ?>
  </div>
  <p class="form-help"><?= h((string) $fx['others']['gap_note']) ?></p>
</section>
<?php endif; ?>

<?php if ($retest !== null): $pv = $retest['prev']; ?>
<!-- 10. 지난번의 나와 비교 -->
<section class="r-chapter" id="retest">
  <h2><?= h($hd('retest')) ?></h2>
  <p class="muted">지난번: <?= h(substr((string) $pv['completed_at'], 0, 10)) ?> <?= h(big5_test_label((string) $pv['test_type'])) ?> → 이번: <?= h($R['date']) ?> <?= h($R['test_label']) ?></p>
<?php foreach ($fx['retest']['guide'] as $g): ?>
  <p><?= h((string) $g) ?></p>
<?php endforeach; ?>
  <p class="r-legend"><span><i></i>지난번</span><span><b></b>이번</span></p>
  <div class="c-list">
<?php foreach ($retest['cmp']['domains'] as $d => $row): if (!isset($D[$d])) { continue; } $x = $D[$d]; ?>
    <div class="c-row<?= $row['flag'] ? ' is-flag' : '' ?>">
      <div class="dial-head">
        <span class="dial-name" style="--c: <?= h($x['color']) ?>"><?= h($x['name']) ?></span>
        <span class="dial-score"><?= h(number_format($row['before'], 1)) ?> → <strong><?= h(number_format($row['after'], 1)) ?></strong> (<?= h(($row['diff'] > 0 ? '+' : '') . number_format($row['diff'], 1)) ?>)<?php if ($row['flag']): ?> <span class="tag-near">10점 이상</span><?php endif; ?></span>
      </div>
      <?= big5_bar_html($row['after'], $x['color'], $x['name'], 'lg', $row['before']) ?>
    </div>
<?php endforeach; ?>
  </div>
<?php $flagged = array_filter($retest['cmp']['facets'], static fn($r) => $r['flag']); if ($flagged !== []): ?>
  <h3>10점 이상 움직인 하위 요인</h3>
  <ul>
<?php foreach ($flagged as $code => $row): ?>
    <li><?= h($code . ' ' . (string) ($items['facets'][$code]['name'] ?? $code)) ?>: <?= h(number_format($row['before'], 1)) ?> → <?= h(number_format($row['after'], 1)) ?></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
  <h3><?= h((string) $fx['retest']['growth_recall']) ?></h3>
<?php if ($retest['growth'] === []): ?>
  <p class="muted">지난번 검사에서 골라 둔 성장 행동이 없습니다.</p>
<?php else: ?>
  <ul id="growthRecall">
<?php foreach ($retest['growth'] as $t): ?>
    <li><?= h($t) ?></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
  <p class="no-print"><a href="<?= h(big5_url('compare.php?pick[]=' . (int) $pv['id'] . '&pick[]=' . $id)) ?>">하위 요인까지 자세히 비교하기</a></p>
</section>
<?php endif; ?>

<!-- 부록 -->
<section class="r-chapter" id="appendix">
  <h2><?= h($hd('appendix')) ?></h2>
  <h3><?= h((string) $fx['appendix']['scoring']['title']) ?></h3>
<?php foreach ($fx['appendix']['scoring']['paragraphs'] as $p): ?>
  <p><?= h_lines((string) $p) ?></p>
<?php endforeach; ?>
  <div class="table-wrap"><table class="r-table r-scores">
    <thead><tr><th>다이얼</th><th>원점수</th><th>범위</th><th>0~100</th><th>구간</th></tr></thead>
    <tbody>
<?php foreach ($D as $d => $x): $min = $R['is_full'] ? 24 : 4; $max = $R['is_full'] ? 120 : 20; ?>
      <tr><th scope="row"><?= h($x['name']) ?></th><td><?= (int) $x['raw'] ?></td><td><?= $min ?>~<?= $max ?></td><td><?= h(number_format($x['pomp'], 1)) ?></td><td><?= h($x['band_label']) ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
  <h3><?= h((string) $fx['appendix']['glossary_title']) ?></h3>
  <dl class="r-glossary">
<?php foreach ($fx['appendix']['glossary'] as $g): ?>
    <dt><?= h((string) $g['term']) ?></dt><dd><?= h((string) $g['text']) ?></dd>
<?php endforeach; ?>
  </dl>
  <h3><?= h((string) $fx['appendix']['reading_title']) ?></h3>
  <ul>
<?php foreach ($fx['appendix']['reading'] as $r): ?>
    <li><?= h((string) $r['title']) ?> — <span class="muted"><?= h((string) $r['note']) ?></span></li>
<?php endforeach; ?>
  </ul>
  <p class="muted"><?= h((string) $fx['appendix']['reading_web']) ?></p>
  <h3><?= h((string) $fx['appendix']['sources_title']) ?></h3>
  <ul>
<?php foreach ($fx['appendix']['sources'] as $s): ?>
    <li><?= h((string) $s) ?></li>
<?php endforeach; ?>
    <li>채점 버전 <?= h((string) ($session['scoring_version'] ?? '')) ?> · 문항 <?= h($R['item_version']) ?> · <?= h(big5_version_label()) ?></li>
  </ul>
</section>

<!-- 인쇄본 꼬리말: 쪽 아래 여백(@page)에 쪽마다 찍힌다. 화면에는 리포트 끝에 한 번 보인다 -->
<p class="print-footer muted"><?= h((string) $big5User['display_name']) ?> · 검사일 <?= h($R['date']) ?> · <?= h((string) $fx['print_footer']) ?></p>
<?php
/** CSS 문자열 안에 넣을 값 이스케이프 (따옴표·역슬래시·꺾쇠·줄바꿈) */
$cssStr = static fn(string $s): string => str_replace(['\\', '"', '<', '>', "\n", "\r"], ['\\\\', '\\"', '\\3C ', '\\3E ', ' ', ' '], $s);
$footerText = (string) $big5User['display_name'] . ' · 검사일 ' . $R['date'] . ' · ' . (string) $fx['print_footer'];
?>
<style>@media print { @page { @bottom-center { content: "<?= $cssStr($footerText) ?>"; font-size: 8pt; color: #666; font-family: "Pretendard Variable", Pretendard, "Apple SD Gothic Neo", "Malgun Gothic", "Noto Sans KR", sans-serif; } @bottom-right { content: counter(page) " / " counter(pages); font-size: 8pt; color: #999; } } }</style>
</div>
<?php require __DIR__ . '/includes/footer.php';
