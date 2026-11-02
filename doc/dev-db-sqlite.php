<?php
/**
 * 개발 전용 — SQLite 접속 설정 (비밀정보 없음). FTP 배포 금지.
 * includes/db_config.php 가 환경변수 BIG5_DB_CONFIG 로 이 파일을 읽는다.
 * DB 파일 위치는 BIG5_DEV_SQLITE 환경변수(없으면 doc/dev.sqlite).
 */
declare(strict_types=1);

define('BIG5_DB_HOST', 'sqlite');
define('BIG5_DB_NAME', getenv('BIG5_DEV_SQLITE') ?: __DIR__ . '/dev.sqlite');
define('BIG5_DB_USER', '');
define('BIG5_DB_PASS', '');
define('BIG5_EXPORT_SALT', 'dev-only-salt-not-for-production');
define('BIG5_BASE_URL', getenv('BIG5_BASE_URL') !== false ? (string) getenv('BIG5_BASE_URL') : '/big5');

try {
    $pdo = new PDO('sqlite:' . BIG5_DB_NAME, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 3000');
} catch (PDOException $e) {
    error_log('다이얼 dev sqlite 연결 실패: ' . $e->getMessage());
    $pdo = null;
}
