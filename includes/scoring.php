<?php
/**
 * 채점 엔진 (순수 함수 — DB 에 의존하지 않는다). PLAN 3장.
 *  - 응답 1~5, 역채점 문항은 6 - 응답
 *  - 하위 요인 원점수 = 4문항 합(4~20), 요인 원점수 = 하위 요인 6개 합(24~120) / 간이 검사는 요인 4문항 합(4~20)
 *  - 표시 점수 POMP = (원점수 - 최소) / (최대 - 최소) × 100, 소수 첫째 자리 반올림
 *  - 구간: 0~34.9 low, 35~65 mid, 65.1~100 high. 경계(35·65) ±5점 이내는 '경계 근처'
 * 규준(백분위)이 없는 동안 이 점수만 쓴다. 순위 표현으로 바꾸지 않는다.
 */
declare(strict_types=1);

const BIG5_SCORING_VERSION = '1.0';
const BIG5_BAND_LOW_MAX = 35.0;   // 이 값 미만이면 low
const BIG5_BAND_HIGH_MIN = 65.0;  // 이 값 초과면 high
const BIG5_NEAR_MARGIN = 5.0;     // 경계 ±5점

/** 역채점 반영 점수 */
function big5_keyed_value(int $value, string $keyed): int
{
    return $keyed === '-' ? 6 - $value : $value;
}

/** POMP 0~100, 소수 첫째 자리 */
function big5_pomp(int $raw, int $min, int $max): float
{
    if ($max <= $min) {
        return 0.0;
    }
    return round(($raw - $min) / ($max - $min) * 100, 1);
}

/** 구간 — 'low' | 'mid' | 'high' */
function big5_band(float $pomp): string
{
    if ($pomp < BIG5_BAND_LOW_MAX) {
        return 'low';
    }
    if ($pomp > BIG5_BAND_HIGH_MIN) {
        return 'high';
    }
    return 'mid';
}

/**
 * 경계 근처면 이웃 구간 이름, 아니면 null.
 * 예: 33 → 'mid'(가운데 쪽 경계 근처), 68 → 'mid', 62 → 'high', 38 → 'low'
 */
function big5_near_band(float $pomp): ?string
{
    if (abs($pomp - BIG5_BAND_LOW_MAX) <= BIG5_NEAR_MARGIN) {
        return big5_band($pomp) === 'low' ? 'mid' : 'low';
    }
    if (abs($pomp - BIG5_BAND_HIGH_MIN) <= BIG5_NEAR_MARGIN) {
        return big5_band($pomp) === 'high' ? 'mid' : 'high';
    }
    return null;
}

/** 점수 한 줄 만들기 */
function big5_score_row(int $raw, int $min, int $max): array
{
    $pomp = big5_pomp($raw, $min, $max);
    return ['raw' => $raw, 'min' => $min, 'max' => $max, 'pomp' => $pomp, 'band' => big5_band($pomp)];
}

/**
 * 채점. $items = 문항 JSON 의 items, $responses = [문항 번호 => 1~5].
 * 반환: ['domains' => [코드 => 점수 줄], 'facets' => [코드 => 점수 줄] (간이 검사는 빈 배열)]
 * 응답이 빠졌거나 범위 밖이면 InvalidArgumentException.
 */
function big5_score(array $items, array $responses): array
{
    $facetSum = [];
    $facetN = [];
    $domainSum = [];
    $domainN = [];
    foreach ($items as $it) {
        $no = (int) $it['no'];
        $v = $responses[$no] ?? null;
        if (!is_int($v) || $v < 1 || $v > 5) {
            throw new InvalidArgumentException("문항 {$no}번 응답이 없거나 범위를 벗어났습니다.");
        }
        $k = big5_keyed_value($v, (string) $it['keyed']);
        if (isset($it['facet'])) {
            $f = (string) $it['facet'];
            $facetSum[$f] = ($facetSum[$f] ?? 0) + $k;
            $facetN[$f] = ($facetN[$f] ?? 0) + 1;
            $d = $f[0];
        } else {
            $d = (string) $it['domain'];
        }
        $domainSum[$d] = ($domainSum[$d] ?? 0) + $k;
        $domainN[$d] = ($domainN[$d] ?? 0) + 1;
    }
    $facets = [];
    ksort($facetSum);
    foreach ($facetSum as $f => $sum) {
        $facets[$f] = big5_score_row($sum, $facetN[$f], $facetN[$f] * 5);
    }
    $domains = [];
    foreach (['N', 'E', 'O', 'A', 'C'] as $d) {
        if (isset($domainSum[$d])) {
            // 요인 원점수 = 하위 요인 합 = 문항 합 (같은 값)
            $domains[$d] = big5_score_row($domainSum[$d], $domainN[$d], $domainN[$d] * 5);
        }
    }
    return ['domains' => $domains, 'facets' => $facets];
}

/** 채점 결과를 big5_scores 에 저장 (기존 행은 지우고 다시 쓴다 — 재채점 가능) */
function big5_scores_save(PDO $pdo, string $ownerType, int $ownerId, array $scored): void
{
    $pdo->prepare('DELETE FROM big5_scores WHERE owner_type = ? AND owner_id = ?')->execute([$ownerType, $ownerId]);
    $ins = $pdo->prepare(
        'INSERT INTO big5_scores (owner_type, owner_id, level, code, raw_score, pomp, band) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (['domain' => 'domains', 'facet' => 'facets'] as $level => $key) {
        foreach ($scored[$key] as $code => $row) {
            $ins->execute([$ownerType, $ownerId, $level, $code, $row['raw'], $row['pomp'], $row['band']]);
        }
    }
}

/** 저장된 점수 읽기 → ['domains' => [...], 'facets' => [...]] (pomp 는 float) */
function big5_scores_load(PDO $pdo, string $ownerType, int $ownerId): array
{
    $st = $pdo->prepare('SELECT level, code, raw_score, pomp, band FROM big5_scores WHERE owner_type = ? AND owner_id = ?');
    $st->execute([$ownerType, $ownerId]);
    $out = ['domains' => [], 'facets' => []];
    foreach ($st->fetchAll() as $r) {
        $key = $r['level'] === 'domain' ? 'domains' : 'facets';
        $out[$key][$r['code']] = ['raw' => (int) $r['raw_score'], 'pomp' => (float) $r['pomp'], 'band' => (string) $r['band']];
    }
    ksort($out['facets']);
    return $out;
}

/** 원응답 읽기 → [문항 번호 => 값] */
function big5_responses_load(PDO $pdo, string $ownerType, int $ownerId): array
{
    $st = $pdo->prepare('SELECT item_no, value FROM big5_responses WHERE owner_type = ? AND owner_id = ?');
    $st->execute([$ownerType, $ownerId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(int) $r['item_no']] = (int) $r['value'];
    }
    ksort($out);
    return $out;
}

/** 응답 1건 저장(있으면 고침). 표준 SQL — SELECT 후 UPDATE/INSERT */
function big5_response_save(PDO $pdo, string $ownerType, int $ownerId, int $itemNo, int $value): void
{
    $st = $pdo->prepare('SELECT id FROM big5_responses WHERE owner_type = ? AND owner_id = ? AND item_no = ?');
    $st->execute([$ownerType, $ownerId, $itemNo]);
    $id = $st->fetchColumn();
    if ($id !== false) {
        $pdo->prepare('UPDATE big5_responses SET value = ?, answered_at = ? WHERE id = ?')->execute([$value, big5_now(), (int) $id]);
        return;
    }
    $pdo->prepare('INSERT INTO big5_responses (owner_type, owner_id, item_no, value, answered_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$ownerType, $ownerId, $itemNo, $value, big5_now()]);
}
