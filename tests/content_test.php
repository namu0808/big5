<?php
/**
 * 4단계 검증 — 해석 문구 누락 0, 금지 표현·유형 이름·백분위·병리 용어 0, 강점/주의 짝, 도움 안내.
 * 검사 자체가 위반을 잡는지도 가짜 문구로 확인한다.
 * 실행: php tests/content_test.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/items.php';
require dirname(__DIR__) . '/includes/content_check.php';
require __DIR__ . '/lib.php';

$content = big5_content();
foreach (BIG5_CONTENT_FILES as $f) {
    t_ok(is_array($content[$f]), "{$f}.json 읽기");
}
$items = big5_items('full120');
$r = big5_content_check($content, array_keys($items['facets']));
foreach ($r['rows'] as $row) {
    t_ok($row['ok'], "{$row['label']} — 기대 {$row['expected']}, 실제 {$row['actual']}");
}
t_ok($r['missing'] === [], '누락 0건' . ($r['missing'] ? ': ' . implode(', ', $r['missing']) : ''));
t_ok($r['violations'] === [], '문구 원칙 위반 0건');
foreach ($r['violations'] as $v) {
    echo "        - {$v['file']} {$v['path']}: {$v['rule']} ({$v['fragment']})\n";
}

// ── 검사가 실제로 잡는가 ──
$cases = [
    ['다양한 경험을 통해 성장합니다.', '금지 표현 다양한'],
    ['이 점은 매우 중요합니다.', '금지 표현 중요한'],
    ['마음이 무너질 때가 있어요.', '금지 표현 무너지다'],
    ['틀에 갇혀 있다고 느낍니다.', '금지 표현 갇히다'],
    ['당신은 성실형입니다.', '유형 이름'],
    ['리더 타입이에요.', '유형 이름'],
    ['상위 10%에 속합니다.', '백분위·순위 표현'],
    ['100명 가운데 55번째쯤입니다.', '백분위·순위 표현'],
    ['불안 장애가 의심됩니다.', '병리 용어'],
];
foreach ($cases as [$text, $rule]) {
    $found = array_column(big5_text_violations($text), 0);
    t_ok(in_array($rule, $found, true), "검사가 잡는다: \"{$text}\" → {$rule}");
}
$clean = ['유형이 아니라 다이얼입니다.', '하위 요인 여섯 개를 봅니다.', '모형의 이름은 빅파이브예요.', '균형이 잡혀요.'];
foreach ($clean as $text) {
    t_ok(big5_text_violations($text) === [], "오탐 없음: \"{$text}\"");
}
// 누락 검사가 잡는가
$broken = $content;
unset($broken['domains']['N']['high']['help_line'], $broken['facets']['facets']['C3']);
$broken['domains']['E']['low']['cautions'] = [];
$rb = big5_content_check($broken, array_keys($items['facets']));
$failed = array_column(array_filter($rb['rows'], static fn($x) => !$x['ok']), 'label');
t_ok(!$rb['ok'], '일부러 망가뜨린 문구 → 점검 실패');
t_ok(in_array('domains.N.high.help_line', $rb['missing'], true), '지운 도움 안내를 누락으로 잡는다');
t_ok(in_array('하위 요인 30 × (정의 + 구간 3)', $failed, true), '지운 하위 요인(C3)을 잡는다');
t_ok(in_array('domains.E.low (강점 3~4 · 주의점 2~3)', $rb['missing'], true), '비운 주의점(E.low)을 잡는다');

t_done();
