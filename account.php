<?php
/** 계정 — 비밀번호 변경, 내 데이터 전체 삭제(되돌릴 수 없음, 비밀번호와 확인 문구로 다시 확인) */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/user_delete.php';

$userId = (int) $big5User['id'];
$pwError = '';
$delError = '';
const BIG5_DELETE_PHRASE = '모두 삭제';

/** 현재 비밀번호 확인 */
function big5_check_password(PDO $pdo, int $userId, string $pw): bool
{
    $st = $pdo->prepare('SELECT password FROM big5_users WHERE id = ?');
    $st->execute([$userId]);
    $hash = $st->fetchColumn();
    return is_string($hash) && password_verify($pw, $hash);
}

if (big5_is_post()) {
    big5_csrf_check();
    $action = big5_param('action');
    $current = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
    if ($action === 'password') {
        $new = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
        $new2 = is_string($_POST['new2'] ?? null) ? $_POST['new2'] : '';
        if (!big5_check_password($pdo, $userId, $current)) {
            $pwError = '지금 비밀번호가 맞지 않습니다.';
        } elseif (strlen($new) < 8 || strlen($new) > 72) {
            $pwError = '새 비밀번호는 8자 이상으로 정해 주세요.';
        } elseif ($new !== $new2) {
            $pwError = '새 비밀번호 확인이 일치하지 않습니다.';
        } else {
            $pdo->prepare('UPDATE big5_users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            big5_flash('비밀번호를 바꿨습니다.');
            big5_redirect('account.php');
        }
    } elseif ($action === 'delete') {
        if (!big5_check_password($pdo, $userId, $current)) {
            $delError = '비밀번호가 맞지 않습니다.';
        } elseif (big5_param('phrase') !== BIG5_DELETE_PHRASE) {
            $delError = "확인 칸에 '" . BIG5_DELETE_PHRASE . "'를 정확히 적어 주세요.";
        } else {
            try {
                big5_user_delete($pdo, $userId);
                big5_logout('user');
                big5_session_begin();
                big5_flash('계정과 모든 검사 기록을 지웠습니다. 그동안 고맙습니다.');
                big5_redirect('index.php');
            } catch (Throwable $e) {
                error_log('다이얼 계정 삭제 오류: ' . $e->getMessage());
                $delError = '삭제하지 못했습니다. 잠시 뒤 다시 시도해 주세요.';
            }
        }
    }
}

$page_title = '계정';
$nav_active = 'account';
require __DIR__ . '/includes/header.php';
?>
<article class="reading">
  <h1>계정</h1>
  <p class="muted">아이디 <?= h((string) $big5User['username']) ?> · 표시 이름 <?= h((string) $big5User['display_name']) ?> · 가입 <?= h(substr((string) $big5User['created_at'], 0, 10)) ?></p>

  <h2>비밀번호 바꾸기</h2>
<?php if ($pwError !== ''): ?>
  <p class="form-error" role="alert"><?= h($pwError) ?></p>
<?php endif; ?>
  <form method="post" action="<?= h(big5_url('account.php')) ?>" class="form-card" id="pwForm">
    <?= big5_csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <label class="field"><span>지금 비밀번호</span><input type="password" name="current" autocomplete="current-password" required></label>
    <label class="field"><span>새 비밀번호 <small>8자 이상</small></span><input type="password" name="new" autocomplete="new-password" required></label>
    <label class="field"><span>새 비밀번호 확인</span><input type="password" name="new2" autocomplete="new-password" required></label>
    <button type="submit" class="btn btn-primary btn-block">바꾸기</button>
  </form>

  <h2>내 데이터 전체 삭제</h2>
  <div class="danger-box">
    <p>계정과 함께 <strong>모든 검사 기록, 응답, 점수, 성장 행동, 보낸 초대 링크, 받은 타인 평가</strong>가 지워집니다. <strong>되돌릴 수 없습니다.</strong></p>
<?php if ($delError !== ''): ?>
    <p class="form-error" role="alert"><?= h($delError) ?></p>
<?php endif; ?>
    <form method="post" action="<?= h(big5_url('account.php')) ?>" id="deleteForm">
      <?= big5_csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <label class="field"><span>비밀번호</span><input type="password" name="current" autocomplete="current-password" required></label>
      <label class="field"><span>확인을 위해 '<?= h(BIG5_DELETE_PHRASE) ?>'라고 적어 주세요</span><input type="text" name="phrase" autocomplete="off" required></label>
      <button type="submit" class="btn btn-danger btn-block">내 데이터 전체 삭제</button>
    </form>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php';
