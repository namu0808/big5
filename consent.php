<?php
/**
 * 검사 전 안내와 동의 — 이 검사가 무엇이고 무엇이 아닌지, 데이터 저장 방식(PLAN 8장), 동의 체크.
 * 같은 종류의 진행 중 검사가 있으면 '이어 하기'를 먼저 보여 준다.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/test_session.php';

$type = big5_param('type');
if (!in_array($type, BIG5_TEST_TYPES, true) || big5_items($type) === null) {
    big5_redirect('index.php');
}
$userId = (int) $big5User['id'];
$current = big5_session_in_progress($pdo, $userId, $type);
$error = '';

if (big5_is_post()) {
    big5_csrf_check();
    $action = big5_param('action');
    if ($action === 'start') {
        if (($_POST['agree'] ?? '') !== '1') {
            $error = '안내를 읽고 동의란에 표시해 주세요.';
        } else {
            try {
                // 진행 중이던 같은 종류 검사는 지우고 새로 시작한다
                if ($current !== null) {
                    big5_session_discard($pdo, (int) $current['id']);
                }
                $id = big5_session_start($pdo, $userId, $type);
                big5_redirect('test.php?id=' . $id);
            } catch (Throwable $e) {
                error_log('다이얼 검사 시작 오류: ' . $e->getMessage());
                $error = '검사를 시작하지 못했습니다. 잠시 뒤 다시 시도해 주세요.';
            }
        }
    }
}

$answered = 0;
if ($current !== null) {
    $answered = count(big5_responses_load($pdo, 'session', (int) $current['id']));
}
$total = $type === 'full120' ? 120 : 20;
$minutes = $type === 'full120' ? '약 15분' : '약 3분';

$page_title = '검사 전 안내';
require __DIR__ . '/includes/header.php';
?>
<article class="reading">
  <h1><?= h(big5_test_label($type)) ?> · 검사 전 안내</h1>

<?php if ($current !== null): ?>
  <div class="resume-box">
    <p><strong>하던 검사가 있어요.</strong> <?= $answered ?> / <?= $total ?>문항까지 답했습니다.</p>
    <a class="btn btn-primary btn-block" href="<?= h(big5_url('test.php?id=' . (int) $current['id'])) ?>">이어 하기</a>
    <p class="form-help">처음부터 다시 하려면 아래 안내를 읽고 동의한 뒤 [새로 시작]을 누르세요. 하던 응답은 지워집니다.</p>
  </div>
<?php endif; ?>

  <h2>이 검사는 무엇인가요</h2>
  <ul>
    <li>성격을 다섯 개의 다이얼(성실성·외향성·친화성·개방성·신경성)로 보는 <strong>빅파이브 성격 검사</strong>입니다.</li>
    <li>문항은 누구나 자유롭게 쓸 수 있게 공개된 국제 성격 문항 은행(IPIP)에서 가져왔습니다. <?= $type === 'full120' ? 'IPIP-NEO-120' : 'Mini-IPIP' ?>, <?= $total ?>문항, <?= h($minutes) ?> 걸립니다.</li>
    <li>결과는 '○○형' 같은 유형이 아니라, 다섯 다이얼 각각이 0~100 사이 어디쯤 놓여 있는지로 보여 드려요.</li>
  </ul>

  <h2>이 검사가 아닌 것</h2>
  <ul>
    <li><strong>진단 도구가 아닙니다.</strong> 정신 건강을 판정하지 않습니다.</li>
    <li>채용·평가·인사 판단이나 남을 판정하는 데 쓰지 않습니다.</li>
    <li>한국어 번역판은 아직 표준화 검증을 거치지 않았습니다. 다른 사람과 비교한 순위도 아닙니다.</li>
  </ul>

  <h2>저장하는 정보</h2>
  <ul>
    <li><strong>무엇을</strong>: 아이디, 표시 이름, 문항별 응답, 검사에 걸린 시간</li>
    <li><strong>왜</strong>: 본인에게 결과 리포트를 보여 드리기 위해, 그리고 이름·아이디를 뺀 익명 통계와 한국어판 기준점(규준) 연구를 위해</li>
    <li><strong>언제까지</strong>: 탈퇴하거나 직접 지울 때까지</li>
    <li><strong>지우는 법</strong>: 다이얼 [계정] 화면에서 다이얼 기록만, 사이트 공통 [내 계정]에서 계정과 모든 검사 기록을 지울 수 있습니다. 되돌릴 수 없습니다.</li>
    <li>결과 리포트는 본인만 볼 수 있습니다.</li>
  </ul>

  <h2>답하는 법</h2>
  <p>각 문장이 <strong>평소의 나</strong>에게 얼마나 들어맞는지 다섯 단계 중 하나를 고르세요. 오늘의 기분보다 평소의 모습을 떠올리면 됩니다. 정답은 없고, 처음 떠오르는 답이 대개 가장 정확해요. 중간에 나가도 답한 곳까지 저장되어 이어서 할 수 있습니다.</p>

<?php if ($error !== ''): ?>
  <p class="form-error" role="alert"><?= h($error) ?></p>
<?php endif; ?>
  <form method="post" action="<?= h(big5_url('consent.php?type=' . $type)) ?>" class="consent-form">
    <?= big5_csrf_field() ?>
    <input type="hidden" name="action" value="start">
    <label class="check">
      <input type="checkbox" name="agree" value="1">
      <span>위 안내를 읽었고, 응답을 저장하는 데 동의합니다.</span>
    </label>
    <button type="submit" class="btn <?= $current !== null ? 'btn-outline' : 'btn-primary' ?> btn-block"><?= $current !== null ? '새로 시작' : '검사 시작' ?></button>
  </form>
</article>
<?php require __DIR__ . '/includes/footer.php';
