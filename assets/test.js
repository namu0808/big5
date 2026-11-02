/**
 * 검사 화면 — 페이지 넘김(10문항), 진행 막대, 답할 때마다 자동 저장, 모두 답해야 [다음] 활성.
 * 데이터는 #test 의 data-items-b64(base64 JSON). 저장은 같은 페이지에 ajax=1 POST.
 * 사용자 텍스트는 textContent 로만 넣는다(innerHTML 금지).
 */
(function () {
  'use strict';

  var root = document.getElementById('test');
  if (!root) return;

  /** base64 → UTF-8 JSON */
  function decodeB64(b64) {
    var bin = atob(b64);
    var bytes = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return JSON.parse(new TextDecoder('utf-8').decode(bytes));
  }

  var data = decodeB64(root.getAttribute('data-items-b64'));
  var csrf = root.getAttribute('data-csrf');
  var postUrl = root.getAttribute('data-post');
  var items = data.items;
  var scale = data.scale;
  var perPage = data.perPage || 10;
  var answers = {};                 // 번호 → 값 (화면 상태)
  Object.keys(data.answers || {}).forEach(function (k) { answers[k] = data.answers[k]; });

  var pending = {};                 // 번호 → 저장 중 Promise
  var failed = {};                  // 번호 → true (저장 실패)
  var pageCount = Math.ceil(items.length / perPage);
  var page = 0;

  var elQ = document.getElementById('questions');
  var elPrev = document.getElementById('btnPrev');
  var elNext = document.getElementById('btnNext');
  var elBar = document.getElementById('progressBar');
  var elText = document.getElementById('progressText');
  var elSave = document.getElementById('saveState');
  var elErr = document.getElementById('testError');
  var elProgress = root.querySelector('.progress');

  function answeredCount() {
    return items.filter(function (it) { return answers[it.no] !== undefined; }).length;
  }

  function pageItems(p) {
    return items.slice(p * perPage, (p + 1) * perPage);
  }

  function pageComplete(p) {
    return pageItems(p).every(function (it) { return answers[it.no] !== undefined; });
  }

  /** 처음 답하지 않은 문항이 있는 페이지 (이어 하기) */
  function firstOpenPage() {
    for (var p = 0; p < pageCount; p++) if (!pageComplete(p)) return p;
    return pageCount - 1;
  }

  function showError(msg) {
    if (!msg) { elErr.hidden = true; elErr.textContent = ''; return; }
    elErr.textContent = msg;
    elErr.hidden = false;
  }

  function updateProgress() {
    var n = answeredCount();
    elBar.style.width = (n / items.length * 100) + '%';
    elText.textContent = n + ' / ' + items.length;
    elProgress.setAttribute('aria-valuenow', String(n));
  }

  function updateNav() {
    elPrev.disabled = page === 0;
    var last = page === pageCount - 1;
    elNext.textContent = last ? '결과 보기' : '다음';
    elNext.disabled = !pageComplete(page);
  }

  function updateSaveState() {
    var saving = Object.keys(pending).length > 0;
    var fails = Object.keys(failed).length;
    if (fails > 0) {
      elSave.textContent = '저장되지 않은 답이 있어요. 해당 문항을 다시 눌러 주세요.';
      elSave.className = 'save-state is-error';
    } else {
      elSave.textContent = saving ? '저장 중…' : (answeredCount() > 0 ? '자동 저장됨' : '');
      elSave.className = 'save-state';
    }
  }

  function post(fields) {
    var fd = new FormData();
    fd.append('ajax', '1');
    fd.append('csrf', csrf);
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    return fetch(postUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (res) { return res.json(); });
  }

  /** 응답 저장 — 같은 문항을 빨리 여러 번 누르면 마지막 값이 남도록 순서대로 보낸다 */
  function save(no, value) {
    var prev = pending[no] || Promise.resolve();
    var p = prev.then(function () {
      return post({ action: 'answer', item_no: String(no), value: String(value) });
    }).then(function (r) {
      if (!r || r.ok !== 1) throw new Error((r && r.error) || '저장 실패');
      delete failed[no];
      markItem(no, false);
    }).catch(function (e) {
      failed[no] = true;
      markItem(no, true);
      if (e && e.message) showError(e.message);
    }).then(function () {
      if (pending[no] === p) delete pending[no];
      updateSaveState();
    });
    pending[no] = p;
    updateSaveState();
    return p;
  }

  function markItem(no, isFail) {
    var el = elQ.querySelector('[data-no="' + no + '"]');
    if (el) el.classList.toggle('is-failed', isFail);
  }

  function choose(no, value, group) {
    answers[no] = value;
    Array.prototype.forEach.call(group.querySelectorAll('button'), function (b) {
      var on = Number(b.getAttribute('data-value')) === value;
      b.setAttribute('aria-checked', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
    });
    group.closest('.q').classList.add('is-answered');
    showError('');
    save(no, value);
    updateProgress();
    updateNav();
  }

  function renderPage() {
    elQ.textContent = '';
    pageItems(page).forEach(function (it) {
      var box = document.createElement('fieldset');
      box.className = 'q' + (answers[it.no] !== undefined ? ' is-answered' : '') + (failed[it.no] ? ' is-failed' : '');
      box.setAttribute('data-no', String(it.no));

      var legend = document.createElement('legend');
      legend.className = 'q-text';
      var num = document.createElement('span');
      num.className = 'q-no';
      num.textContent = it.no + '.';
      legend.appendChild(num);
      legend.appendChild(document.createTextNode(' ' + it.text));
      box.appendChild(legend);

      var group = document.createElement('div');
      group.className = 'scale';
      group.setAttribute('role', 'radiogroup');
      group.setAttribute('aria-label', it.no + '번 응답');
      scale.forEach(function (label, i) {
        var v = i + 1;
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'scale-btn';
        b.setAttribute('role', 'radio');
        b.setAttribute('data-value', String(v));
        b.setAttribute('aria-checked', answers[it.no] === v ? 'true' : 'false');
        b.tabIndex = answers[it.no] === v || (answers[it.no] === undefined && v === 1) ? 0 : -1;
        var n = document.createElement('span');
        n.className = 'scale-num';
        n.textContent = String(v);
        var t = document.createElement('span');
        t.className = 'scale-label';
        t.textContent = label;
        b.appendChild(n);
        b.appendChild(t);
        b.addEventListener('click', function () { choose(it.no, v, group); });
        group.appendChild(b);
      });
      // 방향키로 고르기 (라디오 묶음 관례)
      group.addEventListener('keydown', function (e) {
        if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(e.key) < 0) return;
        e.preventDefault();
        var cur = answers[it.no] || 0;
        var next = (e.key === 'ArrowLeft' || e.key === 'ArrowUp') ? Math.max(1, cur - 1) : Math.min(5, cur + 1);
        choose(it.no, next, group);
        group.querySelector('[data-value="' + next + '"]').focus();
      });
      box.appendChild(group);
      elQ.appendChild(box);
    });
    updateProgress();
    updateNav();
    updateSaveState();
  }

  function goPage(p) {
    page = Math.max(0, Math.min(pageCount - 1, p));
    showError('');
    renderPage();
    window.scrollTo(0, 0);
  }

  /** 저장 중인 요청이 모두 끝날 때까지 */
  function settle() {
    var list = Object.keys(pending).map(function (k) { return pending[k]; });
    return Promise.all(list);
  }

  function finish() {
    elNext.disabled = true;
    elNext.textContent = '결과를 만드는 중…';
    settle().then(function () {
      // 실패했던 답은 한 번 더 보낸다
      var retry = Object.keys(failed).map(function (no) { return save(Number(no), answers[no]); });
      return Promise.all(retry);
    }).then(function () {
      if (Object.keys(failed).length > 0) throw new Error('저장되지 않은 답이 있어요. 해당 문항을 다시 눌러 주세요.');
      return post({ action: 'finish' });
    }).then(function (r) {
      if (r && r.ok === 1 && r.redirect) {
        window.location.href = r.redirect;
        return;
      }
      if (r && r.code === 'missing' && r.missing && r.missing.length) {
        // 서버에 없는 답이 있으면 그 페이지로 이동해 다시 답하게 한다
        r.missing.forEach(function (no) { delete answers[no]; });
        goPage(firstOpenPage());
      }
      if (r && r.redirect) { window.location.href = r.redirect; return; }
      throw new Error((r && r.error) || '결과를 만들지 못했습니다.');
    }).catch(function (e) {
      showError(e.message || '결과를 만들지 못했습니다. 잠시 뒤 다시 눌러 주세요.');
      updateNav();
    });
  }

  elPrev.addEventListener('click', function () { goPage(page - 1); });
  elNext.addEventListener('click', function () {
    if (!pageComplete(page)) return;
    if (page === pageCount - 1) finish(); else goPage(page + 1);
  });

  page = firstOpenPage();
  renderPage();
})();
