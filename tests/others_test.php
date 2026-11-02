<?php
/**
 * 6단계 검증 — 초대 토큰(해시 저장·만료), 타인 평가 채점, 리포트 9장(평균·한 사람·15점 차이),
 * 재검사 비교(10점 강조), 내 데이터 전체 삭제(관련 행 0건). SQLite 메모리 DB.
 * 실행: php tests/others_test.php
 */
declare(strict_types=1);

putenv('BIG5_DB_QUIET=1');
require dirname(__DIR__) . '/includes/db_config.php';
require dirname(__DIR__) . '/includes/util.php';
require dirname(__DIR__) . '/includes/big5_schema.php';
require dirname(__DIR__) . '/includes/others.php';
require dirname(__DIR__) . '/includes/user_delete.php';
require __DIR__ . '/lib.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
big5_ensure_schema($pdo);
big5_seed_accounts($pdo);
$uid = (int) $pdo->query("SELECT id FROM big5_users WHERE username = 'user'")->fetchColumn();
$other = (int) $pdo->query("SELECT id FROM big5_users WHERE username = 'sonbm'")->fetchColumn();

// ── 초대 토큰
$token = big5_invite_create($pdo, $uid, 'colleague', 'mini20');
t_ok(preg_match('/^[0-9a-f]{64}$/', $token) === 1, '초대 토큰 64자 16진수 (random_bytes(32))');
$stored = (string) $pdo->query('SELECT token_hash FROM big5_invites')->fetchColumn();
t_ok($stored === hash('sha256', $token) && $stored !== $token, 'DB 에는 토큰 원문이 아니라 SHA-256 해시만');
$inv = big5_invite_by_token($pdo, $token);
t_ok($inv !== null && !$inv['expired'] && $inv['owner_name'] === '사용자', '토큰으로 초대 찾기 + 초대한 사람 이름');
t_ok(big5_invite_by_token($pdo, str_repeat('0', 64)) === null && big5_invite_by_token($pdo, 'abc') === null, '없는 토큰·형식이 틀린 토큰 → null');
$pdo->prepare('UPDATE big5_invites SET expires_at = ?')->execute([big5_now(-1)]);
t_ok(big5_invite_by_token($pdo, $token)['expired'] === true, '기한이 지난 초대 → expired');
$pdo->prepare('UPDATE big5_invites SET expires_at = ?')->execute([big5_now(86400)]);

// ── 타인 평가 1건 완료 → 점수 저장
$rid = big5_rating_start($pdo, $inv);
$st = $pdo->prepare('SELECT * FROM big5_ratings WHERE id = ?');
$st->execute([$rid]);
$rating = $st->fetch();
t_ok(big5_rating_finish($pdo, $rating)['code'] === 'missing', '다 답하지 않으면 완료 불가');
foreach (big5_items('mini20')['items'] as $it) {
    big5_response_save($pdo, 'rating', $rid, (int) $it['no'], $it['keyed'] === '+' ? 5 : 1);
}
$pdo->prepare('UPDATE big5_ratings SET started_at = ? WHERE id = ?')->execute([big5_now(-600), $rid]);
$st->execute([$rid]);
t_ok(big5_rating_finish($pdo, $st->fetch())['ok'] === true, '타인 평가 완료');
$recv = big5_ratings_received($pdo, $uid);
t_ok(count($recv) === 1 && $recv[0]['relation'] === 'colleague' && $recv[0]['domains']['C'] === 100.0, '받은 평가: 관계 범주와 요인 점수');
t_ok(big5_ratings_received($pdo, $other) === [], '다른 사용자는 이 평가를 받지 않음');

// ── 9장 데이터
$self = ['C' => 50.0, 'E' => 90.0, 'A' => 50.0, 'O' => 50.0, 'N' => 50.0];
$o1 = big5_others_section($self, [['relation' => 'colleague', 'domains' => ['C' => 70.0, 'E' => 80.0, 'A' => 50.0, 'O' => 64.9, 'N' => 35.0]]]);
t_ok($o1['single'] && $o1['count'] === 1 && $o1['relations'] === ['동료' => 1], '평가자 1명 → 한 사람의 시선, 관계 범주만');
t_ok($o1['rows']['C']['flag'] && !$o1['rows']['E']['flag'] && !$o1['rows']['O']['flag'] && $o1['rows']['N']['flag'], '15점 이상 차이만 표시 (C +20, N −15 표시 / E −10, O +14.9 없음)');
$o2 = big5_others_section($self, [['relation' => 'family', 'domains' => ['C' => 60.0]], ['relation' => 'family', 'domains' => ['C' => 80.0]]]);
t_ok(!$o2['single'] && $o2['rows']['C']['others'] === 70.0 && $o2['relations'] === ['가족' => 2], '평가자 2명 → 평균');
t_ok(big5_others_section($self, []) === null, '받은 평가 없으면 9장 없음');

// ── 10장 비교
$before = ['domains' => ['C' => ['pomp' => 40.0], 'E' => ['pomp' => 50.0]], 'facets' => ['C1' => ['pomp' => 30.0]]];
$after = ['domains' => ['C' => ['pomp' => 52.5], 'E' => ['pomp' => 45.0]], 'facets' => []];
$cmp = big5_compare_scores($before, $after);
t_ok($cmp['domains']['C']['diff'] === 12.5 && $cmp['domains']['C']['flag'] && !$cmp['domains']['E']['flag'] && $cmp['facets'] === [], '재검사: 10점 이상만 강조, 하위 요인은 둘 다 있을 때만');

// ── 앞선 검사 찾기
$s1 = big5_session_start($pdo, $uid, 'mini20');
$s2 = big5_session_start($pdo, $uid, 'mini20');
$pdo->prepare("UPDATE big5_sessions SET status = 'completed', completed_at = ? WHERE id = ?")->execute(['2026-10-01 10:00:00', $s1]);
$pdo->prepare("UPDATE big5_sessions SET status = 'completed', completed_at = ? WHERE id = ?")->execute(['2026-11-01 10:00:00', $s2]);
$cur = $pdo->query("SELECT * FROM big5_sessions WHERE id = {$s2}")->fetch();
$prev = big5_session_previous($pdo, $cur);
t_ok($prev !== null && (int) $prev['id'] === $s1, '바로 앞 완료 검사 찾기');
$first = $pdo->query("SELECT * FROM big5_sessions WHERE id = {$s1}")->fetch();
t_ok(big5_session_previous($pdo, $first) === null, '첫 검사는 비교 대상 없음');
$s3 = big5_session_start($pdo, $uid, 'mini20');
$pdo->prepare("UPDATE big5_sessions SET status = 'completed', completed_at = ? WHERE id = ?")->execute(['2026-11-01 10:00:00', $s3]);
$cur2 = $pdo->query("SELECT * FROM big5_sessions WHERE id = {$s2}")->fetch();
t_ok((int) big5_session_previous($pdo, $cur2)['id'] === $s1, '같은 시각에 끝난 뒤 검사는 앞선 검사로 고르지 않음');
$cur3 = $pdo->query("SELECT * FROM big5_sessions WHERE id = {$s3}")->fetch();
t_ok((int) big5_session_previous($pdo, $cur3)['id'] === $s2, '같은 시각이면 번호가 앞선 검사');

// ── 내 데이터 전체 삭제
foreach ([$s1, $s2] as $sid) {
    big5_response_save($pdo, 'session', $sid, 1, 3);
    big5_scores_save($pdo, 'session', $sid, ['domains' => ['C' => ['raw' => 12, 'pomp' => 50.0, 'band' => 'mid']], 'facets' => []]);
}
$pdo->prepare('INSERT INTO big5_growth_choices (user_id, session_id, domain, direction, action_key, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$uid, $s1, 'C', 'grow', 'c_g_calendar', big5_now()]);
$pdo->prepare('INSERT INTO big5_login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)')->execute(['user', '1.1.1.1', big5_now()]);
// 다른 사용자 데이터는 남아야 한다
$os = big5_session_start($pdo, $other, 'mini20');
big5_response_save($pdo, 'session', $os, 1, 3);
big5_user_delete($pdo, $uid);
$count = static fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int) $st->fetchColumn();
})();
t_ok($count('SELECT COUNT(*) FROM big5_users WHERE id = ?', [$uid]) === 0, '삭제: 계정');
t_ok($count('SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?', [$uid]) === 0, '삭제: 검사');
t_ok($count("SELECT COUNT(*) FROM big5_responses WHERE owner_type = 'session' AND owner_id IN (?, ?)", [$s1, $s2]) === 0, '삭제: 본인 응답');
t_ok($count("SELECT COUNT(*) FROM big5_scores WHERE owner_type = 'session' AND owner_id IN (?, ?)", [$s1, $s2]) === 0, '삭제: 본인 점수');
t_ok($count('SELECT COUNT(*) FROM big5_invites') === 0 && $count('SELECT COUNT(*) FROM big5_ratings') === 0, '삭제: 초대·받은 평가');
t_ok($count("SELECT COUNT(*) FROM big5_responses WHERE owner_type = 'rating'") === 0 && $count("SELECT COUNT(*) FROM big5_scores WHERE owner_type = 'rating'") === 0, '삭제: 받은 평가의 응답·점수');
t_ok($count('SELECT COUNT(*) FROM big5_growth_choices') === 0 && $count("SELECT COUNT(*) FROM big5_login_attempts WHERE username = 'user'") === 0, '삭제: 성장 행동·로그인 기록');
t_ok($count('SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?', [$other]) === 1 && $count("SELECT COUNT(*) FROM big5_responses WHERE owner_type = 'session' AND owner_id = ?", [$os]) === 1, '다른 사용자 데이터는 그대로');

t_done();
