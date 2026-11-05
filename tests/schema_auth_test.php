<?php
/**
 * 1단계 검증 — SQLite 메모리 DB 로 스키마(멱등)·초기 계정·로그인·시도 제한·URL 도우미를 확인한다.
 * 실행: php tests/schema_auth_test.php
 */
declare(strict_types=1);

putenv('BIG5_DB_QUIET=1');
require dirname(__DIR__) . '/includes/db_config.php';
require dirname(__DIR__) . '/includes/util.php';
require dirname(__DIR__) . '/includes/big5_schema.php';
require dirname(__DIR__) . '/includes/auth.php';
require __DIR__ . '/lib.php';

$pdo = t_pdo();

$tables = big5_ensure_schema($pdo);
t_ok(count($tables) === 9, '테이블 9개 생성 (공통 계정 2 + 다이얼 7)');
big5_ensure_schema($pdo); // 두 번째 실행도 오류 없이
t_ok(true, '스키마 멱등(두 번 실행)');
foreach ($tables as $name) {
    t_ok(str_starts_with($name, 'big5_') || in_array($name, ['sbm_users', 'sbm_login_attempts'], true), "접두어 big5_ (또는 공통 계정 sbm_): {$name}");
}
t_ok(!in_array('big5_users', $tables, true) && !in_array('big5_login_attempts', $tables, true), '다이얼 전용 계정 테이블 없음 (공통 계정 sbm_users 사용)');

$m1 = big5_seed_accounts($pdo);
$m2 = big5_seed_accounts($pdo);
$n = (int) $pdo->query('SELECT COUNT(*) FROM sbm_users')->fetchColumn();
t_ok($n === 2, '초기 계정 2개 (두 번 실행해도 2개)');
$role = $pdo->query("SELECT role FROM sbm_users WHERE username = 'sonbm'")->fetchColumn();
t_ok($role === 'admin', 'sonbm 은 관리자');

$u = big5_authenticate($pdo, 'user', 'visang');
t_ok(!isset($u['error']) && $u['username'] === 'user', 'user/visang 로그인 성공');
t_ok(!isset($u['password']), '반환값에 비밀번호 해시 없음');
$a = big5_authenticate($pdo, 'sonbm', 'visang', true);
t_ok(!isset($a['error']), 'sonbm/visang 관리자 로그인 성공');
$x = big5_authenticate($pdo, 'user', 'visang', true);
t_ok(isset($x['error']), '일반 사용자는 관리자 로그인 불가');

// 시도 제한: 같은 아이디 5회 실패 → 맞는 비밀번호도 차단
$pdo->exec('DELETE FROM sbm_login_attempts');
for ($i = 0; $i < 5; $i++) {
    big5_authenticate($pdo, 'user', 'wrong');
}
$y = big5_authenticate($pdo, 'user', 'visang');
t_ok(isset($y['error']) && str_contains($y['error'], '15분'), '5회 실패 뒤 차단');
$pdo->exec('DELETE FROM sbm_login_attempts');
$z = big5_authenticate($pdo, 'user', 'visang');
t_ok(!isset($z['error']), '기록이 지워지면 다시 로그인');

// 비활성 계정
$pdo->exec("UPDATE sbm_users SET is_active = 0 WHERE username = 'user'");
$w = big5_authenticate($pdo, 'user', 'visang');
t_ok(isset($w['error']) && str_contains($w['error'], '중지'), '비활성 계정 로그인 불가');
$pdo->exec("UPDATE sbm_users SET is_active = 1 WHERE username = 'user'");

// 외래 키 CASCADE: 사용자 삭제 → 세션 삭제
$uid = (int) $pdo->query("SELECT id FROM sbm_users WHERE username = 'user'")->fetchColumn();
$pdo->prepare("INSERT INTO big5_sessions (user_id, test_type, item_version, started_at, consent_at) VALUES (?, 'mini20', 'v', ?, ?)")
    ->execute([$uid, big5_now(), big5_now()]);
$pdo->prepare('DELETE FROM sbm_users WHERE id = ?')->execute([$uid]);
t_ok((int) $pdo->query('SELECT COUNT(*) FROM big5_sessions')->fetchColumn() === 0, '사용자 삭제 시 세션 CASCADE');

// URL 도우미 — 루트 기준 절대 경로 하드코딩 없이 BASE_URL 기준
t_ok(big5_url('admin/login.php') === '/big5/admin/login.php', "big5_url 기본 /big5");
t_ok(big5_url('/index.php') === '/big5/index.php', 'big5_url 앞 슬래시 정리');
t_ok(big5_account_url('login.php', '/big5/test.php?id=3') === '/account/login.php?next=%2Fbig5%2Ftest.php%3Fid%3D3', '공통 로그인 주소(next=다이얼 경로)');
t_ok(big5_account_url('register.php') === '/account/register.php?next=%2Fbig5%2Findex.php', '공통 가입 주소(기본 next=시작 화면)');
t_ok(big5_account_url('login.php', 'https://evil.example/') === '/account/login.php?next=%2F', '외부 next 거부');
t_ok(big5_valid_username('user') && !big5_valid_username('ab') && !big5_valid_username('Bad!'), '아이디 형식 검사');

t_done();
