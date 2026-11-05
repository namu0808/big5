<?php
/**
 * 인증 — 사이트 공통 계정 코어(sbm.ai.kr core/auth.php)를 감싼다. 계정 테이블은 sbm_users 하나.
 * 사용자 세션 $_SESSION['user_id'], 관리자 세션 $_SESSION['admin_id'] 를 분리한다.
 * 로그인·가입·비밀번호·계정 삭제 화면은 사이트 공통 /account/ 에 있다(big5_account_url()).
 */
declare(strict_types=1);

function big5_valid_username(string $u): bool
{
    return sbm_valid_username($u);
}

/** 아이디·비밀번호 확인. 성공하면 사용자 행, 실패하면 ['error' => 안내문]. $requireAdmin 이면 role=admin 만 */
function big5_authenticate(PDO $pdo, string $username, string $password, bool $requireAdmin = false): array
{
    return sbm_authenticate($pdo, $username, $password, $requireAdmin);
}

function big5_login_user(array $u): void
{
    sbm_login_user($u);
}

function big5_login_admin(array $u): void
{
    sbm_login_admin($u);
}

/** 현재 로그인 사용자 행 (활성 계정만). 없으면 null */
function big5_current_user(?PDO $pdo): ?array
{
    return sbm_current_user($pdo);
}

/** 현재 관리자 행 (role=admin·활성). 없으면 null */
function big5_current_admin(?PDO $pdo): ?array
{
    return sbm_current_admin($pdo);
}

/** 로그아웃 — 'user' | 'admin' | 'all' */
function big5_logout(string $which = 'all'): void
{
    sbm_logout($which);
}

/**
 * 사이트 공통 계정 화면 주소 — 돌아올 곳은 다이얼 안 경로.
 * big5_account_url('login.php') → '/account/login.php?next=%2Fbig5%2Findex.php'
 * $next 를 주지 않으면 다이얼 시작 화면으로 돌아온다.
 */
function big5_account_url(string $page = '', ?string $next = null): string
{
    return sbm_account_url($page, $next ?? big5_url('index.php'));
}
