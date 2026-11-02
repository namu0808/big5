<?php
/**
 * 개발 전용 — 로컬 MariaDB 접속 설정 (운영과 같은 DB 엔진으로 검증할 때). FTP 배포 금지.
 * 운영 설정 견본 doc/big5_db.sample.php 와 같은 계약. 접속 값은 개발용 고정값(로컬 전용 계정).
 * sql_mode 를 운영보다 엄격하게(ONLY_FULL_GROUP_BY 포함) 두어 이식성 문제를 미리 잡는다.
 * 사용: BIG5_DB_CONFIG=doc/dev-db-mysql.php (테스트는 BIG5_TEST_DB=mysql)
 */
declare(strict_types=1);

define('BIG5_DB_HOST', getenv('BIG5_MYSQL_HOST') ?: '127.0.0.1');
define('BIG5_DB_NAME', getenv('BIG5_MYSQL_DB') ?: 'big5_test');
define('BIG5_DB_USER', getenv('BIG5_MYSQL_USER') ?: 'big5dev');
define('BIG5_DB_PASS', getenv('BIG5_MYSQL_PASS') ?: 'big5dev');
defined('BIG5_EXPORT_SALT') || define('BIG5_EXPORT_SALT', 'dev-only-salt-not-for-production');
define('BIG5_BASE_URL', getenv('BIG5_BASE_URL') !== false ? (string) getenv('BIG5_BASE_URL') : '/big5');

try {
    $pdo = new PDO(
        'mysql:host=' . BIG5_DB_HOST . ';dbname=' . BIG5_DB_NAME . ';charset=utf8mb4',
        BIG5_DB_USER,
        BIG5_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+09:00'");
    $pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
} catch (PDOException $e) {
    error_log('다이얼 dev mysql 연결 실패: ' . $e->getMessage());
    $pdo = null;
}
