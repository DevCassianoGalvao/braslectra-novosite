<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('scripts');

if (is_post()) {
    csrf_check();
    $kv = ['trk_enabled' => !empty($_POST['trk_enabled']) ? '1' : '0'];
    $errs = [];

    $gtm = strtoupper(post('trk_gtm'));
    $ga4 = strtoupper(post('trk_ga4'));
    $pix = post('trk_meta_pixel');
    $ads = strtoupper(post('trk_google_ads'));
    if ($gtm !== '' && !preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm)) {
        $errs[] = 'ID do Google Tag Manager inválido. Formato: GTM-XXXXXXX.';
    }
    if ($ga4 !== '' && !preg_match('/^G-[A-Z0-9]{4,14}$/', $ga4)) {
        $errs[] = 'ID do Google Analytics 4 inválido. Formato: G-XXXXXXXXXX.';
    }
    if ($pix !== '' && !preg_match('/^\d{6,20}$/', $pix)) {
        $errs[] = 'ID do Pixel da Meta inválido (apenas números).';
    }
    if ($ads !== '' && !preg_match('/^AW-\d{6,14}$/', $ads)) {
        $errs[] = 'ID do Google Ads inválido. Formato: AW-123456789.';
    }
    $kv += ['trk_gtm' => $gtm, 'trk_ga4' => $ga4, 'trk_meta_pixel' => $pix, 'trk_google_ads' => $ads];
    foreach (['trk_head', 'trk_body_start', 'trk_body_end'] as $k) {
        $kv[$k] = mb_substr((string) ($_POST[$k] ?? ''), 0, 20000);
    }
    if ($errs) {
        foreach ($errs as $e) {
            flash('err', $e);
        }
    } else {
        save_settings($kv);
        flash('ok', 'Scripts salvos. Podem levar alguns minutos para aparecer no site (cache de 5 min).');
    }
    redirect('scripts.php');
}

view_header(['title' => 'Scripts e rastreamento', 'active' => 'scripts']);
$S = all_settings();
?>
<div class="hint">Tudo aqui é instalado automaticamente em <b>todas as páginas</b> do site pelo script <code>embed.js.php</code>. Esta área é para quem gerencia o tráfego pago — não é preciso mexer no código do site.</div>

<form method="post">
  <?= csrf_field() ?>
  <div class="card">
    <label class="chk"><input type="checkbox" name="trk_enabled" value="1" <?= $S['trk_enabled'] === '1' ? 'checked' : '' ?>><span>Rastreamento ativo<small>Desmarque para desligar tudo de uma vez, sem apagar as configurações.</small></span></label>
  </div>

  <div class="card">
    <h2>Ferramentas prontas</h2>
    <p class="sub">Informe apenas o ID — o código correto é montado e instalado para você.</p>
    <div class="row">
      <label class="f"><span class="h">Google Tag Manager</span><input type="text" name="trk_gtm" value="<?= e($S['trk_gtm']) ?>" placeholder="GTM-XXXXXXX" autocomplete="off"></label>
      <label class="f"><span class="h">Google Analytics 4</span><input type="text" name="trk_ga4" value="<?= e($S['trk_ga4']) ?>" placeholder="G-XXXXXXXXXX" autocomplete="off"></label>
    </div>
    <div class="row">
      <label class="f"><span class="h">Pixel da Meta (Facebook/Instagram)</span><input type="text" name="trk_meta_pixel" value="<?= e($S['trk_meta_pixel']) ?>" placeholder="123456789012345" autocomplete="off"></label>
      <label class="f"><span class="h">Google Ads (tag global)</span><input type="text" name="trk_google_ads" value="<?= e($S['trk_google_ads']) ?>" placeholder="AW-123456789" autocomplete="off"></label>
    </div>
    <div class="hint" style="margin:6px 0 0"><b>Conversões automáticas:</b> quando um formulário é enviado com sucesso, o site dispara <code>generate_lead</code> no dataLayer/GA4 e <code>Lead</code> no Pixel da Meta, com a origem do formulário (<code>lead_source</code>). É só criar o acionador no GTM.</div>
  </div>

  <div class="card">
    <h2>Códigos personalizados</h2>
    <p class="sub">Cole aqui qualquer outro script (TikTok, LinkedIn Insight, Hotjar, Clarity, verificação de domínio…). Inclua as tags <code>&lt;script&gt;</code>.</p>
    <label class="f"><span class="h">Dentro do &lt;head&gt;</span><textarea class="code" name="trk_head" spellcheck="false" placeholder="&lt;script&gt;…&lt;/script&gt;"><?= e($S['trk_head']) ?></textarea></label>
    <label class="f"><span class="h">Logo após abrir o &lt;body&gt;</span><textarea class="code" name="trk_body_start" spellcheck="false"><?= e($S['trk_body_start']) ?></textarea></label>
    <label class="f"><span class="h">Antes de fechar o &lt;/body&gt;</span><textarea class="code" name="trk_body_end" spellcheck="false"><?= e($S['trk_body_end']) ?></textarea></label>
    <div class="hint" style="margin:6px 0 0">⚠ Códigos personalizados rodam em todas as páginas e podem afetar a segurança e a velocidade do site. Cole apenas scripts de fontes confiáveis.</div>
  </div>
  <div class="actions"><button class="btn" type="submit">Salvar scripts</button></div>
</form>
<?php view_footer();
