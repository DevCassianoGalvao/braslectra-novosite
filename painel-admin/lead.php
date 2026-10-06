<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('leads');

$id = (int) ($_GET['id'] ?? 0);
$lead = $id ? row('SELECT * FROM leads WHERE id = ?', [$id]) : null;
if (!$lead || !can_see_lead($lead)) {
    flash('err', 'Lead não encontrado.');
    redirect('leads.php');
}
$data = json_decode((string) $lead['data'], true) ?: [];
$utm = json_decode((string) $lead['utm'], true) ?: [];
$notes = rows('SELECT n.*, u.name AS uname FROM lead_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.lead_id = ? ORDER BY n.id DESC', [$id]);
$wa = wa_link($lead['phone'], 'Olá' . ($lead['name'] ? ', ' . explode(' ', $lead['name'])[0] : '') . '! Aqui é do Grupo Braslectra, recebemos sua solicitação.');

view_header([
    'title'   => $lead['name'] ?: 'Lead #' . $lead['id'],
    'active'  => 'leads',
    'actions' => '<a class="btn-ghost sm" href="' . e(url('leads.php?source=' . rawurlencode($lead['source']))) . '">← Voltar à lista</a>',
]);
?>
<div class="layout-2">
  <div>
    <div class="card">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        <span class="src"><?= e(source_label($lead['source'])) ?></span>
        <?= status_badge($lead['status']) ?>
        <span class="muted">Recebido em <?= e(fmt_date($lead['created_at'])) ?></span>
      </div>
      <dl class="kv">
        <dt>Nome</dt><dd><b><?= e($lead['name'] ?: '—') ?></b></dd>
        <dt>WhatsApp / telefone</dt><dd><?= e($lead['phone'] ?: '—') ?></dd>
        <dt>E-mail</dt><dd><?= $lead['email'] ? '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>' : '—' ?></dd>
        <?php if ($lead['company']): ?><dt>Empresa</dt><dd><?= e($lead['company']) ?></dd><?php endif; ?>
        <?php if ($lead['message']): ?><dt>Mensagem</dt><dd><?= nl2br(e($lead['message'])) ?></dd><?php endif; ?>
        <?php foreach ($data as $k => $v): ?>
          <dt><?= e($k) ?></dt><dd><?= nl2br(e(is_array($v) ? implode(', ', $v) : (string) $v)) ?></dd>
        <?php endforeach; ?>
        <?php if ($lead['attachment']): ?>
          <dt>Currículo</dt><dd><a class="btn-ghost sm" href="<?= e(url('download.php?id=' . $lead['id'])) ?>"><?= icon('download') ?> Baixar arquivo</a></dd>
        <?php endif; ?>
      </dl>
      <div class="actions" style="margin-top:18px">
        <?php if ($wa): ?><a class="btn sm" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> Chamar no WhatsApp</a><?php endif; ?>
        <?php if ($lead['email']): ?><a class="btn-ghost sm" href="mailto:<?= e($lead['email']) ?>"><?= icon('mail') ?> Enviar e-mail</a><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h2>Histórico e anotações</h2>
      <form method="post" action="<?= e(url('lead_action.php')) ?>" style="margin:12px 0 18px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $lead['id'] ?>"><input type="hidden" name="action" value="note">
        <label class="f"><span class="h">Nova anotação</span><textarea name="body" rows="3" placeholder="Ex.: Liguei às 14h, cliente pediu proposta para 20 pessoas." required maxlength="3000"></textarea></label>
        <button class="btn dark sm" type="submit">Salvar anotação</button>
      </form>
      <ul class="timeline">
        <?php foreach ($notes as $n): ?>
          <li class="<?= e($n['kind']) ?>">
            <div><span class="who"><?= e($n['uname'] ?: 'Sistema') ?></span> <span class="when">· <?= e(fmt_date($n['created_at'])) ?></span></div>
            <div><?= nl2br(e($n['body'])) ?></div>
          </li>
        <?php endforeach; ?>
        <li class="status"><div><span class="who">Lead recebido</span> <span class="when">· <?= e(fmt_date($lead['created_at'])) ?></span></div><div class="muted">Origem: <?= e(source_label($lead['source'])) ?></div></li>
      </ul>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Status</h2>
      <form method="post" action="<?= e(url('lead_action.php')) ?>" style="margin-top:12px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $lead['id'] ?>"><input type="hidden" name="action" value="status">
        <label class="f"><select name="status">
          <?php foreach (lead_statuses() as $k => [$lab]): ?><option value="<?= e($k) ?>" <?= $lead['status'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
        </select></label>
        <button class="btn sm" type="submit">Atualizar status</button>
      </form>
    </div>
    <div class="card">
      <h2>Origem do acesso</h2>
      <dl class="kv" style="grid-template-columns:110px 1fr;margin-top:12px;font-size:13px">
        <?php if ($lead['page_url']): ?><dt>Página</dt><dd><?= e($lead['page_url']) ?></dd><?php endif; ?>
        <?php if ($lead['referrer']): ?><dt>Veio de</dt><dd><?= e($lead['referrer']) ?></dd><?php endif; ?>
        <?php foreach ($utm as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
        <dt>IP</dt><dd><?= e($lead['ip']) ?></dd>
      </dl>
    </div>
    <?php if (is_admin()): ?>
    <div class="card">
      <h2>Zona de risco</h2>
      <form method="post" action="<?= e(url('lead_action.php')) ?>" data-confirm="Excluir este lead definitivamente? Esta ação não pode ser desfeita." style="margin-top:12px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $lead['id'] ?>"><input type="hidden" name="action" value="delete">
        <button class="btn-danger sm" type="submit"><?= icon('trash') ?> Excluir lead</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php view_footer();
