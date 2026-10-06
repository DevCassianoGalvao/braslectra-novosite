<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('blog');
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Método inválido.'], 405);
}
csrf_check();

$f = $_FILES['file'] ?? null;
if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK) {
    json_out(['ok' => false, 'error' => 'Nenhum arquivo recebido (ou arquivo grande demais para o servidor).'], 400);
}
if ($f['size'] > 6 * 1024 * 1024) {
    json_out(['ok' => false, 'error' => 'A imagem deve ter no máximo 6 MB.'], 413);
}
$info = @getimagesize($f['tmp_name']);
$map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
if (!$info || !isset($map[$info[2]])) {
    json_out(['ok' => false, 'error' => 'Envie uma imagem JPG, PNG, WebP ou GIF.'], 415);
}
$ext = $map[$info[2]];
$root = rtrim((string) cfg('upload_dir'), '/\\');
$sub = 'blog/' . date('Y/m');
$dir = $root . '/' . $sub;
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    json_out(['ok' => false, 'error' => 'Não foi possível criar a pasta de uploads.'], 500);
}
// Impede execução de scripts dentro de uploads (Apache e IIS)
$ht = $root . '/.htaccess';
if (!is_file($ht)) {
    @file_put_contents($ht, "Options -Indexes -ExecCGI\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\nphp_flag engine off\n");
}
$base = slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME), 40);
$name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
    json_out(['ok' => false, 'error' => 'Falha ao salvar a imagem.'], 500);
}
$rel = 'uploads/' . $sub . '/' . $name;
json_out(['ok' => true, 'path' => $rel, 'url' => url($rel), 'width' => $info[0], 'height' => $info[1]]);
