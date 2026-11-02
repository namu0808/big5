<?php
/**
 * 세션 시작 — 이름 BIG5SESSID, 쿠키 경로는 설치 경로(/big5/)로 한정.
 * 같은 도메인의 WordPress·다른 앱과 세션이 섞이지 않는다.
 */
declare(strict_types=1);

function big5_session_begin(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('BIG5SESSID');
    session_set_cookie_params([
        'lifetime' => 0,                       // 브라우저를 닫으면 로그아웃
        'path'     => big5_base_url() . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();
}
