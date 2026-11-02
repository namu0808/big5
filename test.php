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
require_once __DIR__ . '/includes/test_ui.php';

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

$page_title = big5_test_label((string) $session['test_type']);
$body_class = 'page-test';
require __DIR__ . '/includes/header.php';
big5_test_ui(
    array_map(static fn($it) => ['no' => (int) $it['no'], 'text' => (string) $it['text_ko']], $items['items']),
    $items['scale'],
    big5_responses_load($pdo, 'session', $id),
    big5_url('test.php?id=' . $id),
    '오늘의 기분보다 평소의 나를 떠올려 답해 주세요.'
);
$extra_js = ['test.js'];
require __DIR__ . '/includes/footer.php';
