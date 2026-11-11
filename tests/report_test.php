<?php
/**
 * 5단계 검증 — 리포트 데이터 구성(report_builder): 샘플 3종(전부 3점·무작위·극단값)과 간이 검사,
 * 경계 근처·하위 요인 편차·두드러진 다이얼·조합 카드(2~5개) 규칙, 레이더 SVG.
 * 실행: php tests/report_test.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/util.php';
require dirname(__DIR__) . '/includes/report_builder.php';
require dirname(__DIR__) . '/includes/report_charts.php';
require dirname(__DIR__) . '/includes/content_loader.php';
require __DIR__ . '/lib.php';

$content = big5_content();
$i120 = big5_items('full120');
$i20 = big5_items('mini20');

function sample(array $items, callable $v): array
{
    $r = [];
    foreach ($items['items'] as $it) {
        $r[$it['no']] = $v($it);
    }
    return big5_score($items['items'], $r);
}
function build(array $items, string $type, array $scores, array $content): array
{
    $s = ['test_type' => $type, 'completed_at' => '2026-11-02 10:00:00', 'item_version' => $items['version'], 'quality_flags' => null];
    return big5_report_build($s, $scores, $items, $content);
}

mt_srand(20261102);
$samples = [
    'all3' => sample($i120, static fn($it) => 3),
    'random' => sample($i120, static fn($it) => mt_rand(1, 5)),
    'extreme' => sample($i120, static function ($it) {
        $high = in_array($it['facet'][0], ['C', 'E', 'O'], true);
        return ($it['keyed'] === '+') === $high ? 5 : 1;
    }),
];
foreach ($samples as $name => $sc) {
    $R = build($i120, 'full120', $sc, $content);
    t_ok(array_keys($R['domains']) === ['C', 'E', 'A', 'O', 'N'], "{$name}: 요인 순서 성실성→외향성→친화성→개방성→신경성");
    $ok = true;
    foreach ($R['domains'] as $d) {
        $ok = $ok && count($d['facets']) === 6 && $d['entry']['summary_line'] !== '' && $d['about'] !== '';
    }
    t_ok($ok, "{$name}: 요인마다 하위 요인 6개·문구·소개");
    $n = count($R['combos']);
    t_ok($n >= 2 && $n <= 5, "{$name}: 조합 카드 2~5개 ({$n}개)");
}

// 전부 3점: 모두 가운데 → 두드러진 다이얼 대신 안내, 'all_mid' 조합 포함, 경계 근처 없음
$R = build($i120, 'full120', $samples['all3'], $content);
t_ok($R['all_mid'] && $R['standout'] === [], '전부 3점: 다섯 모두 가운데 → 두드러진 다이얼 안내');
t_ok(in_array('all_mid', array_map(static fn($c) => $c['rule']['id'], $R['combos']), true), "전부 3점: '다섯 모두 가운데' 조합 카드");
t_ok(count(array_filter($R['domains'], static fn($d) => $d['near'] !== null)) === 0, '전부 3점(50점): 경계 근처 없음');

// 극단값: C·E·O 100, A·N 0 → 두드러진 두 개, 조건이 맞는 조합 카드, 'all_mid' 없음
$R = build($i120, 'full120', $samples['extreme'], $content);
t_ok(count($R['standout']) === 2, '극단값: 두드러진 다이얼 두 개');
$ids = array_map(static fn($c) => $c['rule']['id'], $R['combos']);
t_ok(in_array('c_high_n_low', $ids, true) && in_array('e_high_a_low', $ids, true) && !in_array('all_mid', $ids, true), '극단값: 조건이 맞는 조합(성실성 높음×신경성 낮음, 외향성 높음×친화성 낮음)');
t_ok(count(array_filter($R['combos'], static fn($c) => !$c['matched'])) === 0, '극단값: 맞는 조합만으로 채움');
t_ok($R['domains']['N']['entry'] === $content['domains']['N']['low'], '극단값: 신경성 낮은 쪽 문구');

// 조합이 부족하면 가까운 조합으로 채운다
$picked = big5_pick_combos($content['combos']['rules'], ['C' => 60.0, 'E' => 50.0, 'A' => 50.0, 'O' => 50.0, 'N' => 30.0]);
t_ok(count($picked) === 2 && $picked[0]['matched'] === false, '맞는 조합이 없으면 가장 가까운 조합 2개(“가까운 조합”)');
t_ok(!in_array('all_mid', array_map(static fn($c) => $c['rule']['id'], $picked), true), "'다섯 모두 가운데'는 실제로 맞을 때만");

// 경계 근처: 한 하위 요인만 바꾸어 성실성을 38 근처로
$sc = $samples['all3'];
$sc['domains']['C']['pomp'] = 38.0;
$sc['domains']['C']['band'] = 'mid';
$R = build($i120, 'full120', $sc, $content);
t_ok($R['domains']['C']['near'] !== null && $R['domains']['C']['near']['band'] === 'low', '성실성 38점: 경계 근처 → 낮은 쪽 요약도 함께');

// 하위 요인 편차 ≥ 40
$sc = $samples['all3'];
$sc['facets']['C1']['pomp'] = 90.0;
$sc['facets']['C2']['pomp'] = 40.0;
$R = build($i120, 'full120', $sc, $content);
t_ok($R['domains']['C']['facet_spread'] && !$R['domains']['E']['facet_spread'], '하위 요인 편차 50 → 안내 (편차 없는 외향성은 안내 없음)');

// 간이 검사: 하위 요인 없음
mt_srand(7);
$R = build($i20, 'mini20', sample($i20, static fn($it) => mt_rand(1, 5)), $content);
t_ok(!$R['is_full'] && count($R['domains']) === 5 && $R['domains']['C']['facets'] === [] && !$R['domains']['C']['facet_spread'], '간이 검사: 요인 5개, 하위 요인 없음');

// 레이더 SVG
$svg = big5_radar_svg([['label' => '성실성', 'value' => 80, 'color' => '#2F6F5E'], ['label' => '외향성<b>', 'value' => 20, 'color' => '#D9822B'], ['label' => '친화성', 'value' => 50, 'color' => '#C2547A'], ['label' => '개방성', 'value' => 0, 'color' => '#4C6EB1'], ['label' => '신경성', 'value' => 100, 'color' => '#7A6A9B']]);
t_ok(str_starts_with($svg, '<svg') && substr_count($svg, '<circle') === 5 && str_contains($svg, '외향성&lt;b&gt;'), '레이더 SVG: 점 5개, 글자 이스케이프');
// 9장 그림: 겹친 레이더(두 번째 계열은 점선, 점수 글자는 뺌) · 아령
$pts5 = [['label' => '성실성', 'value' => 80, 'color' => '#2F6F5E'], ['label' => '외향성', 'value' => 20, 'color' => '#D9822B'], ['label' => '친화성', 'value' => 50, 'color' => '#C2547A'], ['label' => '개방성', 'value' => 0, 'color' => '#4C6EB1'], ['label' => '신경성', 'value' => 100, 'color' => '#7A6A9B']];
$svg2 = big5_radar_svg($pts5, 320, [60, 40, 50, 120, -5]);
t_ok(substr_count($svg2, 'class="radar-shape"') === 1 && substr_count($svg2, 'class="radar-shape2"') === 1 && substr_count($svg2, 'radar-dot2') === 5 && !str_contains($svg2, 'radar-value'), '겹친 레이더: 두 계열, 점수 글자 없음');
t_ok(!str_contains(big5_radar_svg($pts5, 320, [1, 2]), 'radar-shape2') && str_contains(big5_radar_svg($pts5), 'radar-value'), '레이더: 개수가 다른 두 번째 계열은 무시, 기본은 그대로');
$db = big5_dumbbell_html(['self' => 50.0, 'others' => 130.0, 'min' => 60.0, 'max' => 130.0, 'by_relation' => ['family' => ['label' => '가족<', 'n' => 1, 'avg' => 60.0]]], '#000', '성실성<b>', true);
t_ok(str_contains($db, 'db-self" style="left:50.0%') && str_contains($db, 'db-others" style="left:100.0%') && str_contains($db, 'db-range" style="left:60.0%;width:40.0%') && str_contains($db, 'db-link" style="left:50.0%;width:50.0%'), '아령: 나·남 점, 잇는 선, 범위 띠 (100 넘는 값은 100)');
t_ok(str_contains($db, 'db-rel-family') && str_contains($db, '>가</span>') && !str_contains($db, '<b>') && !str_contains($db, '가족<"'), '아령: 관계 표식은 첫 글자, 글자 이스케이프');
$dbm = big5_dumbbell_html(['self' => 50.0, 'others' => 60.0, 'min' => 40.0, 'max' => 80.0, 'by_relation' => ['family' => ['label' => '가족', 'n' => 1, 'avg' => 60.0], 'friend' => ['label' => '친구', 'n' => 1, 'avg' => 62.0], 'colleague' => ['label' => '동료', 'n' => 1, 'avg' => 20.0]]], '#000', 'x', true);
t_ok(substr_count($dbm, 'class="db-rel ') === 2 && str_contains($dbm, 'db-rel-family db-rel-friend" style="left:61.0%') && str_contains($dbm, '>가·친</span>'), '아령: 점수가 가까운 관계는 한 표식에 나란히');
$db1 = big5_dumbbell_html(['self' => 50.0, 'others' => 70.0, 'min' => 70.0, 'max' => 70.0, 'by_relation' => ['family' => ['label' => '가족', 'n' => 1, 'avg' => 70.0]]], '#000', 'x');
t_ok(!str_contains($db1, 'db-range') && !str_contains($db1, 'db-rels'), '아령: 평가자 1명이면 범위 띠·관계 표식 없음');
$bar = big5_bar_html(120.0, '#000', 'x');
t_ok(str_contains($bar, 'width:100.0%'), '막대: 100 넘는 값은 100으로');

t_done();
