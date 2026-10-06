<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('leads');
if (!is_post()) {
    redirect('leads.php');
}
csrf_check();

$wantsJson = strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$id = (int) ($_POST['id'] ?? 0);
$lead = $id ? row('SELECT * FROM leads WHERE id = ?', [$id]) : null;
$fail = static function (string $msg, int $code = 400) use ($wantsJson): void {
    if ($wantsJson) {
        json_out(['ok' => false, 'error' => $msg], $code);
    }
    flash('err', $msg);
    redirect('leads.php');
};
if (!$lead || !can_see_lead($lead)) {
    $fail('Lead não encontrado.', 404);
}

$action = post('action', 'status');

if ($action === 'status') {
    $status = post('status');
    if (!isset(lead_statuses()[$status])) {
        $fail('Status inválido.');
    }
    if ($status !== $lead['status']) {
        q('UPDATE leads SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $id]);
        q(
            'INSERT INTO lead_notes (lead_id, user_id, kind, body, created_at) VALUES (?, ?, \'status\', ?, ?)',
            [$id, $u['id'], 'Status alterado de “' . lead_statuses()[$lead['status']][0] . '” para “' . lead_statuses()[$status][0] . '”', now()]
        );
    }
    if ($wantsJson) {
        json_out(['ok' => true]);
    }
    flash('ok', 'Status atualizado.');
    redirect('lead.php?id=' . $id);
}

if ($action === 'note') {
    $body = mb_substr(post('body'), 0, 3000);
    if ($body === '') {
        flash('err', 'Escreva a anotação antes de salvar.');
    } else {
        q('INSERT INTO lead_notes (lead_id, user_id, kind, body, created_at) VALUES (?, ?, \'note\', ?, ?)', [$id, $u['id'], $body, now()]);
        q('UPDATE leads SET updated_at = ? WHERE id = ?', [now(), $id]);
        flash('ok', 'Anotação adicionada.');
    }
    redirect('lead.php?id=' . $id);
}

if ($action === 'delete') {
    if (!is_admin()) {
        $fail('Apenas administradores podem excluir leads.', 403);
    }
    if (!empty($lead['attachment'])) {
        $f = rtrim((string) cfg('private_dir'), '/\\') . '/' . $lead['attachment'];
        if (is_file($f)) {
            @unlink($f);
        }
    }
    q('DELETE FROM leads WHERE id = ?', [$id]);
    flash('ok', 'Lead excluído.');
    redirect('leads.php');
}

$fail('Ação inválida.');
