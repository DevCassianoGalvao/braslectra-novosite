<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/leads_query.php';
$u = require_perm('leads');
send_security_headers();

[$where, $params] = leads_filter();
$list = rows("SELECT l.* FROM leads l WHERE $where ORDER BY l.id DESC LIMIT 20000", $params);

// Colunas dinâmicas: campos extras presentes nos leads exportados
$extra = [];
foreach ($list as $l) {
    foreach (array_keys(json_decode($l['data'], true) ?: []) as $k) {
        $extra[$k] = true;
    }
}
$extra = array_keys($extra);

$csvSafe = static function ($v): string {
    $v = (string) $v;
    // evita injeção de fórmula ao abrir no Excel/Sheets
    if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
        $v = "'" . $v;
    }
    return $v;
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d-His') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM p/ Excel
fputcsv($out, array_merge(['ID', 'Data', 'Origem', 'Status', 'Nome', 'WhatsApp', 'E-mail', 'Empresa', 'Mensagem', 'Página', 'utm_source', 'utm_medium', 'utm_campaign'], $extra), ';');
foreach ($list as $l) {
    $d = json_decode($l['data'], true) ?: [];
    $utm = json_decode($l['utm'], true) ?: [];
    $line = [
        $l['id'], fmt_date($l['created_at']), source_label($l['source']), lead_statuses()[$l['status']][0] ?? $l['status'],
        $l['name'], $l['phone'], $l['email'], $l['company'], $l['message'], $l['page_url'],
        $utm['utm_source'] ?? '', $utm['utm_medium'] ?? '', $utm['utm_campaign'] ?? '',
    ];
    foreach ($extra as $k) {
        $v = $d[$k] ?? '';
        $line[] = is_array($v) ? implode(', ', $v) : $v;
    }
    fputcsv($out, array_map($csvSafe, $line), ';');
}
fclose($out);
