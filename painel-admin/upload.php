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
[$name, $w, $h] = optimize_blog_image($dir, $name, $info[2], (int) $info[0], (int) $info[1]);
$rel = 'uploads/' . $sub . '/' . $name;
json_out(['ok' => true, 'path' => $rel, 'url' => url($rel), 'width' => $w, 'height' => $h]);

/**
 * Deixa a imagem leve para o site: no máximo 1600 px de largura e convertida para WebP.
 * Sem a extensão GD (ou em GIF animado), mantém o arquivo original.
 */
function optimize_blog_image(string $dir, string $name, int $type, int $w, int $h): array
{
    if (!function_exists('imagewebp') || !in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return [$name, $w, $h];
    }
    $src = $dir . '/' . $name;
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        default => @imagecreatefromwebp($src),
    };
    if (!$img) {
        return [$name, $w, $h];
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) { // foto de celular "deitada"
        $o = (int) (@exif_read_data($src)['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot && ($r = imagerotate($img, $rot, 0))) {
            $img = $r;
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > 1600) {
        $nh = (int) round($h * 1600 / $w);
        $dst = imagecreatetruecolor(1600, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, 1600, $nh, $w, $h);
        $img = $dst;
        [$w, $h] = [1600, $nh];
    } else {
        imagepalettetotruecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }
    $out = preg_replace('/\.(jpe?g|png|webp)$/i', '', $name) . '.webp';
    if (!@imagewebp($img, $dir . '/' . $out, 80)) {
        return [$name, $w, $h];
    }
    if ($out !== $name) {
        @unlink($src);
    }
    return [$out, $w, $h];
}
