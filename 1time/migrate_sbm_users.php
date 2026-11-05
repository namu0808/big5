<?php
/**
 * 일회성 이전 스크립트 (다이얼 0.9.x → 0.10, 사이트 공통 계정) — 서버의 /big5/ 에 올려 브라우저로 1회 실행한 뒤 반드시 삭제한다.
 *   (저장소에서는 1time/ 에 보관. 업로드 위치는 /big5/migrate_sbm_users.php)
 *
 * 하는 일 (MariaDB 전용 — 다시 실행해도 된 단계는 건너뛴다):
 *  1) 공통 계정 테이블 sbm_users·sbm_login_attempts 생성
 *  2) big5_users 의 계정을 id·비밀번호 그대로 sbm_users 로 복사 (같은 id 가 있으면 건너뜀)
 *  3) big5_sessions·big5_invites·big5_growth_choices 의 외래 키를 sbm_users(id) 로 바꿈
 *     — InnoDB 는 테이블 이름을 바꾸면 외래 키가 따라가므로, 반드시 이름 바꾸기 전에 한다
 *  4) big5_users → big5_users_old 로 이름만 바꿔 남김 (확인 뒤 지워도 된다)
 *  5) big5_login_attempts 삭제 (잠깐 쓰는 로그인 실패 기록)
 *  6) 다이얼 테이블 확인(없으면 생성), 결과 보고(계정 수·외래 키·검사 기록 수)
 * CLI 실행도 된다: php migrate_sbm_users.php
 */
declare(strict_types=1);

$big5Root = is_dir(__DIR__ . '/includes') ? __DIR__ : dirname(__DIR__);

require_once $big5Root . '/includes/db_config.php';
require_once $big5Root . '/includes/util.php';
require_once $big5Root . '/includes/core_link.php';
require_once $big5Root . '/includes/big5_schema.php';

ini_set('display_errors', '0');

/** 테이블이 있는가 */
function big5_mig_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$table]);
    return (int) $st->fetchColumn() > 0;
}

/** $table.user_id 가 참조하는 외래 키 [이름, 참조 테이블] 목록 */
function big5_mig_user_fks(PDO $pdo, string $table): array
{
    $st = $pdo->prepare(
        "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
    );
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_NUM);
}

/** 이전 실행 — 메시지 목록을 돌려준다. 실패하면 예외 */
function big5_migrate_sbm_users(PDO $pdo): array
{
    if (!big5_db_is_mysql($pdo)) {
        return ['SQLite(개발 DB)는 이전하지 않습니다. php doc/dev-sqlite-setup.php 로 새로 만드세요.'];
    }
    $msgs = [];
    $count = static function (string $sql) use ($pdo): int {
        return (int) $pdo->query($sql)->fetchColumn();
    };
    $dataTables = ['big5_sessions', 'big5_invites', 'big5_growth_choices', 'big5_ratings', 'big5_responses', 'big5_scores'];
    $before = [];
    foreach ($dataTables as $t) {
        $before[$t] = big5_mig_table_exists($pdo, $t) ? $count("SELECT COUNT(*) FROM `{$t}`") : 0;
    }

    // 1) 공통 계정 테이블
    sbm_ensure_schema($pdo);
    $msgs[] = '공통 계정 테이블 준비: sbm_users, sbm_login_attempts';

    // 2) 계정 복사 (id·비밀번호 그대로)
    if (big5_mig_table_exists($pdo, 'big5_users')) {
        $n = $pdo->exec(
            'INSERT INTO sbm_users (id, username, password, display_name, role, is_active, age_confirmed, created_at, last_login_at)
             SELECT b.id, b.username, b.password, b.display_name, b.role, b.is_active, b.age_confirmed, b.created_at, b.last_login_at
               FROM big5_users b
              WHERE NOT EXISTS (SELECT 1 FROM sbm_users s WHERE s.id = b.id OR s.username = b.username)'
        );
        $msgs[] = "계정 복사: {$n}개 (big5_users " . $count('SELECT COUNT(*) FROM big5_users') . '개 → sbm_users ' . $count('SELECT COUNT(*) FROM sbm_users') . '개)';
        $missing = $count('SELECT COUNT(*) FROM big5_users b WHERE NOT EXISTS (SELECT 1 FROM sbm_users s WHERE s.id = b.id AND s.username = b.username)');
        if ($missing > 0) {
            throw new RuntimeException("id·아이디가 맞지 않는 계정이 {$missing}개 있습니다. 이전을 멈춥니다(아무것도 지우지 않았습니다).");
        }
    } else {
        $msgs[] = '계정 복사: big5_users 가 없습니다 (이미 이전했거나 새 설치) — 건너뜀';
    }

    // 3) 외래 키를 sbm_users 로 (이름 바꾸기 전에)
    $changed = 0;
    foreach (['big5_sessions', 'big5_invites', 'big5_growth_choices'] as $t) {
        if (!big5_mig_table_exists($pdo, $t)) {
            continue;
        }
        $hasSbm = false;
        foreach (big5_mig_user_fks($pdo, $t) as [$name, $ref]) {
            if ($ref === 'sbm_users') {
                $hasSbm = true;
                continue;
            }
            $pdo->exec('ALTER TABLE `' . $t . '` DROP FOREIGN KEY `' . str_replace('`', '', (string) $name) . '`');
        }
        if (!$hasSbm) {
            $pdo->exec('ALTER TABLE `' . $t . '` ADD CONSTRAINT `fk_' . $t . '_sbm_user` FOREIGN KEY (user_id) REFERENCES sbm_users(id) ON DELETE CASCADE');
            $changed++;
        }
    }
    $msgs[] = "외래 키를 sbm_users 로 바꿈: {$changed}개";

    // 4) big5_users → big5_users_old
    if (big5_mig_table_exists($pdo, 'big5_users')) {
        if (big5_mig_table_exists($pdo, 'big5_users_old')) {
            throw new RuntimeException('big5_users_old 가 이미 있습니다. 확인 후 정리하고 다시 실행해 주세요.');
        }
        $pdo->exec('RENAME TABLE big5_users TO big5_users_old');
        $msgs[] = 'big5_users → big5_users_old 로 이름을 바꿔 남겼습니다 (확인 뒤 지워도 됩니다)';
    }
    // 5) 옛 로그인 실패 기록
    if (big5_mig_table_exists($pdo, 'big5_login_attempts')) {
        $pdo->exec('DROP TABLE big5_login_attempts');
        $msgs[] = 'big5_login_attempts 삭제 (로그인 제한은 sbm_login_attempts 로)';
    }

    // 6) 다이얼 테이블 확인 + 결과 대조
    big5_ensure_schema($pdo);
    $same = true;
    foreach ($dataTables as $t) {
        $after = $count("SELECT COUNT(*) FROM `{$t}`");
        if ($after !== $before[$t]) {
            $same = false;
        }
        $msgs[] = "{$t}: 이전 {$before[$t]}건 → 지금 {$after}건";
    }
    if (!$same) {
        throw new RuntimeException('검사 기록 수가 달라졌습니다. 서버 오류 로그와 DB 를 확인해 주세요.');
    }
    $orphans = $count('SELECT COUNT(*) FROM big5_sessions s WHERE NOT EXISTS (SELECT 1 FROM sbm_users u WHERE u.id = s.user_id)');
    if ($orphans > 0) {
        throw new RuntimeException("계정이 없는 검사 기록이 {$orphans}건 있습니다.");
    }
    $msgs[] = '검사 기록 수 일치, 모든 기록이 공통 계정에 연결됨';
    return $msgs;
}

// 직접 실행할 때만 (테스트가 함수만 쓸 수 있게)
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $ok = true;
    $messages = [];
    if ($pdo === null) {
        $ok = false;
        $messages[] = 'DB에 연결할 수 없습니다. 웹루트 밖 {홈}/config/big5_db.php 설정을 확인해 주세요.';
    } else {
        try {
            $messages = big5_migrate_sbm_users($pdo);
        } catch (Throwable $e) {
            error_log('다이얼 공통 계정 이전 오류: ' . $e->getMessage());
            $ok = false;
            $messages[] = '이전하지 못했습니다: ' . $e->getMessage();
        }
    }
    if (PHP_SAPI === 'cli') {
        echo implode(PHP_EOL, $messages), PHP_EOL, $ok ? '이전 완료' : '이전 실패', PHP_EOL;
        exit($ok ? 0 : 1);
    }
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>공통 계정 이전</title></head>
<body style="font-family:system-ui,sans-serif;max-width:680px;margin:3rem auto;padding:0 1rem;line-height:1.7;color:#222">
<h1 style="font-size:1.3rem">공통 계정 이전 <?= $ok ? '완료' : '실패' ?></h1>
<ul>
<?php foreach ($messages as $m): ?>
  <li><?= h($m) ?></li>
<?php endforeach; ?>
</ul>
<?php if ($ok): ?>
<p><strong>이 migrate_sbm_users.php 파일을 서버에서 바로 삭제해 주세요.</strong></p>
<p><a href="/account/login.php">로그인 화면에서 기존 아이디로 로그인</a></p>
<?php endif; ?>
</body></html>
<?php
}
