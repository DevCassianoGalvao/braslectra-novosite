<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/public_settings.php';
$u = require_login();
if (!is_admin($u)) {
    http_response_code(403);
    view_header(['title' => 'Sem permissão', 'active' => '']);
    echo '<div class="card"><h2>Sem permissão</h2><p>Somente o administrador acessa o diagnóstico.</p></div>';
    view_footer();
    exit;
}

// ---------- testes de envio ----------
if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'test_complaint') {
        $to = trim(setting('complaint_email', 'denuncia@braslectra.com.br'));
        [$ok, $msg] = brevo_send([$to], 'Teste do Canal de Denúncia (pode ignorar)', '<div style="font-family:Arial;max-width:520px"><h2>Teste do Canal de Denúncia</h2><p>Se você recebeu este e-mail, o endereço <b>' . e($to) . '</b> está recebendo mensagens do site.</p><p style="color:#888;font-size:12px">Enviado pelo Diagnóstico do painel em ' . e(date('d/m/Y H:i')) . '. Não é uma denúncia.</p></div>', 'Teste do Canal de Denúncia. Se você recebeu, o endereço está funcionando.');
        flash($ok ? 'ok' : 'err', $ok ? 'E-mail de teste enviado para ' . $to . '. Confira a caixa de entrada (e o spam).' : 'Falhou: ' . $msg);
    } elseif ($act === 'test_leads') {
        $to = notify_recipients('contato');
        if (!$to) {
            flash('err', 'Nenhum destinatário de leads configurado (Configurações → Notificações).');
        } else {
            [$ok, $msg] = brevo_send($to, 'Teste de notificação de leads (pode ignorar)', '<div style="font-family:Arial;max-width:520px"><h2>Teste de notificação</h2><p>Se você recebeu este e-mail, os avisos de novos leads chegam neste endereço.</p><p style="color:#888;font-size:12px">Enviado pelo Diagnóstico do painel em ' . e(date('d/m/Y H:i')) . '.</p></div>', 'Teste de notificação de leads.');
            flash($ok ? 'ok' : 'err', $ok ? 'Teste enviado para: ' . implode(', ', $to) : 'Falhou: ' . $msg);
        }
    }
    redirect('diagnostico.php');
}

// ---------- verificações ----------
$checks = [];
$add = static function (string $group, string $label, string $status, string $detail = '', string $fix = '') use (&$checks): void {
    $checks[$group][] = ['label' => $label, 'status' => $status, 'detail' => $detail, 'fix' => $fix];
};
$bytes = static function (string $v): int {
    $v = trim($v);
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
};

// Servidor
$add('Servidor', 'PHP ' . PHP_VERSION, PHP_VERSION_ID >= 80100 ? 'ok' : (PHP_VERSION_ID >= 80000 ? 'warn' : 'err'), PHP_VERSION_ID >= 80100 ? '' : 'Recomendado 8.1 ou superior.');
foreach (['pdo_sqlite' => 'banco do painel', 'mbstring' => 'textos', 'curl' => 'envio de e-mail (Brevo)', 'fileinfo' => 'validação de arquivos', 'gd' => 'fotos', 'dom' => 'editor do blog'] as $ext => $why) {
    $add('Servidor', 'Extensão ' . $ext, extension_loaded($ext) ? 'ok' : 'err', 'Usada para ' . $why . '.', extension_loaded($ext) ? '' : 'Ative em cPanel → Select PHP Version → Extensions.');
}
$webp = function_exists('imagewebp');
$add('Servidor', 'GD com suporte a WebP', $webp ? 'ok' : 'err', 'Necessário para as fotos da frota.', $webp ? '' : 'Peça à hospedagem para ativar o WebP no GD.');
$up = $bytes((string) ini_get('upload_max_filesize'));
$pm = $bytes((string) ini_get('post_max_size'));
$add('Servidor', 'Limite de envio de arquivos: ' . ini_get('upload_max_filesize') . ' (post ' . ini_get('post_max_size') . ')', min($up, $pm) >= 8 * 1048576 ? 'ok' : 'warn', 'Para fotos da denúncia e planilhas, o ideal é 10 MB ou mais.', min($up, $pm) >= 8 * 1048576 ? '' : 'Aumente upload_max_filesize e post_max_size em cPanel → MultiPHP INI Editor.');
$add('Servidor', 'Site em HTTPS', is_https() ? 'ok' : 'warn', '', is_https() ? '' : 'Ative o SSL (cPanel → SSL/TLS Status).');

// Pastas e segurança
$dataDir = dirname((string) cfg('db_path'));
$add('Pastas e segurança', 'Pasta do banco gravável', is_writable($dataDir) ? 'ok' : 'err', $dataDir, 'Permissão 755 e dono correto em painel-admin/data.');
$add('Pastas e segurança', 'Pasta de fotos (uploads) gravável', is_writable((string) cfg('upload_dir')) ? 'ok' : 'err', '', 'Permissão 755 em painel-admin/uploads.');
$self = static function (string $path): ?int {
    $url = abs_url($path);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => false]);
        curl_exec($ch);
        $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $c ?: null;
    }
    return null;
};
foreach (['data/index.html' => 'banco de dados e currículos', 'inc/db.php' => 'código interno'] as $p => $what) {
    $code = $self($p);
    $add('Pastas e segurança', 'Bloqueado na internet: ' . $p, $code === null ? 'warn' : (in_array($code, [403, 404], true) ? 'ok' : 'err'), $code === null ? 'Não foi possível testar.' : 'Resposta HTTP ' . $code . ' (' . $what . ').', ($code !== null && !in_array($code, [403, 404], true)) ? 'Bloqueie o acesso (veja o README).' : '');
}

// E-mail e formulários
$key = trim(setting('brevo_api_key')) !== '';
$sender = trim(setting('brevo_sender_email')) !== '';
$add('E-mail e formulários', 'Chave da Brevo cadastrada', $key ? 'ok' : 'err', '', $key ? '' : 'Configurações → Notificações.');
$add('E-mail e formulários', 'Remetente da Brevo: ' . (setting('brevo_sender_email') ?: 'não informado'), $sender ? 'ok' : 'err', 'Precisa estar validado na Brevo (e o domínio autenticado com SPF/DKIM) para o e-mail não cair no spam.', $sender ? '' : 'Configurações → Notificações.');
$add('E-mail e formulários', 'Aviso de novos leads ligado', setting('notify_enabled') === '1' ? 'ok' : 'err', '', setting('notify_enabled') === '1' ? '' : 'Marque "Enviar e-mail quando entrar um lead novo".');
$rec = notify_recipients('contato');
$add('E-mail e formulários', 'Quem recebe os leads: ' . ($rec ? implode(', ', $rec) : 'ninguém'), $rec ? 'ok' : 'err', '');
$ce = trim(setting('complaint_email', ''));
$add('E-mail e formulários', 'E-mail do Canal de Denúncia: ' . ($ce ?: 'não informado'), filter_var($ce, FILTER_VALIDATE_EMAIL) ? 'ok' : 'err', '');
$last = rows('SELECT * FROM notify_log ORDER BY id DESC LIMIT 5');
$bad = array_filter($last, static fn ($r) => !(int) $r['ok']);
$add('E-mail e formulários', 'Últimos envios de aviso de lead', !$last ? 'warn' : ($bad ? 'err' : 'ok'), !$last ? 'Ainda não houve nenhum lead com aviso.' : count($last) . ' recentes, ' . count($bad) . ' com falha' . ($bad ? ': ' . e((string) reset($bad)['message']) : '.'));
$tr = !empty(tracking_config()['enabled']);
$add('Rastreamento', 'GTM / GA4 / Pixel configurados', ($tr && (setting('trk_gtm') || setting('trk_ga4') || setting('trk_meta_pixel'))) ? 'ok' : 'warn', 'Opcional. Preencha em Scripts e rastreamento se for usar anúncios e métricas.');

// Conteúdo
$add('Conteúdo', 'Artigos publicados no blog: ' . (int) scalar("SELECT COUNT(*) FROM posts WHERE status = 'published'"), (int) scalar("SELECT COUNT(*) FROM posts WHERE status = 'published'") >= 20 ? 'ok' : 'warn');
$add('Conteúdo', 'Veículos visíveis na frota: ' . (int) scalar('SELECT COUNT(*) FROM vehicles WHERE active = 1'), (int) scalar('SELECT COUNT(*) FROM vehicles WHERE active = 1') > 0 ? 'ok' : 'err');
$add('Conteúdo', 'Leads recebidos no total: ' . (int) scalar('SELECT COUNT(*) FROM leads'), 'ok', 'Se for só de teste, apague na tela de Leads antes do lançamento oficial.');

$counts = ['ok' => 0, 'warn' => 0, 'err' => 0];
foreach ($checks as $g) {
    foreach ($g as $c) {
        $counts[$c['status']]++;
    }
}
view_header(['title' => 'Diagnóstico', 'active' => 'diag']);
$ico = ['ok' => ['✓', '#16803C', '#E3F4E9'], 'warn' => ['!', '#8A5B0A', '#FDF0D5'], 'err' => ['✕', '#B42318', '#FDE4E1']];
?>
<div class="chips">
  <span class="chip on">Tudo certo <em><?= $counts['ok'] ?></em></span>
  <span class="chip">Atenção <em><?= $counts['warn'] ?></em></span>
  <span class="chip">Problemas <em><?= $counts['err'] ?></em></span>
</div>
<?php foreach ($checks as $group => $list): ?>
<div class="card">
  <h2><?= e($group) ?></h2>
  <?php foreach ($list as $c): [$sym, $col, $bg] = $ico[$c['status']]; ?>
    <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-top:1px solid var(--line)">
      <span style="flex:0 0 auto;width:26px;height:26px;border-radius:50%;background:<?= $bg ?>;color:<?= $col ?>;font-weight:800;display:flex;align-items:center;justify-content:center"><?= $sym ?></span>
      <div><b><?= e($c['label']) ?></b>
        <?php if ($c['detail'] !== ''): ?><div class="muted" style="font-size:13px"><?= $c['detail'] ?></div><?php endif; ?>
        <?php if ($c['fix'] !== '' && $c['status'] !== 'ok'): ?><div style="font-size:13px;color:<?= $col ?>;font-weight:600">Como resolver: <?= e($c['fix']) ?></div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<div class="card">
  <h2>Testes de envio</h2>
  <p class="sub">Mandam um e-mail de teste pela Brevo (sem criar lead nem denúncia). Confira a caixa de entrada e o spam.</p>
  <div class="actions">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_leads"><button class="btn dark sm" type="submit">Testar aviso de leads</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_complaint"><button class="btn dark sm" type="submit">Testar e-mail do Canal de Denúncia</button></form>
  </div>
</div>
<?php view_footer();
