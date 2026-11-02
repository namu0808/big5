<?php
/**
 * 문항 JSON 빌드 — data/items_120.json, data/items_20.json, data/review_items.md
 *
 * 원칙: 원문 키(번호·하위 요인·방향·영문)는 data/source/master_*.csv 에서만 가져온다(기계적 복사).
 *       번역은 data/translate/*.csv 에서만 가져온다. 이 스크립트는 문항 내용을 만들거나 고치지 않는다.
 * 실행: php tests/build_items.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);

/** '#' 머리 줄(status 등)을 돌려주고, 나머지를 연관 배열로 읽는다 */
function read_csv_meta(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "파일 없음: {$path}\n");
        exit(1);
    }
    $fh = fopen($path, 'r');
    $meta = [];
    $header = null;
    $rows = [];
    while (($r = fgetcsv($fh, null, ',', '"', '')) !== false) {
        if ($r === [null]) {
            continue;
        }
        if (is_string($r[0]) && str_starts_with($r[0], '#')) {
            $meta[] = trim(ltrim(implode(',', $r), '# '));
            continue;
        }
        if ($header === null) {
            $header = $r;
            continue;
        }
        $rows[] = array_combine($header, array_pad($r, count($header), ''));
    }
    fclose($fh);
    return [$meta, $rows];
}

/** 머리 줄에서 status 값 (provisional|final|draft) */
function meta_status(array $meta): string
{
    foreach ($meta as $m) {
        if (preg_match('/status:\s*(\w+)/', $m, $mm)) {
            return $mm[1];
        }
    }
    return 'unknown';
}

/** 요인·하위 요인 이름 — 한국어는 PLAN 2.5, 영문은 [B] results_en/*.ts 에서 추출 */
function names(string $root): array
{
    $ko = [
        'N' => ['신경성', ['불안', '분노', '우울', '자의식', '충동성', '취약성']],
        'E' => ['외향성', ['따뜻함', '사교성', '주장성', '활동성', '자극 추구', '긍정 정서']],
        'O' => ['개방성', ['상상', '심미성', '감정 개방', '행동 개방', '사고 개방', '가치 개방']],
        'A' => ['친화성', ['신뢰', '솔직함', '이타성', '협조', '겸손', '온정']],
        'C' => ['성실성', ['유능감', '질서', '의무감', '성취 추구', '자기 규율', '신중함']],
    ];
    $en = [];
    foreach (glob($root . '/data/source/bigfive_org/results_en/*.ts') as $f) {
        $s = (string) file_get_contents($f);
        if (!preg_match("/domain:\\s*'(\\w)'/", $s, $d)) {
            continue;
        }
        preg_match("/^\\s*title:\\s*'([^']+)'/m", $s, $t);
        preg_match_all("/facet:\\s*(\\d+),\\s*title:\\s*'([^']+)'/", $s, $fm, PREG_SET_ORDER);
        $en[$d[1]] = ['title' => $t[1] ?? '', 'facets' => array_column($fm, 2, 1)];
    }
    $domains = [];
    $facets = [];
    foreach (['N', 'E', 'O', 'A', 'C'] as $code) {
        $domains[$code] = ['name' => $ko[$code][0], 'name_en' => $en[$code]['title'] ?? ''];
        foreach ($ko[$code][1] as $i => $name) {
            $facets[$code . ($i + 1)] = ['name' => $name, 'name_en' => $en[$code]['facets'][(string) ($i + 1)] ?? ''];
        }
    }
    return [$domains, $facets];
}

$scale = ['전혀 아니다', '아닌 편이다', '보통이다', '그런 편이다', '매우 그렇다'];
[$domains, $facets] = names($root);

// ── 120문항 ──
[$m120meta, $m120] = read_csv_meta($root . '/data/source/master_120.csv');
[$t120meta, $t120] = read_csv_meta($root . '/data/translate/items_120_ko.csv');
$tBy = array_column($t120, null, 'item_no_120');
$items120 = [];
$errors = [];
foreach ($m120 as $r) {
    $no = (int) $r['item_no_120'];
    $t = $tBy[(string) $no] ?? null;
    if ($t === null || trim($t['text_ko']) === '' || trim($t['text_ko_other']) === '') {
        $errors[] = "120문항 {$no}번 번역 없음";
        continue;
    }
    $items120[] = [
        'no' => $no,
        'facet' => $r['facet_code'],
        'keyed' => $r['keyed'],
        'text_en' => $r['text_en'],
        'text_ko' => trim($t['text_ko']),
        'text_ko_other' => trim($t['text_ko_other']),
    ];
}
$json120 = [
    'version' => 'ipip-neo-120-ko-v0.1',
    'instrument' => 'IPIP-NEO-120 (Johnson, 2014)',
    'status' => 'draft',                             // 번역 검수 전
    'source_status' => meta_status($m120meta),       // provisional: 원논문 대조 대기
    'source_note' => implode(' / ', $m120meta),
    'scale' => $scale,
    'domains' => $domains,
    'facets' => $facets,
    'items' => $items120,
];

// ── 20문항 ──
[$m20meta, $m20] = read_csv_meta($root . '/data/source/master_20.csv');
[, $t20] = read_csv_meta($root . '/data/translate/items_20_ko_other.csv');
$t20By = array_column($t20, null, 'item_no_20');
$items20 = [];
foreach ($m20 as $r) {
    $no = (int) $r['item_no_20'];
    $other = trim($t20By[(string) $no]['text_ko_other'] ?? '');
    if ($other === '') {
        $errors[] = "20문항 {$no}번 타인용 문장 없음";
    }
    $items20[] = [
        'no' => $no,
        'domain' => $r['factor'],
        'keyed' => $r['keyed'],
        'text_en' => $r['text_en'],                  // [D] 확보 전에는 빈칸
        'text_ko' => $r['text_ko'],                  // 원고 부록 A
        'text_ko_other' => $other,
    ];
}
$json20 = [
    'version' => 'mini-ipip-20-ko-v0.1',
    'instrument' => 'Mini-IPIP (Donnellan et al., 2006)',
    'status' => 'draft',
    'source_status' => meta_status($m20meta),
    'source_note' => implode(' / ', $m20meta),
    'scale' => $scale,
    'domains' => $domains,
    'items' => $items20,
];

// ── 정식·간이 공통 문항: 문장은 간이 검사(원고 부록 A) 하나를 기준으로 통일한다 ──
[$sharedMeta, $shared] = read_csv_meta($root . '/data/translate/shared_items.csv');
$i120By = [];
foreach ($items120 as $k => $it) {
    $i120By[$it['no']] = $k;
}
$i20By = [];
foreach ($items20 as $k => $it) {
    $i20By[$it['no']] = $k;
}
foreach ($shared as $sh) {
    $n20 = (int) $sh['item_no_20'];
    $n120 = (int) $sh['item_no_120'];
    if (!isset($i20By[$n20], $i120By[$n120])) {
        $errors[] = "공통 문항 {$n20}↔{$n120}: 번호 없음";
        continue;
    }
    $a = &$items120[$i120By[$n120]];
    $b = &$items20[$i20By[$n20]];
    if ($a['text_en'] !== $sh['text_en_120']) {
        $errors[] = "공통 문항 {$n20}↔{$n120}: 120문항 영문이 master 와 다름";
    }
    if ($b['text_en'] !== '' && $b['text_en'] !== $a['text_en']) {
        $errors[] = "공통 문항 {$n20}↔{$n120}: 영문이 다름 (간이 \"{$b['text_en']}\" / 정식 \"{$a['text_en']}\")";
    }
    if ($a['keyed'] !== $b['keyed']) {
        $errors[] = "공통 문항 {$n20}↔{$n120}: 채점 방향이 다름 ({$b['keyed']} / {$a['keyed']})";
    }
    if ($a['text_ko'] !== $b['text_ko'] || $a['text_ko_other'] !== $b['text_ko_other']) {
        $errors[] = "공통 문항 {$n20}↔{$n120}: 번역 파일의 120문항 문장이 간이 검사와 다름 — data/translate/items_120_ko.csv 를 부록 A 문장으로 맞출 것";
    }
    $a['mini20_no'] = $n20;
    $b['full120_no'] = $n120;
    unset($a, $b);
}
$json120['items'] = $items120;
$json20['items'] = $items20;
$json120['shared_status'] = meta_status($sharedMeta);
$json20['shared_status'] = meta_status($sharedMeta);

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
file_put_contents($root . '/data/items_120.json', json_encode($json120, $flags) . "\n");
file_put_contents($root . '/data/items_20.json', json_encode($json20, $flags) . "\n");

// ── 검수표 ──
/** 검수 때 방향을 특히 확인할 문장 표시: 부정 표현, 가치 개방(O6) */
function review_marks(array $it): string
{
    $m = [];
    if (preg_match('/않|없|못|드물|드문|어려워|싫어|피한|피하/u', $it['text_ko'])) {
        $m[] = '부정·회피 표현';
    }
    if (($it['facet'] ?? '') === 'O6') {
        $m[] = '가치 개방(중립성)';
    }
    if (isset($it['mini20_no'])) {
        $m[] = '간이 검사 ' . $it['mini20_no'] . '번과 같은 문장';
    }
    if (isset($it['full120_no'])) {
        $m[] = '정식 검사 ' . $it['full120_no'] . '번과 같은 문장';
    }
    return implode(', ', $m);
}

$md = [];
$md[] = '# 문항 번역 검수표';
$md[] = '';
$md[] = '- 만든 날: ' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i') . ' KST (`php tests/build_items.php`)';
$md[] = '- 원문 키 출처: `data/source/master_120.csv` — ' . implode(' / ', $m120meta);
$md[] = '- 번역 초안: `data/translate/items_120_ko.csv` (status: ' . meta_status($t120meta) . ')';
$md[] = '- 검수 방법: 번역이 원문과 같은 방향인지 봅니다. **방향** 열이 `−`인 문항은 "그렇다"고 답할수록 그 하위 요인 점수가 낮아집니다. 고칠 곳은 `data/translate/*.csv`에 적거나 이 표에 메모해 주세요.';
$md[] = '- 정식·간이 공통 문항: `data/translate/shared_items.csv` (' . implode(' / ', $sharedMeta) . ') — 문장은 원고 부록 A 기준으로 통일';
$md[] = '- 확인 표시: `부정·회피 표현` = 문장에 부정어·회피어가 있어 방향을 한 번 더 볼 문항, `가치 개방(중립성)` = 정치적으로 읽힐 수 있는 하위 요인';
$md[] = '';
$md[] = '## IPIP-NEO-120 (120문항)';
$md[] = '';
$md[] = '| 번호 | 하위 요인 | 방향 | 원문 | 본인용 번역 | 타인 평가용 | 확인 |';
$md[] = '|---:|---|:---:|---|---|---|---|';
foreach ($items120 as $it) {
    $f = $it['facet'];
    $md[] = sprintf(
        '| %d | %s %s | %s | %s | %s | %s | %s |',
        $it['no'],
        $f,
        $facets[$f]['name'],
        $it['keyed'] === '-' ? '−' : '+',
        $it['text_en'],
        $it['text_ko'],
        $it['text_ko_other'],
        review_marks($it)
    );
}
$md[] = '';
$md[] = '## Mini-IPIP (20문항)';
$md[] = '';
$md[] = '- 본인용 문장과 실시 번호는 원고 부록 A, 요인·방향·영문은 [D] IPIP 공식 Mini-IPIP 페이지입니다. (' . implode(' / ', $m20meta) . ')';
$md[] = '';
$md[] = '| 번호 | 요인 | 방향 | 원문 | 본인용(부록 A) | 타인 평가용 | 확인 |';
$md[] = '|---:|---|:---:|---|---|---|---|';
foreach ($items20 as $it) {
    $md[] = sprintf(
        '| %d | %s %s | %s | %s | %s | %s | %s |',
        $it['no'],
        $it['domain'],
        $domains[$it['domain']]['name'],
        $it['keyed'] === '-' ? '−' : '+',
        $it['text_en'] !== '' ? $it['text_en'] : '(D 확보 후)',
        $it['text_ko'],
        $it['text_ko_other'],
        review_marks($it)
    );
}
file_put_contents($root . '/data/review_items.md', implode("\n", $md) . "\n");

echo 'items_120.json ' . count($items120) . "문항, items_20.json " . count($items20) . "문항, review_items.md 작성\n";
