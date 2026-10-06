<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/public_settings.php';
$u = require_perm('settings');
$admin = is_admin($u);

function valid_emails_list(string $s): array
{
    $ok = [];
    foreach (preg_split('/[,;\s]+/', $s) ?: [] as $e) {
        $e = trim($e);
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $ok[strtolower($e)] = $e;
        }
    }
    return array_values($ok);
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'site') {
        $kv = [];
        $bad = [];
        foreach (['company_name', 'phone_main', 'whatsapp', 'email_orcamento', 'email_atendimento', 'email_rh', 'email_vagas', 'address_macae', 'address_rio', 'support_cities', 'social_instagram', 'social_facebook', 'social_linkedin', 'footer_text', 'stat_years', 'stat_employees', 'url_courses'] as $k) {
            $kv[$k] = mb_substr(post($k), 0, 500);
        }
        foreach (['email_orcamento', 'email_atendimento', 'email_rh', 'email_vagas'] as $k) {
            if ($kv[$k] !== '' && !filter_var($kv[$k], FILTER_VALIDATE_EMAIL)) {
                $bad[] = 'E-mail inválido: ' . $kv[$k];
            }
        }
        foreach (['social_instagram', 'social_facebook', 'social_linkedin', 'url_courses'] as $k) {
            if ($kv[$k] !== '' && !preg_match('#^https?://#i', $kv[$k])) {
                $bad[] = 'O endereço deve começar com https:// (' . $kv[$k] . ')';
            }
        }
        foreach (['stat_years', 'stat_employees'] as $k) {
            if ($kv[$k] !== '' && !ctype_digit($kv[$k])) {
                $bad[] = 'Use apenas números em “' . $k . '”.';
            }
        }
        if ($bad) {
            foreach ($bad as $b) {
                flash('err', $b);
            }
        } else {
            save_settings($kv);
            flash('ok', 'Dados do site salvos. As mudanças aparecem no site em alguns minutos.');
        }
        redirect('settings.php');
    }

    if ($admin && $action === 'notify') {
        $kv = [
            'notify_enabled'     => !empty($_POST['notify_enabled']) ? '1' : '0',
            'brevo_sender_email' => strtolower(post('brevo_sender_email')),
            'brevo_sender_name'  => mb_substr(post('brevo_sender_name'), 0, 80),
            'allowed_origins'    => mb_substr(post('allowed_origins'), 0, 1000),
        ];
        $emails = valid_emails_list(post('notify_emails'));
        $kv['notify_emails'] = implode(', ', $emails);
        $newKey = trim((string) ($_POST['brevo_api_key'] ?? ''));
        if ($newKey !== '') {
            $kv['brevo_api_key'] = $newKey;
        } elseif (!empty($_POST['brevo_key_remove'])) {
            $kv['brevo_api_key'] = '';
        }
        if ($kv['brevo_sender_email'] !== '' && !filter_var($kv['brevo_sender_email'], FILTER_VALIDATE_EMAIL)) {
            flash('err', 'E-mail remetente inválido.');
            redirect('settings.php#notificacoes');
        }
        save_settings($kv);
        flash('ok', 'Notificações salvas.');
        redirect('settings.php#notificacoes');
    }

    if ($admin && $action === 'sources') {
        foreach ((array) ($_POST['src'] ?? []) as $slug => $v) {
            if (!is_array($v) || !scalar('SELECT 1 FROM lead_sources WHERE slug = ?', [$slug])) {
                continue;
            }
            $label = mb_substr(trim((string) ($v['label'] ?? '')), 0, 60);
            q(
                'UPDATE lead_sources SET label = ?, notify_emails = ?, active = ? WHERE slug = ?',
                [$label !== '' ? $label : $slug, implode(', ', valid_emails_list((string) ($v['emails'] ?? ''))), !empty($v['active']) ? 1 : 0, $slug]
            );
        }
        $newLabel = post('new_label');
        if ($newLabel !== '') {
            $slug = slugify($newLabel, 40);
            if (!scalar('SELECT 1 FROM lead_sources WHERE slug = ?', [$slug])) {
                q('INSERT INTO lead_sources (slug, label, sort_order) VALUES (?, ?, ?)', [$slug, mb_substr($newLabel, 0, 60), 50]);
                flash('info', 'Nova origem criada. Nos formulários use source = "' . $slug . '".');
            }
        }
        flash('ok', 'Origens de leads salvas.');
        redirect('settings.php#origens');
    }

    if ($admin && $action === 'test_email') {
        $to = valid_emails_list(post('test_to') !== '' ? post('test_to') : setting('notify_emails'));
        [$ok, $msg] = brevo_send($to, 'Teste de notificação - Painel Braslectra', '<p>Se você recebeu este e-mail, as notificações de lead estão funcionando. ✅</p>', 'Teste de notificação do painel Braslectra.');
        flash($ok ? 'ok' : 'err', $ok ? 'E-mail de teste enviado para ' . implode(', ', $to) . '.' : 'Falha no envio: ' . $msg);
        redirect('settings.php#notificacoes');
    }
    redirect('settings.php');
}

view_header(['title' => 'Configurações', 'active' => 'settings']);
$S = all_settings();
$field = static function (string $key, string $label, string $type = 'text', string $hint = '') use ($S): void {
    echo '<label class="f"><span class="h">' . e($label) . '</span><input type="' . e($type) . '" name="' . e($key) . '" value="' . e($S[$key] ?? '') . '">' . ($hint ? '<small>' . e($hint) . '</small>' : '') . '</label>';
};
?>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="site">
  <h2>Dados de contato do site</h2>
  <p class="sub">Alterou um telefone ou e-mail? Edite aqui e o site se atualiza sozinho.</p>
  <div class="row">
    <?php $field('company_name', 'Nome da empresa'); $field('phone_main', 'Telefone principal', 'text', 'Ex.: (22) 2773-2800'); $field('whatsapp', 'WhatsApp', 'text', 'Ex.: (22) 9.9758-6858'); ?>
  </div>
  <div class="row">
    <?php $field('email_orcamento', 'E-mail de orçamentos', 'email'); $field('email_atendimento', 'E-mail de atendimento', 'email'); ?>
  </div>
  <div class="row">
    <?php $field('email_rh', 'E-mail do RH', 'email'); $field('email_vagas', 'E-mail de vagas', 'email'); ?>
  </div>
  <label class="f"><span class="h">Endereço — Macaé</span><textarea name="address_macae" rows="2"><?= e($S['address_macae']) ?></textarea></label>
  <label class="f"><span class="h">Endereço — Rio de Janeiro</span><textarea name="address_rio" rows="2"><?= e($S['address_rio']) ?></textarea></label>
  <label class="f"><span class="h">Cidades com base de apoio</span><input type="text" name="support_cities" value="<?= e($S['support_cities']) ?>"><small>Separe por vírgula.</small></label>
  <div class="row">
    <?php $field('social_instagram', 'Instagram (link)', 'url'); $field('social_facebook', 'Facebook (link)', 'url'); $field('social_linkedin', 'LinkedIn (link)', 'url'); ?>
  </div>
  <label class="f"><span class="h">Frase do rodapé</span><input type="text" name="footer_text" value="<?= e($S['footer_text']) ?>"></label>
  <div class="row">
    <?php $field('stat_years', 'Anos de história', 'text'); $field('stat_employees', 'Colaboradores', 'text'); $field('url_courses', 'Link do botão “Acessar cursos”', 'url'); ?>
  </div>
  <div class="actions"><button class="btn" type="submit">Salvar dados do site</button></div>
</form>

<?php if ($admin): ?>
<form method="post" class="card" id="notificacoes">
  <?= csrf_field() ?><input type="hidden" name="action" value="notify">
  <h2>Notificação de novos leads</h2>
  <p class="sub">Cada vez que um formulário do site for enviado, um e-mail é disparado pela API da Brevo.</p>
  <label class="chk"><input type="checkbox" name="notify_enabled" value="1" <?= $S['notify_enabled'] === '1' ? 'checked' : '' ?>><span>Enviar e-mail quando entrar um lead novo</span></label>
  <label class="f" style="margin-top:8px"><span class="h">Quem recebe (padrão para todas as origens)</span><textarea name="notify_emails" rows="2" placeholder="comercial@braslectra.com.br, gestor@braslectra.com.br"><?= e($S['notify_emails']) ?></textarea><small>Mais de um e-mail: separe por vírgula. Cada origem pode ter destinatários próprios (abaixo).</small></label>
  <div class="row">
    <label class="f"><span class="h">Chave da API Brevo</span>
      <input type="password" id="bkey" name="brevo_api_key" value="" autocomplete="off" placeholder="<?= $S['brevo_api_key'] !== '' ? '•••••••• (salva — deixe em branco para manter)' : 'xkeysib-…' ?>">
      <small>Brevo → SMTP e API → Chaves de API. <?= $S['brevo_api_key'] !== '' ? '<label style="font-weight:600"><input type="checkbox" name="brevo_key_remove" value="1"> remover chave salva</label>' : '' ?></small></label>
    <label class="f"><span class="h">E-mail remetente</span><input type="email" name="brevo_sender_email" value="<?= e($S['brevo_sender_email']) ?>" placeholder="site@braslectra.com.br"><small>Precisa estar validado como remetente na Brevo.</small></label>
    <label class="f"><span class="h">Nome do remetente</span><input type="text" name="brevo_sender_name" value="<?= e($S['brevo_sender_name']) ?>"></label>
  </div>
  <label class="f"><span class="h">Sites autorizados a enviar formulários <small style="display:inline">(opcional)</small></span><textarea name="allowed_origins" rows="2" placeholder="https://www.braslectra.com.br"><?= e($S['allowed_origins']) ?></textarea><small>Só é preciso se o site estiver em OUTRO domínio que o painel. Um endereço por linha.</small></label>
  <div class="actions"><button class="btn" type="submit">Salvar notificações</button></div>
</form>

<div class="card">
  <h2>Enviar e-mail de teste</h2>
  <form method="post" class="row" style="align-items:flex-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="test_email">
    <label class="f" style="margin:0"><span class="h">Enviar para</span><input type="text" name="test_to" placeholder="vazio = destinatários padrão"></label>
    <div style="flex:0 0 auto"><button class="btn dark sm" type="submit"><?= icon('mail') ?> Enviar teste</button></div>
  </form>
  <?php $log = rows('SELECT * FROM notify_log ORDER BY id DESC LIMIT 8'); if ($log): ?>
    <h3 style="font-size:1rem;margin:20px 0 8px">Últimos envios</h3>
    <div class="tbl-wrap"><table class="tbl" style="min-width:560px"><tbody>
      <?php foreach ($log as $l): ?>
        <tr><td class="nowrap muted"><?= e(fmt_date($l['created_at'])) ?></td><td><?= $l['ok'] ? '<span class="badge" style="--c:#16803C">Enviado</span>' : '<span class="badge" style="--c:#B42318">Falhou</span>' ?></td>
        <td><?= $l['lead_id'] ? '<a href="' . e(url('lead.php?id=' . $l['lead_id'])) . '">Lead #' . (int) $l['lead_id'] . '</a>' : 'Teste' ?></td><td class="t-sub"><?= e(truncate($l['recipients'] . ' — ' . $l['message'], 120)) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<form method="post" class="card" id="origens">
  <?= csrf_field() ?><input type="hidden" name="action" value="sources">
  <h2>Origens dos leads</h2>
  <p class="sub">Cada formulário do site envia um “source”. Aqui você renomeia as origens e define, se quiser, quem é avisado por e-mail em cada uma.</p>
  <div class="tbl-wrap"><table class="tbl" style="min-width:680px">
    <thead><tr><th>Código</th><th>Nome exibido</th><th>E-mails desta origem <small>(vazio = padrão)</small></th><th>Ativa</th></tr></thead>
    <tbody>
    <?php foreach (lead_sources(false) as $s): ?>
      <tr>
        <td><code><?= e($s['slug']) ?></code></td>
        <td><input type="text" name="src[<?= e($s['slug']) ?>][label]" value="<?= e($s['label']) ?>" maxlength="60"></td>
        <td><input type="text" name="src[<?= e($s['slug']) ?>][emails]" value="<?= e($s['notify_emails']) ?>" placeholder="vendas@…, outro@…"></td>
        <td><input type="checkbox" name="src[<?= e($s['slug']) ?>][active]" value="1" <?= $s['active'] ? 'checked' : '' ?> style="accent-color:var(--gold);width:18px;height:18px"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <label class="f" style="margin-top:16px"><span class="h">Criar nova origem</span><input type="text" name="new_label" placeholder="Ex.: Landing Page Black Friday"></label>
  <div class="actions"><button class="btn" type="submit">Salvar origens</button></div>
</form>
<?php endif; ?>

<div class="card">
  <h2>Como o site usa esses dados</h2>
  <p class="sub">Adicione uma vez no site e tudo o que está acima passa a ser controlado por aqui.</p>
  <pre class="code" style="background:#FBF8F3;border:1px solid var(--line);border-radius:12px;padding:14px;overflow:auto;font-size:12.5px;margin:0">&lt;script src="<?= e(abs_url('api/embed.js.php')) ?>" async&gt;&lt;/script&gt;

&lt;a data-site-tel="phone_main"&gt;&lt;/a&gt;       &lt;!-- vira link tel: com o número --&gt;
&lt;a data-site-wa="whatsapp"&gt;WhatsApp&lt;/a&gt;   &lt;!-- vira link wa.me --&gt;
&lt;a data-site-mail="email_orcamento"&gt;&lt;/a&gt;  &lt;!-- vira link mailto: --&gt;
&lt;span data-site="address_macae"&gt;&lt;/span&gt;   &lt;!-- troca o texto --&gt;</pre>
  <p class="muted" style="margin:10px 0 0;font-size:13px">Também há uma API JSON: <code><?= e(abs_url('api/site.php')) ?></code></p>
</div>
<?php view_footer();
