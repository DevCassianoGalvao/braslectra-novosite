<?php
/**
 * Script único para colocar no site:  <script src="/painel-admin/api/embed.js.php" async></script>
 *  - preenche telefones/e-mails/endereços marcados com data-site-*
 *  - instala GTM / GA4 / Meta Pixel / Google Ads e códigos personalizados do painel
 *  - expõe window.BraslectraLeads.submit(form, 'origem') para enviar formulários ao CRM
 */
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/public_settings.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
// o script é consumido por outros domínios (site estático), então precisa de CORS aberto para leitura
header('Access-Control-Allow-Origin: *');

$cfg = [
    'site'     => public_settings(),
    'tracking' => tracking_config(),
];
$json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
?>
(function () {
  'use strict';
  var CFG = <?= $json ?>;
  window.BRASLECTRA_SITE = CFG.site;
  var SELF = (document.currentScript && document.currentScript.src) || '';
  var API_LEAD = SELF ? new URL('lead.php', SELF).href : '/painel-admin/api/lead.php';
  var T0 = Date.now();

  /* ---------- Dados do site ---------- */
  function digits(s) { return String(s || '').replace(/\D+/g, ''); }
  function waLink(p) { var d = digits(p); if (!d) return ''; if (d.length <= 11) d = '55' + d; return 'https://wa.me/' + d; }
  function applySite(root) {
    var S = CFG.site || {};
    (root || document).querySelectorAll('[data-site],[data-site-tel],[data-site-mail],[data-site-wa],[data-site-href]').forEach(function (el) {
      var k;
      if ((k = el.getAttribute('data-site')) && S[k] != null && el.textContent !== S[k]) el.textContent = S[k];
      if ((k = el.getAttribute('data-site-tel')) && S[k]) { el.setAttribute('href', 'tel:+' + (digits(S[k]).length <= 11 ? '55' : '') + digits(S[k])); if (!el.hasAttribute('data-site') && !el.children.length && !el.textContent.trim()) el.textContent = S[k]; }
      if ((k = el.getAttribute('data-site-mail')) && S[k]) { el.setAttribute('href', 'mailto:' + S[k]); if (!el.hasAttribute('data-site') && !el.children.length && !el.textContent.trim()) el.textContent = S[k]; }
      if ((k = el.getAttribute('data-site-wa')) && S[k]) el.setAttribute('href', waLink(S[k]));
      if ((k = el.getAttribute('data-site-href')) && S[k]) el.setAttribute('href', S[k]);
    });
  }
  var pending = false;
  function schedule() { if (pending) return; pending = true; setTimeout(function () { pending = false; applySite(); }, 60); }
  function startSite() {
    applySite();
    if (window.MutationObserver) new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startSite); else startSite();

  /* ---------- Rastreamento ---------- */
  function inject(code, where) {
    if (!code || !code.trim()) return;
    try {
      var frag = document.createRange().createContextualFragment(code);
      var host = where === 'head' ? document.head : document.body;
      if (where === 'body_start' && host.firstChild) host.insertBefore(frag, host.firstChild); else host.appendChild(frag);
    } catch (e) { if (window.console) console.warn('[braslectra] erro ao instalar script', e); }
  }
  function loadTracking() {
    var T = CFG.tracking || {};
    if (!T.enabled) return;
    window.dataLayer = window.dataLayer || [];
    if (T.gtm) {
      window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
      var g = document.createElement('script'); g.async = true; g.src = 'https://www.googletagmanager.com/gtm.js?id=' + T.gtm; document.head.appendChild(g);
    }
    if (T.ga4 || T.google_ads) {
      window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
      var first = T.ga4 || T.google_ads;
      var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + first; document.head.appendChild(s);
      window.gtag('js', new Date());
      if (T.ga4) window.gtag('config', T.ga4);
      if (T.google_ads) window.gtag('config', T.google_ads);
    }
    if (T.meta_pixel) {
      /* eslint-disable */
      !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s) }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      /* eslint-enable */
      window.fbq('init', T.meta_pixel); window.fbq('track', 'PageView');
    }
    inject(T.head, 'head');
    var doBody = function () { inject(T.body_start, 'body_start'); inject(T.body_end, 'body_end'); };
    if (document.body) doBody(); else document.addEventListener('DOMContentLoaded', doBody);
  }
  loadTracking();

  /* ---------- Formulários -> CRM ---------- */
  var MAP = [
    [/^(nome|name)/i, 'name'], [/^e-?mail/i, 'email'], [/^(whatsapp|telefone|celular|fone|phone)/i, 'phone'],
    [/^empresa/i, 'company'], [/^(mensagem|message|como podemos)/i, 'message']
  ];
  function labelOf(el) {
    var lab = el.closest('label'), t;
    if (lab) {
      var sp = lab.querySelector('span');
      t = sp && !sp.contains(el) ? sp.textContent : lab.textContent;
    } else {
      // <label> irmão do campo (padrão do site) ou aria-label/placeholder
      var prev = el.previousElementSibling;
      while (prev && prev.tagName !== 'LABEL') prev = prev.previousElementSibling;
      if (!prev && el.parentElement) prev = el.parentElement.querySelector(':scope > label');
      t = prev ? prev.textContent : (el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.name || '');
    }
    // "Nome — Seu nome completo" -> "Nome" (descarta a dica depois do travessão)
    return String(t || '').replace(/\s+/g, ' ').split(/\s+[—–]\s+/)[0].replace(/[*:]+\s*$/, '').trim();
  }
  function groupOf(el) {
    var p = el.closest('label'); p = p && p.parentElement;
    while (p && p.tagName !== 'FORM') {
      var h = p.querySelector(':scope > span'); if (h && !h.querySelector('input')) return h.textContent.trim();
      p = p.parentElement;
    }
    return 'Opções';
  }
  function utms() {
    var out = {}, qs = new URLSearchParams(location.search);
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'].forEach(function (k) {
      var v = qs.get(k);
      try { if (v) sessionStorage.setItem('brl_' + k, v); else v = sessionStorage.getItem('brl_' + k); } catch (e) { }
      if (v) out[k] = v;
    });
    return out;
  }
  function collect(form) {
    var data = {}, groups = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.tagName || el.type === 'submit' || el.type === 'button' || el.type === 'file' || el.type === 'hidden') return;
      if (el.type === 'checkbox') {
        if (!el.checked) return;
        var lab = labelOf(el), lone = !el.closest('label') || el.closest('label').parentElement === form || el.closest('label').parentElement.querySelectorAll('input[type=checkbox]').length === 1;
        if (lone) data[lab] = 'Sim'; else { var g = groupOf(el); (groups[g] = groups[g] || []).push(lab); }
        return;
      }
      var val = String(el.value || '').trim(); if (!val) return;
      var name = el.name || labelOf(el); if (!name) return;
      var std = null; for (var i = 0; i < MAP.length; i++) if (MAP[i][0].test(name)) { std = MAP[i][1]; break; }
      if (std && !(std in data)) data[std] = val; else data[name] = val;
    });
    Object.keys(groups).forEach(function (g) { data[g] = groups[g].join(', '); });
    return data;
  }
  function track(source) {
    try {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({ event: 'generate_lead', lead_source: source });
      if (window.fbq) window.fbq('track', 'Lead', { content_name: source });
      if (window.gtag) window.gtag('event', 'generate_lead', { lead_source: source });
    } catch (e) { }
  }
  function submit(form, source, opts) {
    opts = opts || {};
    var data = collect(form);
    var extra = {}; var u = utms();
    Object.keys(u).forEach(function (k) { extra[k] = u[k]; });
    extra.page_url = location.href; extra.referrer = document.referrer || ''; extra._t = T0; extra.source = source;
    var hp = form.querySelector('input[name="website"]'); if (hp) extra.website = hp.value;
    var body, headers = {};
    var file = opts.file || (form.querySelector('input[type=file]') || {}).files && form.querySelector('input[type=file]').files[0];
    if (file) {
      body = new FormData();
      Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
      Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
      body.append('curriculo', file, file.name);
    } else {
      body = JSON.stringify(Object.assign({}, data, extra)); headers['Content-Type'] = 'application/json';
    }
    return fetch(API_LEAD, { method: 'POST', body: body, headers: headers, mode: 'cors' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Resposta inválida do servidor.' }; }); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Não foi possível enviar.');
        track(source); return j;
      });
  }
  window.BraslectraLeads = { submit: submit, track: track, collect: collect, endpoint: API_LEAD };
})();
