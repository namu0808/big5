<?php
/**
 * 검사 화면 마크업 (본인 검사 test.php 와 타인 평가 rate.php 가 함께 쓴다). 동작은 assets/test.js.
 * $texts = [['no' => 1, 'text' => '…'], ...], $answers = [번호 => 값]
 */
declare(strict_types=1);

function big5_test_ui(array $texts, array $scale, array $answers, string $postUrl, string $guide): void
{
    $payload = ['items' => $texts, 'scale' => $scale, 'answers' => (object) $answers, 'perPage' => 10];
    $b64 = base64_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    $n = count($texts);
    ?>
<div class="test" id="test"
     data-items-b64="<?= h($b64) ?>"
     data-csrf="<?= h(big5_csrf_token()) ?>"
     data-post="<?= h($postUrl) ?>">
  <div class="test-head">
    <p class="test-guide"><?= h($guide) ?></p>
    <div class="progress" role="progressbar" aria-label="진행" aria-valuemin="0" aria-valuemax="<?= $n ?>" aria-valuenow="0">
      <div class="progress-bar" id="progressBar"></div>
    </div>
    <p class="progress-text"><span id="progressText">0 / <?= $n ?></span><span id="saveState" class="save-state" aria-live="polite"></span></p>
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
}
