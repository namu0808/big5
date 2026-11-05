<?php
/**
 * CSRF 토큰 — 사이트 공통 코어(sbm_csrf_*)를 쓴다. 세션당 1개. 모든 POST(폼·fetch)는 big5_csrf_check() 를 통과해야 한다.
 * 폼: <?= big5_csrf_field() ?>   fetch: FormData 에 'csrf' (페이지가 data-csrf 로 전달)
 */
declare(strict_types=1);

function big5_csrf_token(): string
{
    return sbm_csrf_token();
}

function big5_csrf_field(): string
{
    return sbm_csrf_field();
}

/** 요청의 csrf 값이 세션 토큰과 같은지 (POST 본문 우선, 헤더 X-BIG5-CSRF 허용) */
function big5_csrf_valid(): bool
{
    return sbm_csrf_valid();
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
