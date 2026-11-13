<?php
/**
 * 관리자 로그인 — 0.12.0부터 통합 관리자 로그인(사이트 /admin/login.php)으로 넘긴다(한 번 로그인으로 세 관리자 화면).
 * 코어가 옛 판(sbm_admin_url 없음)일 때만 아래의 예전 로그인 화면을 쓴다. admin_check 를 거치지 않는 유일한 /admin 파일.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (function_exists('sbm_admin_url')) {
    $big5Next = big5_param('next');
    header('Location: ' . sbm_admin_url('login.php', str_starts_with($big5Next, big5_url('admin/')) ? $big5Next : big5_url('admin/index.php')));
    exit;
}

$error = '';
$username = '';

if ($pdo !== null && big5_current_admin($pdo) !== null) {
    big5_redirect('admin/index.php');
}

if (big5_is_post()) {
    big5_csrf_check();
    $username = strtolower(big5_param('username'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($pdo === null) {
        $error = '지금은 로그인할 수 없습니다. DB 설정을 확인해 주세요.';
    } else {
        $u = big5_authenticate($pdo, $username, $password, true);
        if (isset($u['error'])) {
            $error = (string) $u['error'];
        } else {
            big5_login_admin($u);
            big5_redirect('admin/index.php');
        }
    }
}

$page_title = '관리자 로그인';
$layout_area = 'admin';
require __DIR__ . '/../includes/header.php';
?>
<div class="adm adm-narrow">
  <header class="adm-head">
    <div class="adm-head-text"><h1 class="adm-title">관리자 로그인</h1></div>
  </header>
<?php if ($error !== ''): ?>
  <p class="adm-error" role="alert"><?= h($error) ?></p>
<?php endif; ?>
  <form method="post" action="<?= h(big5_url('admin/login.php')) ?>" class="adm-box" novalidate>
    <?= big5_csrf_field() ?>
    <label class="adm-field">
      <span>아이디</span>
      <input type="text" name="username" value="<?= h($username) ?>" autocomplete="username" autocapitalize="off" required>
    </label>
    <label class="adm-field">
      <span>비밀번호</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="adm-btn adm-btn-main adm-btn-block">로그인</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php';
