<?php
/**
 * 개발 서버 라우터 — php -S 는 .htaccess 를 읽지 않으므로 운영 Apache 의 접근 차단을 흉내 낸다.
 *   includes/ · data/ · tests/ · doc/ · 점(.)으로 시작하는 경로, .md/.json/.sqlite → 403
 * 앱은 /big5/ 아래에서 도는 것처럼 BASE_URL 접두어를 벗겨 서빙한다(운영과 같은 경로 확인용).
 * 실행: BIG5_DB_CONFIG=$PWD/doc/dev-db-sqlite.php php -S 127.0.0.1:8093 doc/dev-router.php
 */
declare(strict_types=1);

$__r_root = dirname(__DIR__);
$__r_base = getenv('BIG5_BASE_URL') !== false ? rtrim((string) getenv('BIG5_BASE_URL'), '/') : '/big5';
$__r_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__r_path = rawurldecode($__r_path);

if ($__r_base !== '') {
    if ($__r_path === $__r_base) {
        header('Location: ' . $__r_base . '/');
        return true;
    }
    if (!str_starts_with($__r_path, $__r_base . '/')) {
        http_response_code(404);
        echo 'not found (앱은 ' . $__r_base . '/ 아래에 있습니다)';
        return true;
    }
    $__r_path = substr($__r_path, strlen($__r_base));
}

$__r_deny = preg_match('#^/(includes|data|tests|doc)(/|$)#', $__r_path)
    || preg_match('#/\.#', $__r_path)
    || preg_match('#\.(md|json|sqlite|log)$#i', $__r_path);
if ($__r_deny) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$__r_file = $__r_root . $__r_path;
if (is_dir($__r_file)) {
    $__r_file = rtrim($__r_file, '/') . '/index.php';
}
if (!is_file($__r_file)) {
    http_response_code(404);
    echo 'not found';
    return true;
}
if (str_ends_with($__r_file, '.php')) {
    $_SERVER['SCRIPT_NAME'] = $__r_base . $__r_path;
    $_SERVER['SCRIPT_FILENAME'] = $__r_file;
    chdir(dirname($__r_file));
    require $__r_file;
    return true;
}
$__r_types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon'];
$__r_ext = strtolower(pathinfo($__r_file, PATHINFO_EXTENSION));
header('Content-Type: ' . ($__r_types[$__r_ext] ?? 'application/octet-stream'));
readfile($__r_file);
return true;
