<?php
/**
 * 공통 도우미 — 이스케이프, 경로(BASE_URL), 리다이렉트, JSON 응답.
 * 모든 링크·리다이렉트·자산 경로는 big5_url() 로 만든다. (루트 기준 절대 경로 하드코딩 금지)
 */
declare(strict_types=1);

/** HTML 이스케이프 */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** 여러 줄 문구 — 이스케이프한 뒤 줄바꿈만 <br> 로 허용 */
function h_lines(?string $s): string
{
    return nl2br(h($s), false);
}

/** 설치 경로 (예: '/big5', 루트 설치는 '') — 외부 설정 BIG5_BASE_URL 이 있으면 그 값 */
function big5_base_url(): string
{
    $base = defined('BIG5_BASE_URL') ? (string) BIG5_BASE_URL : '/big5';
    $env = getenv('BIG5_BASE_URL'); // 개발 서버용
    if (!defined('BIG5_BASE_URL') && is_string($env)) {
        $base = $env;
    }
    return rtrim($base, '/');
}

/** 앱 안의 경로 → URL. big5_url('admin/login.php') → '/big5/admin/login.php' */
function big5_url(string $path = ''): string
{
    return big5_base_url() . '/' . ltrim($path, '/');
}

/** 앱 안의 경로로 이동하고 종료 */
function big5_redirect(string $path): void
{
    header('Location: ' . big5_url($path));
    exit;
}

/** JSON 응답(HTTP 200) 후 종료 — {ok, code, error, ...} */
function big5_json(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function big5_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** POST/GET 문자열 값 (배열 등 이상한 입력은 빈 문자열) */
function big5_param(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? ($_GET[$key] ?? $default);
    return is_string($v) ? trim($v) : $default;
}

/** 클라이언트 IP (로그인 시도 제한용) */
function big5_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
}

/** 한 번만 보여 줄 알림 */
function big5_flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['big5_flash'] = $msg;
        return null;
    }
    $m = $_SESSION['big5_flash'] ?? null;
    unset($_SESSION['big5_flash']);
    return is_string($m) ? $m : null;
}
