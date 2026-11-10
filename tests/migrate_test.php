<?php
/**
 * 공통 계정 이전(1time/migrate_sbm_users.php) 검증 — MariaDB 에서만 (BIG5_TEST_DB=mysql).
 * 0.9.1 구조(big5_users) DB 를 만들고 계정·검사 기록을 넣은 뒤 이전 → id·비밀번호·외래 키·기록 수·멱등을 확인한다.
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

if (getenv('BIG5_TEST_DB') !== 'mysql') {
    echo "  (건너뜀: MariaDB 전용 — BIG5_TEST_DB=mysql 로 실행)\n";
    t_done();
}

$pdo = t_pdo();

// 1) 0.9.1 구조 만들기 — 옛 스키마 함수 이름이 지금 것과 같아 다른 프로세스에서 실행한다
$root = dirname(__DIR__);
$code = <<<'PHP'
require $argv[1] . '/includes/db_config.php';
require $argv[1] . '/includes/util.php';
require $argv[1] . '/tests/fixtures/big5_schema_0.9.1.php';
big5_ensure_schema($pdo);
big5_seed_accounts($pdo);                       // sonbm(1) user(2)
$pdo->exec("INSERT INTO big5_users (id, username, password, display_name, role, is_active, age_confirmed, created_at) VALUES (5, 'kim_05', '" . password_hash('kimpass55', PASSWORD_DEFAULT) . "', '김', 'user', 1, 1, '2026-11-01 10:00:00')");
foreach ([2, 2, 5] as $i => $uid) {
    $pdo->prepare("INSERT INTO big5_sessions (user_id, test_type, item_version, status, started_at, completed_at, consent_at) VALUES (?, 'mini20', 'v', 'completed', '2026-11-02 00:00:00', '2026-11-02 00:10:00', '2026-11-02 00:00:00')")->execute([$uid]);
    $sid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO big5_responses (owner_type, owner_id, item_no, value, answered_at) VALUES ('session', ?, 1, 3, '2026-11-02 00:01:00')")->execute([$sid]);
}
$pdo->exec("INSERT INTO big5_invites (user_id, token_hash, relation, test_type, expires_at, created_at) VALUES (2, '" . str_repeat('a', 64) . "', 'friend', 'mini20', '2026-12-01 00:00:00', '2026-11-02 00:00:00')");
$pdo->exec("INSERT INTO big5_growth_choices (user_id, session_id, domain, direction, action_key, created_at) VALUES (5, 3, 'C', 'grow', 'c_g_calendar', '2026-11-02 00:00:00')");
$pdo->exec("INSERT INTO big5_login_attempts (username, ip, attempted_at) VALUES ('user', '1.1.1.1', '2026-11-02 00:00:00')");
echo 'ok';
PHP;
$env = 'BIG5_DB_CONFIG=' . escapeshellarg($root . '/doc/dev-db-mysql.php') . ' BIG5_DB_QUIET=1 ';
$out = (string) shell_exec($env . 'php -r ' . escapeshellarg($code) . ' ' . escapeshellarg($root) . ' 2>&1');
t_ok(trim($out) === 'ok', '0.9.1 구조 DB 준비 ' . ($out === 'ok' ? '' : $out));
$q = static fn(string $sql) => $pdo->query($sql)->fetchColumn();
t_ok((int) $q("SELECT COUNT(*) FROM big5_users") === 3, '옛 계정 3개(id 1·2·5)');

// 2) 이전
// 이전 스크립트를 함수로만 싣는다 (스크립트의 DB 로더가 $pdo 를 덮어쓰지 않게 보관)
$testPdo = $pdo;
putenv('BIG5_DB_QUIET=1');
require $root . '/1time/migrate_sbm_users.php';
$pdo = $testPdo;
$msgs = big5_migrate_sbm_users($pdo);
echo '    ', implode("\n    ", $msgs), "\n";
t_ok((int) $q('SELECT COUNT(*) FROM sbm_users') === 3, '공통 계정 3개');
t_ok($q('SELECT GROUP_CONCAT(id ORDER BY id) FROM sbm_users') === '1,2,5', 'id 그대로 (1,2,5)');
t_ok($q("SELECT username FROM sbm_users WHERE id = 5") === 'kim_05' && $q("SELECT role FROM sbm_users WHERE id = 1") === 'admin', '아이디·역할 그대로');
t_ok(!isset(sbm_authenticate($pdo, 'kim_05', 'kimpass55')['error']) && !isset(sbm_authenticate($pdo, 'sonbm', 'visang', true)['error']), '같은 비밀번호로 로그인(사용자·관리자)');
t_ok((int) $q("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'big5_users'") === 0, 'big5_users 없음');
t_ok((int) $q("SELECT COUNT(*) FROM big5_users_old") === 3, 'big5_users_old 로 남김');
t_ok((int) $q("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'big5_login_attempts'") === 0, 'big5_login_attempts 삭제');
$refs = $pdo->query("SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_KEY_PAIR);
t_ok($refs === ['big5_growth_choices' => 'sbm_users', 'big5_invites' => 'sbm_users', 'big5_sessions' => 'sbm_users'], '외래 키 3곳 → sbm_users ' . json_encode($refs));
t_ok((int) $q('SELECT COUNT(*) FROM big5_sessions') === 3 && (int) $q('SELECT COUNT(*) FROM big5_responses') === 3
    && (int) $q('SELECT COUNT(*) FROM big5_invites') === 1 && (int) $q('SELECT COUNT(*) FROM big5_growth_choices') === 1, '검사 기록 수 그대로');

// 3) 다시 실행해도 그대로 (멱등)
$msgs2 = big5_migrate_sbm_users($pdo);
t_ok(str_contains(implode(' ', $msgs2), '건너뜀') && str_contains(implode(' ', $msgs2), '0개'), '두 번째 실행: 건너뜀');
t_ok((int) $q('SELECT COUNT(*) FROM sbm_users') === 3 && (int) $q('SELECT COUNT(*) FROM big5_sessions') === 3, '두 번째 실행 뒤 수 그대로');

// 4) 새 가입은 다음 번호, 계정 삭제는 다이얼 기록까지 (CASCADE + 훅)
$r = sbm_user_create($pdo, 'new_user', 'newpass11', 'newpass11', '새', true);
t_ok(($r['id'] ?? 0) === 6, '새 가입 id = 6 (AUTO_INCREMENT 이어짐)');
require_once $root . '/includes/sbm_hook.php';
putenv('SBM_BIG5_DIR=' . $root);
sbm_user_delete_all($pdo, 2);
t_ok((int) $q('SELECT COUNT(*) FROM big5_sessions WHERE user_id = 2') === 2 && (int) $q("SELECT COUNT(*) FROM big5_responses") === 3
    && (int) $q("SELECT is_active FROM sbm_users WHERE id = 2") === 0, '계정 전체 삭제: 계정은 은퇴, 다이얼 기록은 보존(2026-11-10)');
t_ok((int) $q('SELECT COUNT(*) FROM big5_sessions WHERE user_id = 5') === 1, '다른 사람 기록은 그대로');

// 5) 겹치는 아이디가 다른 id 로 이미 있으면 멈춘다 (아무것도 지우지 않음)
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['big5_sessions', 'big5_invites', 'big5_growth_choices', 'big5_responses', 'big5_scores', 'big5_ratings', 'sbm_users', 'big5_users_old', 'sbm_login_attempts', 'big5_norms'] as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
shell_exec($env . 'php -r ' . escapeshellarg('require $argv[1] . "/includes/db_config.php"; require $argv[1] . "/includes/util.php"; require $argv[1] . "/tests/fixtures/big5_schema_0.9.1.php"; big5_ensure_schema($pdo); big5_seed_accounts($pdo);') . ' ' . escapeshellarg($root));
sbm_ensure_schema($pdo);
$pdo->exec("INSERT INTO sbm_users (id, username, password, display_name, role, is_active, age_confirmed, created_at) VALUES (7, 'sonbm', 'x', '다른', 'user', 1, 1, '2026-11-05 00:00:00')");
try {
    big5_migrate_sbm_users($pdo);
    t_ok(false, '아이디 충돌이면 멈춤');
} catch (RuntimeException $e) {
    t_ok(str_contains($e->getMessage(), '멈춥니다'), '아이디 충돌이면 멈춤');
}
t_ok((int) $q("SELECT COUNT(*) FROM big5_users") === 2, '멈춘 뒤 big5_users 그대로');

t_done();
