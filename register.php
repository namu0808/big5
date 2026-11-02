<?php
/** 회원가입 — 아이디, 비밀번호, 이름(표시용), 만 14세 이상 확인 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$username = '';
$displayName = '';

if ($pdo !== null && big5_current_user($pdo) !== null) {
    big5_redirect('index.php');
}

if (big5_is_post()) {
    big5_csrf_check();
    $username = strtolower(big5_param('username'));
    $displayName = big5_param('display_name');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $password2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
    $age = ($_POST['age14'] ?? '') === '1';

    if (!big5_valid_username($username)) {
        $errors[] = '아이디는 영문 소문자·숫자·밑줄(_)로 4~20자입니다.';
    }
    $nameLen = mb_strlen($displayName, 'UTF-8');
    if ($nameLen < 1 || $nameLen > 20) {
        $errors[] = '이름(표시용)은 1~20자로 입력해 주세요.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = '비밀번호는 8자 이상으로 정해 주세요.';
    } elseif ($password !== $password2) {
        $errors[] = '비밀번호 확인이 일치하지 않습니다.';
    }
    if (!$age) {
        $errors[] = '만 14세 이상만 가입할 수 있습니다. 확인란에 표시해 주세요.';
    }
    if ($pdo === null) {
        $errors[] = '지금은 가입할 수 없습니다. 잠시 뒤 다시 시도해 주세요.';
    }

    if ($errors === [] && $pdo !== null) {
        try {
            $st = $pdo->prepare('SELECT 1 FROM big5_users WHERE username = ?');
            $st->execute([$username]);
            if ($st->fetchColumn() !== false) {
                $errors[] = '이미 쓰이고 있는 아이디입니다.';
            } else {
                $pdo->prepare(
                    "INSERT INTO big5_users (username, password, display_name, role, is_active, age_confirmed, created_at)
                     VALUES (?, ?, ?, 'user', 1, 1, ?)"
                )->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName, big5_now()]);
                $id = (int) $pdo->lastInsertId();
                big5_login_user(['id' => $id]);
                big5_flash('가입이 끝났습니다. 이제 검사를 시작할 수 있어요.');
                big5_redirect('index.php');
            }
        } catch (Throwable $e) {
            error_log('다이얼 가입 오류: ' . $e->getMessage());
            $errors[] = '가입 중 문제가 생겼습니다. 잠시 뒤 다시 시도해 주세요.';
        }
    }
}

$page_title = '가입';
$nav_active = 'register';
require __DIR__ . '/includes/header.php';
?>
<section class="form-card">
  <h1>가입</h1>
<?php if ($errors !== []): ?>
  <ul class="form-error" role="alert">
<?php foreach ($errors as $e): ?>
    <li><?= h($e) ?></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
  <form method="post" action="<?= h(big5_url('register.php')) ?>" novalidate>
    <?= big5_csrf_field() ?>
    <label class="field">
      <span>아이디 <small>영문 소문자·숫자·밑줄, 4~20자</small></span>
      <input type="text" name="username" value="<?= h($username) ?>" autocomplete="username" autocapitalize="off" maxlength="20" required>
    </label>
    <label class="field">
      <span>비밀번호 <small>8자 이상</small></span>
      <input type="password" name="password" autocomplete="new-password" required>
    </label>
    <label class="field">
      <span>비밀번호 확인</span>
      <input type="password" name="password2" autocomplete="new-password" required>
    </label>
    <label class="field">
      <span>이름 <small>리포트에 표시됩니다. 별명도 괜찮아요</small></span>
      <input type="text" name="display_name" value="<?= h($displayName) ?>" maxlength="20" required>
    </label>
    <label class="check">
      <input type="checkbox" name="age14" value="1">
      <span>만 14세 이상입니다.</span>
    </label>
    <p class="form-help">가입할 때는 아이디·비밀번호·표시 이름만 받습니다. 연락처나 실명은 받지 않아요. 내 데이터는 언제든 계정 화면에서 모두 지울 수 있습니다.</p>
    <button type="submit" class="btn btn-primary btn-block">가입하기</button>
  </form>
  <p class="form-foot">이미 계정이 있나요? <a href="<?= h(big5_url('login.php')) ?>">로그인</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php';
