<?php
/**
 * 세션 — 사이트 공통 세션(SBMSESSID, 쿠키 경로 '/')을 쓴다. 한 번 로그인하면 홈·/account/·/big5/ 가 함께 로그인 상태.
 * 다이얼이 쓰는 세션 키는 big5_ 접두어를 붙인다(big5_flash, big5_new_invite …).
 */
declare(strict_types=1);

function big5_session_begin(): void
{
    sbm_session_begin();
}
