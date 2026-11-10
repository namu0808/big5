<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/** 사용자 목록 — 검색(아이디·표시 이름), 사용 중지/다시 사용. 관리자 계정은 바꿀 수 없다 */
const BIG5_USERS_PER_PAGE = 50;

if (big5_is_post()) {
    big5_csrf_check();
    $id = (int) big5_param('user_id', '0');
    $active = big5_param('active') === '1' ? 1 : 0;
    // 은퇴(삭제한) 계정은 다시 켜지 않는다 — 아이디가 '~del…' 로 바뀌어 있다
    $pdo->prepare("UPDATE sbm_users SET is_active = ? WHERE id = ? AND role = 'user' AND username NOT LIKE '~%'")->execute([$active, $id]);
    big5_flash($active ? '다시 사용할 수 있게 했습니다.' : '사용을 중지했습니다. 이 사용자는 로그인할 수 없습니다.');
    big5_redirect('admin/users.php?q=' . rawurlencode(big5_param('q')) . '&p=' . (int) big5_param('p', '1'));
}

$q = mb_substr(big5_param('q'), 0, 50);
$page = max(1, (int) big5_param('p', '1'));
$where = '';
$params = [];
if ($q !== '') {
    // LIKE 특수 문자 이스케이프 — 역슬래시는 MariaDB 문자열에서 해석되므로 '!' 를 이스케이프 문자로 쓴다
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
    $where = "WHERE u.username LIKE ? ESCAPE '!' OR u.display_name LIKE ? ESCAPE '!'";
    $params = [$like, $like];
}
$st = $pdo->prepare("SELECT COUNT(*) FROM sbm_users u {$where}");
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / BIG5_USERS_PER_PAGE));
$page = min($page, $pages);
$st = $pdo->prepare(
    "SELECT u.id, u.username, u.display_name, u.role, u.is_active, u.created_at, u.last_login_at,
            (SELECT COUNT(*) FROM big5_sessions s WHERE s.user_id = u.id AND s.status = 'completed') AS done
       FROM sbm_users u {$where} ORDER BY u.id DESC LIMIT " . BIG5_USERS_PER_PAGE . ' OFFSET ' . (($page - 1) * BIG5_USERS_PER_PAGE)
);
$st->execute($params);
$users = $st->fetchAll();

$page_title = '사용자';
$layout_area = 'admin';
$nav_active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<h1>사용자</h1>
<p class="muted">SBM 진단 검사의 <strong>사이트 공통 계정</strong>입니다. 사용을 중지하면 이 사이트의 모든 서비스에 로그인할 수 없습니다.</p>
<form method="get" action="<?= h(big5_url('admin/users.php')) ?>" class="inline-form">
  <label>검색 <input type="search" name="q" value="<?= h($q) ?>" placeholder="아이디 또는 이름" class="input-sm"></label>
  <button type="submit" class="btn btn-outline btn-sm">찾기</button>
  <span class="muted"><?= $total ?>명</span>
</form>
<div class="table-wrap"><table class="table" id="userTable">
  <thead><tr><th>번호</th><th>아이디</th><th>이름</th><th>구분</th><th>완료 검사</th><th>가입</th><th>최근 로그인</th><th>상태</th><th></th></tr></thead>
  <tbody>
<?php foreach ($users as $u): $retired = sbm_user_is_retired($u); $isUser = $u['role'] === 'user' && !$retired; ?>
    <tr data-username="<?= h((string) $u['username']) ?>">
      <td><?= (int) $u['id'] ?></td>
      <td><?= h((string) $u['username']) ?></td>
      <td><?= h((string) $u['display_name']) ?></td>
      <td><?= $retired ? '삭제한 계정' : ($isUser ? '사용자' : '관리자') ?></td>
      <td><?= (int) $u['done'] ?></td>
      <td><?= h(substr((string) $u['created_at'], 0, 10)) ?></td>
      <td><?= h(substr((string) ($u['last_login_at'] ?? ''), 0, 16)) ?></td>
      <td><?= $retired ? '<span class="muted">닫힘(기록 보존)</span>' : ((int) $u['is_active'] === 1 ? '사용 중' : '<strong>중지</strong>') ?></td>
      <td>
<?php if ($isUser): ?>
        <form method="post" action="<?= h(big5_url('admin/users.php')) ?>">
          <?= big5_csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
          <input type="hidden" name="active" value="<?= (int) $u['is_active'] === 1 ? '0' : '1' ?>">
          <input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="p" value="<?= $page ?>">
          <button type="submit" class="btn btn-outline btn-sm"><?= (int) $u['is_active'] === 1 ? '사용 중지' : '다시 사용' ?></button>
        </form>
<?php endif; ?>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($pages > 1): ?>
<p class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= h(big5_url('admin/users.php?q=' . rawurlencode($q) . '&p=' . $i)) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a> <?php endfor; ?></p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
