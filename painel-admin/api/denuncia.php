<?php
/**
 * Canal de Denúncia: recebe o formulário (com fotos opcionais) e envia por e-mail (Brevo) ao endereço exclusivo.
 *
 * PRIVACIDADE (denúncias anônimas):
 *  - NADA da denúncia (texto ou fotos) é gravado no banco, em arquivo ou em log. Só existe no e-mail enviado.
 *    As fotos ficam apenas na pasta temporária do PHP durante o envio, que o PHP apaga ao fim da requisição.
 *  - Não guarda IP, user-agent, referrer, UTM, página de origem nem cookies, e não entra no CRM de leads.
 *  - O IP só é usado para limitar abuso, e apenas como hash com sal (não reversível), apagado em 24 h.
 *  - Em denúncia anônima, qualquer nome/contato enviado pelo navegador é descartado no servidor.
 *  - As fotos são decodificadas e recodificadas como JPEG: isso remove EXIF/GPS/modelo do aparelho/data e
 *    garante que o arquivo é mesmo uma imagem. O nome original do arquivo também é descartado.
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

const DEN_MAX_PHOTOS = 5;                 // fotos por denúncia
const DEN_MAX_PHOTO_BYTES = 10 * 1024 * 1024;   // por foto enviada (o navegador já reduz antes)
const DEN_MAX_PIXELS = 40_000_000;        // 40 megapixels
const DEN_MAX_SIDE = 1600;                // lado maior após o processamento
const DEN_MAX_TOTAL_BYTES = 7 * 1024 * 1024;    // soma das fotos já processadas (limite seguro de e-mail)

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_contains($ctype, 'multipart/form-data')) {
    $in = $_POST;
    if (!$in && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {   // passou do limite do servidor: o PHP descarta tudo
        json_out(['ok' => false, 'error' => 'As fotos ultrapassam o limite de envio do servidor. Envie menos fotos ou fotos menores.'], 413);
    }
} else {
    $in = json_decode((string) file_get_contents('php://input', false, null, 0, 40000), true);
    if (!is_array($in)) {
        json_out(['ok' => false, 'error' => 'Requisição inválida.'], 400);
    }
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
$truthy = static fn ($v): bool => $v === true || in_array(strtolower((string) $v), ['1', 'true', 'on', 'sim'], true);

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
if (!$truthy($in['goodfaith'] ?? false)) {
    json_out(['ok' => false, 'error' => 'Confirme que o relato é feito de boa-fé.'], 422);
}
$to = trim(setting('complaint_email', 'denuncia@braslectra.com.br'));
if (!filter_var($to, FILTER_VALIDATE_EMAIL) || trim(setting('brevo_api_key')) === '') {
    error_log('denuncia: canal sem destinatário válido ou Brevo sem chave');
    json_out(['ok' => false, 'error' => 'O canal está temporariamente indisponível. Tente novamente mais tarde.'], 503);
}

// ---------- fotos (opcionais): valida, recodifica sem metadados e prepara como anexo ----------
$attachments = [];
$photos = $_FILES['photos'] ?? null;
if ($photos && is_array($photos['name'] ?? null)) {
    $n = count($photos['name']);
    $present = [];
    for ($i = 0; $i < $n; $i++) {
        if (($photos['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $present[] = $i;
        }
    }
    if (count($present) > DEN_MAX_PHOTOS) {
        json_out(['ok' => false, 'error' => 'Envie no máximo ' . DEN_MAX_PHOTOS . ' fotos.'], 422);
    }
    @ini_set('memory_limit', '384M');
    $total = 0;
    foreach ($present as $k => $i) {
        $tmp = (string) ($photos['tmp_name'][$i] ?? '');
        $err = (int) ($photos['error'][$i] ?? 0);
        $label = 'A foto ' . ($k + 1);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            json_out(['ok' => false, 'error' => $label . ' é grande demais. Limite: ' . (DEN_MAX_PHOTO_BYTES / 1048576) . ' MB por foto.'], 413);
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            json_out(['ok' => false, 'error' => $label . ' não pôde ser recebida. Tente novamente.'], 400);
        }
        if (filesize($tmp) > DEN_MAX_PHOTO_BYTES) {
            json_out(['ok' => false, 'error' => $label . ' é grande demais. Limite: ' . (DEN_MAX_PHOTO_BYTES / 1048576) . ' MB por foto.'], 413);
        }
        $info = @getimagesize($tmp);
        $mime = class_exists('finfo') ? ((new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '') : '';
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || ($mime !== '' && !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true))) {
            json_out(['ok' => false, 'error' => $label . ' não é uma imagem válida. Envie JPG, PNG ou WebP.'], 415);
        }
        if ($info[0] * $info[1] > DEN_MAX_PIXELS) {
            json_out(['ok' => false, 'error' => $label . ' tem resolução alta demais.'], 413);
        }
        $img = fleet_load_image($tmp, $info[2]);   // decodifica de verdade (e corrige a rotação) — falha se não for imagem
        if (!$img) {
            json_out(['ok' => false, 'error' => $label . ' não pôde ser lida como imagem.'], 415);
        }
        $w = imagesx($img);
        $hgt = imagesy($img);
        $k2 = min(1, DEN_MAX_SIDE / max($w, $hgt));
        $nw = max(1, (int) round($w * $k2));
        $nh = max(1, (int) round($hgt * $k2));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // PNG/WebP com transparência: fundo branco
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
        ob_start();
        imagejpeg($dst, null, 82);                                       // recodifica: sem EXIF, GPS, miniatura ou comentários
        $jpeg = (string) ob_get_clean();
        unset($img, $dst);
        $total += strlen($jpeg);
        if ($jpeg === '' || $total > DEN_MAX_TOTAL_BYTES) {
            json_out(['ok' => false, 'error' => 'As fotos juntas são pesadas demais. Envie menos fotos ou fotos menores.'], 413);
        }
        $attachments[] = ['name' => 'foto-' . ($k + 1) . '.jpg', 'content' => base64_encode($jpeg)];   // nome genérico
    }
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
if ($attachments) {
    $rows['Anexos'] = count($attachments) . ' foto' . (count($attachments) > 1 ? 's' : '') . ' em anexo neste e-mail';
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

[$ok, $msg] = brevo_send([$to], 'Canal de Denúncia | Nova denúncia ' . $protocol, $html, $txt, null, null, $attachments);   // sem Reply-To
if (!$ok) {
    error_log('denuncia: falha no envio (' . substr($msg, 0, 120) . ')');   // nunca registra o conteúdo
    json_out(['ok' => false, 'error' => 'Não foi possível enviar agora. Seus dados não foram guardados: tente novamente em alguns minutos.'], 502);
}
json_out(['ok' => true, 'protocol' => $protocol]);
