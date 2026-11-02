<?php
/**
 * 인증 함수 — 사용자 세션 $_SESSION['user_id'], 관리자 세션 $_SESSION['admin_id'] 를 분리한다.
 * 관리자 계정(role=admin)은 사용자 화면도 쓸 수 있지만, 관리자 화면은 관리자 로그인(admin_id)이 있어야 한다.
 */
declare(strict_types=1);

const BIG5_LOGIN_WINDOW_SEC = 900;   // 15분
const BIG5_LOGIN_MAX_PER_USER = 5;   // 같은 아이디 실패 허용 횟수
const BIG5_LOGIN_MAX_PER_IP = 20;    // 같은 IP 실패 허용 횟수

/** 아이디 형식: 영문 소문자·숫자·밑줄, 4~20자 (시드 계정 'user' 포함) */
function big5_valid_username(string $u): bool
{
    return (bool) preg_match('/^[a-z0-9_]{4,20}$/', $u);
}

/** 로그인 차단 여부 (최근 15분 실패 횟수) */
function big5_login_blocked(PDO $pdo, string $username, string $ip): bool
{
    $since = big5_now(-BIG5_LOGIN_WINDOW_SEC);
    $st = $pdo->prepare('SELECT COUNT(*) FROM big5_login_attempts WHERE username = ? AND attempted_at >= ?');
    $st->execute([$username, $since]);
    if ((int) $st->fetchColumn() >= BIG5_LOGIN_MAX_PER_USER) {
        return true;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM big5_login_attempts WHERE ip = ? AND attempted_at >= ?');
    $st->execute([$ip, $since]);
    return (int) $st->fetchColumn() >= BIG5_LOGIN_MAX_PER_IP;
}

function big5_login_record_failure(PDO $pdo, string $username, string $ip): void
{
    $pdo->prepare('INSERT INTO big5_login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)')
        ->execute([substr($username, 0, 50), $ip, big5_now()]);
    // 오래된 기록 정리 (하루 지난 것)
    $pdo->prepare('DELETE FROM big5_login_attempts WHERE attempted_at < ?')->execute([big5_now(-86400)]);
}

/**
 * 아이디·비밀번호 확인. 성공하면 사용자 행, 실패하면 ['error' => 안내문].
 * $requireAdmin 이면 role=admin 만 통과.
 */
function big5_authenticate(PDO $pdo, string $username, string $password, bool $requireAdmin = false): array
{
    $ip = big5_client_ip();
    $username = strtolower(trim($username));
    if ($username === '' || $password === '') {
        return ['error' => '아이디와 비밀번호를 입력해 주세요.'];
    }
    if (big5_login_blocked($pdo, $username, $ip)) {
        return ['error' => '로그인 시도가 너무 많습니다. 15분 뒤에 다시 시도해 주세요.'];
    }
    $st = $pdo->prepare('SELECT id, username, password, display_name, role, is_active FROM big5_users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    // 없는 아이디도 같은 시간이 걸리게 가짜 해시를 검증한다 (아이디 존재 여부 노출 방지)
    $hash = is_array($u) ? (string) $u['password'] : password_hash('big5-dummy', PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash) && is_array($u);
    if (!$ok || ($requireAdmin && $u['role'] !== 'admin')) {
        big5_login_record_failure($pdo, $username, $ip);
        return ['error' => '아이디 또는 비밀번호가 맞지 않습니다.'];
    }
    if ((int) $u['is_active'] !== 1) {
        return ['error' => '사용이 중지된 계정입니다. 관리자에게 문의해 주세요.'];
    }
    $pdo->prepare('DELETE FROM big5_login_attempts WHERE username = ?')->execute([$username]);
    $pdo->prepare('UPDATE big5_users SET last_login_at = ? WHERE id = ?')->execute([big5_now(), (int) $u['id']]);
    if (password_needs_rehash((string) $u['password'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE big5_users SET password = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $u['id']]);
    }
    unset($u['password']);
    return $u;
}

/** 사용자 로그인 세션 시작 (세션 고정 공격 방지: id 재발급) */
function big5_login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $u['id'];
}

/** 관리자 로그인 세션 시작 */
function big5_login_admin(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $u['id'];
}

/** 현재 로그인 사용자 행 (활성 계정만). 없으면 null */
function big5_current_user(?PDO $pdo): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) || $pdo === null) {
        return null;
    }
    $st = $pdo->prepare('SELECT id, username, display_name, role, is_active, created_at FROM big5_users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!is_array($u) || (int) $u['is_active'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $u;
}

/** 현재 관리자 행 (role=admin·활성). 없으면 null */
function big5_current_admin(?PDO $pdo): ?array
{
    $id = $_SESSION['admin_id'] ?? null;
    if (!is_int($id) || $pdo === null) {
        return null;
    }
    $st = $pdo->prepare("SELECT id, username, display_name, role, is_active FROM big5_users WHERE id = ? AND role = 'admin'");
    $st->execute([$id]);
    $u = $st->fetch();
    if (!is_array($u) || (int) $u['is_active'] !== 1) {
        unset($_SESSION['admin_id']);
        return null;
    }
    return $u;
}

/** 로그아웃 — 'user' | 'admin' | 'all' */
function big5_logout(string $which = 'all'): void
{
    if ($which === 'user' || $which === 'all') {
        unset($_SESSION['user_id']);
    }
    if ($which === 'admin' || $which === 'all') {
        unset($_SESSION['admin_id']);
    }
    if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
                'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();
    } else {
        session_regenerate_id(true);
    }
}
