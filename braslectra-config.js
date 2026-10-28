// Liga o site ao painel admin (pasta painel-admin/, que fica ao lado destas páginas).
//  - Preenche telefones/e-mails/endereços marcados com data-site-*
//  - Instala GTM / Pixel / GA4 configurados no painel
//  - Habilita o envio dos formulários para o CRM (window.BraslectraLeads)
// O endereço do painel é detectado sozinho: <pasta onde este arquivo está>/painel-admin.
// Para apontar para outro endereço, defina window.BRASLECTRA_ADMIN_URL antes deste arquivo.
(function () {
  var cs = document.currentScript;
  var base = window.BRASLECTRA_ADMIN_URL;
  try { base = base || window.localStorage.getItem('BRASLECTRA_ADMIN_URL'); } catch (e) { }
  if (!base) {
    try { base = cs && cs.src ? new URL('painel-admin', cs.src).href : '/painel-admin'; } catch (e) { base = '/painel-admin'; }
  }
  base = String(base).replace(/\/$/, '');
  window.BRASLECTRA_ADMIN_URL = base;
  // carrega depois da página pronta: dados de contato, rastreamento e envio de formulários não precisam atrasar a primeira pintura
  function add() {
    var s = document.createElement('script');
    s.src = base + '/api/embed.js.php';
    s.async = true;
    document.head.appendChild(s);
  }
  function idle() { if (window.requestIdleCallback) window.requestIdleCallback(add, { timeout: 1500 }); else setTimeout(add, 200); }
  if (document.readyState === 'complete') idle(); else window.addEventListener('load', idle);
})();
