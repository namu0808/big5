<?php
/**
 * 원문 자료 추출·대조 도구 — data/source/ 의 자료를 같은 열 구조로 추출하고 대조한다.
 * (문항을 만들거나 고치지 않는다. 자료에 있는 값을 옮기고 비교만 한다)
 *
 * 하는 일
 *  1) [B] bigfive_org/{en,ko}/questions.json → extracted_B_bigfiveorg.csv
 *  2) 원고 부록 A(간이 자가 진단 20문항) → extracted_appendixA_mini.csv
 *  3) 있으면 extracted_A_johnson.csv · extracted_C_uga.csv · extracted_D_mini.csv 를 읽어 대조
 *  4) 검증 결과를 화면에 출력 (보고서 COMPARISON_REPORT.md 의 근거)
 *  5) --write-master: 확정 기준 파일 master_120.csv · master_20.csv 작성
 *     - 120문항: A(원논문) 추출본이 있으면 A 가 기준(status: final 후보), 없으면 B(status: provisional)
 *     - 20문항: 순서·방향·한국어는 원고 부록 A. text_en 은 D 추출본이 있을 때만 채운다(없으면 빈칸, provisional)
 *     (어느 경우든 대조 불일치가 있으면 기준 자료 값을 쓰고, 화면에 고친 목록을 남긴다)
 *
 * 실행: php tests/source_compare.php [--write-master]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$src = $root . '/data/source';
$fail = 0;

/** CSV 쓰기 (UTF-8, 첫 줄 머리글) */
function csv_write(string $path, array $header, array $rows): void
{
    $fh = fopen($path, 'w');
    fputcsv($fh, $header, ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($fh, array_map(static fn($k) => $r[$k] ?? '', $header), ',', '"', '');
    }
    fclose($fh);
}

/** CSV 읽기 → 연관 배열 목록. 없으면 null */
function csv_read(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $fh = fopen($path, 'r');
    do {
        $header = fgetcsv($fh, null, ',', '"', '');
    } while ($header !== false && isset($header[0]) && is_string($header[0]) && str_starts_with($header[0], '#'));
    $rows = [];
    while (($r = fgetcsv($fh, null, ',', '"', '')) !== false) {
        if ($r === [null] || (is_string($r[0] ?? null) && str_starts_with((string) $r[0], '#'))) {
            continue;
        }
        $rows[] = array_combine($header, array_pad($r, count($header), ''));
    }
    fclose($fh);
    return $rows;
}

/** 문구 비교용 정규화 — 대소문자·구두점·공백 차이 무시 */
function norm_text(string $s): string
{
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

function line(string $s = ''): void
{
    echo $s, "\n";
}

function check(bool $cond, string $name, int &$fail): void
{
    echo ($cond ? '  통과  ' : '  불일치 ') . $name . "\n";
    if (!$cond) {
        $fail++;
    }
}

/** 120문항 자료 하나의 구조 점검 (번호·하위 요인 4문항·요인 24문항·역채점 수) */
function structure_report(string $label, array $rows, int &$fail): void
{
    line("== {$label} 구조 점검");
    $nos = array_map(static fn($r) => (int) $r['item_no_120'], $rows);
    $dup = array_keys(array_filter(array_count_values($nos), static fn($c) => $c > 1));
    $missing = array_values(array_diff(range(1, 120), $nos));
    check(count($rows) === 120, '문항 수 120 (실제 ' . count($rows) . ')', $fail);
    check($dup === [], '번호 중복 없음' . ($dup ? ' — 중복: ' . implode(',', $dup) : ''), $fail);
    check($missing === [], '번호 누락 없음' . ($missing ? ' — 누락: ' . implode(',', $missing) : ''), $fail);
    $facet = array_count_values(array_map(static fn($r) => $r['facet_code'], $rows));
    ksort($facet);
    $bad = array_filter($facet, static fn($c) => $c !== 4);
    check(count($facet) === 30 && $bad === [], '하위 요인 30개 × 4문항' . ($bad ? ' — 어긋남: ' . json_encode($bad) : ''), $fail);
    $dom = array_count_values(array_map(static fn($r) => $r['facet_code'][0], $rows));
    ksort($dom);
    $badD = array_filter($dom, static fn($c) => $c !== 24);
    check(count($dom) === 5 && $badD === [], '요인 5개 × 24문항 ' . json_encode($dom), $fail);
    $minus = count(array_filter($rows, static fn($r) => $r['keyed'] === '-'));
    line("  정보  역채점 문항 총 {$minus}개, 정채점 " . (120 - $minus) . '개');
    $perDom = [];
    foreach ($rows as $r) {
        if ($r['keyed'] === '-') {
            $perDom[$r['facet_code'][0]] = ($perDom[$r['facet_code'][0]] ?? 0) + 1;
        }
    }
    ksort($perDom);
    line('  정보  요인별 역채점 수 ' . json_encode($perDom));
    $texts = array_count_values(array_map(static fn($r) => norm_text($r['text_en']), $rows));
    $dupT = array_keys(array_filter($texts, static fn($c) => $c > 1));
    check($dupT === [], '영문 문구 중복 없음' . ($dupT ? ' — ' . implode(' / ', $dupT) : ''), $fail);
    line();
}

// ── 1) [B] 추출 ─────────────────────────────────────────────
$bEn = $src . '/bigfive_org/en/questions.json';
$bKo = $src . '/bigfive_org/ko/questions.json';
$B = null;
if (is_file($bEn)) {
    $en = json_decode((string) file_get_contents($bEn), true, 512, JSON_THROW_ON_ERROR);
    $ko = is_file($bKo) ? json_decode((string) file_get_contents($bKo), true, 512, JSON_THROW_ON_ERROR) : [];
    $koById = [];
    foreach ($ko as $q) {
        $koById[$q['id']] = $q['text'];
    }
    $B = [];
    foreach ($en as $i => $q) {
        $B[] = [
            'item_no_120' => (string) ($i + 1),        // 패키지의 실시 순서(getItems 의 num)
            'facet_code' => $q['domain'] . $q['facet'],
            'keyed' => $q['keyed'] === 'plus' ? '+' : ($q['keyed'] === 'minus' ? '-' : '?'),
            'text_en' => $q['text'],
            'text_ko_ref' => $koById[$q['id']] ?? '',
            'source_id' => $q['id'],
        ];
    }
    csv_write($src . '/extracted_B_bigfiveorg.csv', ['item_no_120', 'facet_code', 'keyed', 'text_en', 'text_ko_ref', 'source_id'], $B);
    line('[B] extracted_B_bigfiveorg.csv 작성 (' . count($B) . '행)');
    $koMissing = count(array_filter($B, static fn($r) => $r['text_ko_ref'] === ''));
    line("    한국어 참고 번역 없는 문항: {$koMissing}");
} else {
    line('[B] 자료 없음 — 건너뜀');
}

// ── 2) 원고 부록 A 추출 ──────────────────────────────────────
$ms = $root . '/빅파이브 Big 5.md';
$AP = null;
if (is_file($ms)) {
    $text = (string) file_get_contents($ms);
    $start = strpos($text, '### A. 간이 자가 진단');
    $end = strpos($text, '### B. ', $start ?: 0);
    $sec = ($start !== false && $end !== false) ? substr($text, $start, $end - $start) : '';
    preg_match_all('/^\| (\d{1,2}) \| (.+?) \| \|$/mu', $sec, $m, PREG_SET_ORDER);
    preg_match('/뒤집는 문항: \*\*([\d, ]+)\*\*/u', $sec, $rm);
    $reversed = isset($rm[1]) ? array_map('intval', preg_split('/\s*,\s*/', trim($rm[1]))) : [];
    $factorMap = [];
    foreach (['외향성' => 'E', '친화성' => 'A', '성실성' => 'C', '신경성' => 'N', '개방성' => 'O'] as $ko => $code) {
        if (preg_match('/^\| ' . $ko . ' \| ([^|]+) \|/mu', $sec, $fm)) {
            preg_match_all('/\d+/', $fm[1], $nums);
            foreach ($nums[0] as $n) {
                $factorMap[(int) $n] = $code;
            }
        }
    }
    $AP = [];
    foreach ($m as $row) {
        $no = (int) $row[1];
        $AP[] = [
            'item_no_20' => (string) $no,
            'factor' => $factorMap[$no] ?? '?',
            'keyed' => in_array($no, $reversed, true) ? '-' : '+',
            'text_ko' => trim($row[2]),
        ];
    }
    csv_write($src . '/extracted_appendixA_mini.csv', ['item_no_20', 'factor', 'keyed', 'text_ko'], $AP);
    line('[부록 A] extracted_appendixA_mini.csv 작성 (' . count($AP) . '행), 뒤집는 문항: ' . implode(',', $reversed));
}
line();

// ── 3) 구조 점검과 대조 ─────────────────────────────────────
$A = csv_read($src . '/extracted_A_johnson.csv');
$C = csv_read($src . '/extracted_C_uga.csv');
$D = csv_read($src . '/extracted_D_mini.csv');

if ($B !== null) {
    structure_report('[B] bigfive-org', $B, $fail);
    // Johnson 실시 순서 규칙(N,E,O,A,C 반복, 5문항마다 하위 요인 번호 증가)과 맞는지 — 정보용
    $order = ['N', 'E', 'O', 'A', 'C'];
    $off = 0;
    foreach ($B as $r) {
        $i = (int) $r['item_no_120'] - 1;
        $expect = $order[$i % 5] . ((intdiv($i, 5) % 6) + 1);
        if ($r['facet_code'] !== $expect) {
            $off++;
        }
    }
    line("  정보  번호 배열이 'N1,E1,O1,A1,C1,N2…' 순환 규칙과 다른 문항: {$off}개 (A 와 대조해야 확정)");
    line();
}

foreach (['A' => $A, 'C' => $C] as $k => $rows) {
    if ($rows === null) {
        line("[{$k}] 추출 파일 없음 — 대조 불가");
    } elseif ($k === 'A') {
        structure_report('[A] Johnson (2014)', $rows, $fail);
    }
}

if ($A !== null) {
    $aByNo = [];
    foreach ($A as $r) {
        $aByNo[(int) $r['item_no_120']] = $r;
    }
    foreach (['B' => $B, 'C' => $C] as $k => $rows) {
        if ($rows === null) {
            continue;
        }
        line("== A 기준 [{$k}] 대조");
        $mis = ['facet' => 0, 'keyed' => 0, 'text' => 0];
        foreach ($rows as $r) {
            $a = $aByNo[(int) $r['item_no_120']] ?? null;
            if ($a === null) {
                line("  불일치 {$r['item_no_120']}번: A 에 없음");
                $fail++;
                continue;
            }
            foreach (['facet' => 'facet_code', 'keyed' => 'keyed'] as $label => $col) {
                if (($r[$col] ?? '') !== '' && $r[$col] !== $a[$col]) {
                    $mis[$label]++;
                    line("  불일치 {$r['item_no_120']}번 {$label}: A={$a[$col]} {$k}={$r[$col]}");
                }
            }
            if (($r['text_en'] ?? '') !== '' && norm_text($r['text_en']) !== norm_text($a['text_en'])) {
                $mis['text']++;
                line("  불일치 {$r['item_no_120']}번 문구: A=\"{$a['text_en']}\" {$k}=\"{$r['text_en']}\"");
            }
        }
        $fail += array_sum($mis);
        line('  합계 ' . json_encode($mis));
        line();
    }
}

if ($D === null) {
    line('[D] 추출 파일 없음 — 20문항 기준 대조 불가');
}
if ($AP !== null) {
    line('== [부록 A] 20문항 내부 점검');
    $f = array_count_values(array_map(static fn($r) => $r['factor'], $AP));
    ksort($f);
    check(count($AP) === 20, '문항 20개', $fail);
    check(!isset($f['?']) && count($f) === 5 && array_unique(array_values($f)) === [4], '요인 5개 × 4문항 ' . json_encode($f), $fail);
    $minus = array_map(static fn($r) => (int) $r['item_no_20'], array_filter($AP, static fn($r) => $r['keyed'] === '-'));
    check(array_values($minus) === [6, 7, 8, 9, 10, 15, 16, 17, 18, 19, 20], '역채점 번호 6,7,8,9,10,15,16,17,18,19,20', $fail);
    $order = ['E', 'A', 'C', 'N', 'O'];
    $off = 0;
    foreach ($AP as $r) {
        if ($r['factor'] !== $order[((int) $r['item_no_20'] - 1) % 5]) {
            $off++;
        }
    }
    check($off === 0, "실시 순서 E,A,C,N,O 반복 (어긋남 {$off})", $fail);
    if ($D !== null) {
        line('== D 기준 [부록 A] 대조');
        foreach ($D as $i => $d) {
            $ap = $AP[$i] ?? null;
            $same = $ap !== null && $ap['factor'] === $d['factor'] && $ap['keyed'] === $d['keyed'];
            check($same, "{$d['item_no_20']}번 요인·방향 (D={$d['factor']}{$d['keyed']} 부록A=" . ($ap ? $ap['factor'] . $ap['keyed'] : '없음') . ')', $fail);
        }
    }
    line();
}

// ── 4) [A'] IPIP 공식 사이트 120문항 페이지 (Johnson, 2014) — 하위 요인·방향·문구 대조 ──
// 페이지는 하위 요인마다 4문항을 싣고, '+ keyed'/'– keyed' 표시는 그 방향의 첫 문항에만 붙는다(다음 표시까지 같은 방향).
// 실시 순서 번호(1~120)는 이 페이지에 없다 → 번호 순서는 원논문 표 1 로만 확정한다.
$AW = null;
$awOk = false; // [B] 가 [A'] 와 하위 요인·방향·문구 모두 일치하면 true
$awFile = $src . '/ipip_neo120_items.html';
if (is_file($awFile)) {
    $html = (string) iconv('CP1252', 'UTF-8//IGNORE', (string) file_get_contents($awFile));
    preg_match_all('#<td[^>]*>(.*?)</td>#su', $html, $cells);
    $AW = [];
    $facet = null;
    $sign = null;
    $pos = 0;
    $cellsText = array_map(static fn($c) => trim(html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{00A0}"), $cells[1]);
    for ($i = 0; $i < count($cellsText); $i++) {
        $t = $cellsText[$i];
        if (preg_match('/^([NEOAC][1-6]):/u', $t, $fm)) {
            $facet = $fm[1];
            $sign = null;
            $pos = 0;
            continue;
        }
        if ($facet === null) {
            continue;
        }
        if (preg_match('/^(\+|–|-)\s*keyed$/u', $t, $km)) {
            $sign = $km[1] === '+' ? '+' : '-';
            continue;
        }
        // 방향 칸(빈칸) 다음의 문장 칸
        if ($t !== '' && $sign !== null && !str_starts_with($t, 'Johnson, J. A.') && !str_starts_with($t, 'Alphas') && !str_starts_with($t, 'Return to')) {
            $pos++;
            $AW[] = ['facet_code' => $facet, 'keyed' => $sign, 'text_en' => rtrim($t, '.'), 'order_in_facet' => (string) $pos];
            if ($facet === 'C6' && $pos === 4) {
                $facet = null; // 마지막 하위 요인 뒤 각주는 문항이 아니다
            }
        }
    }
    csv_write($src . '/extracted_A_ipip120page.csv', ['facet_code', 'keyed', 'text_en', 'order_in_facet'], $AW);
    line("[A'] extracted_A_ipip120page.csv 작성 (" . count($AW) . '행)');
    line("== [A'] IPIP 공식 120문항 페이지 구조 점검");
    $fc = array_count_values(array_column($AW, 'facet_code'));
    check(count($AW) === 120, '문항 120', $fail);
    check(count($fc) === 30 && array_unique(array_values($fc)) === [4], '하위 요인 30 × 4문항', $fail);
    $minusAW = count(array_filter($AW, static fn($r) => $r['keyed'] === '-'));
    line("  정보  역채점 {$minusAW}개, 정채점 " . (count($AW) - $minusAW) . '개');
    if ($B !== null) {
        line("== [A'] 기준 [B] 대조 (하위 요인·방향·문구)");
        $awBy = [];
        foreach ($AW as $r) {
            $awBy[$r['facet_code'] . '|' . norm_text($r['text_en'])] = $r;
        }
        $mis = ['facet_or_text' => 0, 'keyed' => 0];
        foreach ($B as $r) {
            $key = $r['facet_code'] . '|' . norm_text($r['text_en']);
            if (!isset($awBy[$key])) {
                $mis['facet_or_text']++;
                line("  불일치 {$r['item_no_120']}번 [{$r['facet_code']}] \"{$r['text_en']}\" — A' 의 같은 하위 요인에 같은 문구 없음");
                continue;
            }
            if ($awBy[$key]['keyed'] !== $r['keyed']) {
                $mis['keyed']++;
                line("  불일치 {$r['item_no_120']}번 방향: A'={$awBy[$key]['keyed']} B={$r['keyed']}");
            }
            unset($awBy[$key]);
        }
        check($mis['facet_or_text'] === 0, "120문항 모두 A' 와 하위 요인·문구 일치 (불일치 {$mis['facet_or_text']})", $fail);
        check($mis['keyed'] === 0, "120문항 모두 A' 와 채점 방향 일치 (불일치 {$mis['keyed']})", $fail);
        check($awBy === [], "A' 문항이 B 에 모두 있음 (남은 것 " . count($awBy) . ')', $fail);
        $awOk = $mis === ['facet_or_text' => 0, 'keyed' => 0] && $awBy === [] && count($AW) === 120;
        // 하위 요인 안 순서: B 의 실시 순서로 본 하위 요인 내 순서 = A' 페이지 순서인가 (정보)
        $order = [];
        foreach ($B as $r) {
            $order[$r['facet_code']][] = norm_text($r['text_en']);
        }
        $sameOrder = 0;
        foreach ($order as $f => $texts) {
            $pageTexts = array_map(static fn($r) => norm_text($r['text_en']), array_values(array_filter($AW, static fn($r) => $r['facet_code'] === $f)));
            $sameOrder += $texts === $pageTexts ? 1 : 0;
        }
        line("  정보  하위 요인 안 문항 순서가 A' 페이지와 같은 하위 요인: {$sameOrder} / 30 (실시 번호는 원논문 표 1 로 확정)");
    }
    line();
}

// [D'] Mini-IPIP 요약표 — 문항 문장은 없고, 요인별 정·역채점 문항 수만 있다
$dtFile = $src . '/ipip_mini_ipip_table.html';
if (is_file($dtFile) && $AP !== null) {
    $html = (string) iconv('CP1252', 'UTF-8//IGNORE', (string) file_get_contents($dtFile));
    $txt = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $txt = str_replace("\u{00A0}", ' ', (string) $txt);
    line("== [D'] Mini-IPIP 요약표 기준 [부록 A] 요인별 +/− 문항 수 대조");
    foreach (['Extraversion' => 'E', 'Agreeableness' => 'A', 'Conscientiousness' => 'C', 'Neuroticism' => 'N', 'Intellect' => 'O'] as $label => $code) {
        if (!preg_match('/' . $label . '[a-z]?\s+(\d)\s*\+\s*(\d)\s*=/u', $txt, $m)) {
            check(false, "{$label}: 요약표에서 문항 수를 읽지 못함", $fail);
            continue;
        }
        $plus = count(array_filter($AP, static fn($r) => $r['factor'] === $code && $r['keyed'] === '+'));
        $minus = count(array_filter($AP, static fn($r) => $r['factor'] === $code && $r['keyed'] === '-'));
        check((int) $m[1] === $plus && (int) $m[2] === $minus, "{$code} {$label}: 요약표 +{$m[1]} −{$m[2]} / 부록 A +{$plus} −{$minus}", $fail);
    }
    line();
}

// ── 5) 확정 기준 파일 작성 ─────────────────────────────────
if (in_array('--write-master', $argv, true)) {
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d');
    // 120문항
    if ($A !== null) {
        $base = $A;
        $meta = "# status: final — 기준: [A] Johnson (2014) 원논문 표 1. 작성 {$today}";
        $srcLabel = 'A';
    } elseif ($B !== null) {
        $base = $B;
        $meta = $awOk
            ? "# status: provisional — 기준: [B] @bigfive-org/questions 1.2.1 (MIT). 하위 요인·방향·문구는 [A'] IPIP 공식 120문항 페이지(Johnson, 2014)와 모두 일치 확인. 실시 번호 순서만 원논문(A) 표 1 대조 대기. 작성 {$today}"
            : "# status: provisional — 임시 기준: [B] @bigfive-org/questions 1.2.1 (MIT). 원논문(A) 대조 대기. 작성 {$today}";
        $srcLabel = 'B';
    } else {
        $base = null;
    }
    if ($base !== null) {
        usort($base, static fn($x, $y) => (int) $x['item_no_120'] <=> (int) $y['item_no_120']);
        $rows = array_map(static fn($r) => [
            'item_no_120' => $r['item_no_120'], 'facet_code' => $r['facet_code'], 'keyed' => $r['keyed'],
            'text_en' => $r['text_en'], 'source' => $srcLabel, 'note' => $r['note'] ?? '',
        ], $base);
        $path = $src . '/master_120.csv';
        file_put_contents($path, $meta . "\n");
        $fh = fopen($path, 'a');
        $cols = ['item_no_120', 'facet_code', 'keyed', 'text_en', 'source', 'note'];
        fputcsv($fh, $cols, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($fh, array_map(static fn($k) => $r[$k], $cols), ',', '"', '');
        }
        fclose($fh);
        line("master_120.csv 작성 — 기준 {$srcLabel}, " . count($rows) . '행');
    }
    // 20문항
    if ($AP !== null) {
        $dBy = [];
        foreach ($D ?? [] as $d) {
            $dBy[(int) $d['item_no_20']] = $d;
        }
        $status = $D !== null ? 'final' : 'provisional';
        $meta = $D !== null
            ? "# status: final — 순서·방향·요인: [D] IPIP Mini-IPIP 공식 페이지, 한국어: 원고 부록 A. 작성 {$today}"
            : "# status: provisional — 순서·방향·요인·한국어: 원고 부록 A. text_en 은 [D] 확보 후 채움(기억으로 채우지 않음). 작성 {$today}";
        $path = $src . '/master_20.csv';
        file_put_contents($path, $meta . "\n");
        $fh = fopen($path, 'a');
        $cols = ['item_no_20', 'factor', 'keyed', 'text_en', 'text_ko', 'source', 'note'];
        fputcsv($fh, $cols, ',', '"', '');
        foreach ($AP as $r) {
            $no = (int) $r['item_no_20'];
            $d = $dBy[$no] ?? null;
            $note = '';
            $factor = $r['factor'];
            $keyed = $r['keyed'];
            if ($d !== null && ($d['factor'] !== $factor || $d['keyed'] !== $keyed)) {
                $note = "부록A {$factor}{$keyed} → D 기준 {$d['factor']}{$d['keyed']}";
                $factor = $d['factor'];
                $keyed = $d['keyed'];
                line("  고침 20문항 {$no}번: {$note}");
            }
            fputcsv($fh, [$no, $factor, $keyed, $d['text_en'] ?? '', $r['text_ko'], $d !== null ? 'D+부록A' : '부록A', $note], ',', '"', '');
        }
        fclose($fh);
        line("master_20.csv 작성 — status {$status}, " . count($AP) . '행');
    }
}

line($fail === 0 ? '점검 결과: 불일치 0건' : "점검 결과: 불일치·실패 {$fail}건");
exit(0); // 대조 도구는 보고용 — tests/run.php 일괄 실행에서 실패로 세지 않는다
