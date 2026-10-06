<?php
/**
 * Endpoint público que recebe os formulários do site.
 * POST application/json ou multipart/form-data. Campos: source, name, email, phone, company, message + extras.
 */
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/public_api.php';

public_cors(['POST', 'OPTIONS']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$in = $_POST;
$ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input', false, null, 0, 200000);
    $dec = json_decode((string) $raw, true);
    if (!is_array($dec)) {
        json_out(['ok' => false, 'error' => 'Requisição inválida.'], 400);
    }
    $in = $dec;
}

$str = static function ($v, int $max): string {
    if (is_array($v)) {
        $v = implode(', ', array_map(static fn ($x) => is_scalar($x) ? (string) $x : '', $v));
    }
    $v = is_scalar($v) ? (string) $v : '';
    if (!mb_check_encoding($v, 'UTF-8')) {
        $v = mb_convert_encoding($v, 'UTF-8', 'Windows-1252'); // clientes antigos/ferramentas sem UTF-8
    }
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
};

// Honeypot e tempo mínimo (bots): responde "ok" sem gravar nada
if ($str($in['website'] ?? $in['hp'] ?? '', 50) !== '') {
    json_out(['ok' => true, 'id' => 0]);
}
$t = (int) ($in['_t'] ?? 0);
if ($t > 0 && (microtime(true) * 1000 - $t) < 2000) {
    json_out(['ok' => true, 'id' => 0]);
}

$ip = client_ip();
if (rate_limit_hit('lead:ip:10m:' . $ip, 8, 600) || rate_limit_hit('lead:ip:day:' . $ip, 40, 86400)) {
    json_out(['ok' => false, 'error' => 'Muitos envios em pouco tempo. Tente novamente em alguns minutos.'], 429);
}

$name = $str($in['name'] ?? $in['nome'] ?? '', 120);
$email = strtolower($str($in['email'] ?? '', 160));
$phone = $str($in['phone'] ?? $in['telefone'] ?? $in['whatsapp'] ?? '', 40);
$company = $str($in['company'] ?? $in['empresa'] ?? '', 160);
$message = $str($in['message'] ?? $in['mensagem'] ?? '', 4000);

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Informe um e-mail válido.'], 422);
}
$pd = digits($phone);
if ($phone !== '' && (strlen($pd) < 8 || strlen($pd) > 15)) {
    json_out(['ok' => false, 'error' => 'Informe um telefone/WhatsApp válido, com DDD.'], 422);
}
if ($name === '' && $email === '' && $phone === '') {
    json_out(['ok' => false, 'error' => 'Preencha seu nome e um contato (WhatsApp ou e-mail).'], 422);
}
if ($email === '' && $phone === '') {
    json_out(['ok' => false, 'error' => 'Informe um WhatsApp ou e-mail para retornarmos.'], 422);
}

// Origem
$requested = slugify($str($in['source'] ?? 'outros', 60), 60);
$source = (string) scalar('SELECT slug FROM lead_sources WHERE slug = ? AND active = 1', [$requested]);
$extraFromSource = [];
if ($source === '') {
    $source = 'outros';
    $extraFromSource['origem_informada'] = $requested;
}

// Campos extras (qualquer outro campo do formulário)
$reserved = ['source', 'name', 'nome', 'email', 'phone', 'telefone', 'whatsapp', 'company', 'empresa', 'message', 'mensagem', 'website', 'hp', '_t', '_csrf', 'page_url', 'referrer', 'gclid', 'fbclid'];
$data = $extraFromSource;
$n = 0;
foreach ($in as $k => $v) {
    $k = (string) $k;
    if (in_array(strtolower($k), $reserved, true) || stripos($k, 'utm_') === 0 || $n >= 30) {
        continue;
    }
    // PHP troca espaços por "_" em nomes de campo de formulário; desfaz para exibir bonito
    $label = mb_substr(trim(preg_replace('/[^\p{L}\p{N} \-\/\.\?]+/u', '', str_replace('_', ' ', $k)) ?? ''), 0, 60);
    $val = $str($v, 1000);
    if ($label === '' || $val === '') {
        continue;
    }
    $data[$label] = $val;
    $n++;
}

$utm = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'] as $k) {
    if (isset($in[$k]) && ($val = $str($in[$k], 200)) !== '') {
        $utm[$k] = $val;
    }
}
$pageUrl = $str($in['page_url'] ?? '', 500);
$referrer = $str($in['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''), 500);

// Currículo (apenas Trabalhe Conosco)
$attachment = null;
if ($source === 'trabalhe-conosco' && !empty($_FILES['curriculo']) && ($_FILES['curriculo']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
    try {
        $attachment = save_resume($_FILES['curriculo']);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

// Duplicado recente (duplo clique)
$dup = scalar(
    'SELECT id FROM leads WHERE source = ? AND ip = ? AND created_at > ? AND ((phone != \'\' AND phone = ?) OR (email != \'\' AND email = ?)) ORDER BY id DESC LIMIT 1',
    [$source, $ip, date('Y-m-d H:i:s', time() - 90), $phone, $email]
);
if ($dup) {
    json_out(['ok' => true, 'id' => (int) $dup]);
}

q(
    'INSERT INTO leads (source, name, email, phone, company, message, data, status, utm, page_url, referrer, ip, user_agent, attachment, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, \'novo\', ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $source, $name, $email, $phone, $company, $message,
        json_encode($data, JSON_UNESCAPED_UNICODE), json_encode($utm, JSON_UNESCAPED_UNICODE),
        $pageUrl, $referrer, $ip, client_ua(), $attachment, now(), now(),
    ]
);
$id = insert_id();

// Responde ao visitante e só depois dispara o e-mail (não atrasa a página)
ignore_user_abort(true);
$body = json_encode(['ok' => true, 'id' => $id]);
header('Content-Length: ' . strlen($body));
header('Connection: close');
echo $body;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    @flush();
}
try {
    notify_new_lead($id);
} catch (Throwable $e) {
    error_log('notify_new_lead: ' . $e->getMessage());
}
