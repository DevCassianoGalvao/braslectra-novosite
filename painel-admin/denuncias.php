<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('complaints');

$statuses = [
    'nova'      => ['Nova', '#B42318'],
    'apuracao'  => ['Em apuração', '#2563EB'],
    'concluida' => ['Concluída', '#16803C'],
    'arquivada' => ['Arquivada', '#8A8377'],
];
$badge = static function (string $s) use ($statuses): string {
    [$l, $c] = $statuses[$s] ?? [$s, '#8A8377'];
    return '<span class="badge" style="--c:' . e($c) . '">' . e($l) . '</span>';
};
$photoPaths = static fn (array $c): array => array_values(array_filter(json_decode((string) $c['photos'], true) ?: [], 'is_string'));

$id = (int) ($_GET['id'] ?? 0);
$c = $id ? row('SELECT * FROM complaints WHERE id = ?', [$id]) : null;

if (is_post()) {
    csrf_check();
    $act = post('action');
    $pid = (int) ($_POST['id'] ?? 0);
    $cc = $pid ? row('SELECT * FROM complaints WHERE id = ?', [$pid]) : null;
    if ($cc && $act === 'status' && isset($statuses[post('status')])) {
        q('UPDATE complaints SET status = ?, updated_at = ? WHERE id = ?', [post('status'), now(), $pid]);
        flash('ok', 'Status atualizado.');
        redirect('denuncias.php?id=' . $pid);
    }
    if ($cc && $act === 'note' && trim(post('note')) !== '') {
        $line = '[' . date('d/m/Y H:i') . ' · ' . $u['name'] . '] ' . mb_substr(trim(post('note')), 0, 4000);
        q('UPDATE complaints SET notes = ?, updated_at = ? WHERE id = ?', [trim($line . "\n\n" . $cc['notes']), now(), $pid]);
        flash('ok', 'Anotação registrada.');
        redirect('denuncias.php?id=' . $pid);
    }
    if ($cc && $act === 'delete' && is_admin($u)) {
        $base = rtrim((string) cfg('private_dir'), '/\\');
        foreach ($photoPaths($cc) as $p) {
            if (str_starts_with($p, 'denuncias/') && !str_contains($p, '..') && is_file($base . '/' . $p)) {
                @unlink($base . '/' . $p);
            }
        }
        q('DELETE FROM complaints WHERE id = ?', [$pid]);
        flash('ok', 'Denúncia ' . $cc['protocol'] . ' excluída.');
        redirect('denuncias.php');
    }
    redirect($pid ? 'denuncias.php?id=' . $pid : 'denuncias.php');
}

// ---------- detalhe ----------
if ($c) {
    $photos = $photoPaths($c);
    view_header([
        'title'   => 'Denúncia ' . $c['protocol'],
        'active'  => 'complaints',
        'actions' => '<a class="btn-ghost sm" href="' . e(url('denuncias.php')) . '">← Voltar à lista</a>',
    ]);
    ?>
<div class="hint" style="border-color:#B42318;background:#FEF3F2"><b>Conteúdo confidencial.</b> Acesso restrito às pessoas responsáveis pela apuração. Não compartilhe prints nem encaminhe sem necessidade.</div>
<div class="layout-2">
  <div>
    <div class="card">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        <span class="src"><?= e($c['protocol']) ?></span><?= $badge($c['status']) ?>
        <span class="muted">Recebida em <?= e(fmt_date($c['created_at'])) ?></span>
      </div>
      <dl class="kv">
        <dt>Tipo</dt><dd><b><?= $c['ident'] === 'identificada' ? 'Identificada' : 'Anônima' ?></b></dd>
        <?php if ($c['ident'] === 'identificada'): ?>
          <dt>Nome</dt><dd><?= e($c['name'] ?: '—') ?></dd>
          <dt>Contato</dt><dd><?= e($c['contact'] ?: '—') ?></dd>
        <?php endif; ?>
        <dt>Assunto</dt><dd><?= e($c['subject']) ?></dd>
        <dt>Pessoas envolvidas</dt><dd><?= nl2br(e($c['people'] ?: '—')) ?></dd>
        <dt>Local</dt><dd><?= nl2br(e($c['place'] ?: '—')) ?></dd>
        <dt>Descrição</dt><dd style="white-space:normal"><?= nl2br(e($c['description'])) ?></dd>
      </dl>
    </div>
    <?php if ($photos): ?>
    <div class="card">
      <h2>Fotos anexadas (<?= count($photos) ?>)</h2>
      <p class="sub">Os dados ocultos das fotos (local, data e aparelho) foram removidos no envio.</p>
      <div class="fl-gal">
        <?php foreach ($photos as $i => $p): $src = url('denuncia_foto.php?id=' . $c['id'] . '&n=' . $i); ?>
          <a class="fl-item" href="<?= e($src) ?>" target="_blank" rel="noopener" title="Abrir foto <?= $i + 1 ?>"><img src="<?= e($src) ?>" alt="Foto <?= $i + 1 ?>" loading="lazy"></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <h2>Andamento</h2>
      <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <label class="f"><span class="h">Status</span>
          <select name="status"><?php foreach ($statuses as $k => [$l]): ?><option value="<?= e($k) ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </label>
        <div class="actions"><button class="btn sm" type="submit">Salvar status</button></div>
      </form>
    </div>
    <div class="card">
      <h2>Anotações internas</h2>
      <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="note"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <label class="f"><textarea name="note" rows="3" placeholder="Registre providências, conversas e decisões da apuração."></textarea></label>
        <div class="actions"><button class="btn-ghost sm" type="submit">Adicionar anotação</button></div>
      </form>
      <?php if (trim((string) $c['notes']) !== ''): ?><div style="margin-top:14px;white-space:pre-wrap;font-size:13.5px;line-height:1.55;color:var(--ink2)"><?= e($c['notes']) ?></div><?php endif; ?>
    </div>
    <div class="card">
      <h2>E-mail</h2>
      <p class="sub"><?= (int) $c['email_ok'] ? 'Cópia enviada para o e-mail do Canal de Denúncia.' : 'A cópia por e-mail não foi enviada (verifique a Brevo no Diagnóstico). A denúncia está guardada aqui.' ?></p>
    </div>
    <?php if (is_admin($u)): ?>
    <form method="post" data-confirm="Excluir a denúncia <?= e($c['protocol']) ?> e as fotos? Esta ação não pode ser desfeita."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <button class="btn-danger sm" type="submit"><?= icon('trash') ?> Excluir denúncia</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
    view_footer();
    exit;
}

// ---------- lista ----------
$st = (string) ($_GET['status'] ?? '');
$where = '1=1';
$params = [];
if (isset($statuses[$st])) {
    $where = 'status = ?';
    $params[] = $st;
} else {
    $st = '';
}
$total = (int) scalar("SELECT COUNT(*) FROM complaints WHERE $where", $params);
$pg = pagination($total, (int) ($_GET['p'] ?? 1), 30);
$list = rows("SELECT * FROM complaints WHERE $where ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$cnt = [];
foreach (rows('SELECT status, COUNT(*) n FROM complaints GROUP BY status') as $r) {
    $cnt[$r['status']] = (int) $r['n'];
}
view_header(['title' => 'Denúncias', 'active' => 'complaints']);
?>
<div class="chips">
  <a class="chip <?= $st === '' ? 'on' : '' ?>" href="<?= e(url('denuncias.php')) ?>">Todas <em><?= array_sum($cnt) ?></em></a>
  <?php foreach ($statuses as $k => [$l]): ?><a class="chip <?= $st === $k ? 'on' : '' ?>" href="<?= e(url('denuncias.php?status=' . $k)) ?>"><?= e($l) ?> <em><?= $cnt[$k] ?? 0 ?></em></a><?php endforeach; ?>
</div>
<div class="hint">Conteúdo confidencial. Só usuários com a permissão <b>Canal de Denúncia</b> (e administradores) veem esta área. O site não guarda IP nem dados do aparelho de quem denuncia.</div>
<?php if (!$list): ?>
  <div class="card empty">Nenhuma denúncia<?= $st ? ' com este status' : ' recebida até agora' ?>.</div>
<?php else: ?>
<div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Protocolo</th><th>Assunto</th><th>Tipo</th><th>Fotos</th><th>Status</th><th class="right">Recebida</th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): $n = count($photoPaths($r)); ?>
      <tr class="row-link <?= $r['status'] === 'nova' ? 'is-new' : '' ?>" data-href="<?= e(url('denuncias.php?id=' . $r['id'])) ?>">
        <td><a class="t-main" href="<?= e(url('denuncias.php?id=' . $r['id'])) ?>"><?= e($r['protocol']) ?></a></td>
        <td><?= e($r['subject']) ?><div class="t-sub"><?= e(truncate((string) $r['description'], 90)) ?></div></td>
        <td class="muted"><?= $r['ident'] === 'identificada' ? 'Identificada' : 'Anônima' ?></td>
        <td class="muted"><?= $n ?: '—' ?></td>
        <td><?= $badge($r['status']) ?></td>
        <td class="right muted nowrap"><?= e(fmt_date($r['created_at'])) ?><div class="t-sub"><?= e(time_ago($r['created_at'])) ?></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_html($pg) ?>
<?php endif;
view_footer();
