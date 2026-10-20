<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('fleet');
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Método inválido.'], 405);
}
csrf_check();

$kind = post('kind') === 'main' ? 'main' : 'gallery';
$f = $_FILES['file'] ?? null;
if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK) {
    json_out(['ok' => false, 'error' => 'Nenhum arquivo recebido (ou arquivo maior que o limite do servidor, ' . ini_get('upload_max_filesize') . ').'], 400);
}
$max = ($kind === 'main' ? 14 : 10) * 1024 * 1024;
if ($f['size'] > $max) {
    json_out(['ok' => false, 'error' => 'A imagem deve ter no máximo ' . ($max / 1048576) . ' MB.'], 413);
}
if (!function_exists('imagewebp')) {
    json_out(['ok' => false, 'error' => 'O servidor não tem suporte a imagens WebP (extensão GD). Peça à hospedagem para ativar.'], 500);
}
$info = @getimagesize($f['tmp_name']);
if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
    json_out(['ok' => false, 'error' => 'Envie uma imagem JPG, PNG ou WebP.'], 415);
}
if ($info[0] * $info[1] > 40_000_000) {
    json_out(['ok' => false, 'error' => 'Imagem com resolução alta demais (máx. 40 megapixels).'], 413);
}
@ini_set('memory_limit', '512M');
$img = fleet_load_image($f['tmp_name'], $info[2]);
if (!$img) {
    json_out(['ok' => false, 'error' => 'Não foi possível ler a imagem.'], 415);
}
[$dir, $rel] = fleet_upload_dir();
if (!$dir) {
    json_out(['ok' => false, 'error' => 'Não foi possível criar a pasta de uploads.'], 500);
}
$base = slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME), 30) ?: 'foto';
$name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.webp';
[$ok, $msg] = $kind === 'main' ? fleet_save_main($img, $dir . '/' . $name) : fleet_save_gallery($img, $dir . '/' . $name);
if (!$ok) {
    json_out(['ok' => false, 'error' => $msg], 500);
}
$path = $rel . '/' . $name;
json_out(['ok' => true, 'path' => $path, 'url' => url($path), 'thumb' => url(fleet_thumb($path))]);
