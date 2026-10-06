<?php
declare(strict_types=1);

/**
 * Envio de e-mail transacional pela API da Brevo (v3/smtp/email).
 * @param string[] $to
 * @return array{0:bool,1:string} [ok, mensagem]
 */
function brevo_send(array $to, string $subject, string $html, string $text = '', ?string $replyTo = null, ?string $replyName = null, array $attachments = []): array
{
    $key = trim(setting('brevo_api_key'));
    $sender = trim(setting('brevo_sender_email'));
    if ($key === '' || $sender === '') {
        return [false, 'Brevo não configurada (chave de API ou e-mail remetente ausente).'];
    }
    $to = array_values(array_filter(array_map('trim', $to), static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    if (!$to) {
        return [false, 'Nenhum destinatário válido.'];
    }
    $payload = [
        'sender'      => ['email' => $sender, 'name' => setting('brevo_sender_name', 'Site') ?: 'Site'],
        'to'          => array_map(static fn ($e) => ['email' => $e], $to),
        'subject'     => $subject,
        'htmlContent' => $html,
    ];
    if ($text !== '') {
        $payload['textContent'] = $text;
    }
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $payload['replyTo'] = ['email' => $replyTo] + ($replyName ? ['name' => $replyName] : []);
    }
    if ($attachments) {
        $payload['attachment'] = $attachments; // [['name' => ..., 'content' => base64]]
    }
    $endpoint = (string) cfg('brevo_endpoint', 'https://api.brevo.com/v3/smtp/email');
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headers = ['accept: application/json', 'content-type: application/json', 'api-key: ' . $key];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 10, 'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($endpoint, false, $ctx);
        $code = 0;
        $err = $resp === false ? 'falha de conexão' : '';
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
    }
    if ($code >= 200 && $code < 300) {
        return [true, 'Enviado'];
    }
    $detail = is_string($resp) ? (json_decode($resp, true)['message'] ?? substr($resp, 0, 200)) : $err;
    return [false, 'Brevo respondeu HTTP ' . $code . ($detail ? ': ' . $detail : '')];
}

function notify_recipients(string $source): array
{
    $over = (string) scalar('SELECT notify_emails FROM lead_sources WHERE slug = ?', [$source]);
    $list = trim($over) !== '' ? $over : setting('notify_emails');
    $out = [];
    foreach (preg_split('/[,;\s]+/', $list) ?: [] as $e) {
        if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $out[strtolower($e)] = $e;
        }
    }
    return array_values($out);
}

function notify_new_lead(int $leadId): void
{
    if (setting('notify_enabled') !== '1') {
        return;
    }
    $lead = row('SELECT * FROM leads WHERE id = ?', [$leadId]);
    if (!$lead) {
        return;
    }
    $to = notify_recipients($lead['source']);
    if (!$to) {
        q('INSERT INTO notify_log (lead_id, recipients, ok, message, created_at) VALUES (?, ?, 0, ?, ?)', [$leadId, '', 'Sem destinatários configurados.', now()]);
        return;
    }
    $label = source_label($lead['source']);
    $data = json_decode((string) $lead['data'], true) ?: [];
    $rows = [
        'Origem'   => $label,
        'Nome'     => $lead['name'],
        'WhatsApp' => $lead['phone'],
        'E-mail'   => $lead['email'],
        'Empresa'  => $lead['company'],
        'Mensagem' => $lead['message'],
    ];
    foreach ($data as $k => $v) {
        $rows[(string) $k] = is_array($v) ? implode(', ', array_map('strval', $v)) : (string) $v;
    }
    $link = cfg('site_url')
        ? rtrim((string) cfg('site_url'), '/') . url('lead.php?id=' . $leadId)
        : abs_url('lead.php?id=' . $leadId);
    $tr = '';
    $txt = "Novo lead - $label\n";
    foreach ($rows as $k => $v) {
        if (trim((string) $v) === '') {
            continue;
        }
        $tr .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;color:#6b6459;width:150px;vertical-align:top">' . e($k) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;color:#1e1b18">' . nl2br(e($v)) . '</td></tr>';
        $txt .= "$k: $v\n";
    }
    $txt .= "\nAbrir no painel: $link\n";
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:0 auto">'
        . '<div style="background:#1e1b18;color:#fff;padding:18px 22px;border-radius:12px 12px 0 0"><div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#e3ae4f">Novo lead</div><div style="font-size:20px;font-weight:700;margin-top:4px">' . e($label) . '</div></div>'
        . '<table style="width:100%;border-collapse:collapse;border:1px solid #eee;border-top:0;font-size:14px">' . $tr . '</table>'
        . '<p style="margin:18px 0"><a href="' . e($link) . '" style="background:#c8850f;color:#fff;text-decoration:none;padding:12px 20px;border-radius:999px;font-weight:700;display:inline-block">Abrir no painel</a></p>'
        . '<p style="color:#8a8377;font-size:12px">Recebido em ' . e(fmt_date($lead['created_at'])) . '</p></div>';
    // Assunto: Site | Segmento | Empresa ou solicitante | Finalidade (padrão do briefing comercial)
    $who = trim((string) ($lead['company'] ?: ($data['Organização ou grupo'] ?? '') ?: $lead['name']));
    $why = '';
    foreach (['Finalidade', 'Público', 'Tipo de viagem'] as $k) {
        if (!empty($data[$k])) {
            $why = (string) $data[$k];
            break;
        }
    }
    $subject = implode(' | ', array_filter(['Site', $label, $who, mb_substr($why, 0, 80)], static fn ($p) => trim((string) $p) !== ''));
    // anexo comercial vai junto no e-mail (até 8 MB)
    $files = [];
    if (!empty($lead['attachment']) && str_starts_with((string) $lead['attachment'], 'anexos/')) {
        $path = rtrim((string) cfg('private_dir'), '/\\') . '/' . $lead['attachment'];
        if (is_file($path) && filesize($path) <= 8 * 1024 * 1024) {
            $files[] = ['name' => (string) ($data['Anexo'] ?? basename($path)), 'content' => base64_encode((string) file_get_contents($path))];
        }
    }
    [$ok, $msg] = brevo_send($to, $subject, $html, $txt, $lead['email'] ?: null, $lead['name'] ?: null, $files);
    q('INSERT INTO notify_log (lead_id, recipients, ok, message, created_at) VALUES (?, ?, ?, ?, ?)', [$leadId, implode(', ', $to), $ok ? 1 : 0, $msg, now()]);
}
