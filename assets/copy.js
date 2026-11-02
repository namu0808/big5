/** [복사] 단추 — data-target 입력칸의 값을 클립보드로 */
(function () {
  'use strict';
  var btn = document.getElementById('btnCopy');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.getAttribute('data-target'));
    input.select();
    var done = function () { btn.textContent = '복사됨'; };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
    } else {
      document.execCommand('copy');
      done();
    }
  });
})();
