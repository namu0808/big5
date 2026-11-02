<?php
/**
 * 개발 전용 — SQLite 개발 DB 를 새로 만든다 (기존 파일 삭제 → 1time/install.php 와 같은 스키마·초기 계정).
 * 실행: php doc/dev-sqlite-setup.php
 */
declare(strict_types=1);

$db = getenv('BIG5_DEV_SQLITE') ?: __DIR__ . '/dev.sqlite';
if (is_file($db)) {
    unlink($db);
}
putenv('BIG5_DB_CONFIG=' . __DIR__ . '/dev-db-sqlite.php');
require dirname(__DIR__) . '/includes/db_config.php';
require dirname(__DIR__) . '/includes/util.php';
require dirname(__DIR__) . '/includes/big5_schema.php';
if ($pdo === null) {
    fwrite(STDERR, "SQLite 연결 실패\n");
    exit(1);
}
big5_ensure_schema($pdo);
foreach (big5_seed_accounts($pdo) as $m) {
    echo $m, PHP_EOL;
}
echo "개발 DB 준비: {$db}", PHP_EOL;
