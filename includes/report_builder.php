<?php
/**
 * 리포트 데이터 구성 (순수 함수) — 점수 + 해석 문구(data/content) → 리포트 화면이 그대로 그릴 배열.
 * DB 를 읽지 않는다. 화면(report.php)·테스트(tests/report_test.php)가 같은 함수를 쓴다.
 *
 * 규칙 (PLAN 5.2)
 *  - 요인 소개 순서: 성실성 → 외향성 → 친화성 → 개방성 → 신경성
 *  - 경계 근처(35·65 ±5): 이웃 구간의 요약 한 줄을 함께 보여 준다
 *  - 하위 요인 편차: 같은 요인 안 최고 − 최저 ≥ 40 이면 안내
 *  - 가장 두드러진 다이얼 두 개: 50점에서 가장 먼 두 요인 (다섯 모두 가운데면 그 안내)
 *  - 조합 카드: 조건이 모두 맞는 규칙(강한 순) 최대 5개, 2개보다 적으면 가장 가까운 규칙으로 채움(“가까운 조합” 표시)
 */
declare(strict_types=1);

require_once __DIR__ . '/scoring.php';
require_once __DIR__ . '/items.php';

const BIG5_FACET_SPREAD = 40.0;
const BIG5_COMBO_MIN = 2;
const BIG5_COMBO_MAX = 5;

/** 점수가 구간에서 얼마나 떨어져 있나 (구간 안이면 0) */
function big5_band_distance(float $pomp, string $band): float
{
    return match ($band) {
        'low' => $pomp < BIG5_BAND_LOW_MAX ? 0.0 : $pomp - 34.9,
        'high' => $pomp > BIG5_BAND_HIGH_MIN ? 0.0 : 65.1 - $pomp,
        default => $pomp < BIG5_BAND_LOW_MAX ? BIG5_BAND_LOW_MAX - $pomp : ($pomp > BIG5_BAND_HIGH_MIN ? $pomp - BIG5_BAND_HIGH_MIN : 0.0),
    };
}

/**
 * 조합 카드 고르기. $pomp = [요인 => 점수]
 * @return array<int, array{rule: array, matched: bool, distance: float}>
 */
function big5_pick_combos(array $rules, array $pomp): array
{
    $scored = [];
    foreach ($rules as $r) {
        $dist = 0.0;
        $strength = 0.0;
        foreach ((array) ($r['when'] ?? []) as $d => $band) {
            if (!isset($pomp[$d])) {
                continue 2;
            }
            $dist += big5_band_distance($pomp[$d], (string) $band);
            $strength += abs($pomp[$d] - 50.0);
        }
        $scored[] = ['rule' => $r, 'matched' => $dist === 0.0, 'distance' => round($dist, 1), 'strength' => $strength];
    }
    $matched = array_values(array_filter($scored, static fn($x) => $x['matched']));
    usort($matched, static fn($a, $b) => $b['strength'] <=> $a['strength']);
    $out = array_slice($matched, 0, BIG5_COMBO_MAX);
    if (count($out) < BIG5_COMBO_MIN) {
        // 'all_mid' 는 실제로 맞을 때만 보여 준다 (가깝다고 붙이지 않음)
        $near = array_values(array_filter($scored, static fn($x) => !$x['matched'] && ($x['rule']['id'] ?? '') !== 'all_mid'));
        usort($near, static fn($a, $b) => [$a['distance'], -$a['strength']] <=> [$b['distance'], -$b['strength']]);
        foreach ($near as $x) {
            if (count($out) >= BIG5_COMBO_MIN) {
                break;
            }
            $out[] = $x;
        }
    }
    return array_map(static fn($x) => ['rule' => $x['rule'], 'matched' => $x['matched'], 'distance' => $x['distance']], $out);
}

/**
 * 리포트 데이터.
 * @param array $session  big5_sessions 행 (test_type, completed_at, item_version, quality_flags)
 * @param array $scores   big5_scores_load() 결과
 * @param array $items    big5_items() 결과
 * @param array $content  big5_content() 결과
 */
function big5_report_build(array $session, array $scores, array $items, array $content): array
{
    $isFull = ($session['test_type'] ?? '') === 'full120';
    $fx = $content['fixed'];
    $bandLabel = $fx['band_labels'];
    $pomp = [];
    foreach ($scores['domains'] as $d => $row) {
        $pomp[$d] = (float) $row['pomp'];
    }

    $domains = [];
    foreach (big5_domain_order() as $d) {
        if (!isset($scores['domains'][$d])) {
            continue;
        }
        $row = $scores['domains'][$d];
        $band = (string) $row['band'];
        $near = big5_near_band((float) $row['pomp']);
        $dc = $content['domains'][$d];
        $facets = [];
        if ($isFull) {
            foreach ($scores['facets'] as $code => $fr) {
                if ($code[0] !== $d) {
                    continue;
                }
                $fc = $content['facets']['facets'][$code] ?? [];
                $facets[] = [
                    'code' => $code,
                    'name' => (string) ($fc['name'] ?? $items['facets'][$code]['name'] ?? $code),
                    'definition' => (string) ($fc['definition'] ?? ''),
                    'pomp' => (float) $fr['pomp'],
                    'band' => (string) $fr['band'],
                    'band_label' => $bandLabel[$fr['band']],
                    'text' => (string) ($fc[$fr['band']] ?? ''),
                ];
            }
        }
        $fp = array_column($facets, 'pomp');
        $spread = $fp === [] ? 0.0 : max($fp) - min($fp);
        $domains[$d] = [
            'code' => $d,
            'name' => (string) $dc['name'],
            'color' => (string) $dc['color'],
            'tagline' => (string) ($fx['domain_about'][$d]['tagline'] ?? ''),
            'about' => (string) ($fx['domain_about'][$d]['about'] ?? ''),
            'misconceptions' => $fx['domain_about'][$d]['misconceptions'] ?? [],
            'pomp' => (float) $row['pomp'],
            'raw' => (int) $row['raw'],
            'band' => $band,
            'band_label' => $bandLabel[$band],
            'entry' => $dc[$band],
            'near' => $near === null ? null : [
                'band' => $near,
                'band_label' => $bandLabel[$near],
                'summary_line' => (string) $dc[$near]['summary_line'],
            ],
            'facets' => $facets,
            'facet_spread' => $isFull && $spread >= BIG5_FACET_SPREAD,
            'facet_spread_value' => round($spread, 1),
        ];
    }

    // 가장 두드러진 두 다이얼
    $allMid = count(array_filter($domains, static fn($x) => $x['band'] === 'mid')) === count($domains);
    $byDist = $domains;
    uasort($byDist, static fn($a, $b) => abs($b['pomp'] - 50) <=> abs($a['pomp'] - 50));
    $standout = $allMid ? [] : array_slice(array_keys($byDist), 0, 2);

    // 조합 카드
    $combos = big5_pick_combos($content['combos']['rules'] ?? [], $pomp);

    // 일터·관계 — [섹션 => [[요인, 구간, 문구], ...]]
    $grid = static function (array $sec) use ($domains): array {
        $rows = [];
        foreach ($sec['domains'] ?? [] as $d) {
            if (isset($domains[$d])) {
                $b = $domains[$d]['band'];
                $rows[] = ['code' => $d, 'name' => $domains[$d]['name'], 'band' => $b, 'band_label' => $domains[$d]['band_label'], 'text' => (string) ($sec['by'][$d][$b] ?? '')];
            }
        }
        return ['title' => (string) ($sec['title'] ?? ''), 'intro' => (string) ($sec['intro'] ?? ''), 'rows' => $rows];
    };
    $w = $content['work'];
    $energy = [];
    foreach ($domains as $d => $x) {
        $e = $w['energy']['by'][$d][$x['band']] ?? ['gain' => [], 'drain' => []];
        $energy[] = ['code' => $d, 'name' => $x['name'], 'band_label' => $x['band_label'], 'gain' => $e['gain'], 'drain' => $e['drain']];
    }
    $r = $content['relations'];

    $flags = array_values(array_filter(explode(',', (string) ($session['quality_flags'] ?? ''))));

    return [
        'is_full' => $isFull,
        'test_label' => big5_test_label((string) $session['test_type']),
        'date' => substr((string) ($session['completed_at'] ?? ''), 0, 10),
        'item_version' => (string) ($session['item_version'] ?? ''),
        'quality' => $flags,
        'domains' => $domains,
        'all_mid' => $allMid,
        'standout' => $standout,
        'combos' => $combos,
        'work' => [
            'style' => $grid($w['style']),
            'energy' => ['title' => $w['energy']['title'], 'note' => $w['energy']['note'], 'gain_label' => $w['energy']['gain_label'], 'drain_label' => $w['energy']['drain_label'], 'rows' => $energy],
            'team' => $grid($w['team']),
            'pressure' => $grid($w['pressure']),
            'leader' => $grid($w['leader']),
        ],
        'relations' => [
            'close' => $grid($r['close']),
            'conflict' => $grid($r['conflict']),
            'energy' => $grid($r['energy']),
        ],
    ];
}
