<?php
declare(strict_types=1);

/** Monta WHERE/params dos leads respeitando permissões e filtros (GET). */
function leads_filter(): array
{
    [$where, $params] = lead_scope_sql('l.source');
    $f = [
        'source' => (string) ($_GET['source'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'q'      => trim((string) ($_GET['q'] ?? '')),
        'from'   => (string) ($_GET['from'] ?? ''),
        'to'     => (string) ($_GET['to'] ?? ''),
    ];
    if ($f['source'] !== '') {
        $where .= ' AND l.source = ?';
        $params[] = $f['source'];
    }
    if ($f['status'] !== '' && isset(lead_statuses()[$f['status']])) {
        $where .= ' AND l.status = ?';
        $params[] = $f['status'];
    } else {
        $f['status'] = '';
    }
    if ($f['q'] !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
        $where .= " AND (l.name LIKE ? ESCAPE '\\' OR l.email LIKE ? ESCAPE '\\' OR l.phone LIKE ? ESCAPE '\\' OR l.company LIKE ? ESCAPE '\\' OR l.message LIKE ? ESCAPE '\\' OR l.data LIKE ? ESCAPE '\\')";
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
        $where .= ' AND l.created_at >= ?';
        $params[] = $f['from'] . ' 00:00:00';
    } else {
        $f['from'] = '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
        $where .= ' AND l.created_at <= ?';
        $params[] = $f['to'] . ' 23:59:59';
    } else {
        $f['to'] = '';
    }
    return [$where, $params, $f];
}
