<?php
/**
 * 타인 평가 초대 — 링크 만들기(관계·검사 종류, 14일), 받은 응답 수, 링크 멈추기.
 * 토큰 원문은 만든 직후 한 번만 보여 준다(DB 에는 해시만 있어 다시 만들 수 없다). 만들기는 POST → 리다이렉트.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/others.php';

$userId = (int) $big5User['id'];
$newLink = null;
$error = '';

if (big5_is_post()) {
    big5_csrf_check();
    $action = big5_param('action');
    try {
        if ($action === 'create') {
            $relation = big5_param('relation');
            $type = big5_param('test_type', 'mini20');
            if (!isset(BIG5_RELATIONS[$relation]) || !in_array($type, BIG5_TEST_TYPES, true)) {
                $error = '관계와 검사 종류를 골라 주세요.';
            } else {
                $token = big5_invite_create($pdo, $userId, $relation, $type);
                // 새로고침으로 다시 만들어지지 않게 세션에 한 번만 담고 다시 불러온다 (POST → 리다이렉트)
                $_SESSION['big5_new_invite'] = big5_origin() . big5_url('rate.php?t=' . $token);
                big5_redirect('invite.php');
            }
        } elseif ($action === 'stop') {
            $id = (int) big5_param('invite_id', '0');
            $pdo->prepare('UPDATE big5_invites SET expires_at = ? WHERE id = ? AND user_id = ?')->execute([big5_now(-1), $id, $userId]);
            big5_flash('링크를 멈췄습니다. 이제 이 링크로는 평가할 수 없어요.');
            big5_redirect('invite.php');
        }
    } catch (Throwable $e) {
        error_log('다이얼 초대 오류: ' . $e->getMessage());
        $error = '처리하지 못했습니다. 잠시 뒤 다시 시도해 주세요.';
    }
}

$newLink = is_string($_SESSION['big5_new_invite'] ?? null) ? $_SESSION['big5_new_invite'] : null;
unset($_SESSION['big5_new_invite']);
$invites = big5_invites_of($pdo, $userId);
$now = big5_now();

$page_title = '타인 평가';
$nav_active = 'invite';
require __DIR__ . '/includes/header.php';
?>
<article class="reading">
  <h1>다른 사람에게 평가 부탁하기</h1>
  <p>가족이나 친구, 동료에게 '나'에 대해 답해 달라고 부탁해 보세요. 내가 보는 나와 남이 보는 나가 어디서 다른지 알 수 있어요. 결과는 내 리포트의 '다른 사람이 본 나' 장에 나옵니다.</p>
  <ul class="form-help">
    <li>평가하는 사람은 로그인하지 않으며, 이름이나 연락처를 받지 않습니다. 리포트에는 관계(가족·친구·동료·기타)만 표시됩니다.</li>
    <li><strong>평가해 준 사람이 한 명뿐이면, 그 사람의 응답이 그대로 드러납니다.</strong> 이 점을 미리 알려 주세요.</li>
    <li>평가한 사람은 결과를 볼 수 없습니다. 링크는 <?= BIG5_INVITE_DAYS ?>일 동안 쓸 수 있어요.</li>
  </ul>

<?php if ($newLink !== null): ?>
  <div class="resume-box" id="newInvite">
    <p><strong>링크가 만들어졌어요.</strong> 이 링크는 지금 한 번만 보여 드립니다. 복사해서 보내 주세요.</p>
    <input type="text" class="copy-input" id="inviteLink" value="<?= h($newLink) ?>" readonly onclick="this.select()">
    <button type="button" class="btn btn-outline btn-sm" id="btnCopy" data-target="inviteLink">복사</button>
  </div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <p class="form-error" role="alert"><?= h($error) ?></p>
<?php endif; ?>

  <form method="post" action="<?= h(big5_url('invite.php')) ?>" class="form-card invite-form">
    <?= big5_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label class="field"><span>평가해 줄 사람과의 관계</span>
      <select name="relation" required>
<?php foreach (BIG5_RELATIONS as $k => $label): ?>
        <option value="<?= h($k) ?>"><?= h($label) ?></option>
<?php endforeach; ?>
      </select>
    </label>
    <label class="field"><span>검사 종류</span>
      <select name="test_type">
        <option value="mini20">간이 20문항 · 약 3분 (권장)</option>
        <option value="full120">정식 120문항 · 약 15분</option>
      </select>
    </label>
    <button type="submit" class="btn btn-primary btn-block">링크 만들기</button>
  </form>

  <h2>보낸 링크</h2>
<?php if ($invites === []): ?>
  <p class="muted">아직 만든 링크가 없습니다.</p>
<?php else: ?>
  <div class="table-wrap"><table class="r-table" id="inviteTable">
    <thead><tr><th>관계</th><th>검사</th><th>받은 응답</th><th>상태</th><th></th></tr></thead>
    <tbody>
<?php foreach ($invites as $iv): $live = (string) $iv['expires_at'] > $now; ?>
      <tr>
        <td><?= h(BIG5_RELATIONS[$iv['relation']] ?? '기타') ?></td>
        <td><?= $iv['test_type'] === 'full120' ? '정식 120' : '간이 20' ?></td>
        <td><?= (int) $iv['done'] ?>건</td>
        <td><?= $live ? h(substr((string) $iv['expires_at'], 0, 10)) . '까지' : '끝남' ?></td>
        <td>
<?php if ($live): ?>
          <form method="post" action="<?= h(big5_url('invite.php')) ?>"><?= big5_csrf_field() ?><input type="hidden" name="action" value="stop"><input type="hidden" name="invite_id" value="<?= (int) $iv['id'] ?>"><button type="submit" class="btn btn-outline btn-sm">멈추기</button></form>
<?php endif; ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</article>
<?php
$extra_js = ['copy.js'];
require __DIR__ . '/includes/footer.php';
