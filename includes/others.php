<?php
/**
 * 타인 평가·재검사 도우미
 *  - 초대: 토큰 원문(64hex)은 링크에만, DB 에는 SHA-256 해시만 (승인된 변경 5). 유효기간 14일
 *  - 평가 1건 = big5_ratings 행. 응답·점수는 owner_type='rating' 으로 본인 검사와 같은 표에 저장
 *  - 리포트 9장(다른 사람이 본 나)·10장(지난번과 비교) 데이터 구성
 */
declare(strict_types=1);

require_once __DIR__ . '/test_session.php';

const BIG5_INVITE_DAYS = 14;
const BIG5_INVITE_MAX_RATINGS = 20;   // 링크 하나로 받을 수 있는 평가 수
const BIG5_GAP_OTHERS = 15.0;         // 나와 남의 차이 표시 기준 ('다르게 보는 곳')
const BIG5_GAP_NEAR = 8.0;            // 이보다 작으면 '비슷하게 보는 곳' (8~15 는 '조금 다른 곳')
const BIG5_SPREAD_SPLIT = 20.0;       // 평가자들 점수의 최대−최소가 이 이상이면 '남들끼리도 갈림'
const BIG5_GAP_RETEST = 10.0;         // 재검사 변화 강조 기준
const BIG5_RELATIONS = ['family' => '가족', 'friend' => '친구', 'colleague' => '동료', 'other' => '기타'];

function big5_token_hash(string $token): string
{
    return hash('sha256', $token);
}

/** 새 초대 → 토큰 원문 (한 번만 보여 줄 수 있다) */
function big5_invite_create(PDO $pdo, int $userId, string $relation, string $testType): string
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO big5_invites (user_id, token_hash, relation, test_type, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$userId, big5_token_hash($token), $relation, $testType, big5_now(BIG5_INVITE_DAYS * 86400), big5_now()]);
    return $token;
}

/** 토큰 → 초대 행 + 초대한 사람 이름. 형식이 틀렸거나 없으면 null (만료 여부는 'expired' 로 알려 준다) */
function big5_invite_by_token(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT i.*, u.display_name AS owner_name, u.is_active AS owner_active
           FROM big5_invites i JOIN sbm_users u ON u.id = i.user_id WHERE i.token_hash = ?'
    );
    $st->execute([big5_token_hash($token)]);
    $row = $st->fetch();
    if (!is_array($row) || (int) $row['owner_active'] !== 1) {
        return null;
    }
    $row['expired'] = (string) $row['expires_at'] <= big5_now();
    return $row;
}

/** 내 초대 목록 + 완료 응답 수 */
function big5_invites_of(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        "SELECT i.id, i.relation, i.test_type, i.expires_at, i.created_at,
                (SELECT COUNT(*) FROM big5_ratings r WHERE r.invite_id = i.id AND r.status = 'completed') AS done
           FROM big5_invites i WHERE i.user_id = ? ORDER BY i.id DESC"
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

/** 평가 시작 → rating id */
function big5_rating_start(PDO $pdo, array $invite): int
{
    $items = big5_items((string) $invite['test_type']);
    $pdo->prepare("INSERT INTO big5_ratings (invite_id, test_type, item_version, status, started_at) VALUES (?, ?, ?, 'in_progress', ?)")
        ->execute([(int) $invite['id'], (string) $invite['test_type'], (string) ($items['version'] ?? ''), big5_now()]);
    return (int) $pdo->lastInsertId();
}

/** 평가 완료 처리 (본인 검사와 같은 채점·품질 점검) */
function big5_rating_finish(PDO $pdo, array $rating): array
{
    $items = big5_items((string) $rating['test_type']);
    $resp = big5_responses_load($pdo, 'rating', (int) $rating['id']);
    $missing = [];
    foreach ($items['items'] as $it) {
        if (!isset($resp[(int) $it['no']])) {
            $missing[] = (int) $it['no'];
        }
    }
    if ($missing !== []) {
        return ['ok' => false, 'code' => 'missing', 'missing' => $missing];
    }
    $now = big5_now();
    $duration = big5_seconds_between((string) $rating['started_at'], $now);
    $quality = big5_quality($items['items'], $resp, $duration);
    $pdo->beginTransaction();
    try {
        big5_scores_save($pdo, 'rating', (int) $rating['id'], big5_score($items['items'], $resp));
        $pdo->prepare("UPDATE big5_ratings SET status = 'completed', completed_at = ?, duration_sec = ?, quality_flags = ?, scoring_version = ? WHERE id = ?")
            ->execute([$now, $duration, $quality['flags'] === [] ? null : implode(',', $quality['flags']), BIG5_SCORING_VERSION, (int) $rating['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true];
}

/**
 * 이 사용자가 받은 완료 평가
 * → [['relation' => .., 'test_type' => .., 'flags' => [품질 표시], 'domains' => [요인 => pomp]], ...]
 */
function big5_ratings_received(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        "SELECT r.id, r.test_type, r.quality_flags, i.relation FROM big5_ratings r JOIN big5_invites i ON i.id = r.invite_id
          WHERE i.user_id = ? AND r.status = 'completed' ORDER BY r.id"
    );
    $st->execute([$userId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $sc = big5_scores_load($pdo, 'rating', (int) $r['id']);
        $flags = trim((string) ($r['quality_flags'] ?? ''));
        $out[] = [
            'relation' => (string) $r['relation'],
            'test_type' => (string) $r['test_type'],
            'flags' => $flags === '' ? [] : explode(',', $flags),
            'domains' => array_map(static fn($x) => (float) $x['pomp'], $sc['domains']),
        ];
    }
    return $out;
}

/**
 * 리포트 9장 데이터 (순수 함수). $self = [요인 => pomp], $ratings = big5_ratings_received()
 *
 * 평가자 2명 이상이면 평균, 1명이면 '한 사람의 시선'. 평가자는 관계 범주로만 보여 준다.
 * 품질 표시가 붙은 평가는 뺀다 — 다만 그러면 하나도 남지 않을 때는 모두 쓴다(excluded = 0).
 *
 * 반환:
 *  count(쓴 평가 수), single, excluded(뺀 수), relations[라벨 => 수], types[test_type => 수],
 *  rows[요인]{
 *    self, others(평균), gap(남 − 나), flag(|gap| ≥ 15),
 *    level 'near'|'some'|'far', direction 'higher'|'lower'|'same'(남이 더 높게·낮게),
 *    band_self, band_others, band_cross(구간이 다름),
 *    min, max, spread(평가자 사이 최대−최소), split(2명 이상이고 spread ≥ 20),
 *    pattern 'consistent'(다르게 보고 남들끼리는 비슷) | 'split'(다르게 보고 남들끼리도 갈림)
 *            | 'hidden_split'(평균은 비슷한데 남들끼리 갈림) | null,
 *    residual(전체 쏠림을 뺀 차이), by_relation[관계 키]{label, n, avg},
 *    rel_gap{hi, lo, hi_avg, lo_avg, diff} | null (관계 2종류 이상이고 관계 평균 차이 ≥ 15)
 *  },
 *  summary{
 *    near[], some[], far[] (요인 코드 — far 는 차이 큰 순),
 *    mean_gap, tilt 'higher'|'lower'|null(다섯 가운데 넷 이상을 같은 쪽으로 8점 이상 다르게 봄), tilt_count,
 *    standout(쏠림을 빼고도 가장 튀는 요인 | null),
 *    order{self_top, self_bottom, others_top, others_bottom, top_same, bottom_same} | null(어느 한쪽이 평평하면 null),
 *    hidden_split[], relation_gaps[] (요인 코드), relation_kinds(관계 종류 수)
 *  }
 */
function big5_others_section(array $self, array $ratings): ?array
{
    if ($ratings === []) {
        return null;
    }
    $clean = array_values(array_filter($ratings, static fn($r) => ($r['flags'] ?? []) === []));
    $excluded = 0;
    if ($clean !== [] && count($clean) < count($ratings)) {
        $excluded = count($ratings) - count($clean);
        $ratings = $clean;
    }
    $n = count($ratings);

    $rel = [];
    $types = [];
    foreach ($ratings as $r) {
        $label = BIG5_RELATIONS[$r['relation']] ?? '기타';
        $rel[$label] = ($rel[$label] ?? 0) + 1;
        $t = (string) ($r['test_type'] ?? '');
        if ($t !== '') {
            $types[$t] = ($types[$t] ?? 0) + 1;
        }
    }
    $kinds = count($rel);

    $rows = [];
    foreach ($self as $d => $mine) {
        $mine = (float) $mine;
        $vals = [];
        $byRel = [];
        foreach ($ratings as $r) {
            if (!isset($r['domains'][$d])) {
                continue;
            }
            $v = (float) $r['domains'][$d];
            $vals[] = $v;
            $key = isset(BIG5_RELATIONS[$r['relation']]) ? (string) $r['relation'] : 'other';
            $byRel[$key][] = $v;
        }
        if ($vals === []) {
            continue;
        }
        $avg = round(array_sum($vals) / count($vals), 1);
        $gap = round($avg - $mine, 1);
        $abs = abs($avg - $mine);
        $far = $abs >= BIG5_GAP_OTHERS;
        $spread = round(max($vals) - min($vals), 1);
        $split = count($vals) >= 2 && $spread >= BIG5_SPREAD_SPLIT;
        $relOut = [];
        foreach (BIG5_RELATIONS as $key => $label) { // 고정 순서
            if (isset($byRel[$key])) {
                $relOut[$key] = ['label' => $label, 'n' => count($byRel[$key]), 'avg' => round(array_sum($byRel[$key]) / count($byRel[$key]), 1)];
            }
        }
        $relGap = null;
        if (count($relOut) >= 2) {
            $hi = $lo = array_key_first($relOut);
            foreach ($relOut as $key => $g) {
                if ($g['avg'] > $relOut[$hi]['avg']) {
                    $hi = $key;
                }
                if ($g['avg'] < $relOut[$lo]['avg']) {
                    $lo = $key;
                }
            }
            $diff = round($relOut[$hi]['avg'] - $relOut[$lo]['avg'], 1);
            if ($diff >= BIG5_GAP_OTHERS) {
                $relGap = ['hi' => $hi, 'lo' => $lo, 'hi_avg' => $relOut[$hi]['avg'], 'lo_avg' => $relOut[$lo]['avg'], 'diff' => $diff];
            }
        }
        $bs = big5_band($mine);
        $bo = big5_band($avg);
        $rows[$d] = [
            'self' => $mine, 'others' => $avg, 'gap' => $gap, 'flag' => $far,
            'level' => $far ? 'far' : ($abs >= BIG5_GAP_NEAR ? 'some' : 'near'),
            'direction' => $gap > 0 ? 'higher' : ($gap < 0 ? 'lower' : 'same'),
            'band_self' => $bs, 'band_others' => $bo, 'band_cross' => $bs !== $bo,
            'min' => min($vals), 'max' => max($vals), 'spread' => $spread, 'split' => $split,
            'pattern' => count($vals) < 2 ? null : ($far ? ($split ? 'split' : 'consistent') : ($split ? 'hidden_split' : null)),
            'residual' => 0.0, 'by_relation' => $relOut, 'rel_gap' => $relGap,
        ];
    }

    // 요약: 세 묶음, 전체 쏠림(수준 차이)과 그것을 뺀 차이(모양 차이), 높낮이 순서
    $sum = ['near' => [], 'some' => [], 'far' => [], 'mean_gap' => 0.0, 'tilt' => null, 'tilt_count' => 0, 'standout' => null,
        'order' => null, 'hidden_split' => [], 'relation_gaps' => [], 'relation_kinds' => $kinds];
    if ($rows !== []) {
        $up = $down = 0;
        foreach ($rows as $d => $row) {
            $sum[$row['level']][] = $d;
            if ($row['gap'] >= BIG5_GAP_NEAR) {
                $up++;
            } elseif ($row['gap'] <= -BIG5_GAP_NEAR) {
                $down++;
            }
            if ($row['pattern'] === 'hidden_split') {
                $sum['hidden_split'][] = $d;
            }
            if ($row['rel_gap'] !== null) {
                $sum['relation_gaps'][] = $d;
            }
        }
        usort($sum['far'], static fn($a, $b) => abs($rows[$b]['gap']) <=> abs($rows[$a]['gap']));
        $mean = array_sum(array_column($rows, 'gap')) / count($rows);
        $sum['mean_gap'] = round($mean, 1);
        foreach ($rows as $d => $row) {
            $rows[$d]['residual'] = round($row['gap'] - $mean, 1);
        }
        if (count($rows) >= 5 && max($up, $down) >= 4) {
            $sum['tilt'] = $up > $down ? 'higher' : 'lower';
            $sum['tilt_count'] = max($up, $down);
            // 쏠림을 빼고도 남는 차이가 가장 큰 곳 (8점 이상일 때만)
            $best = null;
            foreach ($rows as $d => $row) {
                if (abs($row['residual']) >= BIG5_GAP_NEAR && ($best === null || abs($row['residual']) > abs($rows[$best]['residual']))) {
                    $best = $d;
                }
            }
            $sum['standout'] = $best;
        }
        $mineAll = array_map(static fn($r) => $r['self'], $rows);
        $theirs = array_map(static fn($r) => $r['others'], $rows);
        // 어느 한쪽이 평평하면(가장 높은 곳과 낮은 곳이 8점도 안 벌어지면) 순서를 말하지 않는다
        if (count($rows) >= 3 && max($mineAll) - min($mineAll) >= BIG5_GAP_NEAR && max($theirs) - min($theirs) >= BIG5_GAP_NEAR) {
            $st = array_search(max($mineAll), $mineAll, true);
            $sb = array_search(min($mineAll), $mineAll, true);
            $ot = array_search(max($theirs), $theirs, true);
            $ob = array_search(min($theirs), $theirs, true);
            $sum['order'] = ['self_top' => $st, 'self_bottom' => $sb, 'others_top' => $ot, 'others_bottom' => $ob,
                'top_same' => $st === $ot, 'bottom_same' => $sb === $ob];
        }
    }
    return ['count' => $n, 'single' => $n === 1, 'excluded' => $excluded, 'relations' => $rel, 'types' => $types,
        'rows' => $rows, 'summary' => $sum];
}

/** 같은 사용자의 바로 앞 완료 검사 (재검사 비교용). 없으면 null */
function big5_session_previous(PDO $pdo, array $session): ?array
{
    $st = $pdo->prepare(
        "SELECT * FROM big5_sessions WHERE user_id = ? AND status = 'completed'
            AND (completed_at < ? OR (completed_at = ? AND id < ?))
          ORDER BY completed_at DESC, id DESC LIMIT 1"
    );
    $at = (string) $session['completed_at'];
    $st->execute([(int) $session['user_id'], $at, $at, (int) $session['id']]);
    $row = $st->fetch();
    return is_array($row) ? $row : null;
}

/**
 * 재검사 비교 (순수 함수). $before·$after = big5_scores_load() 결과. 하위 요인은 둘 다 있을 때만.
 */
function big5_compare_scores(array $before, array $after): array
{
    $mk = static function (array $a, array $b): array {
        $rows = [];
        foreach ($b as $code => $x) {
            if (!isset($a[$code])) {
                continue;
            }
            $diff = round((float) $x['pomp'] - (float) $a[$code]['pomp'], 1);
            $rows[$code] = ['before' => (float) $a[$code]['pomp'], 'after' => (float) $x['pomp'], 'diff' => $diff, 'flag' => abs($diff) >= BIG5_GAP_RETEST];
        }
        return $rows;
    };
    return ['domains' => $mk($before['domains'], $after['domains']), 'facets' => $mk($before['facets'], $after['facets'])];
}

/** 어떤 검사에 저장한 성장 행동 key 목록 */
function big5_growth_keys(PDO $pdo, int $userId, int $sessionId): array
{
    $st = $pdo->prepare('SELECT action_key FROM big5_growth_choices WHERE user_id = ? AND session_id = ? ORDER BY id');
    $st->execute([$userId, $sessionId]);
    return array_column($st->fetchAll(), 'action_key');
}

/** 성장 행동 key → 문장 */
function big5_growth_text(array $growthContent, string $key): string
{
    foreach ($growthContent as $dirs) {
        foreach (['grow', 'shadow'] as $dir) {
            foreach ($dirs[$dir]['actions'] ?? [] as $a) {
                if (($a['key'] ?? '') === $key) {
                    return (string) $a['text'];
                }
            }
        }
    }
    return '';
}
