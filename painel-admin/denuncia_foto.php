<?php
// Serve uma foto de denúncia somente para quem tem a permissão do Canal de Denúncia (as fotos ficam fora da área pública).
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('complaints');

$c = row('SELECT photos FROM complaints WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$list = $c ? (json_decode((string) $c['photos'], true) ?: []) : [];
$rel = (string) ($list[(int) ($_GET['n'] ?? -1)] ?? '');
$base = realpath((string) cfg('private_dir'));
$file = ($base && str_starts_with($rel, 'denuncias/')) ? realpath($base . '/' . $rel) : false;
if (!$file || !str_starts_with($file, $base) || !is_file($file)) {
    http_response_code(404);
    exit('Foto não encontrada.');
}
header('X-Content-Type-Options: nosniff');
header('Content-Type: image/jpeg');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($file));
readfile($file);
