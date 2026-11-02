<?php
/** CLI 테스트 공통 — t_ok(조건, 이름), t_done() */
declare(strict_types=1);

$GLOBALS['t_pass'] = 0;
$GLOBALS['t_fail'] = 0;

function t_ok(bool $cond, string $name): void
{
    if ($cond) {
        $GLOBALS['t_pass']++;
        echo "  PASS  {$name}\n";
    } else {
        $GLOBALS['t_fail']++;
        echo "  FAIL  {$name}\n";
    }
}

function t_done(): void
{
    $p = $GLOBALS['t_pass'];
    $f = $GLOBALS['t_fail'];
    echo "\n결과: PASS {$p} / FAIL {$f}\n";
    exit($f === 0 ? 0 : 1);
}

/**
 * 테스트용 빈 DB — 기본은 SQLite 메모리, BIG5_TEST_DB=mysql 이면 로컬 MariaDB(doc/dev-db-mysql.php)의 big5_ 테이블을 모두 지우고 쓴다.
 * 스키마는 호출한 쪽이 big5_ensure_schema() 로 만든다.
 */
function t_pdo(): PDO
{
    if (getenv('BIG5_TEST_DB') === 'mysql') {
        $pdo = null;
        require dirname(__DIR__) . '/doc/dev-db-mysql.php';
        if (!$pdo instanceof PDO) {
            fwrite(STDERR, "MariaDB 에 연결할 수 없습니다.\n");
            exit(1);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (str_starts_with((string) $t, 'big5_')) {
                $pdo->exec('DROP TABLE `' . str_replace('`', '', (string) $t) . '`');
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        echo "  (DB: MariaDB)\n";
        return $pdo;
    }
    return new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
