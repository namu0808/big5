/**
 * 리포트 화면 — [인쇄/PDF 저장] 버튼, 성장 행동 고르기·저장(ajax).
 * 차트는 서버가 만든 SVG/CSS 라 인쇄 전 변환이 필요 없다.
 * 문구는 textContent 로만 넣는다(innerHTML 금지).
 */
(function () {
  'use strict';

  var btnPrint = document.getElementById('btnPrint');
  if (btnPrint) btnPrint.addEventListener('click', function () { window.print(); });

  var app = document.getElementById('growthApp');
  if (!app) return;

  function decodeB64(b64) {
    var bin = atob(b64);
    var bytes = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return JSON.parse(new TextDecoder('utf-8').decode(bytes));
  }

  var data = decodeB64(app.getAttribute('data-growth-b64'));
  var csrf = app.getAttribute('data-csrf');
  var postUrl = app.getAttribute('data-post');
  var labels = { grow: app.getAttribute('data-grow-label'), shadow: app.getAttribute('data-shadow-label') };
  var saved = data.saved || [];
  var elPick = document.getElementById('growthPick');
  var elList = document.getElementById('growthList');
  var elErr = document.getElementById('growthError');
  var elSaved = document.getElementById('growthSaved');
  var elEmpty = document.getElementById('growthEmpty');
  var codes = Object.keys(data.domains);
  var state = { domain: null, dir: null, offset: 0 };

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  function showError(msg) {
    elErr.textContent = msg || '';
    elErr.hidden = !msg;
  }

  function post(fields) {
    var fd = new FormData();
    fd.append('ajax', '1');
    fd.append('csrf', csrf);
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    return fetch(postUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  /** 고르는 단추: 방향(키우기/그림자) × 다이얼 */
  function renderPick() {
    elPick.textContent = '';
    ['grow', 'shadow'].forEach(function (dir) {
      var row = el('div', 'r-growth-row');
      row.appendChild(el('p', 'r-growth-label', labels[dir]));
      var btns = el('div', 'r-growth-btns');
      codes.forEach(function (d) {
        var b = el('button', 'chip-btn', data.domains[d].name);
        b.type = 'button';
        b.setAttribute('data-domain', d);
        b.setAttribute('data-dir', dir);
        b.setAttribute('aria-pressed', state.domain === d && state.dir === dir ? 'true' : 'false');
        b.style.setProperty('--c', 'var(--c-' + d + ')');
        b.addEventListener('click', function () {
          state.domain = d; state.dir = dir; state.offset = 0;
          renderPick(); renderList();
        });
        btns.appendChild(b);
      });
      row.appendChild(btns);
      elPick.appendChild(row);
    });
  }

  /** 고른 다이얼의 행동 세 가지 */
  function renderList() {
    elList.textContent = '';
    if (!state.domain) return;
    var g = data.domains[state.domain][state.dir];
    var acts = g.actions;
    elList.appendChild(el('p', 'r-growth-title', g.label));
    var ul = el('ul', 'r-growth-actions');
    for (var i = 0; i < Math.min(3, acts.length); i++) {
      var a = acts[(state.offset + i) % acts.length];
      var li = el('li');
      var label = el('label', 'check');
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = saved.indexOf(a.key) >= 0;
      cb.setAttribute('data-key', a.key);
      (function (action, box) {
        box.addEventListener('change', function () { toggle(action, box); });
      })(a, cb);
      label.appendChild(cb);
      label.appendChild(el('span', null, a.text));
      li.appendChild(label);
      ul.appendChild(li);
    }
    elList.appendChild(ul);
    if (acts.length > 3) {
      var more = el('button', 'btn btn-outline btn-sm', '다른 행동 보기');
      more.type = 'button';
      more.addEventListener('click', function () { state.offset = (state.offset + 3) % acts.length; renderList(); });
      elList.appendChild(more);
    }
  }

  function textOf(key) {
    var found = '';
    codes.forEach(function (d) {
      ['grow', 'shadow'].forEach(function (dir) {
        data.domains[d][dir].actions.forEach(function (a) { if (a.key === key) found = a.text; });
      });
    });
    return found;
  }

  function renderSaved() {
    elSaved.textContent = '';
    saved.forEach(function (k) {
      var li = el('li', null, textOf(k));
      li.setAttribute('data-key', k);
      elSaved.appendChild(li);
    });
    elEmpty.hidden = saved.length > 0;
  }

  function toggle(action, box) {
    showError('');
    box.disabled = true;
    post({ action: box.checked ? 'growth_add' : 'growth_remove', domain: state.domain, direction: state.dir, action_key: action.key })
      .then(function (r) {
        if (!r || r.ok !== 1) throw new Error((r && r.error) || '저장하지 못했습니다.');
        saved = r.saved || [];
        renderSaved();
      })
      .catch(function (e) {
        box.checked = !box.checked;
        showError(e.message);
      })
      .then(function () { box.disabled = false; });
  }

  renderPick();
  renderSaved();
})();
