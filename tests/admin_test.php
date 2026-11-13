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
$uid = (int) $pdo->query("SELECT id FROM sbm_users WHERE username = 'user'")->fetchColumn();
$items = big5_items('full120');

/** 같은 값인지 — 다르면 실제 값을 함께 보인다 */
function adm_eq(mixed $expected, mixed $actual, string $name): void
{
    t_ok($expected === $actual, $name . ($expected === $actual ? '' : ' — 실제 ' . json_encode($actual, JSON_UNESCAPED_UNICODE)));
}

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

// ── 관리자 '결과지' 목록·보기 (삭제한 계정의 보존 기록 포함)
$cnt = static fn(string $where): int => (int) $pdo->query("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed'" . $where)->fetchColumn();
$rs = big5_admin_results($pdo, ['type' => '', 'owner' => '', 'q' => ''], 1);
t_ok($rs['total'] === $cnt('') && count($rs['rows']) === min($rs['total'], BIG5_ADMIN_RESULTS_PER) && $rs['rows'][0]['id'] > $rs['rows'][1]['id'], '결과지 목록: 완료 검사 전부, 최근 순, 한 쪽 ' . BIG5_ADMIN_RESULTS_PER . '건');
t_ok(count($rs['rows'][0]['domains']) === 5 && $rs['rows'][0]['username'] === 'user' && !$rs['rows'][0]['retired'] && is_array($rs['rows'][0]['quality_flags']), '결과지 목록: 요인 점수 5개·계정·응답 품질');
$mini = big5_admin_results($pdo, ['type' => 'mini20', 'owner' => '', 'q' => ''], 1);
t_ok($mini['total'] === $cnt(" AND test_type = 'mini20'") && $mini['total'] > 0 && $mini['rows'][0]['test_type'] === 'mini20' && big5_admin_results($pdo, ['type' => '', 'owner' => 'deleted', 'q' => ''], 1)['total'] === 0, '결과지 목록: 종류·계정 거르기');
t_ok(big5_admin_results($pdo, ['type' => '', 'owner' => '', 'q' => 'us%'], 1)['total'] === 0 && big5_admin_results($pdo, ['type' => '', 'owner' => '', 'q' => 'use'], 1)['total'] === $rs['total'], '결과지 목록: 아이디 찾기(와일드카드 글자는 글자 그대로)');
$lastPage = big5_admin_results($pdo, ['type' => '', 'owner' => '', 'q' => ''], 9999);
t_ok($lastPage['page'] === $lastPage['pages'] && $lastPage['rows'] !== [], '결과지 목록: 범위를 넘는 쪽은 마지막 쪽으로');
// 0.15.0: 끝낸 날 필터(그날 포함·기간 밖 0건·틀린 값 무시) — 인자가 없으면 예전과 같은 결과
$today = substr(big5_now(), 0, 10);
$f = static fn(string $from, string $to, string $type = ''): int => big5_admin_results($pdo, ['type' => $type, 'owner' => '', 'q' => '', 'from' => $from, 'to' => $to], 1)['total'];
$pdo->prepare('UPDATE big5_sessions SET completed_at = ? WHERE id = ?')->execute(['2020-03-05 23:59:59', $rs['rows'][0]['id']]);
$pdo->prepare('UPDATE big5_sessions SET completed_at = ? WHERE id = ?')->execute(['2020-03-06 00:00:00', $rs['rows'][1]['id']]);
adm_eq([$rs['total'] - 2, 1, 1, 2, 0], [$f($today, $today), $f('2020-03-05', '2020-03-05'), $f('2020-03-06', '2020-03-06'), $f('2020-03-01', '2020-03-31'), $f('2000-01-01', '2000-01-02')], '검사 기록: 끝낸 날 필터(그날 포함)·기간 밖 0건');
adm_eq([$rs['total'], $rs['total'] - 2, 2], [$f("x' OR 1=1", '2026-13-40'), $f($today, ''), $f('', '2020-12-31')], '검사 기록: 틀린 날짜는 무시·한쪽만 주어도 됨');
adm_eq($mini['total'], $f($today, $today, 'mini20'), '검사 기록: 끝낸 날 + 대상 함께 거르기');
adm_eq(['2026-02-28', '', '', ''], [big5_admin_date('2026-02-28'), big5_admin_date('2026-02-30'), big5_admin_date('20260228'), big5_admin_date('')], '날짜 값 검사');
$pdo->prepare('UPDATE big5_sessions SET completed_at = ? WHERE id IN (?, ?)')->execute([big5_now(), $rs['rows'][0]['id'], $rs['rows'][1]['id']]);
$firstId = $rs['rows'][0]['id'];
$pdo->prepare('UPDATE sbm_users SET username = ? WHERE id = ?')->execute(['~del' . $uid, $uid]);
$del = big5_admin_results($pdo, ['type' => '', 'owner' => 'deleted', 'q' => ''], 1);
$as = big5_admin_session_get($pdo, $firstId);
t_ok($del['total'] === $rs['total'] && $del['rows'][0]['retired'] && $as !== null && $as['owner_retired'] && $as['owner_label'] === '삭제한 계정', '삭제한 계정의 기록도 목록·보기에 나옴');
$pdo->prepare('UPDATE sbm_users SET username = ? WHERE id = ?')->execute(['user', $uid]);
t_ok(big5_admin_session_get($pdo, $firstId)['owner_label'] === 'user' && big5_admin_session_get($pdo, 999999) === null, '관리자 보기: 계정 표시·없는 검사는 null');

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
// 통합 관리자 첫 화면 숫자 훅(0.12.0) — 대시보드 집계와 같은 수, 숫자만(아이디·이름 없음)
require_once dirname(__DIR__) . '/includes/sbm_hook.php';
$c = big5_admin_counts($pdo);
$hs = big5_sbm_admin_summary($pdo, big5_now(-7 * 86400), '/big5/');
$tiles = array_column($hs['groups'][0]['tiles'], 'value', 'label');
t_ok($hs['version'] === BIG5_VERSION && $hs['groups'][0]['title'] === '다이얼', '관리자 요약 훅: 버전·묶음 이름');
t_ok($tiles['정식 검사 완료(120문항)'] === (string) $c['full'] && $tiles['간이 검사 완료(20문항)'] === (string) $c['mini'] && $tiles['최근 7일 완료'] === (string) $c['last7'] && $tiles['타인 평가 완료'] === (string) $c['ratings'], '관리자 요약 훅: 대시보드와 같은 수');
t_ok($hs['groups'][0]['links'][0]['url'] === '/big5/admin/', '관리자 요약 훅: 다이얼 관리 링크');
t_ok(!preg_match('/user|sonbm|사용자|손병목/', json_encode($hs, JSON_UNESCAPED_UNICODE)), '관리자 요약 훅: 아이디·이름 없음');
adm_eq(['다이얼 관리', '검사 기록', '분석', 'CSV 내려받기'], array_column($hs['groups'][0]['links'], 'label'), '관리자 요약 훅: 바로 가기 이름(규격 낱말)');
// 통합 관리자 '최근 끝낸 검사' 훅(0.15.0) — 끝낸 때·대상·결과지 주소만(아이디·식별자 없음), 타인 평가 없음
$rec = big5_sbm_admin_recent($pdo, 2, '/big5/');
adm_eq(2, count($rec), '최근 끝낸 검사: 건수 제한');
t_ok(array_keys($rec[0]) === ['at', 'kind', 'url'] && in_array($rec[0]['kind'], ['정식 120문항', '간이 20문항'], true) && preg_match('#^/big5/admin/report\.php\?id=\d+$#', $rec[0]['url']) === 1, '최근 끝낸 검사: 끝낸 때·대상·결과지 주소만');
t_ok($rec[0]['at'] >= $rec[1]['at'] && $rec[0]['url'] === '/big5/admin/report.php?id=' . big5_admin_results($pdo, ['type' => '', 'owner' => '', 'q' => ''], 1)['rows'][0]['id'], '최근 끝낸 검사: 최신순(검사 기록 첫 줄과 같음)');
$allRec = big5_sbm_admin_recent($pdo, 50, '/big5/');
t_ok(in_array('간이 20문항', array_column($allRec, 'kind'), true) || count($allRec) === 50, '최근 끝낸 검사: 대상 이름');
t_ok(!preg_match('/user|sonbm|사용자|손병목/', json_encode($allRec, JSON_UNESCAPED_UNICODE)), '최근 끝낸 검사: 아이디·이름 없음');

t_ok($bad === [], '/admin 모든 파일 첫 문장이 admin_check' . ($bad ? ' — 어긋남: ' . implode(', ', $bad) : ''));

t_done();
