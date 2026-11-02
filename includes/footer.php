<?php
/** 공통 꼬리 — 사용 제한 고지와 출처. $extra_js(경로 배열, assets/ 기준) */
declare(strict_types=1);

$extra_js = isset($extra_js) && is_array($extra_js) ? $extra_js : [];
?>
</main>
<footer class="site-footer">
  <p>이 검사는 진단 도구가 아니며, 채용·평가·인사 판단에 사용하지 않습니다.</p>
  <p>문항 출처: International Personality Item Pool (ipip.ori.org, 공유 저작물) · 한국어 번역판은 표준화 검증 전입니다. · <?= h(big5_version_label()) ?></p>
</footer>
<?php foreach ($extra_js as $js): ?>
<script src="<?= h(big5_url('assets/' . $js)) ?>?v=<?= h(BIG5_VERSION) ?>"></script>
<?php endforeach; ?>
</body>
</html>
