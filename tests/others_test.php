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
require dirname(__DIR__) . '/includes/sbm_hook.php';
putenv('SBM_BIG5_DIR=' . dirname(__DIR__)); // 공통 계정 전체 삭제가 다이얼 훅을 찾는 곳
require __DIR__ . '/lib.php';

$pdo = t_pdo();
big5_ensure_schema($pdo);
big5_seed_accounts($pdo);
$uid = (int) $pdo->query("SELECT id FROM sbm_users WHERE username = 'user'")->fetchColumn();
$other = (int) $pdo->query("SELECT id FROM sbm_users WHERE username = 'sonbm'")->fetchColumn();

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

// ── 9장 확장: 세 묶음(8·15), 방향, 구간, 남들끼리의 일치, 관계별, 쏠림, 순서, 품질 제외
t_ok($recv[0]['test_type'] === 'mini20' && is_array($recv[0]['flags']), '받은 평가: 검사 종류와 품질 표시도 함께 (' . implode(',', $recv[0]['flags']) . ')');
$r1 = $o1['rows'];
t_ok($r1['C']['level'] === 'far' && $r1['E']['level'] === 'some' && $r1['A']['level'] === 'near' && $r1['O']['level'] === 'some' && $r1['N']['level'] === 'far', '세 묶음: 8점 미만 비슷 / 8~15 조금 / 15 이상 다름');
t_ok($o1['summary']['far'] === ['C', 'N'] && $o1['summary']['near'] === ['A'] && $o1['summary']['some'] === ['E', 'O'], '요약 묶음 (다르게 보는 곳은 차이 큰 순)');
t_ok($r1['C']['direction'] === 'higher' && $r1['N']['direction'] === 'lower' && $r1['A']['direction'] === 'same', '방향: 남이 더 높게·낮게·같음');
t_ok($r1['C']['band_cross'] && $r1['C']['band_self'] === 'mid' && $r1['C']['band_others'] === 'high' && !$r1['N']['band_cross'], '구간이 달라지는 곳 표시 (C 가운데→높은 쪽, N 35.0 은 가운데)');
t_ok($r1['C']['pattern'] === null && !$r1['C']['split'] && $r1['C']['rel_gap'] === null && $o1['summary']['relation_kinds'] === 1, '평가자 1명: 일치·관계 분석 없음');
t_ok($o1['summary']['order'] !== null && $o1['summary']['order']['self_top'] === 'E' && $o1['summary']['order']['others_top'] === 'E' && $o1['summary']['order']['top_same'], '높낮이 순서: 가장 높은 다이얼이 같음');
$flat = big5_others_section(['C' => 50.0, 'E' => 50.0, 'A' => 50.0, 'O' => 50.0, 'N' => 50.0], [['relation' => 'friend', 'domains' => ['C' => 70.0, 'E' => 30.0, 'A' => 50.0, 'O' => 50.0, 'N' => 50.0]]]);
t_ok($flat['summary']['order'] === null, '한쪽이 평평하면 순서를 말하지 않음');
// 평가자 여럿: 남들끼리 비슷한데 나와 다름 / 남들끼리도 갈림 / 평균은 비슷한데 갈림 / 관계별 차이
$mk = static fn(string $rel, array $d, array $flags = [], string $type = 'mini20') => ['relation' => $rel, 'test_type' => $type, 'flags' => $flags, 'domains' => $d];
$o3 = big5_others_section($self, [
    $mk('colleague', ['C' => 75.0, 'E' => 60.0, 'A' => 30.0, 'O' => 50.0, 'N' => 50.0]),
    $mk('colleague', ['C' => 80.0, 'E' => 70.0, 'A' => 40.0, 'O' => 50.0, 'N' => 50.0]),
    $mk('family', ['C' => 70.0, 'E' => 35.0, 'A' => 80.0, 'O' => 50.0, 'N' => 50.0], [], 'full120'),
]);
$r3 = $o3['rows'];
t_ok($o3['count'] === 3 && $r3['C']['others'] === 75.0 && $r3['C']['min'] === 70.0 && $r3['C']['max'] === 80.0 && $r3['C']['spread'] === 10.0, '평가자 3명: 평균과 범위');
t_ok($r3['C']['pattern'] === 'consistent' && $r3['E']['pattern'] === 'split' && $r3['A']['pattern'] === 'hidden_split' && $r3['O']['pattern'] === null, '차이 × 남들끼리의 일치 (비슷·갈림·평균 뒤에 숨은 갈림)');
t_ok($o3['summary']['hidden_split'] === ['A'], '요약: 평균은 비슷하지만 갈린 곳');
t_ok($r3['A']['by_relation']['colleague'] === ['label' => '동료', 'n' => 2, 'avg' => 35.0] && $r3['A']['by_relation']['family']['n'] === 1, '관계별 평균 (1명뿐인 관계도 보여 줌)');
t_ok($r3['A']['rel_gap'] !== null && $r3['A']['rel_gap']['hi'] === 'family' && $r3['A']['rel_gap']['lo'] === 'colleague' && $r3['A']['rel_gap']['diff'] === 45.0 && $r3['C']['rel_gap'] === null, '관계에 따라 15점 이상 다르게 보이는 곳');
t_ok($o3['summary']['relation_gaps'] === ['E', 'A'] && $o3['summary']['relation_kinds'] === 2 && $o3['types'] === ['mini20' => 2, 'full120' => 1], '요약: 관계별 차이·검사 구성');
// 전체 쏠림 (다섯 가운데 넷 이상이 같은 쪽으로 8점 이상) 과 그것을 뺀 차이
$o4 = big5_others_section($self, [$mk('friend', ['C' => 60.0, 'E' => 100.0, 'A' => 60.0, 'O' => 80.0, 'N' => 52.0])]);
t_ok($o4['summary']['tilt'] === 'higher' && $o4['summary']['tilt_count'] === 4 && $o4['summary']['mean_gap'] === 12.4, '쏠림: 넷을 남이 더 높게');
t_ok($o4['summary']['standout'] === 'O' && $o4['rows']['O']['residual'] === 17.6, '쏠림을 빼고도 가장 튀는 곳');
t_ok($o1['summary']['tilt'] === null && $o1['summary']['standout'] === null, '쏠림이 아니면 말하지 않음');
// 품질 표시가 붙은 평가는 뺀다 — 하나도 남지 않으면 모두 쓴다
$o5 = big5_others_section($self, [$mk('friend', ['C' => 100.0], ['straightline']), $mk('family', ['C' => 60.0])]);
t_ok($o5['count'] === 1 && $o5['excluded'] === 1 && $o5['rows']['C']['others'] === 60.0 && $o5['relations'] === ['가족' => 1], '품질 표시가 붙은 평가는 평균에서 뺌');
$o6 = big5_others_section($self, [$mk('friend', ['C' => 100.0], ['fast'])]);
t_ok($o6['count'] === 1 && $o6['excluded'] === 0 && $o6['rows']['C']['others'] === 100.0, '빼면 하나도 안 남을 때는 그대로 씀');

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
$pdo->prepare('INSERT INTO sbm_login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)')->execute(['user', '1.1.1.1', big5_now()]);
// 다른 사용자 데이터는 남아야 한다
$os = big5_session_start($pdo, $other, 'mini20');
big5_response_save($pdo, 'session', $os, 1, 3);
$count = static fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int) $st->fetchColumn();
})();
// 공통 계정 훅 — 내 계정 화면의 요약
$summary = big5_sbm_summary($pdo, $uid);
t_ok(is_string($summary) && str_contains($summary, '완료한 검사'), '훅 요약: ' . (string) $summary);
t_ok(big5_sbm_summary($pdo, 999999) === null, '훅 요약: 기록 없으면 null');
$hooks = sbm_user_summaries($pdo, $uid);
t_ok(count($hooks) === 1 && $hooks[0]['id'] === 'big5' && $hooks[0]['summary'] === $summary, '공통 계정이 다이얼 훅을 찾음');
// 사이트 공통 계정 전체 삭제 — 계정은 은퇴(로그인 불가·익명), 다이얼 기록은 DB 에 그대로 남는다(2026-11-10 기록 보존)
$beforeResp = $count('SELECT COUNT(*) FROM big5_responses');
$beforeRatings = $count('SELECT COUNT(*) FROM big5_ratings');
$beforeSessions = $count('SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?', [$uid]);
sbm_user_delete_all($pdo, $uid);
$ret = $pdo->query("SELECT username, is_active, display_name FROM sbm_users WHERE id = {$uid}")->fetch();
t_ok(is_array($ret) && sbm_user_is_retired($ret) && (int) $ret['is_active'] === 0 && $ret['display_name'] === '', '전체 삭제: 계정은 은퇴(행 남음·익명·비활성)');
t_ok($count('SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?', [$uid]) === $beforeSessions && $beforeSessions > 0, '전체 삭제: 검사 기록 보존');
t_ok($count('SELECT COUNT(*) FROM big5_responses') === $beforeResp && $count('SELECT COUNT(*) FROM big5_ratings') === $beforeRatings, '전체 삭제: 응답·받은 평가 보존');
t_ok($count('SELECT COUNT(*) FROM big5_invites WHERE user_id = ? AND expires_at > ?', [$uid, big5_now()]) === 0, '전체 삭제: 초대 링크 만료');
t_ok($count("SELECT COUNT(*) FROM sbm_login_attempts WHERE username = 'user'") === 0, '전체 삭제: 로그인 기록 정리');
t_ok(isset(sbm_authenticate($pdo, 'user', 'userpass1')['error']), '전체 삭제: 옛 아이디로 로그인 불가');
t_ok($count('SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?', [$other]) === 1 && $count("SELECT COUNT(*) FROM big5_responses WHERE owner_type = 'session' AND owner_id = ?", [$os]) === 1, '다른 사용자 데이터는 그대로');


// 다이얼 기록만 지우기 — 계정은 남고, 기록은 빈 은퇴 계정으로 옮겨 보존
$os2 = big5_session_start($pdo, $other, 'full120');
$totalSessions = $count('SELECT COUNT(*) FROM big5_sessions');
$tomb = big5_user_delete_data_tx($pdo, $other);
t_ok($count('SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?', [$other]) === 0, '다이얼 기록만 지우기: 내 검사 없음');
t_ok($count('SELECT COUNT(*) FROM sbm_users WHERE id = ? AND is_active = 1', [$other]) === 1, '다이얼 기록만 지우기: 계정은 남음');
t_ok($count('SELECT COUNT(*) FROM big5_sessions') === $totalSessions && $count('SELECT COUNT(*) FROM big5_sessions WHERE user_id = ?', [$tomb]) === 2, '다이얼 기록만 지우기: 기록은 은퇴 계정에 보존');
t_ok($count('SELECT COUNT(*) FROM sbm_users WHERE id = ? AND is_active = 0', [$tomb]) === 1, '은퇴 계정은 로그인 불가');

t_done();
