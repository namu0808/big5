<?php
// 시험 자료: 다이얼 0.9.1 의 스키마(계정 테이블 big5_users) 그대로 — tests/migrate_test.php 가 공통 계정 이전을 검증할 때 쓴다. 고치지 않는다.
/**
 * 스키마 단일 원본 — 모든 big5_ 테이블을 멱등(IF NOT EXISTS)으로 만든다.
 * MariaDB(운영)와 SQLite(개발·검증) DDL 을 한 파일에 나란히 두어 서로 어긋나지 않게 한다.
 * 앱 쿼리는 두 DB 모두에서 도는 표준 SQL 만 쓴다. (ENUM 값 검사는 애플리케이션이 한다)
 *
 * 원칙: 원응답(big5_responses)을 보존해 문항·채점 버전이 바뀌어도 다시 계산할 수 있다.
 * big5_responses·big5_scores 의 owner_id 는 다형 참조(session|rating)라 외래 키가 없다.
 * 사용자 삭제 시 includes/user_delete.php 가 함께 지운다.
 */
declare(strict_types=1);

/** 테이블 이름 → [MariaDB DDL, SQLite DDL] */
function big5_schema_tables(): array
{
    $my = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $t = [];

    $t['big5_users'] = [
        "CREATE TABLE IF NOT EXISTS big5_users (
          id            INT AUTO_INCREMENT PRIMARY KEY,
          username      VARCHAR(50)  NOT NULL UNIQUE,
          password      VARCHAR(255) NOT NULL,
          display_name  VARCHAR(50)  NOT NULL,
          role          ENUM('admin','user') NOT NULL DEFAULT 'user',
          is_active     TINYINT(1)   NOT NULL DEFAULT 1,
          age_confirmed TINYINT(1)   NOT NULL DEFAULT 0,
          created_at    DATETIME     NOT NULL,
          last_login_at DATETIME     NULL
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_users (
          id            INTEGER PRIMARY KEY AUTOINCREMENT,
          username      TEXT NOT NULL UNIQUE,
          password      TEXT NOT NULL,
          display_name  TEXT NOT NULL,
          role          TEXT NOT NULL DEFAULT 'user',
          is_active     INTEGER NOT NULL DEFAULT 1,
          age_confirmed INTEGER NOT NULL DEFAULT 0,
          created_at    TEXT NOT NULL,
          last_login_at TEXT NULL
        )",
    ];

    // 본인 검사 1회
    $t['big5_sessions'] = [
        "CREATE TABLE IF NOT EXISTS big5_sessions (
          id              INT AUTO_INCREMENT PRIMARY KEY,
          user_id         INT          NOT NULL,
          test_type       ENUM('full120','mini20') NOT NULL,
          item_version    VARCHAR(40)  NOT NULL,
          scoring_version VARCHAR(20)  NULL,
          status          ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
          started_at      DATETIME     NOT NULL,
          completed_at    DATETIME     NULL,
          duration_sec    INT          NULL,
          quality_flags   VARCHAR(255) NULL,
          consent_at      DATETIME     NOT NULL,
          INDEX idx_user (user_id),
          FOREIGN KEY (user_id) REFERENCES big5_users(id) ON DELETE CASCADE
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_sessions (
          id              INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id         INTEGER NOT NULL REFERENCES big5_users(id) ON DELETE CASCADE,
          test_type       TEXT NOT NULL,
          item_version    TEXT NOT NULL,
          scoring_version TEXT NULL,
          status          TEXT NOT NULL DEFAULT 'in_progress',
          started_at      TEXT NOT NULL,
          completed_at    TEXT NULL,
          duration_sec    INTEGER NULL,
          quality_flags   TEXT NULL,
          consent_at      TEXT NOT NULL
        )",
    ];

    // 문항별 응답 (본인·타인 공통)
    $t['big5_responses'] = [
        "CREATE TABLE IF NOT EXISTS big5_responses (
          id          BIGINT AUTO_INCREMENT PRIMARY KEY,
          owner_type  ENUM('session','rating') NOT NULL,
          owner_id    INT      NOT NULL,
          item_no     SMALLINT NOT NULL,
          value       TINYINT  NOT NULL,
          answered_at DATETIME NOT NULL,
          UNIQUE KEY uq_owner_item (owner_type, owner_id, item_no)
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_responses (
          id          INTEGER PRIMARY KEY AUTOINCREMENT,
          owner_type  TEXT NOT NULL,
          owner_id    INTEGER NOT NULL,
          item_no     INTEGER NOT NULL,
          value       INTEGER NOT NULL,
          answered_at TEXT NOT NULL,
          UNIQUE (owner_type, owner_id, item_no)
        )",
    ];

    // 채점 결과 (요인·하위 요인)
    $t['big5_scores'] = [
        "CREATE TABLE IF NOT EXISTS big5_scores (
          id          BIGINT AUTO_INCREMENT PRIMARY KEY,
          owner_type  ENUM('session','rating') NOT NULL,
          owner_id    INT          NOT NULL,
          level       ENUM('domain','facet') NOT NULL,
          code        VARCHAR(4)   NOT NULL,
          raw_score   SMALLINT     NOT NULL,
          pomp        DECIMAL(5,1) NOT NULL,
          band        ENUM('low','mid','high') NOT NULL,
          UNIQUE KEY uq_score (owner_type, owner_id, level, code)
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_scores (
          id          INTEGER PRIMARY KEY AUTOINCREMENT,
          owner_type  TEXT NOT NULL,
          owner_id    INTEGER NOT NULL,
          level       TEXT NOT NULL,
          code        TEXT NOT NULL,
          raw_score   INTEGER NOT NULL,
          pomp        REAL NOT NULL,
          band        TEXT NOT NULL,
          UNIQUE (owner_type, owner_id, level, code)
        )",
    ];

    // 타인 평가 초대 — 토큰 원문은 링크에만, DB 에는 SHA-256 해시만 둔다
    $t['big5_invites'] = [
        "CREATE TABLE IF NOT EXISTS big5_invites (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          user_id     INT      NOT NULL,
          token_hash  CHAR(64) NOT NULL UNIQUE,
          relation    ENUM('family','friend','colleague','other') NOT NULL,
          test_type   ENUM('full120','mini20') NOT NULL DEFAULT 'mini20',
          expires_at  DATETIME NOT NULL,
          created_at  DATETIME NOT NULL,
          INDEX idx_invite_user (user_id),
          FOREIGN KEY (user_id) REFERENCES big5_users(id) ON DELETE CASCADE
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_invites (
          id          INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id     INTEGER NOT NULL REFERENCES big5_users(id) ON DELETE CASCADE,
          token_hash  TEXT NOT NULL UNIQUE,
          relation    TEXT NOT NULL,
          test_type   TEXT NOT NULL DEFAULT 'mini20',
          expires_at  TEXT NOT NULL,
          created_at  TEXT NOT NULL
        )",
    ];

    // 타인 평가 1건
    $t['big5_ratings'] = [
        "CREATE TABLE IF NOT EXISTS big5_ratings (
          id              INT AUTO_INCREMENT PRIMARY KEY,
          invite_id       INT          NOT NULL,
          test_type       ENUM('full120','mini20') NOT NULL,
          item_version    VARCHAR(40)  NOT NULL,
          scoring_version VARCHAR(20)  NULL,
          status          ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
          started_at      DATETIME     NOT NULL,
          completed_at    DATETIME     NULL,
          duration_sec    INT          NULL,
          quality_flags   VARCHAR(255) NULL,
          INDEX idx_rating_invite (invite_id),
          FOREIGN KEY (invite_id) REFERENCES big5_invites(id) ON DELETE CASCADE
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_ratings (
          id              INTEGER PRIMARY KEY AUTOINCREMENT,
          invite_id       INTEGER NOT NULL REFERENCES big5_invites(id) ON DELETE CASCADE,
          test_type       TEXT NOT NULL,
          item_version    TEXT NOT NULL,
          scoring_version TEXT NULL,
          status          TEXT NOT NULL DEFAULT 'in_progress',
          started_at      TEXT NOT NULL,
          completed_at    TEXT NULL,
          duration_sec    INTEGER NULL,
          quality_flags   TEXT NULL
        )",
    ];

    // 성장 행동 선택
    $t['big5_growth_choices'] = [
        "CREATE TABLE IF NOT EXISTS big5_growth_choices (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          user_id     INT         NOT NULL,
          session_id  INT         NOT NULL,
          domain      CHAR(1)     NOT NULL,
          direction   ENUM('grow','shadow') NOT NULL,
          action_key  VARCHAR(40) NOT NULL,
          created_at  DATETIME    NOT NULL,
          INDEX idx_growth_user (user_id),
          FOREIGN KEY (user_id) REFERENCES big5_users(id) ON DELETE CASCADE
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_growth_choices (
          id          INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id     INTEGER NOT NULL REFERENCES big5_users(id) ON DELETE CASCADE,
          session_id  INTEGER NOT NULL,
          domain      TEXT NOT NULL,
          direction   TEXT NOT NULL,
          action_key  TEXT NOT NULL,
          created_at  TEXT NOT NULL
        )",
    ];

    // 규준 (다음 단계, 기본 비활성)
    $t['big5_norms'] = [
        "CREATE TABLE IF NOT EXISTS big5_norms (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          norm_set    VARCHAR(40)  NOT NULL,
          level       ENUM('domain','facet') NOT NULL,
          code        VARCHAR(4)   NOT NULL,
          n           INT          NOT NULL,
          mean_raw    DECIMAL(6,2) NOT NULL,
          sd_raw      DECIMAL(6,2) NOT NULL,
          is_active   TINYINT(1)   NOT NULL DEFAULT 0,
          created_at  DATETIME     NOT NULL,
          UNIQUE KEY uq_norm (norm_set, level, code)
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_norms (
          id          INTEGER PRIMARY KEY AUTOINCREMENT,
          norm_set    TEXT NOT NULL,
          level       TEXT NOT NULL,
          code        TEXT NOT NULL,
          n           INTEGER NOT NULL,
          mean_raw    REAL NOT NULL,
          sd_raw      REAL NOT NULL,
          is_active   INTEGER NOT NULL DEFAULT 0,
          created_at  TEXT NOT NULL,
          UNIQUE (norm_set, level, code)
        )",
    ];

    // 로그인 실패 기록 (무차별 대입 차단) — 성공하면 그 아이디의 기록을 지운다
    $t['big5_login_attempts'] = [
        "CREATE TABLE IF NOT EXISTS big5_login_attempts (
          id           INT AUTO_INCREMENT PRIMARY KEY,
          username     VARCHAR(50) NOT NULL,
          ip           VARCHAR(45) NOT NULL,
          attempted_at DATETIME    NOT NULL,
          INDEX idx_attempt_user (username, attempted_at),
          INDEX idx_attempt_ip (ip, attempted_at)
        )" . $my,
        "CREATE TABLE IF NOT EXISTS big5_login_attempts (
          id           INTEGER PRIMARY KEY AUTOINCREMENT,
          username     TEXT NOT NULL,
          ip           TEXT NOT NULL,
          attempted_at TEXT NOT NULL
        )",
    ];

    return $t;
}

/** 모든 테이블 생성 (멱등). 만든(확인한) 테이블 이름 목록을 돌려준다 */
function big5_ensure_schema(PDO $pdo): array
{
    $isMysql = big5_db_is_mysql($pdo);
    if (!$isMysql) {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    $done = [];
    foreach (big5_schema_tables() as $name => [$mysqlDdl, $sqliteDdl]) {
        $pdo->exec($isMysql ? $mysqlDdl : $sqliteDdl);
        $done[] = $name;
    }
    if (!$isMysql) {
        // SQLite 는 CREATE TABLE 안에 INDEX 를 둘 수 없어 따로 만든다
        foreach ([
            'CREATE INDEX IF NOT EXISTS idx_sessions_user ON big5_sessions(user_id)',
            'CREATE INDEX IF NOT EXISTS idx_invites_user ON big5_invites(user_id)',
            'CREATE INDEX IF NOT EXISTS idx_ratings_invite ON big5_ratings(invite_id)',
            'CREATE INDEX IF NOT EXISTS idx_growth_user ON big5_growth_choices(user_id)',
            'CREATE INDEX IF NOT EXISTS idx_attempt_user ON big5_login_attempts(username, attempted_at)',
            'CREATE INDEX IF NOT EXISTS idx_attempt_ip ON big5_login_attempts(ip, attempted_at)',
        ] as $sql) {
            $pdo->exec($sql);
        }
    }
    return $done;
}

/**
 * 초기 계정 — 관리자 sonbm / visang, 사용자 user / visang. 이미 있으면 건드리지 않는다.
 * 운영 배포 후에는 두 계정의 비밀번호를 바로 바꾼다.
 */
function big5_seed_accounts(PDO $pdo): array
{
    $msgs = [];
    $seeds = [
        ['sonbm', 'visang', '손병목', 'admin'],
        ['user', 'visang', '사용자', 'user'],
    ];
    $find = $pdo->prepare('SELECT id FROM big5_users WHERE username = ?');
    $ins = $pdo->prepare(
        'INSERT INTO big5_users (username, password, display_name, role, is_active, age_confirmed, created_at)
         VALUES (?, ?, ?, ?, 1, 1, ?)'
    );
    foreach ($seeds as [$username, $pw, $name, $role]) {
        $find->execute([$username]);
        if ($find->fetchColumn() !== false) {
            $msgs[] = "계정 {$username} 은(는) 이미 있습니다 (변경하지 않음)";
            continue;
        }
        $ins->execute([$username, password_hash($pw, PASSWORD_DEFAULT), $name, $role, big5_now()]);
        $msgs[] = "계정 {$username} 생성 (" . ($role === 'admin' ? '관리자' : '사용자') . ')';
    }
    return $msgs;
}
