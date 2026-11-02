<?php
/**
 * 타인 평가 응답 (로그인 없음) — rate.php?t=<64hex>
 *  안내 → 검사(초대에 정한 간이 20 또는 정식 120, '이 사람은 ~' 문장) → 완료(결과는 보여 주지 않음)
 *  진행 중 평가는 이 브라우저 세션에 기억해 이어 한다. 만료·멈춘 링크는 막는다.
 *  ajax=1 POST: action=answer | finish (test.php 와 같은 형식, assets/test.js 공용)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/others.php';
require_once __DIR__ . '/includes/test_ui.php';

$token = big5_param('t');
$isAjax = big5_is_post() && ($_POST['ajax'] ?? '') === '1';
$invite = $pdo !== null ? big5_invite_by_token($pdo, $token) : null;
$selfUrl = big5_url('rate.php?t=' . rawurlencode($token));

/** 안내 화면 하나를 그리고 끝낸다 */
function big5_rate_page(string $title, string $html, int $status = 200): void
{
    global $pdo;
    http_response_code($status);
    $page_title = $title;
    $hide_nav = true;
    require __DIR__ . '/includes/header.php';
    echo '<article class="reading rate-box"><p class="r-kicker">다이얼 · 타인 평가</p><h1>' . h($title) . '</h1>' . $html . '</article>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($invite === null) {
    if ($isAjax) {
        big5_json(['ok' => 0, 'code' => 'not_found', 'error' => '링크를 찾을 수 없습니다.']);
    }
    big5_rate_page('링크를 찾을 수 없습니다', '<p>주소가 바르지 않거나 더는 쓸 수 없는 링크입니다. 링크를 보낸 사람에게 다시 받아 주세요.</p>', 404);
}
if ($invite['expired']) {
    if ($isAjax) {
        big5_json(['ok' => 0, 'code' => 'expired', 'error' => '기한이 지난 링크입니다.']);
    }
    big5_rate_page('기한이 지난 링크입니다', '<p>이 링크는 쓸 수 있는 기간이 지났거나 멈춘 링크예요. 링크를 보낸 사람에게 새 링크를 받아 주세요.</p>', 410);
}

$hash = big5_token_hash($token);
$owner = (string) $invite['owner_name'];
$items = big5_items((string) $invite['test_type']);

/** 이 브라우저에서 진행 중인 평가 (이 초대의 것만) */
$rating = null;
$rid = (int) ($_SESSION['big5_rate'][$hash] ?? 0);
if ($rid > 0) {
    $st = $pdo->prepare('SELECT * FROM big5_ratings WHERE id = ? AND invite_id = ?');
    $st->execute([$rid, (int) $invite['id']]);
    $rating = $st->fetch() ?: null;
}

if ($isAjax) {
    big5_csrf_check(true);
    if ($rating === null || $rating['status'] !== 'in_progress') {
        big5_json(['ok' => 0, 'code' => 'no_rating', 'error' => '평가를 다시 시작해 주세요.', 'redirect' => $selfUrl]);
    }
    try {
        $action = big5_param('action');
        if ($action === 'answer') {
            $valid = array_flip(array_map(static fn($it) => (int) $it['no'], $items['items']));
            $no = filter_var($_POST['item_no'] ?? null, FILTER_VALIDATE_INT);
            $value = filter_var($_POST['value'] ?? null, FILTER_VALIDATE_INT);
            if (!is_int($no) || !isset($valid[$no]) || !is_int($value) || $value < 1 || $value > 5) {
                big5_json(['ok' => 0, 'code' => 'invalid', 'error' => '응답 값이 올바르지 않습니다.']);
            }
            big5_response_save($pdo, 'rating', (int) $rating['id'], $no, $value);
            big5_json(['ok' => 1]);
        }
        if ($action === 'finish') {
            $r = big5_rating_finish($pdo, $rating);
            if (!$r['ok']) {
                big5_json(['ok' => 0, 'code' => $r['code'], 'missing' => $r['missing'] ?? [], 'error' => '아직 답하지 않은 문항이 있습니다.']);
            }
            big5_json(['ok' => 1, 'redirect' => $selfUrl . '&done=1']);
        }
        big5_json(['ok' => 0, 'code' => 'action', 'error' => '알 수 없는 요청입니다.']);
    } catch (Throwable $e) {
        error_log('다이얼 타인 평가 저장 오류: ' . $e->getMessage());
        big5_json(['ok' => 0, 'code' => 'server', 'error' => '저장하지 못했습니다. 잠시 뒤 다시 눌러 주세요.']);
    }
}

if (big5_is_post() && big5_param('action') === 'start') {
    big5_csrf_check();
    if (($_POST['agree'] ?? '') !== '1') {
        big5_flash('안내를 읽고 동의란에 표시해 주세요.');
        header('Location: ' . $selfUrl);
        exit;
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM big5_ratings WHERE invite_id = ? AND status = 'completed'");
    $st->execute([(int) $invite['id']]);
    if ((int) $st->fetchColumn() >= BIG5_INVITE_MAX_RATINGS) {
        big5_rate_page('더 받을 수 없는 링크입니다', '<p>이 링크로 받을 수 있는 평가 수를 모두 채웠어요.</p>', 410);
    }
    $_SESSION['big5_rate'][$hash] = big5_rating_start($pdo, $invite);
    header('Location: ' . $selfUrl);
    exit;
}

if (big5_param('done') === '1' && $rating !== null && $rating['status'] === 'completed') {
    unset($_SESSION['big5_rate'][$hash]);
    big5_rate_page('평가를 마쳤습니다. 고맙습니다', '<p>' . h($owner) . '님에게 큰 도움이 될 거예요. 응답은 이름 없이 관계(가족·친구·동료·기타)로만 전해지고, 평가한 분께는 결과가 보이지 않습니다.</p><p class="muted">이 창은 닫으셔도 됩니다.</p>');
}

if ($rating !== null && $rating['status'] === 'in_progress') {
    $page_title = $owner . '님 평가';
    $body_class = 'page-test';
    $hide_nav = true;
    require __DIR__ . '/includes/header.php';
    big5_test_ui(
        array_map(static fn($it) => ['no' => (int) $it['no'], 'text' => (string) $it['text_ko_other']], $items['items']),
        $items['scale'],
        big5_responses_load($pdo, 'rating', (int) $rating['id']),
        $selfUrl,
        '“이 사람”은 ' . $owner . '님입니다. 평소 그분의 모습을 떠올려 답해 주세요.'
    );
    $extra_js = ['test.js'];
    require __DIR__ . '/includes/footer.php';
    exit;
}

// 안내 화면
$n = count($items['items']);
$page_title = $owner . '님이 평가를 부탁했어요';
$hide_nav = true;
require __DIR__ . '/includes/header.php';
?>
<article class="reading rate-box">
  <p class="r-kicker">다이얼 · 타인 평가</p>
  <h1><?= h($owner) ?>님이 평가를 부탁했어요</h1>
  <p><?= h($owner) ?>님은 빅파이브 성격 검사를 하고, 다른 사람 눈에 비친 자신의 모습도 궁금해하고 있어요. 문장 <?= $n ?>개를 읽고, 평소의 <?= h($owner) ?>님에게 얼마나 들어맞는지 골라 주세요. <?= $n > 20 ? '약 15분' : '약 3분' ?> 걸립니다.</p>
  <ul>
    <li>로그인하지 않으며, 이름이나 연락처를 묻지 않습니다.</li>
    <li>응답은 관계(<?= h(BIG5_RELATIONS[$invite['relation']] ?? '기타') ?>)로만 묶여 <?= h($owner) ?>님의 리포트에 전해집니다. 평가한 사람이 한 명뿐이면 응답이 그대로 드러날 수 있어요.</li>
    <li>평가한 분께는 결과가 보이지 않습니다. 이 검사는 진단 도구가 아니며, 채용·평가에 사용하지 않습니다.</li>
  </ul>
  <form method="post" action="<?= h($selfUrl) ?>" class="consent-form">
    <?= big5_csrf_field() ?>
    <input type="hidden" name="action" value="start">
    <label class="check"><input type="checkbox" name="agree" value="1"><span>위 안내를 읽었고, 응답을 저장하는 데 동의합니다.</span></label>
    <button type="submit" class="btn btn-primary btn-block">평가 시작</button>
  </form>
</article>
<?php require __DIR__ . '/includes/footer.php';
