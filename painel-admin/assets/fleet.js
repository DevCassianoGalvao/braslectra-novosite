(function () {
  'use strict';
  var form = document.getElementById('fleet-form');
  if (!form) return;
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var cats = JSON.parse(form.getAttribute('data-cats') || '{}');
  var uploadUrl = form.getAttribute('data-upload');
  var selCat = document.getElementById('f-cat'), selGrp = document.getElementById('f-grp'), grpWrap = document.getElementById('f-grp-wrap');
  var wanted = form.getAttribute('data-group') || '';

  // tipo -> grupos e campos de ficha
  function syncCat() {
    var def = cats[selCat.value] || { groups: {}, fields: [] };
    var keys = Object.keys(def.groups);
    selGrp.innerHTML = '';
    keys.forEach(function (k) {
      var o = document.createElement('option'); o.value = k; o.textContent = def.groups[k] || '—'; if (k === wanted) o.selected = true; selGrp.appendChild(o);
    });
    grpWrap.style.display = keys.length > 1 ? '' : 'none';
    document.querySelectorAll('#f-specs [data-spec]').forEach(function (el) {
      el.style.display = def.fields.indexOf(el.getAttribute('data-spec')) > -1 ? '' : 'none';
    });
    document.getElementById('f-specs').style.display = def.fields.length ? '' : 'none';
  }
  selCat.addEventListener('change', function () { wanted = ''; syncCat(); });
  selGrp.addEventListener('change', function () { wanted = selGrp.value; });
  syncCat();

  // reduz a imagem no navegador antes de enviar (hospedagens costumam limitar o upload a 2 MB); mantém a transparência (WebP)
  function shrink(file, maxDim, quality) {
    if (!window.createImageBitmap || !/^image\/(png|jpeg|webp)$/.test(file.type)) return Promise.resolve(file);
    return createImageBitmap(file).then(function (bmp) {
      var k = Math.min(1, maxDim / Math.max(bmp.width, bmp.height));
      var c = document.createElement('canvas'); c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise(function (ok) {
        c.toBlob(function (b) { ok(b && b.size < file.size ? new File([b], file.name.replace(/\.\w+$/, '') + (b.type === 'image/webp' ? '.webp' : '.png'), { type: b.type }) : file); }, 'image/webp', quality);
      });
    }).catch(function () { return file; });
  }

  function send(file, kind, onDone, onErr) {
    shrink(file, 1800, kind === 'main' ? 0.95 : 0.86).then(function (small) { upload(small, kind, onDone, onErr); });
  }
  function upload(file, kind, onDone, onErr) {
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('kind', kind); fd.append('file', file);
    fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Resposta inesperada do servidor.' }; }); })
      .then(function (j) { j.ok ? onDone(j) : onErr(j.error || 'Falha no envio.'); })
      .catch(function () { onErr('Falha de conexão no envio.'); });
  }

  // foto principal
  var mainFile = document.getElementById('main-file'), mainSt = document.getElementById('main-status');
  document.getElementById('btn-main').addEventListener('click', function () { mainFile.click(); });
  mainFile.addEventListener('change', function () {
    var f = mainFile.files[0]; if (!f) return;
    mainSt.textContent = 'Enviando…';
    send(f, 'main', function (j) {
      document.getElementById('main-path').value = j.path;
      document.getElementById('main-prev').src = j.url + '?t=' + Date.now();
      mainSt.textContent = 'Foto enviada. Clique em Salvar.';
    }, function (m) { mainSt.textContent = m; });
    mainFile.value = '';
  });

  // galeria
  var gal = document.getElementById('gal'), galFile = document.getElementById('gal-file'), galSt = document.getElementById('gal-status');
  document.getElementById('btn-gal').addEventListener('click', function () { galFile.click(); });
  function addItem(path, thumb) {
    var d = document.createElement('div'); d.className = 'fl-item'; d.setAttribute('data-path', path);
    d.innerHTML = '<img alt=""><input type="hidden" name="gallery[]"><div class="fl-ctl"><button type="button" data-mv="-1" aria-label="Mover para trás">←</button><button type="button" data-mv="1" aria-label="Mover para frente">→</button><button type="button" data-rm aria-label="Remover foto">×</button></div>';
    d.querySelector('img').src = thumb; d.querySelector('input').value = path; gal.appendChild(d);
  }
  galFile.addEventListener('change', function () {
    var files = Array.prototype.slice.call(galFile.files); galFile.value = '';
    var n = 0, bad = 0;
    (function next() {
      if (!files.length) { galSt.textContent = n + ' foto(s) adicionada(s)' + (bad ? ', ' + bad + ' com erro' : '') + '. Clique em Salvar.'; return; }
      galSt.textContent = 'Enviando ' + (n + bad + 1) + ' de ' + (n + bad + files.length) + '…';
      send(files.shift(), 'gallery', function (j) { addItem(j.path, j.thumb); n++; next(); }, function (m) { bad++; galSt.textContent = m; setTimeout(next, 900); });
    })();
  });
  gal.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var it = b.closest('.fl-item'); if (!it) return;
    if (b.hasAttribute('data-rm')) { it.remove(); return; }
    var mv = parseInt(b.getAttribute('data-mv') || '0', 10);
    if (mv < 0 && it.previousElementSibling) gal.insertBefore(it, it.previousElementSibling);
    if (mv > 0 && it.nextElementSibling) gal.insertBefore(it.nextElementSibling, it);
  });
})();
