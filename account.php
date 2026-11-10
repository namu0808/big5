<?php
/**
 * 계정 — 다이얼 기록 관리. 계정 정보·비밀번호·계정 전체 삭제는 사이트 공통 [내 계정](/account/) 에서 한다.
 * 여기서는 다이얼 기록만 지운다(계정은 남는다). 비밀번호와 확인 문구로 다시 확인한다.
 * 지운 기록은 내 화면에서 사라지지만 DB 에는 익명으로 남는다(빈 은퇴 계정으로 옮김 — includes/user_delete.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/user_delete.php';

$userId = (int) $big5User['id'];
$delError = '';
const BIG5_DELETE_PHRASE = '모두 삭제';

if (big5_is_post()) {
    big5_csrf_check();
    if (big5_param('action') === 'delete') {
        $current = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
        if (!sbm_check_password($pdo, $userId, $current)) {
            $delError = '비밀번호가 맞지 않습니다.';
        } elseif (big5_param('phrase') !== BIG5_DELETE_PHRASE) {
            $delError = "확인 칸에 '" . BIG5_DELETE_PHRASE . "'를 정확히 적어 주세요.";
        } else {
            try {
                big5_user_delete_data_tx($pdo, $userId);
                big5_flash('다이얼 검사 기록을 모두 지웠습니다. 계정은 그대로 남아 있어요.');
                big5_redirect('index.php');
            } catch (Throwable $e) {
                error_log('다이얼 기록 삭제 오류: ' . $e->getMessage());
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

  <h2>SBM 진단 검사 공통 계정</h2>
  <p>다이얼은 <strong>SBM 진단 검사</strong>의 한 계정으로 이용합니다. 비밀번호 바꾸기와 계정 전체 삭제(모든 검사 기록 포함)는 [내 계정]에서 할 수 있어요.</p>
  <p><a class="btn btn-primary btn-block" href="/account/" id="sbmAccountLink">내 계정으로 가기</a></p>

  <h2>다이얼 기록만 삭제</h2>
  <div class="danger-box">
    <p>다이얼의 <strong>모든 검사 기록, 응답, 점수, 성장 행동, 보낸 초대 링크, 받은 타인 평가</strong>가 내 화면에서 사라지고, 보낸 초대 링크는 더 열리지 않습니다. 계정은 남아 다른 검사를 계속 이용할 수 있어요. <strong>되돌릴 수 없습니다.</strong></p>
    <p class="muted small">아이디·이름과 떼어 낸 익명 응답 기록은 검사 개선을 위한 연구 자료로 보관됩니다(<a href="/privacy.php">개인정보 처리 안내</a>).</p>
<?php if ($delError !== ''): ?>
    <p class="form-error" role="alert"><?= h($delError) ?></p>
<?php endif; ?>
    <form method="post" action="<?= h(big5_url('account.php')) ?>" id="deleteForm">
      <?= big5_csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <label class="field"><span>비밀번호</span><input type="password" name="current" autocomplete="current-password" required></label>
      <label class="field"><span>확인을 위해 '<?= h(BIG5_DELETE_PHRASE) ?>'라고 적어 주세요</span><input type="text" name="phrase" autocomplete="off" required></label>
      <button type="submit" class="btn btn-danger btn-block">다이얼 기록 전체 삭제</button>
    </form>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php';
