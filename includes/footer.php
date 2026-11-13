<?php
/** 공통 꼬리 — 사용 제한 고지와 출처. $extra_js(경로 배열, assets/ 기준) */
declare(strict_types=1);

$extra_js = isset($extra_js) && is_array($extra_js) ? $extra_js : [];
?>
</main>
<?php if (($layout_area ?? 'user') === 'admin'): /* 관리자 꼬리 한 줄(규격 sbm.ai.kr doc/ADMIN-UI.md) */ ?>
<footer class="adm-foot">
  <div class="adm-foot-inner"><span>SBM 진단 검사 · 다이얼 관리자</span><span>ver <?= h(BIG5_VERSION) ?></span></div>
</footer>
<?php else: ?>
<footer class="site-footer">
  <p><a href="/">SBM 진단 검사</a> · 『초등 공부력』 저자 손병목의 진단 검사</p>
  <p>이 검사는 진단 도구가 아니며, 채용·평가·인사 판단에 사용하지 않습니다.</p>
  <p>문항 출처: International Personality Item Pool (ipip.ori.org, 공유 저작물) · 한국어 번역판은 표준화 검증 전입니다. · <?= h(big5_version_label()) ?></p>
</footer>
<?php endif; ?>
<?php foreach ($extra_js as $js): ?>
<script src="<?= h(big5_url('assets/' . $js)) ?>?v=<?= h(BIG5_VERSION) ?>"></script>
<?php endforeach; ?>
</body>
</html>
