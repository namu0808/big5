<?php
/**
 * 본인 검사 화면 — 한 화면에 10문항, 진행 막대, 5점 버튼, [이전][다음], 답할 때마다 자동 저장(이어 하기).
 * 새로고침 없는 동작은 이 페이지에 ajax=1 POST:
 *   action=answer  item_no, value(1~5) → {ok, answered}
 *   action=finish  → 채점·품질 점검·완료 → {ok, redirect} | {ok:0, code:'missing', missing:[...]}
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/test_session.php';
require_once __DIR__ . '/includes/not_found.php';

$id = (int) big5_param('id', '0');
$session = $id > 0 ? big5_session_get($pdo, $id, (int) $big5User['id']) : null;
$isAjax = big5_is_post() && ($_POST['ajax'] ?? '') === '1';

if ($session === null) {
    if ($isAjax) {
        big5_json(['ok' => 0, 'code' => 'not_found', 'error' => '검사를 찾을 수 없습니다.']);
    }
    big5_not_found();
}

$items = big5_items((string) $session['test_type']);
if ($items === null) {
    error_log('다이얼 문항 데이터 없음: ' . $session['test_type']);
    if ($isAjax) {
        big5_json(['ok' => 0, 'code' => 'items', 'error' => '문항을 불러오지 못했습니다.']);
    }
    big5_not_found();
}
$validNos = array_flip(array_map(static fn($it) => (int) $it['no'], $items['items']));

if ($isAjax) {
    big5_csrf_check(true);
    if ($session['status'] === 'completed') {
        big5_json(['ok' => 0, 'code' => 'completed', 'error' => '이미 끝난 검사입니다.', 'redirect' => big5_url('report.php?id=' . $id)]);
    }
    $action = big5_param('action');
    try {
        if ($action === 'answer') {
            $no = filter_var($_POST['item_no'] ?? null, FILTER_VALIDATE_INT);
            $value = filter_var($_POST['value'] ?? null, FILTER_VALIDATE_INT);
            if (!is_int($no) || !isset($validNos[$no]) || !is_int($value) || $value < 1 || $value > 5) {
                big5_json(['ok' => 0, 'code' => 'invalid', 'error' => '응답 값이 올바르지 않습니다.']);
            }
            big5_response_save($pdo, 'session', $id, $no, $value);
            $st = $pdo->prepare("SELECT COUNT(*) FROM big5_responses WHERE owner_type = 'session' AND owner_id = ?");
            $st->execute([$id]);
            big5_json(['ok' => 1, 'answered' => (int) $st->fetchColumn()]);
        }
        if ($action === 'finish') {
            $r = big5_session_finish($pdo, $session);
            if (!$r['ok']) {
                big5_json(['ok' => 0, 'code' => $r['code'], 'missing' => $r['missing'] ?? [], 'error' => '아직 답하지 않은 문항이 있습니다.']);
            }
            big5_json(['ok' => 1, 'redirect' => big5_url('report.php?id=' . $id)]);
        }
        big5_json(['ok' => 0, 'code' => 'action', 'error' => '알 수 없는 요청입니다.']);
    } catch (Throwable $e) {
        error_log('다이얼 검사 저장 오류: ' . $e->getMessage());
        big5_json(['ok' => 0, 'code' => 'server', 'error' => '저장하지 못했습니다. 잠시 뒤 다시 눌러 주세요.']);
    }
}

if ($session['status'] === 'completed') {
    big5_redirect('report.php?id=' . $id);
}

// 화면에 실어 보낼 데이터 — 번호와 본인용 문장, 저장된 응답
$payload = [
    'items' => array_map(static fn($it) => ['no' => (int) $it['no'], 'text' => (string) $it['text_ko']], $items['items']),
    'scale' => $items['scale'],
    'answers' => (object) big5_responses_load($pdo, 'session', $id),
    'perPage' => 10,
];
$b64 = base64_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

$page_title = big5_test_label((string) $session['test_type']);
$body_class = 'page-test';
require __DIR__ . '/includes/header.php';
?>
<div class="test" id="test"
     data-items-b64="<?= h($b64) ?>"
     data-csrf="<?= h(big5_csrf_token()) ?>"
     data-post="<?= h(big5_url('test.php?id=' . $id)) ?>">
  <div class="test-head">
    <p class="test-guide">오늘의 기분보다 평소의 나를 떠올려 답해 주세요.</p>
    <div class="progress" role="progressbar" aria-label="진행" aria-valuemin="0" aria-valuemax="<?= count($items['items']) ?>" aria-valuenow="0">
      <div class="progress-bar" id="progressBar"></div>
    </div>
    <p class="progress-text"><span id="progressText">0 / <?= count($items['items']) ?></span><span id="saveState" class="save-state" aria-live="polite"></span></p>
  </div>
  <div id="questions" class="questions"></div>
  <p id="testError" class="form-error" role="alert" hidden></p>
  <div class="test-nav">
    <button type="button" class="btn btn-outline" id="btnPrev">이전</button>
    <button type="button" class="btn btn-primary" id="btnNext" disabled>다음</button>
  </div>
  <noscript><p class="form-error">이 검사는 자바스크립트가 켜져 있어야 진행할 수 있습니다.</p></noscript>
</div>
<?php
$extra_js = ['test.js'];
require __DIR__ . '/includes/footer.php';
