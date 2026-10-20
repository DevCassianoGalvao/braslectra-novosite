<?php
/**
 * Canal de Denúncia: recebe o formulário e envia por e-mail (Brevo) ao endereço exclusivo.
 *
 * PRIVACIDADE (denúncias anônimas):
 *  - NADA da denúncia é gravado no banco, em arquivo ou em log. O conteúdo só existe no e-mail enviado.
 *  - Não guarda IP, user-agent, referrer, UTM, página de origem nem cookies, e não entra no CRM de leads.
 *  - O IP só é usado para limitar abuso, e apenas como hash com sal (não reversível), apagado em 24 h.
 *  - Em denúncia anônima, qualquer nome/contato enviado pelo navegador é descartado no servidor.
 *  - O e-mail não leva Reply-To nem dados técnicos do denunciante.
 * Gravado apenas: o contador do protocolo (ano + número), sem relação com o conteúdo.
 */
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/public_api.php';

public_cors(['POST', 'OPTIONS']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Método não permitido.'], 405);
}
$raw = file_get_contents('php://input', false, null, 0, 40000);
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    json_out(['ok' => false, 'error' => 'Requisição inválida.'], 400);
}

$str = static function ($v, int $max): string {
    $v = is_scalar($v) ? (string) $v : '';
    if (!mb_check_encoding($v, 'UTF-8')) {
        $v = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
    }
    $v = str_replace("\r\n", "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
};

// bots: responde "ok" sem enviar nada (honeypot e preenchimento rápido demais)
if ($str($in['website'] ?? '', 50) !== '') {
    json_out(['ok' => true, 'protocol' => 'CD-' . date('Y') . '-0000']);
}
$t = (int) ($in['_t'] ?? 0);
if ($t > 0 && (microtime(true) * 1000 - $t) < 6000) {
    json_out(['ok' => false, 'error' => 'Revise o relato com calma antes de enviar e tente novamente.'], 429);
}

// limite de abuso: IP só como hash com sal, descartado em 24 h (rate_limits)
$salt = setting('complaint_salt');
if ($salt === '') {
    $salt = bin2hex(random_bytes(16));
    save_settings(['complaint_salt' => $salt]);
}
$h = hash_hmac('sha256', client_ip(), $salt);
if (rate_limit_hit('den:10m:' . $h, 8, 600) || rate_limit_hit('den:day:' . $h, 30, 86400)) {
    json_out(['ok' => false, 'error' => 'Muitos envios em pouco tempo. Tente novamente mais tarde.'], 429);
}

$subjects = ['Assédio moral', 'Assédio sexual', 'Discriminação', 'Fraude ou corrupção', 'Conflito de interesses', 'Segurança do trabalho', 'Descumprimento de normas internas', 'Conduta inadequada ou antiética', 'Outro'];
$identified = ($in['ident'] ?? '') === 'identificada';
$subject = $str($in['subject'] ?? '', 80);
$people = $str($in['people'] ?? '', 400);
$place = $str($in['place'] ?? '', 400);
$desc = $str($in['description'] ?? '', 6000);
$name = $identified ? $str($in['name'] ?? '', 120) : '';
$contact = $identified ? $str($in['contact'] ?? '', 160) : '';

if (!in_array($subject, $subjects, true)) {
    json_out(['ok' => false, 'error' => 'Escolha o assunto da denúncia.'], 422);
}
if (mb_strlen($desc) < 15) {
    json_out(['ok' => false, 'error' => 'Descreva o ocorrido com um pouco mais de detalhes.'], 422);
}
if (empty($in['goodfaith'])) {
    json_out(['ok' => false, 'error' => 'Confirme que o relato é feito de boa-fé.'], 422);
}
$to = trim(setting('complaint_email', 'denuncia@braslectra.com.br'));
if (!filter_var($to, FILTER_VALIDATE_EMAIL) || trim(setting('brevo_api_key')) === '') {
    error_log('denuncia: canal sem destinatário válido ou Brevo sem chave');
    json_out(['ok' => false, 'error' => 'O canal está temporariamente indisponível. Tente novamente mais tarde.'], 503);
}

// protocolo CD-AAAA-NNNN (contador independente do conteúdo)
$year = (int) date('Y');
$pdo = db();
$pdo->exec('BEGIN IMMEDIATE');
$n = (int) scalar('SELECT n FROM complaint_seq WHERE year = ?', [$year]) + 1;
q('INSERT INTO complaint_seq (year, n) VALUES (?, ?) ON CONFLICT(year) DO UPDATE SET n = excluded.n', [$year, $n]);
$pdo->exec('COMMIT');
$protocol = sprintf('CD-%d-%04d', $year, $n);
$when = date('d/m/Y – H:i');

$rows = ['Protocolo' => $protocol, 'Data e hora' => $when, 'Tipo' => $identified ? 'Identificada' : 'Anônima'];
if ($identified) {
    if ($name !== '') {
        $rows['Nome'] = $name;
    }
    if ($contact !== '') {
        $rows['Contato'] = $contact;
    }
}
$rows['Assunto'] = $subject;
if ($people !== '') {
    $rows['Pessoas envolvidas'] = $people;
}
if ($place !== '') {
    $rows['Local'] = $place;
}

$tr = '';
$txt = "NOVA DENÚNCIA RECEBIDA\n\n";
foreach ($rows as $k => $v) {
    $tr .= '<tr><td style="padding:11px 14px;border-bottom:1px solid #eeeeee;color:#555555;font-weight:bold;width:170px;vertical-align:top">' . e($k) . '</td>'
        . '<td style="padding:11px 14px;border-bottom:1px solid #eeeeee;color:#111111;vertical-align:top">' . nl2br(e($v)) . '</td></tr>';
    $txt .= "$k: $v\n";
}
$txt .= "\nDescrição da denúncia:\n$desc\n\nMensagem gerada automaticamente pelo Canal de Denúncia do site. Conteúdo confidencial — acesso restrito aos responsáveis pela apuração.\n";
$html = '<div style="background:#f3f3f3;padding:18px 0;font-family:Arial,Helvetica,sans-serif">'
    . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #dddddd;border-collapse:collapse;font-size:15px">'
    . '<tr><td colspan="2" style="background:#000000;padding:24px 28px"><span style="color:#e8a21a;font-size:22px;letter-spacing:.02em;text-transform:uppercase">Nova denúncia recebida</span></td></tr>'
    . '<tr><td colspan="2" style="padding:0"><table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:15px">' . $tr . '</table></td></tr>'
    . '<tr><td colspan="2" style="padding:16px 14px 6px;color:#555555;font-weight:bold;font-size:14px">Descrição da denúncia:</td></tr>'
    . '<tr><td colspan="2" style="padding:0 14px 16px"><div style="background:#f6f6f6;border-left:4px solid #e8a21a;padding:14px 18px;color:#111111;line-height:1.55">' . nl2br(e($desc)) . '</div></td></tr>'
    . '<tr><td colspan="2" style="padding:14px 16px;background:#fafafa;border-top:1px solid #eeeeee;color:#888888;font-size:12.5px;line-height:1.45">Mensagem gerada automaticamente pelo Canal de Denúncia do site. Conteúdo confidencial — acesso restrito aos responsáveis pela apuração.</td></tr>'
    . '</table></div>';

[$ok, $msg] = brevo_send([$to], 'Canal de Denúncia | Nova denúncia ' . $protocol, $html, $txt);   // sem Reply-To, sem anexos
if (!$ok) {
    error_log('denuncia: falha no envio (' . substr($msg, 0, 120) . ')');   // nunca registra o conteúdo
    json_out(['ok' => false, 'error' => 'Não foi possível enviar agora. Seus dados não foram guardados: tente novamente em alguns minutos.'], 502);
}
json_out(['ok' => true, 'protocol' => $protocol]);
