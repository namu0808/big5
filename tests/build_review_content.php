<?php
/**
 * 해석 문구 검수본 — data/content/*.json 을 리포트 순서대로 펼쳐 data/review_content.md 로 쓴다.
 * 맨 위에 점검 결과(누락·금지 표현)를 둔다. 문구를 고친 뒤 다시 실행한다.
 * 실행: php tests/build_review_content.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/items.php';
require dirname(__DIR__) . '/includes/content_check.php';

$c = big5_content();
$items = big5_items('full120');
$check = big5_content_check($c, array_keys($items['facets']));
$band = ['low' => '낮은 쪽', 'mid' => '가운데', 'high' => '높은 쪽'];
$order = ['C', 'E', 'A', 'O', 'N'];
$name = static fn(string $d): string => (string) ($c['domains'][$d]['name'] ?? $d);

$md = [];
$L = static function (string $s = '') use (&$md): void {
    $md[] = $s;
};
$list = static function (array $xs) use ($L): void {
    foreach ($xs as $x) {
        $L('- ' . $x);
    }
};

$L('# 해석 문구 검수본');
$L();
$L('- 만든 날: ' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i') . ' KST (`php tests/build_review_content.php`)');
$status = [];
foreach (BIG5_CONTENT_FILES as $f) {
    $status[] = "{$f} " . ($c[$f]['status'] ?? '?');
}
$L('- 파일 상태: ' . implode(' · ', $status));
$L('- 점검 결과: ' . ($check['ok'] ? '**통과** — 누락 0건, 금지 표현·유형 이름·백분위·병리 용어 0건' : '**확인 필요** — 아래 표 참고'));
$L('- 검수 방법');
$L('  - 고칠 문장 옆에 메모를 남기거나 이 파일을 직접 고쳐 주세요.');
$L('  - 반영할 때는 `data/content/*.json`을 고치고, 고친 항목의 status를 final로 바꿉니다(PLAN 14장 [문구 수정 반영]).');
$L('  - 원칙: PLAN 6.3. 판정하지 않고 해석하며, 모든 구간에 강점과 주의점을 함께 둡니다. 유형 이름과 순위 표현은 쓰지 않습니다.');
$L();
$L('| 점검 항목 | 기대 | 실제 | 결과 |');
$L('|---|---|---|---|');
foreach ($check['rows'] as $r) {
    $L("| {$r['label']} | {$r['expected']} | {$r['actual']} | " . ($r['ok'] ? '통과' : '**확인**') . ' |');
}
foreach ($check['violations'] as $v) {
    $L("- 위반: {$v['file']} `{$v['path']}` — {$v['rule']} ({$v['fragment']})");
}
$L();

// ── 고정 문구 ──
$fx = $c['fixed'];
$L('## 0. ' . $fx['reading_guide']['title']);
$L();
foreach ($fx['reading_guide']['paragraphs'] as $p) {
    $L($p);
    $L();
}
$L('## 1. ' . $fx['intro']['title']);
$L();
foreach ($fx['intro']['sections'] as $sec) {
    $L('### ' . $sec['heading']);
    $L();
    foreach ($sec['paragraphs'] as $p) {
        $L($p);
        $L();
    }
}
$L('### ' . $fx['intro']['domain_table_title']);
$L();
$L('| 다이얼 | 한 줄 정의 |');
$L('|---|---|');
foreach ($fx['intro']['domain_table'] as $r) {
    $L("| {$r['name']} | {$r['line']} |");
}
$L();
$L('### ' . $fx['intro']['mbti']['heading']);
$L();
$L($fx['intro']['mbti']['paragraph']);
$L();
$t = $fx['intro']['mbti']['table'];
$L('| ' . implode(' | ', $t['head']) . ' |');
$L('|' . str_repeat('---|', count($t['head'])));
foreach ($t['rows'] as $r) {
    $L('| ' . implode(' | ', $r) . ' |');
}
$L();
$L('## 2. ' . $fx['seven_cautions']['title']);
$L();
foreach ($fx['seven_cautions']['items'] as $i => $it) {
    $L(($i + 1) . '. **' . $it['title'] . '** ' . $it['text']);
}
$L();
$L('## 3. ' . $fx['glance']['title']);
$L();
$L('- 구간 안내: ' . $fx['glance']['band_note']);
$L('- ' . $fx['glance']['standout_title'] . ': ' . $fx['glance']['standout_intro']);
$L('- 모두 가운데일 때: ' . $fx['glance']['all_mid_note']);
$L();

// ── 4. 다이얼별 ──
$L('## 4. ' . $fx['detail']['title']);
$L();
$L('- 경계 근처: ' . $fx['detail']['near_boundary_note']);
$L('- 하위 요인 편차: ' . $fx['detail']['facet_spread_note']);
$L('- 간이 검사: ' . $fx['detail']['mini_no_facets']);
$L();
foreach ($order as $i => $d) {
    $ab = $fx['domain_about'][$d];
    $L('### 4-' . ($i + 1) . ' ' . $name($d) . ' — ' . $ab['tagline']);
    $L();
    $L('**이 다이얼은 무엇을 재나** ' . $ab['about']);
    $L();
    foreach (['high', 'mid', 'low'] as $b) {
        $e = $c['domains'][$d][$b];
        $L("#### {$name($d)} · {$band[$b]}");
        $L();
        $L('> ' . $e['summary_line']);
        $L();
        foreach ($e['body'] as $p) {
            $L($p);
            $L();
        }
        $L('**강점**');
        $list($e['strengths']);
        $L();
        $L('**주의할 점**');
        $list($e['cautions']);
        $L();
        $L('**한 줄로 남기면** ' . $e['closing']);
        if (!empty($e['help_line'])) {
            $L();
            $L('**도움 받을 곳** ' . $e['help_line'] . ' _(운영 전 번호 재확인)_');
        }
        $L();
    }
    $L("#### {$name($d)} · 하위 요인");
    $L();
    $L('| 코드 | 이름 | 정의 | 낮은 쪽 | 가운데 | 높은 쪽 |');
    $L('|---|---|---|---|---|---|');
    foreach ($c['facets']['facets'] as $code => $f) {
        if ($code[0] === $d) {
            $L("| {$code} | {$f['name']} | {$f['definition']} | {$f['low']} | {$f['mid']} | {$f['high']} |");
        }
    }
    $L();
    $L("#### {$name($d)} · 흔한 오해");
    $L();
    foreach ($ab['misconceptions'] as $m) {
        $L('- **' . $m['title'] . '** ' . $m['text']);
    }
    $L();
}

// ── 5. 조합 ──
$L('## 5. ' . $fx['combos']['title']);
$L();
$L($fx['combos']['intro']);
$L();
foreach ($c['combos']['rules'] as $r) {
    $cond = [];
    foreach ($r['when'] as $d => $b) {
        $cond[] = $name($d) . ' ' . $band[$b];
    }
    $L('### ' . $r['title']);
    $L();
    $L('_조건: ' . implode(' × ', $cond) . ' · ' . ($r['source'] === 'plan' ? 'PLAN 예시' : '보충') . '_');
    $L();
    foreach ($r['body'] as $p) {
        $L($p);
        $L();
    }
}

// ── 6. 일터 ──
$w = $c['work'];
$L('## 6. ' . $fx['work']['title']);
$L();
$L($fx['work']['intro']);
$L();
foreach (['style', 'team', 'pressure', 'leader'] as $sec) {
    $L('### ' . $w[$sec]['title']);
    $L();
    if (!empty($w[$sec]['intro'])) {
        $L($w[$sec]['intro']);
        $L();
    }
    foreach ($w[$sec]['domains'] as $d) {
        foreach (['high', 'mid', 'low'] as $b) {
            $L("- **{$name($d)} · {$band[$b]}** " . $w[$sec]['by'][$d][$b]);
        }
    }
    $L();
}
$L('### ' . $w['energy']['title']);
$L();
$L($w['energy']['note']);
$L();
$L('| 다이얼 · 구간 | ' . $w['energy']['gain_label'] . ' | ' . $w['energy']['drain_label'] . ' |');
$L('|---|---|---|');
foreach ($order as $d) {
    foreach (['high', 'mid', 'low'] as $b) {
        $e = $w['energy']['by'][$d][$b];
        $L("| {$name($d)} · {$band[$b]} | " . implode(' / ', $e['gain']) . ' | ' . implode(' / ', $e['drain']) . ' |');
    }
}
$L();

// ── 7. 관계 ──
$rl = $c['relations'];
$L('## 7. ' . $fx['relations']['title']);
$L();
$L($fx['relations']['intro']);
$L();
foreach (['close', 'conflict', 'energy'] as $sec) {
    $L('### ' . $rl[$sec]['title']);
    $L();
    foreach ($rl[$sec]['domains'] as $d) {
        foreach (['high', 'mid', 'low'] as $b) {
            $L("- **{$name($d)} · {$band[$b]}** " . $rl[$sec]['by'][$d][$b]);
        }
    }
    $L();
}

// ── 8. 성장 ──
$L('## 8. ' . $fx['growth']['title']);
$L();
foreach ($fx['growth']['paragraphs'] as $p) {
    $L($p);
    $L();
}
$L($fx['growth']['how_to']);
$L();
foreach ($order as $d) {
    foreach (['grow', 'shadow'] as $dir) {
        $g = $c['growth']['domains'][$d][$dir];
        $L('### ' . $g['label']);
        $L();
        foreach ($g['actions'] as $a) {
            $L('- ' . $a['text'] . " `{$a['key']}`");
        }
        $L();
    }
}

// ── 9·10·부록 ──
$L('## 9. ' . $fx['others']['title']);
$L();
$list($fx['others']['guide']);
$L('- 평가자 1명일 때 표기: ' . $fx['others']['single_rater_label'] . ' / 차이 표시: ' . $fx['others']['gap_label'] . ' (' . $fx['others']['gap_note'] . ')');
$L();
$ox = $fx['others'];
$L('### 한 줄 요약·묶음');
$L();
$L('- 요약 문장: ' . $ox['summary']['prefix'] . $ox['summary']['near'] . ', ' . $ox['summary']['some'] . ', ' . $ox['summary']['far'] . $ox['summary']['suffix'] . ' / 모두 비슷할 때: ' . $ox['summary']['all_near']);
$L('- 묶음 이름: ' . implode(' / ', $ox['level_labels']) . ' · 차이 표기: ' . implode(' / ', $ox['direction_labels']) . ' · 구간이 다를 때: ' . $ox['band_cross']);
$L('- 그림 범례: ' . implode(' / ', $ox['legend']));
$L();
$L('### ' . $ox['shape']['title']);
$L();
foreach (['order_same', 'top_same', 'top_diff', 'bottom_same', 'bottom_diff', 'tilt_higher', 'tilt_lower', 'standout'] as $k) {
    $L('- `' . $k . '` ' . $ox['shape'][$k]);
}
$L();
$L('### ' . $ox['far']['title']);
$L();
$L($ox['far']['intro']);
$L();
foreach (BIG5_DOMAINS as $d) {
    foreach (['higher', 'lower'] as $dir) {
        $L('**' . $c['domains'][$d]['name'] . ' — ' . $ox['direction_titles'][$dir] . '**');
        $L();
        $list($ox['direction'][$d][$dir]);
        $L();
    }
    $L('- ' . $ox['far']['visibility_label'] . ': ' . $ox['visibility'][$d]);
    $L('- 물어볼 질문: ' . implode(' / ', $ox['talk']['questions'][$d]));
    $L();
}
$L('### 남들끼리의 일치·관계별 차이');
$L();
$L('- 남들끼리 비슷(' . $ox['pattern']['consistent_tag'] . '): ' . $ox['pattern']['consistent']);
$L('- 남들끼리 갈림(' . $ox['pattern']['split_tag'] . '): ' . $ox['pattern']['split']);
$L('- ' . $ox['pattern']['hidden_title'] . ': ' . $ox['pattern']['hidden_split'] . ' (' . $ox['pattern']['range'] . ')');
$L('- ' . $ox['relation']['title'] . ': ' . $ox['relation']['intro'] . ' / ' . $ox['relation']['note']);
$L('- 다르게 보는 곳이 없을 때: ' . $ox['agree']);
$L();
$L('### ' . $ox['trust']['title']);
$L();
foreach (['single', 'count', 'mini', 'mixed', 'excluded', 'close'] as $k) {
    $L('- `' . $k . '` ' . $ox['trust'][$k]);
}
$L();
$L('### ' . $ox['talk']['title']);
$L();
$list($ox['talk']['intro']);
$L('- 다르게 보는 곳이 없을 때 질문: ' . implode(' / ', $ox['talk']['general']));
$L();
$L('## 10. ' . $fx['retest']['title']);
$L();
$list($fx['retest']['guide']);
$L();
$ap = $fx['appendix'];
$L('## 11. ' . $ap['title']);
$L();
$L('### ' . $ap['scoring']['title']);
$L();
foreach ($ap['scoring']['paragraphs'] as $p) {
    $L($p);
    $L();
}
$L('### ' . $ap['glossary_title']);
$L();
foreach ($ap['glossary'] as $g) {
    $L('- **' . $g['term'] . '** ' . $g['text']);
}
$L();
$L('### ' . $ap['reading_title']);
$L();
foreach ($ap['reading'] as $r) {
    $L('- ' . $r['title'] . ' — ' . $r['note']);
}
$L('- ' . $ap['reading_web']);
$L();
$L('### ' . $ap['sources_title']);
$L();
$list($ap['sources']);
$L();
$L('---');
$L();
$L('- 도움 안내 문구: ' . $fx['help']['line'] . ' _(' . $fx['help']['check_before_launch'] . ')_');
$L('- 인쇄 꼬리말: ' . $fx['print_footer']);

file_put_contents(dirname(__DIR__) . '/data/review_content.md', implode("\n", $md) . "\n");
echo 'review_content.md 작성 — ' . ($check['ok'] ? '점검 통과' : '점검 확인 필요') . ', ' . mb_strlen(implode('', $md)) . "자\n";
