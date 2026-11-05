<?php
/**
 * 개발 전용 — 리포트 확인용 샘플 검사를 개발 DB 에 만든다 (사용자 'user').
 *  all3: 모든 문항 3점 / random: 무작위(고정 시드) / extreme: C·E·O 높게, A·N 낮게 / mini: 간이 검사 무작위 / nhigh: N·A 높게, 나머지 낮게
 * 실행: BIG5_DB_CONFIG=doc/dev-db-sqlite.php php doc/dev-seed-reports.php  → JSON {이름: 검사 id}
 */
declare(strict_types=1);

putenv('BIG5_DB_QUIET=1');
require dirname(__DIR__) . '/includes/db_config.php';
require dirname(__DIR__) . '/includes/util.php';
require dirname(__DIR__) . '/includes/test_session.php';

if ($pdo === null) {
    fwrite(STDERR, "DB 없음\n");
    exit(1);
}
$uid = (int) $pdo->query("SELECT id FROM sbm_users WHERE username = 'user'")->fetchColumn();

/** 응답을 넣고 완료 처리 → 검사 id */
function seed(PDO $pdo, int $uid, string $type, callable $value): int
{
    $id = big5_session_start($pdo, $uid, $type);
    // 시작 시각을 20분 전으로 (빠른 응답 플래그를 피한다)
    $pdo->prepare('UPDATE big5_sessions SET started_at = ? WHERE id = ?')->execute([big5_now(-1200), $id]);
    foreach (big5_items($type)['items'] as $it) {
        big5_response_save($pdo, 'session', $id, (int) $it['no'], (int) $value($it));
    }
    $s = big5_session_get($pdo, $id, $uid);
    big5_session_finish($pdo, $s);
    return $id;
}

mt_srand(20261102);
// 극단값: C·E·O 높게, A·N 낮게 (정채점 5/역채점 1 이면 높음)
$extremeHigh = ['C' => true, 'E' => true, 'O' => true, 'A' => false, 'N' => false];
$out = [
    'all3' => seed($pdo, $uid, 'full120', static fn($it) => 3),
    'random' => seed($pdo, $uid, 'full120', static fn($it) => mt_rand(1, 5)),
    'extreme' => seed($pdo, $uid, 'full120', static function ($it) use ($extremeHigh) {
        $high = $extremeHigh[$it['facet'][0]];
        return ($it['keyed'] === '+') === $high ? 5 : 1;
    }),
    'mini' => seed($pdo, $uid, 'mini20', static fn($it) => mt_rand(1, 5)),
    // 신경성·친화성 높게, 나머지 낮게 (신경성 높은 구간의 도움 안내 확인용)
    'nhigh' => seed($pdo, $uid, 'full120', static function ($it) {
        $high = in_array($it['facet'][0], ['N', 'A'], true);
        return ($it['keyed'] === '+') === $high ? 5 : 1;
    }),
];
echo json_encode($out), "\n";
