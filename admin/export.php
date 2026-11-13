<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/**
 * CSV 내려받기 — 익명 응답 데이터 (PLAN 8장) — 아이디·이름 없음, 응답자는 HMAC 해시(BIG5_EXPORT_SALT).
 * ?download=1&kind=self|ratings&type=full120|mini20&clean=1 (품질 플래그 제외)
 */
require_once __DIR__ . '/../includes/admin_stats.php';

$kind = big5_param('kind', 'self') === 'ratings' ? 'ratings' : 'self';
$type = big5_param('type', 'full120') === 'mini20' ? 'mini20' : 'full120';
$clean = big5_param('clean') === '1';
$saltOk = defined('BIG5_EXPORT_SALT') && strlen((string) BIG5_EXPORT_SALT) >= 16;

if (big5_param('download') === '1') {
    if (!$saltOk) {
        http_response_code(503);
        echo '내보내기 해시용 BIG5_EXPORT_SALT 가 설정 파일에 없습니다(16자 이상).';
        exit;
    }
    try {
        $rows = big5_export_rows($pdo, $kind, $type, $clean);
    } catch (Throwable $e) {
        error_log('다이얼 내보내기 오류: ' . $e->getMessage());
        http_response_code(500);
        echo '내보내지 못했습니다.';
        exit;
    }
    $name = 'big5_' . $kind . '_' . $type . ($clean ? '_clean' : '') . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // 엑셀에서 한글이 깨지지 않게 BOM
    foreach ($rows as $r) {
        // 셀이 = + - @ 로 시작하면 엑셀 수식으로 읽히지 않게 앞에 ' 를 붙인다 (CSV 수식 주입 방지)
        fputcsv($out, array_map(static fn($v) => preg_match('/^[=+\-@]/', (string) $v) && !is_numeric($v) ? "'" . $v : $v, $r), ',', '"', '');
    }
    fclose($out);
    exit;
}

$page_title = 'CSV 내려받기';
$layout_area = 'admin';
$nav_active = 'export';
require __DIR__ . '/../includes/header.php';
?>
<div class="adm">
  <header class="adm-head">
    <div class="adm-head-text">
      <h1 class="adm-title">CSV 내려받기</h1>
      <p class="adm-lead">익명 응답 데이터를 CSV 로 내려받습니다.</p>
    </div>
  </header>
  <ul class="export-notes">
    <li>CSV에는 <strong>아이디·이름·내부 번호를 넣지 않습니다.</strong> 응답자는 설정 파일의 비밀 값으로 만든 해시(HMAC-SHA256)로만 구별됩니다. 같은 사람의 여러 검사는 같은 해시가 됩니다.</li>
    <li>열: 응답자 해시, 본인/타인, 관계(타인 평가), 검사 종류, 문항·채점 버전, 완료일(날짜만), 소요 초, 품질 플래그, 요인 0~100, 하위 요인 0~100(정식), 문항별 응답</li>
  </ul>
<?php if (!$saltOk): ?>
  <p class="adm-error" role="alert">설정 파일에 <code class="adm-code">BIG5_EXPORT_SALT</code>(16자 이상)가 없어 내려받을 수 없습니다.</p>
<?php endif; ?>
  <form method="get" action="<?= h(big5_url('admin/export.php')) ?>" class="adm-box" id="exportForm">
    <input type="hidden" name="download" value="1">
    <label class="adm-field"><span>응답</span>
      <select name="kind"><option value="self">본인 검사</option><option value="ratings">받은 타인 평가</option></select>
    </label>
    <label class="adm-field"><span>대상</span>
      <select name="type"><option value="full120">정식 120문항</option><option value="mini20">간이 20문항</option></select>
    </label>
    <label class="adm-check"><input type="checkbox" name="clean" value="1"><span>품질 플래그가 붙은 응답은 빼기</span></label>
    <button type="submit" class="adm-btn adm-btn-main adm-btn-block"<?= $saltOk ? '' : ' disabled' ?>>CSV 내려받기</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php';
