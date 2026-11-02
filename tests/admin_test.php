<?php
/**
 * 7단계 검증 — 관리자 집계·분포, 규준(300건 미만 잠금·계산·비활성 저장·활성화), 익명 CSV(아이디·이름 없음, HMAC, 품질 제외).
 * 실행: php tests/admin_test.php
 */
declare(strict_types=1);

putenv('BIG5_DB_QUIET=1');
define('BIG5_EXPORT_SALT', 'test-salt-0123456789abcdef');
require dirname(__DIR__) . '/includes/db_config.php';
require dirname(__DIR__) . '/includes/util.php';
require dirname(__DIR__) . '/includes/big5_schema.php';
require dirname(__DIR__) . '/includes/test_session.php';
require dirname(__DIR__) . '/includes/admin_stats.php';
require __DIR__ . '/lib.php';

$pdo = t_pdo();
big5_ensure_schema($pdo);
big5_seed_accounts($pdo);
$uid = (int) $pdo->query("SELECT id FROM big5_users WHERE username = 'user'")->fetchColumn();
$items = big5_items('full120');

/** 완료 검사 하나 (모든 문항 같은 값 $v, 플래그 지정) */
function seed_done(PDO $pdo, int $uid, string $type, int $v, ?string $flags): int
{
    $id = big5_session_start($pdo, $uid, $type);
    $set = big5_items($type);
    $resp = [];
    foreach ($set['items'] as $it) {
        $resp[(int) $it['no']] = $v;
        big5_response_save($pdo, 'session', $id, (int) $it['no'], $v);
    }
    big5_scores_save($pdo, 'session', $id, big5_score($set['items'], $resp));
    $pdo->prepare("UPDATE big5_sessions SET status = 'completed', completed_at = ?, duration_sec = 900, quality_flags = ?, scoring_version = '1.0' WHERE id = ?")
        ->execute([big5_now(), $flags, $id]);
    return $id;
}

seed_done($pdo, $uid, 'full120', 3, null);
seed_done($pdo, $uid, 'full120', 3, 'straightline');
seed_done($pdo, $uid, 'mini20', 3, null);
$c = big5_admin_counts($pdo);
t_ok($c['full'] === 2 && $c['mini'] === 1 && $c['flagged'] === 1 && $c['by_flag'] === ['straightline' => 1], '집계: 정식 2·간이 1·플래그 1');
t_ok($c['flag_ratio'] === 33.3, '품질 플래그 비율 33.3%');
$all = big5_admin_distribution($pdo, false);
$clean = big5_admin_distribution($pdo, true);
t_ok($all['C']['n'] === 3 && $clean['C']['n'] === 2 && $all['C']['mean'] === 50.0 && $all['C']['bins'][5] === 3, '분포: 전체 3건, 플래그 제외 2건, 50점 칸');
t_ok(str_contains(big5_histogram_svg($all['C']['bins'], '#2F6F5E', '성실성<'), '성실성&lt;'), '분포 SVG 글자 이스케이프');

// ── 규준: 300건 미만 잠금
t_ok(big5_norms_eligible($pdo) === 1, '규준 대상: 품질 통과 정식 검사만 (1건)');
$r = big5_norms_compute($pdo, 'site-test');
t_ok(!$r['ok'] && str_contains($r['error'], '300'), '300건 미만이면 계산하지 않음');
t_ok((int) $pdo->query('SELECT COUNT(*) FROM big5_norms')->fetchColumn() === 0, '규준 표 비어 있음');

// 정식 299건 더 (값을 돌려 가며) → 정확히 300건
for ($i = 0; $i < 299; $i++) {
    seed_done($pdo, $uid, 'full120', ($i % 5) + 1, null);
}
t_ok(big5_norms_eligible($pdo) === 300, '규준 대상 300건');
$r = big5_norms_compute($pdo, 'site-test');
t_ok($r['ok'] && $r['n'] === 300 && $r['rows'] === 35, '300건 → 요인 5 + 하위 요인 30 규준 계산');
$row = $pdo->query("SELECT n, mean_raw, sd_raw, is_active FROM big5_norms WHERE level = 'domain' AND code = 'C'")->fetch();
// 기대 원점수: 모든 문항이 같은 값 v 인 검사의 성실성 원점수 (역채점 문항은 6 − v 로 들어간다)
$rawOf = static function (int $v) use ($items): int {
    $resp = [];
    foreach ($items['items'] as $it) {
        $resp[(int) $it['no']] = $v;
    }
    return big5_score($items['items'], $resp)['domains']['C']['raw'];
};
$xs = array_merge([$rawOf(3)], array_map(static fn($i) => $rawOf(($i % 5) + 1), range(0, 298)));
$mean = array_sum($xs) / 300;
$sd = sqrt(array_sum(array_map(static fn($x) => ($x - $mean) ** 2, $xs)) / 299);
t_ok((int) $row['n'] === 300 && abs((float) $row['mean_raw'] - round($mean, 2)) < 0.01 && abs((float) $row['sd_raw'] - round($sd, 2)) < 0.01, '성실성 원점수 평균·표준편차 손 계산과 일치');
t_ok((int) $row['is_active'] === 0, '계산한 규준은 비활성으로 저장 (기본값)');
big5_norm_activate($pdo, 'site-test');
t_ok((int) $pdo->query('SELECT MIN(is_active) FROM big5_norms')->fetchColumn() === 1, '활성화');
big5_norm_activate($pdo, null);
t_ok((int) $pdo->query('SELECT MAX(is_active) FROM big5_norms')->fetchColumn() === 0, '다시 비활성');

// ── 익명 CSV
$rows = big5_export_rows($pdo, 'self', 'full120', false);
$header = $rows[0];
t_ok(count($rows) === 302, 'CSV: 머리 줄 + 정식 301건');
t_ok(!array_intersect(['username', 'display_name', 'user_id', 'id', 'session_id', 'name'], $header), 'CSV 열에 아이디·이름·번호 없음');
t_ok(in_array('pomp_C', $header, true) && in_array('pomp_C6', $header, true) && in_array('q120', $header, true), 'CSV: 요인·하위 요인·문항 응답 열');
$flat = implode(',', array_merge(...$rows));
t_ok(!str_contains($flat, 'user') && !str_contains($flat, '사용자') && !str_contains($flat, 'sonbm'), 'CSV 값에 아이디·이름 없음');
t_ok($rows[1][0] === hash_hmac('sha256', (string) $uid, BIG5_EXPORT_SALT) && $rows[1][0] !== hash('sha256', (string) $uid), '응답자 = HMAC 해시 (단순 해시 아님)');
t_ok(preg_match('/^\d{4}-\d{2}-\d{2}$/', $rows[1][6]) === 1, '완료일은 날짜만');
$cleanRows = big5_export_rows($pdo, 'self', 'full120', true);
t_ok(count($cleanRows) === 301 && !str_contains(implode(',', array_column(array_slice($cleanRows, 1), 8)), 'straightline'), '품질 플래그 제외 옵션: 300건, 플래그 없음');
$mini = big5_export_rows($pdo, 'self', 'mini20', false);
t_ok(count($mini) === 2 && !in_array('pomp_C1', $mini[0], true) && in_array('q20', $mini[0], true), '간이 CSV: 하위 요인 열 없음');

// ── /admin 파일 규칙: declare 바로 다음 첫 문장이 admin_check (login.php 제외)
$bad = [];
foreach (glob(dirname(__DIR__) . '/admin/*.php') as $f) {
    if (basename($f) === 'login.php') {
        continue;
    }
    $lines = array_values(array_filter(array_map('trim', file($f)), static fn($l) => $l !== '' && $l !== '<?php'));
    if (($lines[0] ?? '') !== 'declare(strict_types=1);' || ($lines[1] ?? '') !== "require_once '../includes/admin_check.php';") {
        $bad[] = basename($f);
    }
}
t_ok($bad === [], '/admin 모든 파일 첫 문장이 admin_check' . ($bad ? ' — 어긋남: ' . implode(', ', $bad) : ''));

t_done();
