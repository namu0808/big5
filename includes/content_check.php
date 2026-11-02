<?php
/**
 * 해석 문구 점검 (순수 함수) — CLI(tests/content_test.php)와 관리자 화면(admin/content.php)이 함께 쓴다.
 *  1) 누락: 요인 5 × 구간 3, 하위 요인 30 × (정의 + 구간 3), 조합 규칙, 일터·관계 칸, 성장 행동, 고정 문구 필수 키
 *  2) 문구 원칙(PLAN 6.3): 금지 표현, 유형 이름, 백분위·순위 표현, 병리 용어
 *  3) 짝: 모든 구간에 강점과 주의점, 신경성 높은 구간 도움 안내
 */
declare(strict_types=1);

require_once __DIR__ . '/content_loader.php';

const BIG5_DOMAINS = ['C', 'E', 'A', 'O', 'N'];
const BIG5_BANDS = ['low', 'mid', 'high'];

/** 금지 표현 — 이름 => 정규식 (활용형까지) */
function big5_forbidden_patterns(): array
{
    return [
        '~를 통해' => '/통해/u',
        '~함으로써' => '/함으로써|으로써/u',
        '~에 있어서' => '/에\s?있어서/u',
        '~로 인해' => '/로\s?인해|로\s?인한/u',
        '다양한' => '/다양/u',
        '효과적인' => '/효과적/u',
        '긍정적인' => '/긍정적/u',
        '중요한' => '/중요/u',
        '올바른' => '/올바르|올바른/u',
        '무너지다' => '/무너/u',
        '갇히다' => '/갇히|갇혀|갇힌|갇혔/u',
    ];
}

/** 문자열 하나의 원칙 위반 목록 → [[규칙, 조각], ...] */
function big5_text_violations(string $s): array
{
    $v = [];
    foreach (big5_forbidden_patterns() as $name => $re) {
        if (preg_match($re, $s, $m)) {
            $v[] = ['금지 표현 ' . $name, $m[0]];
        }
    }
    // 유형 이름: 'OO형입니다/OO형의/OO형으로…' 꼴. 일반 낱말(유형·모형·균형 등)은 뺀다
    $allow = ['유형', '모형', '균형', '대형', '원형', '변형', '체형', '외형', '정형', '성형', '소형', '중형', '신형', '구형'];
    if (preg_match_all('/([가-힣]{1,6}형)(입니다|이에요|이다|인|의|으로|이라|이래)/u', $s, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $word = $m[1];
            $ok = false;
            foreach ($allow as $a) {
                if (str_ends_with($word, $a)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $v[] = ['유형 이름', $m[0]];
            }
        }
    }
    if (preg_match('/타입/u', $s, $m)) {
        $v[] = ['유형 이름', $m[0]];
    }
    // 백분위·순위 표현 (규준 비활성). '하위 요인'은 허용
    if (preg_match('/상위\s*\d|하위\s*\d|\d+\s*%|％|퍼센트|백분위|번째/u', $s, $m)) {
        $v[] = ['백분위·순위 표현', $m[0]];
    }
    if (preg_match('/장애|질환|증상/u', $s, $m)) {
        $v[] = ['병리 용어', $m[0]];
    }
    return $v;
}

/** 배열 안의 모든 문자열을 [경로, 문자열] 로 펼친다 (식별자 성격의 키는 건너뜀) */
function big5_content_strings(array $a, string $path = ''): array
{
    $skip = ['status', 'version', 'color', 'key', 'id', 'code', 'source', 'check_before_launch', 'note'];
    $out = [];
    foreach ($a as $k => $v) {
        if (is_string($k) && in_array($k, $skip, true)) {
            continue;
        }
        $p = $path === '' ? (string) $k : $path . '.' . $k;
        if (is_array($v)) {
            $out = array_merge($out, big5_content_strings($v, $p));
        } elseif (is_string($v)) {
            $out[] = [$p, $v];
        }
    }
    return $out;
}

function big5_nonempty($v): bool
{
    return is_string($v) && trim($v) !== '';
}

function big5_list_count($v, int $min, int $max): bool
{
    if (!is_array($v) || count($v) < $min || count($v) > $max) {
        return false;
    }
    foreach ($v as $x) {
        if (!big5_nonempty($x)) {
            return false;
        }
    }
    return true;
}

/**
 * 점검. $facetCodes = 문항 JSON 의 하위 요인 코드 30개(교차 검사용, 비우면 생략)
 * @return array{ok: bool, rows: array, violations: array, missing: array}
 */
function big5_content_check(array $c, array $facetCodes = []): array
{
    $rows = [];
    $missing = [];
    $add = static function (string $label, string $expected, int|string $actual, bool $ok) use (&$rows): void {
        $rows[] = ['label' => $label, 'expected' => $expected, 'actual' => (string) $actual, 'ok' => $ok];
    };

    // 0) 파일과 status
    $okFiles = 0;
    foreach (BIG5_CONTENT_FILES as $f) {
        if (isset($c[$f]) && is_array($c[$f]) && in_array($c[$f]['status'] ?? '', ['draft', 'final'], true)) {
            $okFiles++;
        } else {
            $missing[] = "{$f}.json (파일 또는 status)";
        }
    }
    $add('파일 7개 + status 표시', '7', $okFiles, $okFiles === 7);

    // 1) 요인 × 구간
    $d = $c['domains'] ?? [];
    $cells = 0;
    $pairs = 0;
    foreach (BIG5_DOMAINS as $dm) {
        foreach (BIG5_BANDS as $b) {
            $e = $d[$dm][$b] ?? null;
            $ok = is_array($e) && big5_nonempty($e['summary_line'] ?? null) && big5_list_count($e['body'] ?? null, 3, 5)
                && big5_nonempty($e['closing'] ?? null);
            $pair = is_array($e) && big5_list_count($e['strengths'] ?? null, 3, 4) && big5_list_count($e['cautions'] ?? null, 2, 3);
            if ($ok) {
                $cells++;
            } else {
                $missing[] = "domains.{$dm}.{$b} (요약·본문 3~5문단·마무리)";
            }
            if ($pair) {
                $pairs++;
            } else {
                $missing[] = "domains.{$dm}.{$b} (강점 3~4 · 주의점 2~3)";
            }
        }
    }
    $add('요인 5 × 구간 3 — 요약·본문·마무리', '15', $cells, $cells === 15);
    $add('요인 5 × 구간 3 — 강점과 주의점 함께', '15', $pairs, $pairs === 15);
    $help = (string) ($d['N']['high']['help_line'] ?? '');
    $add('신경성 높은 구간 도움 안내 한 줄', '있음', $help !== '' && str_contains($help, '1577-0199') ? '있음' : '없음', $help !== '' && str_contains($help, '1577-0199'));
    if ($help === '') {
        $missing[] = 'domains.N.high.help_line';
    }

    // 2) 하위 요인
    $fa = $c['facets']['facets'] ?? [];
    $fOk = 0;
    foreach ($fa as $code => $e) {
        $ok = big5_nonempty($e['name'] ?? null) && big5_nonempty($e['definition'] ?? null)
            && big5_nonempty($e['low'] ?? null) && big5_nonempty($e['mid'] ?? null) && big5_nonempty($e['high'] ?? null);
        if ($ok) {
            $fOk++;
        } else {
            $missing[] = "facets.{$code}";
        }
    }
    $add('하위 요인 30 × (정의 + 구간 3)', '30', $fOk, $fOk === 30 && count($fa) === 30);
    if ($facetCodes !== []) {
        $keys = array_keys($fa);
        sort($keys);
        $want = $facetCodes;
        sort($want);
        $add('하위 요인 코드 = 문항 JSON', '일치', $keys === $want ? '일치' : '다름', $keys === $want);
    }

    // 3) 조합 규칙
    $rules = $c['combos']['rules'] ?? [];
    $rOk = 0;
    $ids = [];
    foreach ($rules as $i => $r) {
        $when = $r['when'] ?? [];
        $whenOk = is_array($when) && $when !== [];
        foreach ((array) $when as $k => $b) {
            $whenOk = $whenOk && in_array($k, BIG5_DOMAINS, true) && in_array($b, BIG5_BANDS, true);
        }
        $ok = big5_nonempty($r['id'] ?? null) && $whenOk && big5_nonempty($r['title'] ?? null) && big5_list_count($r['body'] ?? null, 2, 3);
        if ($ok) {
            $rOk++;
        } else {
            $missing[] = 'combos.rules.' . ($r['id'] ?? $i);
        }
        $ids[] = $r['id'] ?? '';
    }
    $add('조합 규칙 (11개 이상, 조건·제목·본문 2~3)', '11+', $rOk, $rOk >= 11 && $rOk === count($rules) && count(array_unique($ids)) === count($ids));
    $add("'다섯 모두 가운데' 규칙", '있음', in_array('all_mid', $ids, true) ? '있음' : '없음', in_array('all_mid', $ids, true));

    // 4) 일터·관계
    $grid = static function (array $sec, array $domains, bool $energy = false): int {
        $n = 0;
        foreach ($domains as $dm) {
            foreach (BIG5_BANDS as $b) {
                $v = $sec['by'][$dm][$b] ?? null;
                $ok = $energy
                    ? (is_array($v) && big5_list_count($v['gain'] ?? null, 1, 5) && big5_list_count($v['drain'] ?? null, 1, 5))
                    : big5_nonempty($v);
                $n += $ok ? 1 : 0;
            }
        }
        return $n;
    };
    $w = $c['work'] ?? [];
    foreach ([['style', ['C', 'O'], false], ['energy', BIG5_DOMAINS, true], ['team', ['A', 'E'], false], ['pressure', ['N', 'E'], false], ['leader', BIG5_DOMAINS, false]] as [$sec, $dms, $en]) {
        $n = $grid($w[$sec] ?? [], $dms, $en);
        $want = count($dms) * 3;
        $add("일터 {$sec} (요인 × 구간)", (string) $want, $n, $n === $want && big5_nonempty($w[$sec]['title'] ?? null));
        if ($n !== $want) {
            $missing[] = "work.{$sec}";
        }
    }
    $rl = $c['relations'] ?? [];
    foreach ([['close', ['E', 'A']], ['conflict', ['A', 'N']], ['energy', ['E']]] as [$sec, $dms]) {
        $n = $grid($rl[$sec] ?? [], $dms);
        $want = count($dms) * 3;
        $add("관계 {$sec} (요인 × 구간)", (string) $want, $n, $n === $want && big5_nonempty($rl[$sec]['title'] ?? null));
        if ($n !== $want) {
            $missing[] = "relations.{$sec}";
        }
    }

    // 5) 성장 행동
    $g = $c['growth']['domains'] ?? [];
    $gOk = 0;
    $keys = [];
    foreach (BIG5_DOMAINS as $dm) {
        foreach (['grow', 'shadow'] as $dir) {
            $acts = $g[$dm][$dir]['actions'] ?? [];
            $ok = is_array($acts) && count($acts) >= 5 && count($acts) <= 8 && big5_nonempty($g[$dm][$dir]['label'] ?? null);
            foreach ((array) $acts as $a) {
                $ok = $ok && big5_nonempty($a['text'] ?? null) && is_string($a['key'] ?? null) && preg_match('/^[a-z0-9_]{1,40}$/', $a['key']) === 1;
                $keys[] = $a['key'] ?? '';
            }
            if ($ok) {
                $gOk++;
            } else {
                $missing[] = "growth.{$dm}.{$dir}";
            }
        }
    }
    $add('성장 행동 5 × (키우기·그림자) × 5~8개', '10', $gOk, $gOk === 10);
    $add('성장 행동 key 중복 없음', '0', count($keys) - count(array_unique($keys)), count($keys) === count(array_unique($keys)));

    // 6) 고정 문구 필수 키
    $fx = $c['fixed'] ?? [];
    $need = [
        '0장 읽는 법' => big5_list_count($fx['reading_guide']['paragraphs'] ?? null, 2, 6),
        '1장 소개 절' => is_array($fx['intro']['sections'] ?? null) && count($fx['intro']['sections']) >= 3,
        '1장 다섯 요인 한 줄 정의 표' => count($fx['intro']['domain_table'] ?? []) === 5,
        '1장 MBTI 비교(문단·표)' => big5_nonempty($fx['intro']['mbti']['paragraph'] ?? null) && count($fx['intro']['mbti']['table']['rows'] ?? []) >= 3,
        '2장 일곱 가지 주의점' => count($fx['seven_cautions']['items'] ?? []) === 7,
        '3장 구간 안내' => big5_nonempty($fx['glance']['band_note'] ?? null),
        '4장 경계 근처·편차 안내' => big5_nonempty($fx['detail']['near_boundary_note'] ?? null) && big5_nonempty($fx['detail']['facet_spread_note'] ?? null),
        '8장 머리말 2문단' => big5_list_count($fx['growth']['paragraphs'] ?? null, 2, 2),
        '9장·10장 고정 안내' => big5_list_count($fx['others']['guide'] ?? null, 1, 5) && big5_list_count($fx['retest']['guide'] ?? null, 1, 5),
        '부록 계산법·용어·읽을거리·출처' => big5_list_count($fx['appendix']['scoring']['paragraphs'] ?? null, 1, 8)
            && count($fx['appendix']['glossary'] ?? []) >= 5 && count($fx['appendix']['reading'] ?? []) >= 3 && count($fx['appendix']['sources'] ?? []) >= 1,
        '인쇄 꼬리말' => big5_nonempty($fx['print_footer'] ?? null),
    ];
    $about = 0;
    foreach (BIG5_DOMAINS as $dm) {
        $a = $fx['domain_about'][$dm] ?? [];
        $about += (big5_nonempty($a['about'] ?? null) && count($a['misconceptions'] ?? []) >= 1 && count($a['misconceptions'] ?? []) <= 2) ? 1 : 0;
    }
    $need['요인별 무엇을 재나·흔한 오해 (5)'] = $about === 5;
    $nOk = 0;
    foreach ($need as $label => $ok) {
        if ($ok) {
            $nOk++;
        } else {
            $missing[] = "fixed: {$label}";
        }
    }
    $add('고정 문구 필수 항목', (string) count($need), $nOk, $nOk === count($need));

    // 7) 문구 원칙
    $violations = [];
    foreach (BIG5_CONTENT_FILES as $f) {
        if (!is_array($c[$f] ?? null)) {
            continue;
        }
        foreach (big5_content_strings($c[$f]) as [$path, $s]) {
            foreach (big5_text_violations($s) as [$rule, $frag]) {
                $violations[] = ['file' => $f, 'path' => $path, 'rule' => $rule, 'fragment' => $frag];
            }
        }
    }
    $add('금지 표현·유형 이름·백분위·병리 용어', '0', count($violations), $violations === []);

    $ok = $missing === [] && $violations === [];
    foreach ($rows as $r) {
        $ok = $ok && $r['ok'];
    }
    return ['ok' => $ok, 'rows' => $rows, 'violations' => $violations, 'missing' => $missing];
}
