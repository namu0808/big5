<?php
/**
 * 관리자 통계·규준·내보내기 (PLAN 3.5, 4.4, 8장)
 *  - 품질 플래그가 있는 응답은 통계·규준에서 뺄 수 있다
 *  - 규준: 품질 플래그 없는 정식 검사 완료가 300건 이상일 때만 계산. 계산해도 기본은 비활성(is_active=0)
 *  - 내보내기: 아이디·이름 없음, 응답자 식별값은 hash_hmac('sha256', user_id, BIG5_EXPORT_SALT) (승인된 변경 6)
 */
declare(strict_types=1);

require_once __DIR__ . '/items.php';
require_once __DIR__ . '/scoring.php';

const BIG5_NORM_MIN = 300;
const BIG5_QUALITY_FLAGS = ['fast', 'straightline', 'inconsistent'];

/** 응답 수 집계 */
function big5_admin_counts(PDO $pdo): array
{
    $one = static function (string $sql, array $p = []) use ($pdo): int {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    };
    $c = [
        'users' => $one("SELECT COUNT(*) FROM sbm_users WHERE role = 'user' AND username NOT LIKE '~%'"),
        'users_inactive' => $one("SELECT COUNT(*) FROM sbm_users WHERE role = 'user' AND is_active = 0 AND username NOT LIKE '~%'"),
        'users_retired' => $one("SELECT COUNT(*) FROM sbm_users WHERE username LIKE '~%'"),
        'full' => $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND test_type = 'full120'"),
        'mini' => $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND test_type = 'mini20'"),
        'in_progress' => $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'in_progress'"),
        'ratings' => $one("SELECT COUNT(*) FROM big5_ratings WHERE status = 'completed'"),
        'last7' => $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND completed_at >= ?", [big5_now(-7 * 86400)]),
        'flagged' => $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND quality_flags IS NOT NULL AND quality_flags <> ''"),
    ];
    $done = $c['full'] + $c['mini'];
    $c['flag_ratio'] = $done > 0 ? round($c['flagged'] / $done * 100, 1) : 0.0;
    $c['by_flag'] = [];
    $st = $pdo->query("SELECT quality_flags FROM big5_sessions WHERE status = 'completed' AND quality_flags IS NOT NULL AND quality_flags <> ''");
    foreach ($st->fetchAll() as $r) {
        foreach (explode(',', (string) $r['quality_flags']) as $f) {
            $c['by_flag'][$f] = ($c['by_flag'][$f] ?? 0) + 1;
        }
    }
    return $c;
}

/**
 * 요인별 점수 분포 (본인 검사 완료분). 구간 10점 × 10칸, 평균.
 * @return array<string, array{n:int, mean:float, bins:int[]}>
 */
function big5_admin_distribution(PDO $pdo, bool $excludeFlagged, ?string $testType = null): array
{
    $sql = "SELECT sc.code, sc.pomp FROM big5_scores sc JOIN big5_sessions s ON s.id = sc.owner_id
             WHERE sc.owner_type = 'session' AND sc.level = 'domain' AND s.status = 'completed'";
    $p = [];
    if ($excludeFlagged) {
        $sql .= " AND (s.quality_flags IS NULL OR s.quality_flags = '')";
    }
    if ($testType !== null) {
        $sql .= ' AND s.test_type = ?';
        $p[] = $testType;
    }
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $out = [];
    foreach (big5_domain_order() as $d) {
        $out[$d] = ['n' => 0, 'mean' => 0.0, 'bins' => array_fill(0, 10, 0), 'sum' => 0.0];
    }
    foreach ($st->fetchAll() as $r) {
        $d = (string) $r['code'];
        if (!isset($out[$d])) {
            continue;
        }
        $v = (float) $r['pomp'];
        $out[$d]['n']++;
        $out[$d]['sum'] += $v;
        $out[$d]['bins'][min(9, (int) floor($v / 10))]++;
    }
    foreach ($out as $d => $x) {
        $out[$d]['mean'] = $x['n'] > 0 ? round($x['sum'] / $x['n'], 1) : 0.0;
        unset($out[$d]['sum']);
    }
    return $out;
}

/** 분포 막대 SVG (구간 10칸) */
function big5_histogram_svg(array $bins, string $color, string $label): string
{
    $w = 220;
    $hgt = 70;
    $max = max(1, max($bins));
    $bw = $w / 10;
    $svg = '<svg class="hist" viewBox="0 0 ' . $w . ' ' . ($hgt + 14) . '" width="100%" role="img" aria-label="' . h($label . ' 점수 분포') . '" xmlns="http://www.w3.org/2000/svg">';
    foreach ($bins as $i => $n) {
        $bh = $n === 0 ? 0 : max(2, $n / $max * $hgt);
        $svg .= '<rect x="' . number_format($i * $bw + 1, 1, '.', '') . '" y="' . number_format($hgt - $bh, 1, '.', '') . '" width="' . number_format($bw - 2, 1, '.', '')
            . '" height="' . number_format($bh, 1, '.', '') . '" fill="' . h($color) . '"><title>' . ($i * 10) . '~' . ($i * 10 + ($i === 9 ? 10 : 9.9)) . ': ' . (int) $n . '건</title></rect>';
    }
    $svg .= '<text x="0" y="' . ($hgt + 12) . '" class="hist-axis">0</text><text x="' . ($w / 2) . '" y="' . ($hgt + 12) . '" class="hist-axis" text-anchor="middle">50</text><text x="' . $w . '" y="' . ($hgt + 12) . '" class="hist-axis" text-anchor="end">100</text>';
    return $svg . '</svg>';
}

/** 규준 계산 대상: 품질 플래그 없는 정식 검사 완료 */
function big5_norms_eligible(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND test_type = 'full120' AND (quality_flags IS NULL OR quality_flags = '')")->fetchColumn();
}

/**
 * 규준 계산·저장 (원점수 평균·표준편차, 요인 5 + 하위 요인 30). 300건 미만이면 계산하지 않는다.
 * 같은 이름의 규준 세트가 있으면 바꿔 쓴다. 저장한 세트는 비활성.
 * @return array{ok: bool, n?: int, rows?: int, error?: string}
 */
function big5_norms_compute(PDO $pdo, string $normSet): array
{
    $n = big5_norms_eligible($pdo);
    if ($n < BIG5_NORM_MIN) {
        return ['ok' => false, 'error' => '품질 점검을 통과한 정식 검사 완료가 ' . BIG5_NORM_MIN . "건 이상이어야 합니다(지금 {$n}건)."];
    }
    $st = $pdo->query(
        "SELECT sc.level, sc.code, sc.raw_score FROM big5_scores sc JOIN big5_sessions s ON s.id = sc.owner_id
          WHERE sc.owner_type = 'session' AND s.status = 'completed' AND s.test_type = 'full120'
            AND (s.quality_flags IS NULL OR s.quality_flags = '')"
    );
    $vals = [];
    foreach ($st->fetchAll() as $r) {
        $vals[$r['level'] . '|' . $r['code']][] = (int) $r['raw_score'];
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM big5_norms WHERE norm_set = ?')->execute([$normSet]);
        $ins = $pdo->prepare('INSERT INTO big5_norms (norm_set, level, code, n, mean_raw, sd_raw, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?)');
        foreach ($vals as $key => $xs) {
            [$level, $code] = explode('|', $key);
            $cnt = count($xs);
            $mean = array_sum($xs) / $cnt;
            $var = $cnt > 1 ? array_sum(array_map(static fn($x) => ($x - $mean) ** 2, $xs)) / ($cnt - 1) : 0.0;
            $ins->execute([$normSet, $level, $code, $cnt, round($mean, 2), round(sqrt($var), 2), big5_now()]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'n' => $n, 'rows' => count($vals)];
}

/** 규준 세트 목록 */
function big5_norm_sets(PDO $pdo): array
{
    return $pdo->query('SELECT norm_set, MAX(n) AS n, MAX(is_active) AS active, MAX(created_at) AS created_at, COUNT(*) AS rows_count FROM big5_norms GROUP BY norm_set ORDER BY created_at DESC')->fetchAll();
}

/** 규준 세트 하나만 활성(또는 모두 비활성) */
function big5_norm_activate(PDO $pdo, ?string $normSet): void
{
    $pdo->beginTransaction();
    $pdo->exec('UPDATE big5_norms SET is_active = 0');
    if ($normSet !== null) {
        $pdo->prepare('UPDATE big5_norms SET is_active = 1 WHERE norm_set = ?')->execute([$normSet]);
    }
    $pdo->commit();
}

/** 응답자 익명 식별값 */
function big5_export_hash(int $userId): string
{
    return hash_hmac('sha256', (string) $userId, (string) BIG5_EXPORT_SALT);
}

/**
 * 익명 CSV 행. 본인 검사(self) 또는 받은 타인 평가(ratings).
 * 열: 응답자 해시, 검사 종류, 문항·채점 버전, 완료일(날짜만), 소요 초, 품질 플래그, 요인 0~100 ×5, 하위 요인 ×30(정식), 문항 응답
 * 아이디·이름·세션 번호는 넣지 않는다.
 */
function big5_export_rows(PDO $pdo, string $kind, string $testType, bool $excludeFlagged): array
{
    $codes = big5_domain_order();
    $items = big5_items($testType);
    $facetCodes = $testType === 'full120' ? array_keys($items['facets']) : [];
    $itemNos = array_map(static fn($it) => (int) $it['no'], $items['items']);
    $header = array_merge(
        ['respondent', 'kind', 'relation', 'test_type', 'item_version', 'scoring_version', 'completed_date', 'duration_sec', 'quality_flags'],
        array_map(static fn($c) => 'pomp_' . $c, $codes),
        array_map(static fn($c) => 'pomp_' . $c, $facetCodes),
        array_map(static fn($n) => 'q' . $n, $itemNos)
    );
    if ($kind === 'ratings') {
        $sql = "SELECT r.id, i.user_id, i.relation, r.test_type, r.item_version, r.scoring_version, r.completed_at, r.duration_sec, r.quality_flags
                  FROM big5_ratings r JOIN big5_invites i ON i.id = r.invite_id WHERE r.status = 'completed' AND r.test_type = ?";
        $owner = 'rating';
    } else {
        $sql = "SELECT id, user_id, '' AS relation, test_type, item_version, scoring_version, completed_at, duration_sec, quality_flags
                  FROM big5_sessions WHERE status = 'completed' AND test_type = ?";
        $owner = 'session';
    }
    if ($excludeFlagged) {
        $sql .= ($kind === 'ratings' ? ' AND (r.quality_flags IS NULL OR r.quality_flags = \'\')' : " AND (quality_flags IS NULL OR quality_flags = '')");
    }
    $sql .= ' ORDER BY ' . ($kind === 'ratings' ? 'r.completed_at' : 'completed_at');
    $st = $pdo->prepare($sql);
    $st->execute([$testType]);
    $rows = [$header];
    foreach ($st->fetchAll() as $r) {
        $sc = big5_scores_load($pdo, $owner, (int) $r['id']);
        $resp = big5_responses_load($pdo, $owner, (int) $r['id']);
        $row = [
            big5_export_hash((int) $r['user_id']),
            $kind === 'ratings' ? 'other' : 'self',
            (string) $r['relation'],
            (string) $r['test_type'],
            (string) $r['item_version'],
            (string) ($r['scoring_version'] ?? ''),
            substr((string) $r['completed_at'], 0, 10),
            (string) ($r['duration_sec'] ?? ''),
            (string) ($r['quality_flags'] ?? ''),
        ];
        foreach ($codes as $c) {
            $row[] = isset($sc['domains'][$c]) ? number_format((float) $sc['domains'][$c]['pomp'], 1, '.', '') : '';
        }
        foreach ($facetCodes as $c) {
            $row[] = isset($sc['facets'][$c]) ? number_format((float) $sc['facets'][$c]['pomp'], 1, '.', '') : '';
        }
        foreach ($itemNos as $n) {
            $row[] = isset($resp[$n]) ? (string) $resp[$n] : '';
        }
        $rows[] = $row;
    }
    return $rows;
}

const BIG5_ADMIN_RESULTS_PER = 50;

/**
 * 관리자 '결과지' 목록 — 완료한 본인 검사를 최근 순으로(삭제한 계정의 보존 기록 포함).
 * $filter: type 'full120'|'mini20'|''(전체), owner 'active'|'deleted'|''(전체), q 아이디 일부
 * → ['total' => 전체 건수, 'pages' => 쪽수, 'page' => 지금 쪽, 'rows' => [...]]
 *   rows[]{id, user_id, username, retired(bool), test_type, completed_at, quality_flags[], ratings(받은 평가 수), domains[요인 => pomp]}
 */
function big5_admin_results(PDO $pdo, array $filter, int $page): array
{
    $where = ["s.status = 'completed'"];
    $args = [];
    if (in_array($filter['type'] ?? '', ['full120', 'mini20'], true)) {
        $where[] = 's.test_type = ?';
        $args[] = $filter['type'];
    }
    if (($filter['owner'] ?? '') === 'deleted') {
        $where[] = "u.username LIKE '~%'";
    } elseif (($filter['owner'] ?? '') === 'active') {
        $where[] = "u.username NOT LIKE '~%'";
    }
    $q = trim((string) ($filter['q'] ?? ''));
    if ($q !== '') {
        $where[] = "u.username LIKE ? ESCAPE '!'";
        $args[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
    }
    $cond = implode(' AND ', $where);
    $st = $pdo->prepare("SELECT COUNT(*) FROM big5_sessions s JOIN sbm_users u ON u.id = s.user_id WHERE {$cond}");
    $st->execute($args);
    $total = (int) $st->fetchColumn();
    $pages = max(1, (int) ceil($total / BIG5_ADMIN_RESULTS_PER));
    $page = max(1, min($pages, $page));
    $st = $pdo->prepare(
        "SELECT s.id, s.user_id, s.test_type, s.completed_at, s.quality_flags, u.username,
                (SELECT COUNT(*) FROM big5_ratings r JOIN big5_invites i ON i.id = r.invite_id
                  WHERE i.user_id = s.user_id AND r.status = 'completed') AS ratings
           FROM big5_sessions s JOIN sbm_users u ON u.id = s.user_id
          WHERE {$cond} ORDER BY s.completed_at DESC, s.id DESC LIMIT ? OFFSET ?"
    );
    $n = 1;
    foreach ($args as $a) {
        $st->bindValue($n++, $a);
    }
    $st->bindValue($n++, BIG5_ADMIN_RESULTS_PER, PDO::PARAM_INT);
    $st->bindValue($n, ($page - 1) * BIG5_ADMIN_RESULTS_PER, PDO::PARAM_INT);
    $st->execute();
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $flags = trim((string) ($r['quality_flags'] ?? ''));
        $rows[(int) $r['id']] = [
            'id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'username' => (string) $r['username'],
            'retired' => str_starts_with((string) $r['username'], '~'),
            'test_type' => (string) $r['test_type'], 'completed_at' => (string) $r['completed_at'],
            'quality_flags' => $flags === '' ? [] : explode(',', $flags), 'ratings' => (int) $r['ratings'], 'domains' => [],
        ];
    }
    if ($rows !== []) {
        $in = implode(',', array_fill(0, count($rows), '?'));
        $sc = $pdo->prepare("SELECT owner_id, code, pomp FROM big5_scores WHERE owner_type = 'session' AND level = 'domain' AND owner_id IN ({$in})");
        $sc->execute(array_keys($rows));
        foreach ($sc->fetchAll() as $x) {
            $rows[(int) $x['owner_id']]['domains'][(string) $x['code']] = (float) $x['pomp'];
        }
    }
    return ['total' => $total, 'pages' => $pages, 'page' => $page, 'rows' => array_values($rows)];
}
