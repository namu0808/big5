<?php
/**
 * 2단계 검증 — 문항 데이터 구조, 원문 키 일치, 채점(PLAN 10장 2단계 4가지 경우), 구간, 품질 점검.
 * 실행: php tests/scoring_test.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/items.php';
require dirname(__DIR__) . '/includes/scoring.php';
require dirname(__DIR__) . '/includes/quality.php';
require __DIR__ . '/lib.php';

$root = dirname(__DIR__);
$i120 = big5_items('full120');
$i20 = big5_items('mini20');
t_ok($i120 !== null && $i20 !== null, '문항 JSON 두 개 읽기');

// ── 구조: 120문항 ──
$nos = array_map(static fn($it) => $it['no'], $i120['items']);
t_ok(count($nos) === 120 && array_values(array_unique($nos)) === range(1, 120), '120문항 번호 1~120 중복·누락 없음');
$fc = array_count_values(array_map(static fn($it) => $it['facet'], $i120['items']));
t_ok(count($fc) === 30 && array_unique(array_values($fc)) === [4], '하위 요인 30개 × 정확히 4문항');
$dc = array_count_values(array_map(static fn($it) => $it['facet'][0], $i120['items']));
$fpd = [];
foreach (array_keys($fc) as $f) {
    $fpd[$f[0]] = ($fpd[$f[0]] ?? 0) + 1;
}
t_ok(count($dc) === 5 && array_unique(array_values($dc)) === [24] && array_unique(array_values($fpd)) === [6], '요인 5개 × 하위 요인 6개 × 24문항');
t_ok(count($i120['facets']) === 30 && count($i120['domains']) === 5, '하위 요인 30개·요인 5개 이름 정의');
$emptyKo = array_filter($i120['items'], static fn($it) => $it['text_ko'] === '' || $it['text_ko_other'] === '' || $it['text_en'] === '');
t_ok($emptyKo === [], '120문항 원문·번역·타인용 빈칸 없음');
$otherPrefix = array_filter($i120['items'], static fn($it) => !str_starts_with($it['text_ko_other'], '이 사람은 '));
t_ok($otherPrefix === [], "타인용 문장은 모두 '이 사람은'으로 시작");

// ── 구조: 20문항 ──
$n20 = array_map(static fn($it) => $it['no'], $i20['items']);
t_ok(array_values($n20) === range(1, 20), '20문항 번호 1~20');
$d20 = array_count_values(array_map(static fn($it) => $it['domain'], $i20['items']));
t_ok(count($d20) === 5 && array_unique(array_values($d20)) === [4], '20문항 요인 5개 × 4문항');
$minus20 = array_values(array_map(static fn($it) => $it['no'], array_filter($i20['items'], static fn($it) => $it['keyed'] === '-')));
t_ok($minus20 === [6, 7, 8, 9, 10, 15, 16, 17, 18, 19, 20], '20문항 역채점 번호 = 원고 부록 A');

// ── 원문 키 일치: JSON 이 master_*.csv 와 한 글자도 다르지 않은가 ──
function master_rows(string $path): array
{
    $fh = fopen($path, 'r');
    $h = null;
    $rows = [];
    while (($r = fgetcsv($fh, null, ',', '"', '')) !== false) {
        if ($r === [null] || str_starts_with((string) $r[0], '#')) {
            continue;
        }
        if ($h === null) {
            $h = $r;
            continue;
        }
        $rows[] = array_combine($h, $r);
    }
    return $rows;
}
$m120 = master_rows($root . '/data/source/master_120.csv');
$diff = 0;
foreach ($m120 as $k => $m) {
    $it = $i120['items'][$k];
    if ($it['no'] !== (int) $m['item_no_120'] || $it['facet'] !== $m['facet_code'] || $it['keyed'] !== $m['keyed'] || $it['text_en'] !== $m['text_en']) {
        $diff++;
    }
}
t_ok(count($m120) === 120 && $diff === 0, "items_120.json ↔ master_120.csv 번호·하위 요인·방향·원문 일치 (차이 {$diff})");
$m20 = master_rows($root . '/data/source/master_20.csv');
$diff = 0;
foreach ($m20 as $k => $m) {
    $it = $i20['items'][$k];
    if ($it['no'] !== (int) $m['item_no_20'] || $it['domain'] !== $m['factor'] || $it['keyed'] !== $m['keyed'] || $it['text_ko'] !== $m['text_ko'] || $it['text_en'] !== $m['text_en']) {
        $diff++;
    }
}
t_ok(count($m20) === 20 && $diff === 0, "items_20.json ↔ master_20.csv 일치 (차이 {$diff})");

// ── 채점: PLAN 10장 2단계 필수 경우 ──
/** 응답 만들기: 정채점 문항 값 $plus, 역채점 문항 값 $minus */
function make_resp(array $items, int $plus, int $minus): array
{
    $r = [];
    foreach ($items as $it) {
        $r[$it['no']] = $it['keyed'] === '-' ? $minus : $plus;
    }
    return $r;
}
foreach (['full120' => $i120, 'mini20' => $i20] as $type => $set) {
    $s = big5_score($set['items'], make_resp($set['items'], 3, 3));
    $all = array_merge(array_values($s['domains']), array_values($s['facets']));
    t_ok(array_unique(array_map(static fn($x) => $x['pomp'], $all)) === [50.0], "{$type}: 모든 문항 3점 → 모든 점수 50.0");
    t_ok(array_unique(array_map(static fn($x) => $x['band'], $all)) === ['mid'], "{$type}: 모든 문항 3점 → 구간 mid");
    $s = big5_score($set['items'], make_resp($set['items'], 5, 1));
    $all = array_merge(array_values($s['domains']), array_values($s['facets']));
    t_ok(array_unique(array_map(static fn($x) => $x['pomp'], $all)) === [100.0], "{$type}: 정채점 5 / 역채점 1 → 모든 점수 100.0");
    $s = big5_score($set['items'], make_resp($set['items'], 1, 5));
    $all = array_merge(array_values($s['domains']), array_values($s['facets']));
    t_ok(array_unique(array_map(static fn($x) => $x['pomp'], $all)) === [0.0], "{$type}: 정채점 1 / 역채점 5 → 모든 점수 0.0");
}
$s = big5_score($i120['items'], make_resp($i120['items'], 3, 3));
t_ok(count($s['domains']) === 5 && count($s['facets']) === 30, '정식 검사: 요인 5 + 하위 요인 30 점수');
t_ok($s['domains']['C']['min'] === 24 && $s['domains']['C']['max'] === 120 && $s['facets']['C1']['min'] === 4 && $s['facets']['C1']['max'] === 20, '원점수 범위: 요인 24~120, 하위 요인 4~20');
$s = big5_score($i20['items'], make_resp($i20['items'], 3, 3));
t_ok($s['facets'] === [] && $s['domains']['C']['min'] === 4 && $s['domains']['C']['max'] === 20, '간이 검사: 하위 요인 없음, 요인 4~20');

// 요인 원점수 = 하위 요인 6개 합
$rnd = [];
mt_srand(20261102);
foreach ($i120['items'] as $it) {
    $rnd[$it['no']] = mt_rand(1, 5);
}
$s = big5_score($i120['items'], $rnd);
$ok = true;
foreach ($s['domains'] as $d => $row) {
    $sum = 0;
    for ($k = 1; $k <= 6; $k++) {
        $sum += $s['facets'][$d . $k]['raw'];
    }
    $ok = $ok && $sum === $row['raw'];
}
t_ok($ok, '요인 원점수 = 하위 요인 6개 원점수의 합 (무작위 응답)');

// 손 계산 한 건: C1 = 5,35,65,95번 (모두 +) 에 1,2,3,4 → 10 → (10-4)/16×100 = 37.5
$one = make_resp($i120['items'], 3, 3);
foreach ([5 => 1, 35 => 2, 65 => 3, 95 => 4] as $no => $v) {
    $one[$no] = $v;
}
$s = big5_score($i120['items'], $one);
t_ok($s['facets']['C1']['raw'] === 10 && $s['facets']['C1']['pomp'] === 37.5, '손 계산 대조: C1 원점수 10 → 37.5');

// 역채점 확인: 9번(A2, −)에 5 → 역채점 1
$one = make_resp($i120['items'], 3, 3);
$one[9] = 5;
$s = big5_score($i120['items'], $one);
t_ok($s['facets']['A2']['raw'] === 10, '역채점: 9번(A2 −) 5점 → 1점으로 계산');

// 응답 누락 → 예외
$miss = make_resp($i120['items'], 3, 3);
unset($miss[77]);
try {
    big5_score($i120['items'], $miss);
    t_ok(false, '응답 누락 시 예외');
} catch (InvalidArgumentException $e) {
    t_ok(true, '응답 누락 시 예외');
}

// ── 구간·경계 ──
t_ok(big5_band(34.9) === 'low' && big5_band(35.0) === 'mid' && big5_band(65.0) === 'mid' && big5_band(65.1) === 'high', '구간 경계 34.9 low / 35 mid / 65 mid / 65.1 high');
t_ok(big5_band(0.0) === 'low' && big5_band(100.0) === 'high', '구간 양 끝');
t_ok(big5_near_band(32.0) === 'mid' && big5_near_band(38.0) === 'low' && big5_near_band(62.0) === 'high' && big5_near_band(69.0) === 'mid' && big5_near_band(50.0) === null && big5_near_band(29.0) === null, '경계 ±5 근처 판정');
t_ok(big5_pomp(13, 4, 20) === 56.3, 'POMP 소수 첫째 자리 반올림 (13/4~20 → 56.3)');

// ── 품질 점검 ──
$q = big5_quality($i120['items'], make_resp($i120['items'], 3, 3), 900);
t_ok($q['flags'] === ['straightline'], '모두 3점: straightline 만 (inconsistent 아님)');
$q = big5_quality($i120['items'], make_resp($i120['items'], 4, 2), 900);
t_ok($q['flags'] === [], '일관된 응답(정 4 / 역 2), 15분: 플래그 없음');
$q = big5_quality($i120['items'], make_resp($i120['items'], 4, 2), 200);
t_ok($q['flags'] === ['fast'], '120문항 200초(4분 미만): fast');
$q = big5_quality($i120['items'], make_resp($i120['items'], 5, 5), 900);
t_ok(in_array('inconsistent', $q['flags'], true) && in_array('straightline', $q['flags'], true), '모두 5점(반대 문장에도 동의): inconsistent + straightline');
$q = big5_quality($i20['items'], make_resp($i20['items'], 5, 5), 30);
t_ok($q['flags'] === ['fast', 'straightline', 'inconsistent'], '간이 검사 30초·모두 5점: fast, straightline, inconsistent');
t_ok(big5_quality_message('fast') !== '' && big5_quality_message('inconsistent') !== '', '품질 안내 문구');

// ── 부정문 문항의 응답 방향 (회귀 테스트, 2026-11-02 점검) ──
// 문항은 질문이 아니라 서술문이고, 선지는 '이 문장이 나에게 해당하는가'를 묻는다.
// 부정문에 '그런 편이다'(4)를 고르면 그 문장이 나에게 맞다는 뜻 → 역채점(−) 문항은 해당 점수를 낮춰야 한다.
function one_item(array $items, int $no, int $value): array
{
    $r = make_resp($items, 3, 3);
    $r[$no] = $value;
    return $r;
}
$s = big5_score($i20['items'], one_item($i20['items'], 6, 4));
t_ok($s['domains']['E']['pomp'] < 50, "20문항 6번 '말을 많이 하지 않는다'(E −)에 '그런 편이다' → 외향성 50 미만 ({$s['domains']['E']['pomp']})");
$s = big5_score($i20['items'], one_item($i20['items'], 6, 2));
t_ok($s['domains']['E']['pomp'] > 50, "20문항 6번에 '아닌 편이다' → 외향성 50 초과 ({$s['domains']['E']['pomp']})");
$s = big5_score($i20['items'], one_item($i20['items'], 7, 4));
t_ok($s['domains']['A']['pomp'] < 50, "20문항 7번 '다른 사람의 문제에는 별로 관심이 없다'(A −)에 '그런 편이다' → 친화성 50 미만 ({$s['domains']['A']['pomp']})");
$it96 = $i120['items'][95];
$s = big5_score($i120['items'], one_item($i120['items'], 96, 4));
t_ok($it96['facet'] === 'N2' && $it96['keyed'] === '-' && $s['facets']['N2']['pomp'] < 50,
    "120문항 96번 '{$it96['text_ko']}'(N2 −)에 '그런 편이다' → 분노 50 미만 ({$s['facets']['N2']['pomp']})");
// 일반 규칙: 문항 하나만 5로 올리면 + 문항은 점수가 오르고, − 문항은 내려간다 (140문항 전부)
$bad = [];
foreach (['full120' => $i120, 'mini20' => $i20] as $type => $set) {
    foreach ($set['items'] as $it) {
        $s = big5_score($set['items'], one_item($set['items'], (int) $it['no'], 5));
        $row = isset($it['facet']) ? $s['facets'][$it['facet']] : $s['domains'][$it['domain']];
        $okDir = $it['keyed'] === '-' ? $row['pomp'] < 50 : $row['pomp'] > 50;
        if (!$okDir) {
            $bad[] = "{$type}#{$it['no']}";
        }
    }
}
t_ok($bad === [], '모든 문항: 하나만 5점 → + 문항은 점수 상승, − 문항은 하락 (140문항)' . ($bad ? ' — 어긋남: ' . implode(',', $bad) : ''));

t_done();
