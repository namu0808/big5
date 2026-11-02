<?php
/**
 * CSRF 토큰 — 세션당 1개. 모든 POST(폼·fetch)는 big5_csrf_check() 를 통과해야 한다.
 * 폼: <?= big5_csrf_field() ?>   fetch: FormData 에 'csrf' (페이지가 data-csrf 로 전달)
 */
declare(strict_types=1);

function big5_csrf_token(): string
{
    big5_session_begin();
    if (empty($_SESSION['big5_csrf']) || !is_string($_SESSION['big5_csrf'])) {
        $_SESSION['big5_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['big5_csrf'];
}

function big5_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(big5_csrf_token()) . '">';
}

/** 요청의 csrf 값이 세션 토큰과 같은지 (POST 본문 우선, 헤더 X-BIG5-CSRF 허용) */
function big5_csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_BIG5_CSRF'] ?? '');
    if (!is_string($sent) || $sent === '') {
        return false;
    }
    return hash_equals(big5_csrf_token(), $sent);
}

/** 검사 실패 시 종료. ajax 요청이면 JSON {ok:0, code:'csrf'}, 아니면 400 안내문 */
function big5_csrf_check(bool $isAjax = false): void
{
    if (big5_csrf_valid()) {
        return;
    }
    error_log('다이얼 CSRF 검사 실패: ' . ($_SERVER['REQUEST_URI'] ?? ''));
    if ($isAjax) {
        big5_json(['ok' => 0, 'code' => 'csrf', 'error' => '보안 토큰이 만료되었습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.']);
    }
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><title>요청 확인 실패</title></head>'
        . '<body style="font-family:sans-serif;padding:2rem"><p>보안 토큰이 만료되었거나 올바르지 않습니다. '
        . '이전 페이지로 돌아가 새로고침한 뒤 다시 시도해 주세요.</p><p><a href="javascript:history.back()">돌아가기</a></p></body></html>';
    exit;
}
