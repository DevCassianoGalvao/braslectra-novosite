(function () {
  'use strict';
  var form = document.getElementById('post-form');
  if (!form) return;
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var uploadUrl = form.getAttribute('data-upload');
  var editor = document.getElementById('editor');
  var src = document.getElementById('editor-src');
  var hidden = document.getElementById('content');
  var srcMode = false;

  function exec(cmd, val) { editor.focus(); document.execCommand(cmd, false, val || null); }

  // evita perder a seleção ao clicar na barra
  document.getElementById('editor-bar').addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); });
  document.getElementById('editor-bar').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b || srcMode && b.id !== 'btn-src') return;
    if (b.dataset.cmd) exec(b.dataset.cmd);
    else if (b.dataset.block) exec('formatBlock', '<' + b.dataset.block + '>');
  });

  document.getElementById('btn-link').addEventListener('click', function () {
    if (srcMode) return;
    var u = window.prompt('Endereço do link (https://…)', 'https://');
    if (u && /^(https?:|mailto:|tel:|#)/i.test(u)) exec('createLink', u);
  });

  function upload(file) {
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('file', file);
    return fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) throw new Error(j.error || 'Falha no envio'); return j; });
  }

  // imagem dentro do texto
  var imgFile = document.getElementById('img-file');
  document.getElementById('btn-img').addEventListener('click', function () { if (!srcMode) imgFile.click(); });
  imgFile.addEventListener('change', function () {
    var f = imgFile.files[0]; if (!f) return;
    upload(f).then(function (j) {
      var alt = window.prompt('Descrição da imagem (para acessibilidade e Google):', '') || '';
      exec('insertHTML', '<img src="' + j.path + '" alt="' + alt.replace(/"/g, '&quot;') + '">');
    }).catch(function (e) { alert(e.message); }).finally(function () { imgFile.value = ''; });
  });

  // capa
  var cover = document.getElementById('cover'), prev = document.getElementById('cover-prev'), cfile = document.getElementById('cover-file');
  var blank = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
  document.getElementById('btn-cover').addEventListener('click', function () { cfile.click(); });
  document.getElementById('btn-cover-rm').addEventListener('click', function () { cover.value = ''; prev.src = blank; });
  cfile.addEventListener('change', function () {
    var f = cfile.files[0]; if (!f) return;
    upload(f).then(function (j) { cover.value = j.path; prev.src = j.url; })
      .catch(function (e) { alert(e.message); }).finally(function () { cfile.value = ''; });
  });

  // modo HTML
  var btnSrc = document.getElementById('btn-src');
  btnSrc.addEventListener('click', function () {
    srcMode = !srcMode;
    btnSrc.classList.toggle('on', srcMode);
    if (srcMode) { src.value = editor.innerHTML; editor.style.display = 'none'; src.style.display = ''; }
    else { editor.innerHTML = src.value; src.style.display = 'none'; editor.style.display = ''; }
  });

  form.addEventListener('submit', function () {
    hidden.value = srcMode ? src.value : editor.innerHTML;
  });

  // aviso ao sair com alterações
  var dirty = false;
  form.addEventListener('input', function () { dirty = true; });
  editor.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
