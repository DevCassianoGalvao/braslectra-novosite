(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  // alerta se a pasta data/ estiver exposta
  var sc = document.getElementById('sec-check');
  if (sc) fetch(sc.getAttribute('data-url'), { credentials: 'omit', cache: 'no-store' }).then(function (r) { if (r.status === 200) sc.style.display = ''; }).catch(function () {});

  // menu mobile
  var side = document.getElementById('side'), burger = document.getElementById('burger');
  if (side && burger) {
    burger.addEventListener('click', function () {
      var open = side.classList.toggle('open');
      var sc = document.querySelector('.scrim');
      if (open && !sc) {
        sc = document.createElement('div'); sc.className = 'scrim';
        sc.addEventListener('click', function () { side.classList.remove('open'); sc.remove(); });
        document.body.appendChild(sc);
      } else if (!open && sc) { sc.remove(); }
    });
  }

  // confirmação em formulários/botões
  document.addEventListener('submit', function (e) {
    var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (m && !window.confirm(m)) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('button[data-confirm]');
    if (b && !window.confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  });

  // flash some sozinho
  setTimeout(function () {
    document.querySelectorAll('.flash-ok').forEach(function (f) { f.style.transition = 'opacity .5s'; f.style.opacity = '0'; setTimeout(function () { f.remove(); }, 600); });
  }, 6000);

  // slug automático a partir do título
  var slugSrc = document.querySelector('[data-slug-from]');
  if (slugSrc) {
    var target = document.querySelector(slugSrc.getAttribute('data-slug-from'));
    var touched = target && target.value !== '';
    if (target) {
      target.addEventListener('input', function () { touched = true; });
      slugSrc.addEventListener('input', function () {
        if (touched) return;
        target.value = slugify(slugSrc.value);
      });
    }
  }
  function slugify(s) {
    return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
  }

  // status do lead inline
  document.querySelectorAll('select[data-lead-status]').forEach(function (sel) {
    paint(sel);
    sel.addEventListener('change', function () {
      var fd = new FormData();
      fd.append('_csrf', csrf); fd.append('id', sel.getAttribute('data-lead-status')); fd.append('status', sel.value);
      sel.disabled = true;
      fetch(sel.getAttribute('data-url'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { sel.disabled = false; if (!j.ok) { alert(j.error || 'Erro ao salvar'); } else { paint(sel); var tr = sel.closest('tr'); if (tr) tr.classList.remove('is-new'); } })
        .catch(function () { sel.disabled = false; alert('Não foi possível salvar. Verifique a conexão.'); });
    });
  });
  function paint(sel) {
    var o = sel.options[sel.selectedIndex]; var c = o && o.getAttribute('data-c');
    if (c) { sel.style.color = c; sel.style.borderColor = c; }
  }

  // copiar
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-copy');
      (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () {
        var o = b.textContent; b.textContent = 'Copiado!'; setTimeout(function () { b.textContent = o; }, 1500);
      }).catch(function () { window.prompt('Copie:', t); });
    });
  });

  // mostrar/ocultar campos secretos
  document.querySelectorAll('[data-reveal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.querySelector(b.getAttribute('data-reveal'));
      if (!i) return; i.type = i.type === 'password' ? 'text' : 'password';
      b.textContent = i.type === 'password' ? 'Mostrar' : 'Ocultar';
    });
  });

  // alternância de modos (usuários)
  document.querySelectorAll('input[name="leads_mode"]').forEach(function (r) {
    r.addEventListener('change', syncSources);
  });
  function syncSources() {
    var box = document.getElementById('sources-box'); if (!box) return;
    var c = document.querySelector('input[name="leads_mode"]:checked');
    box.style.display = c && c.value === 'sources' ? '' : 'none';
  }
  syncSources();
})();
