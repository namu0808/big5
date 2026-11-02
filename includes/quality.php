<?php
/**
 * 응답 품질 점검 (PLAN 3.4) — 결과를 막지 않고 리포트 상단에 안내만 한다.
 * 플래그는 DB(quality_flags)에 쉼표로 저장되고, 관리자 통계·규준 생성에서 제외할 수 있다.
 *  - fast         : 너무 빨리 끝냄 (120문항 4분 미만 = 문항당 2초 미만. 간이 검사도 같은 비율로 40초 미만)
 *  - straightline : 같은 응답값이 전체의 70% 이상
 *  - inconsistent : 정·역 문항이 섞인 묶음(하위 요인, 간이 검사는 요인)마다
 *                   (+문항 평균)과 (−문항 역채점 평균)의 차이가 2.0 이상인 묶음이 30% 이상 (승인 2026-11-02)
 */
declare(strict_types=1);

const BIG5_Q_SEC_PER_ITEM = 2.0;
const BIG5_Q_STRAIGHT_RATIO = 0.70;
const BIG5_Q_INCONS_DIFF = 2.0;
const BIG5_Q_INCONS_RATIO = 0.30;

/**
 * @return array{flags: string[], detail: array}
 */
function big5_quality(array $items, array $responses, ?int $durationSec): array
{
    $flags = [];
    $n = count($items);
    $detail = ['items' => $n, 'duration_sec' => $durationSec];

    // 1) 소요 시간
    $minSec = (int) round($n * BIG5_Q_SEC_PER_ITEM);
    $detail['min_sec'] = $minSec;
    if ($durationSec !== null && $durationSec < $minSec) {
        $flags[] = 'fast';
    }

    // 2) 한 가지 답 반복
    $counts = array_count_values(array_map('intval', $responses));
    $maxShare = $n > 0 && $counts !== [] ? max($counts) / $n : 0.0;
    $detail['max_same_ratio'] = round($maxShare, 3);
    if ($maxShare >= BIG5_Q_STRAIGHT_RATIO) {
        $flags[] = 'straightline';
    }

    // 3) 역문항 일관성
    $groups = [];
    foreach ($items as $it) {
        $g = isset($it['facet']) ? (string) $it['facet'] : (string) $it['domain'];
        $v = (int) ($responses[(int) $it['no']] ?? 0);
        if ($v < 1) {
            continue;
        }
        if ($it['keyed'] === '-') {
            $groups[$g]['minus'][] = 6 - $v;   // 역채점한 값
        } else {
            $groups[$g]['plus'][] = $v;
        }
    }
    $eligible = 0;
    $bad = [];
    foreach ($groups as $g => $set) {
        if (empty($set['plus']) || empty($set['minus'])) {
            continue; // 한 방향 문항만 있는 묶음은 판단하지 않는다
        }
        $eligible++;
        $diff = abs(array_sum($set['plus']) / count($set['plus']) - array_sum($set['minus']) / count($set['minus']));
        if ($diff >= BIG5_Q_INCONS_DIFF) {
            $bad[] = $g;
        }
    }
    $detail['mixed_groups'] = $eligible;
    $detail['inconsistent_groups'] = $bad;
    if ($eligible > 0 && count($bad) / $eligible >= BIG5_Q_INCONS_RATIO) {
        $flags[] = 'inconsistent';
    }

    return ['flags' => $flags, 'detail' => $detail];
}

/** 플래그별 리포트 안내 문구 */
function big5_quality_message(string $flag): string
{
    return [
        'fast' => '문항에 답한 시간이 매우 짧았어요. 문장을 충분히 읽지 못하고 답했을 수 있습니다.',
        'straightline' => '같은 답을 아주 많이 고르셨어요. 결과가 평소의 모습을 다 담지 못했을 수 있습니다.',
        'inconsistent' => '서로 반대되는 문장에 비슷하게 답한 곳이 여럿 있어요. 문장을 다시 읽어 보며 검사하면 결과가 달라질 수 있습니다.',
    ][$flag] ?? '';
}
