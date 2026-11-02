<?php
/** 사용자 로그인 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** 로그인 뒤 돌아갈 곳 — 앱 안 경로만 허용 (열린 리다이렉트 방지) */
function big5_login_next(): string
{
    $next = $_SESSION['big5_next'] ?? '';
    unset($_SESSION['big5_next']);
    $base = big5_base_url() . '/';
    if (!is_string($next) || $next === '' || !str_starts_with($next, $base) || str_contains($next, '//')
        || str_contains($next, '\\') || preg_match('/[\r\n]/', $next)) {
        return big5_url('index.php');
    }
    return $next;
}

$error = '';
$username = '';

if ($pdo !== null && big5_current_user($pdo) !== null) {
    big5_redirect('index.php');
}

if (big5_is_post()) {
    big5_csrf_check();
    $username = strtolower(big5_param('username'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($pdo === null) {
        $error = '지금은 로그인할 수 없습니다. 잠시 뒤 다시 시도해 주세요.';
    } else {
        $u = big5_authenticate($pdo, $username, $password);
        if (isset($u['error'])) {
            $error = (string) $u['error'];
        } else {
            big5_login_user($u);
            header('Location: ' . big5_login_next());
            exit;
        }
    }
}

$page_title = '로그인';
$nav_active = 'login';
require __DIR__ . '/includes/header.php';
?>
<section class="form-card">
  <h1>로그인</h1>
<?php if ($error !== ''): ?>
  <p class="form-error" role="alert"><?= h($error) ?></p>
<?php endif; ?>
  <form method="post" action="<?= h(big5_url('login.php')) ?>" novalidate>
    <?= big5_csrf_field() ?>
    <label class="field">
      <span>아이디</span>
      <input type="text" name="username" value="<?= h($username) ?>" autocomplete="username" autocapitalize="off" required>
    </label>
    <label class="field">
      <span>비밀번호</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn btn-primary btn-block">로그인</button>
  </form>
  <p class="form-foot">처음이신가요? <a href="<?= h(big5_url('register.php')) ?>">가입하기</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php';
