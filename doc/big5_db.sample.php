<?php
/**
 * 운영 DB 설정 견본 — 이 파일을 서버의 {홈}/config/big5_db.php 로 복사해 값을 채운다.
 * (웹루트 www 밖. 저장소·FTP 업로드 목록에 실제 값이 든 파일을 두지 않는다)
 */
declare(strict_types=1);

define('BIG5_DB_HOST', 'localhost');
define('BIG5_DB_NAME', 'DB이름');
define('BIG5_DB_USER', 'DB아이디');
define('BIG5_DB_PASS', 'DB비밀번호');
define('BIG5_EXPORT_SALT', '여기에-40자-이상의-무작위-문자열');  // 관리자 CSV 익명 해시용, 한 번 정하면 바꾸지 않는다
define('BIG5_BASE_URL', '/big5');                                  // 설치 경로

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
} catch (PDOException $e) {
    error_log('다이얼 DB 연결 실패: ' . $e->getMessage());
    $pdo = null;
}
