<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('leads');

$id = (int) ($_GET['id'] ?? 0);
$lead = $id ? row('SELECT * FROM leads WHERE id = ?', [$id]) : null;
if (!$lead || !can_see_lead($lead) || empty($lead['attachment'])) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$base = realpath((string) cfg('private_dir'));
$file = $base ? realpath($base . '/' . $lead['attachment']) : false;
if (!$file || strpos($file, $base) !== 0 || !is_file($file)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'odt' => 'application/vnd.oasis.opendocument.text', 'rtf' => 'application/rtf'];
$nice = 'curriculo-' . slugify($lead['name'] ?: 'candidato') . '-' . $lead['id'] . '.' . $ext;
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $nice . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
