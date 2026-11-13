<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/**
 * 규준 생성 (PLAN 3.5 — 다음 단계 준비, 뼈대).
 * 품질 플래그 없는 정식 검사 완료가 300건 미만이면 계산 단추를 막는다. 계산한 규준은 비활성으로 저장하고,
 * 활성화는 따로 한다. (리포트의 '참고 백분위' 표시는 다음 단계에서 붙인다 — 지금 리포트는 규준을 읽지 않는다)
 */
require_once __DIR__ . '/../includes/admin_stats.php';

$error = '';
if (big5_is_post()) {
    big5_csrf_check();
    $action = big5_param('action');
    try {
        if ($action === 'compute') {
            $name = 'site-' . date('Y-m-d');
            $r = big5_norms_compute($pdo, $name);
            if ($r['ok']) {
                big5_flash("규준 {$name} 을(를) 계산했습니다 ({$r['n']}건, {$r['rows']}항목). 활성화 전에는 리포트에 쓰이지 않습니다.");
                big5_redirect('admin/norms.php');
            }
            $error = (string) $r['error'];
        } elseif ($action === 'activate' || $action === 'deactivate') {
            $set = big5_param('norm_set');
            $known = array_column(big5_norm_sets($pdo), 'norm_set');
            big5_norm_activate($pdo, $action === 'activate' && in_array($set, $known, true) ? $set : null);
            big5_flash($action === 'activate' ? "규준 {$set} 을(를) 활성화했습니다." : '모든 규준을 비활성으로 돌렸습니다.');
            big5_redirect('admin/norms.php');
        }
    } catch (Throwable $e) {
        error_log('다이얼 규준 오류: ' . $e->getMessage());
        $error = '처리하지 못했습니다. 서버 오류 로그를 확인해 주세요.';
    }
}

$eligible = big5_norms_eligible($pdo);
$ready = $eligible >= BIG5_NORM_MIN;
$sets = big5_norm_sets($pdo);

$page_title = '규준';
$layout_area = 'admin';
$nav_active = 'norms';
require __DIR__ . '/../includes/header.php';
?>
<div class="adm">
  <header class="adm-head">
    <div class="adm-head-text">
      <h1 class="adm-title">규준</h1>
      <p class="adm-lead">규준은 이 사이트 응답자들의 요인·하위 요인별 평균과 표준편차입니다. 규준이 생기면 리포트에 '참고 백분위(이 사이트 응답자 기준)'를 보탤 수 있습니다. <strong>기본값은 비활성</strong>이고, 규준이 없거나 비활성이면 리포트는 0~100 환산 점수만 씁니다.</p>
    </div>
  </header>
  <section class="adm-sec">
    <dl class="adm-tiles">
      <div class="adm-tile"><dt>계산 대상 (품질 통과 정식 검사)</dt><dd><b id="eligible"><?= $eligible ?></b><small>필요 <?= BIG5_NORM_MIN ?>건</small></dd></div>
    </dl>
<?php if ($error !== ''): ?>
    <p class="adm-error norms-msg" role="alert"><?= h($error) ?></p>
<?php endif; ?>
<?php if (!$ready): ?>
    <p class="adm-note norms-msg" id="normsLocked">아직 <?= BIG5_NORM_MIN ?>건이 되지 않아 규준을 계산할 수 없습니다. (지금 <?= $eligible ?>건, <?= BIG5_NORM_MIN - $eligible ?>건 더 필요)</p>
<?php endif; ?>
    <form method="post" action="<?= h(big5_url('admin/norms.php')) ?>">
      <?= big5_csrf_field() ?>
      <input type="hidden" name="action" value="compute">
      <p class="adm-row"><button type="submit" class="adm-btn adm-btn-main" id="btnCompute"<?= $ready ? '' : ' disabled' ?>>규준 계산</button></p>
    </form>
  </section>
  <section class="adm-sec">
    <h2 class="adm-h">저장된 규준</h2>
<?php if ($sets === []): ?>
    <p class="adm-muted">저장된 규준이 없습니다.</p>
<?php else: ?>
    <div class="adm-table-wrap"><table class="adm-table" id="normsTable">
      <thead><tr><th>이름</th><th class="adm-num">표본 수</th><th class="adm-num">항목</th><th>만든 때</th><th>상태</th><th><span class="adm-sr">바꾸기</span></th></tr></thead>
      <tbody>
<?php foreach ($sets as $s): ?>
        <tr>
          <td class="adm-code"><?= h((string) $s['norm_set']) ?></td><td class="adm-num"><?= (int) $s['n'] ?></td><td class="adm-num"><?= (int) $s['rows_count'] ?></td>
          <td class="adm-code adm-nw"><?= h(substr((string) $s['created_at'], 0, 16)) ?></td><td><?= (int) $s['active'] === 1 ? '<span class="adm-badge adm-badge-ok">활성</span>' : '<span class="adm-badge adm-badge-off">비활성</span>' ?></td>
          <td><form method="post" action="<?= h(big5_url('admin/norms.php')) ?>"><?= big5_csrf_field() ?>
            <input type="hidden" name="norm_set" value="<?= h((string) $s['norm_set']) ?>">
            <input type="hidden" name="action" value="<?= (int) $s['active'] === 1 ? 'deactivate' : 'activate' ?>">
            <button type="submit" class="adm-btn adm-btn-sm"><?= (int) $s['active'] === 1 ? '비활성으로' : '활성화' ?></button></form></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php';
