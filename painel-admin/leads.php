<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/leads_query.php';
$u = require_perm('leads');

[$where, $params, $f] = leads_filter();

// contagem por origem (ignora filtro de origem para os chips)
$chipF = $_GET;
unset($chipF['source'], $chipF['p']);
$getBackup = $_GET;
$_GET = $chipF;
[$wChip, $pChip] = leads_filter();
$_GET = $getBackup;
$counts = [];
foreach (rows("SELECT l.source, COUNT(*) c FROM leads l WHERE $wChip GROUP BY l.source", $pChip) as $r) {
    $counts[$r['source']] = (int) $r['c'];
}
$allowed = allowed_sources();
$sources = array_values(array_filter(lead_sources(false), static fn ($s) => $allowed === null || in_array($s['slug'], $allowed, true)));
$totalAll = array_sum($counts);

$total = (int) scalar("SELECT COUNT(*) FROM leads l WHERE $where", $params);
$pg = pagination($total, (int) ($_GET['p'] ?? 1), 25);
$list = rows("SELECT l.* FROM leads l WHERE $where ORDER BY l.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$exportUrl = url('leads_export.php') . query_with(['p' => '']);
view_header([
    'title'   => 'Leads',
    'active'  => 'leads',
    'actions' => '<a class="btn-ghost sm" href="' . e($exportUrl) . '">' . icon('download') . ' Exportar CSV</a>',
]);
?>
<div class="chips">
  <a class="chip <?= $f['source'] === '' ? 'on' : '' ?>" href="<?= e(query_with(['source' => '', 'p' => ''])) ?>">Todos <em><?= $totalAll ?></em></a>
  <?php foreach ($sources as $s): if (empty($counts[$s['slug']]) && $f['source'] !== $s['slug'] && $s['active'] != 1) continue; ?>
    <a class="chip <?= $f['source'] === $s['slug'] ? 'on' : '' ?>" href="<?= e(query_with(['source' => $s['slug'], 'p' => ''])) ?>"><?= e($s['label']) ?> <em><?= $counts[$s['slug']] ?? 0 ?></em></a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get">
  <?php if ($f['source'] !== ''): ?><input type="hidden" name="source" value="<?= e($f['source']) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por nome, telefone, e-mail, empresa…">
  <select name="status">
    <option value="">Todos os status</option>
    <?php foreach (lead_statuses() as $k => [$lab]): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?= e($f['from']) ?>" title="De">
  <input type="date" name="to" value="<?= e($f['to']) ?>" title="Até">
  <button class="btn dark sm" type="submit"><?= icon('search') ?> Filtrar</button>
  <?php if ($f['q'] !== '' || $f['status'] !== '' || $f['from'] !== '' || $f['to'] !== ''): ?>
    <a class="btn-ghost sm" href="<?= e(query_with(['q' => '', 'status' => '', 'from' => '', 'to' => '', 'p' => ''])) ?>">Limpar</a>
  <?php endif; ?>
</form>

<?php if (!$list): ?>
  <div class="card empty">Nenhum lead encontrado<?= $total === 0 && !$f['q'] ? '. Quando os formulários do site forem enviados, eles aparecem aqui.' : ' com esses filtros.' ?></div>
<?php else: ?>
<div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Contato</th><th>Origem</th><th>Mensagem</th><th>Status</th><th class="right">Recebido</th></tr></thead>
    <tbody>
    <?php foreach ($list as $l): ?>
      <tr class="<?= $l['status'] === 'novo' ? 'is-new' : '' ?>">
        <td>
          <a class="t-main" href="<?= e(url('lead.php?id=' . $l['id'])) ?>"><?= e($l['name'] ?: '(sem nome)') ?></a>
          <div class="t-sub"><?= e($l['phone']) ?><?= $l['phone'] && $l['email'] ? ' · ' : '' ?><?= e($l['email']) ?></div>
          <?php if ($l['company']): ?><div class="t-sub"><?= e($l['company']) ?></div><?php endif; ?>
        </td>
        <td><span class="src"><?= e(source_label($l['source'])) ?></span></td>
        <td class="t-sub" style="max-width:300px"><?= e(truncate($l['message'] !== '' ? $l['message'] : implode(' · ', array_map('strval', array_slice(json_decode($l['data'], true) ?: [], 0, 3))), 110)) ?></td>
        <td>
          <select class="status-sel" data-lead-status="<?= (int) $l['id'] ?>" data-url="<?= e(url('lead_action.php')) ?>" aria-label="Status do lead">
            <?php foreach (lead_statuses() as $k => [$lab, $c]): ?><option value="<?= e($k) ?>" data-c="<?= e($c) ?>" <?= $l['status'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
          </select>
        </td>
        <td class="right muted nowrap" title="<?= e(fmt_date($l['created_at'])) ?>"><?= e(fmt_date($l['created_at'])) ?><div class="t-sub"><?= e(time_ago($l['created_at'])) ?></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_html($pg) ?>
<p class="muted" style="text-align:center;margin-top:10px"><?= $total ?> lead<?= $total === 1 ? '' : 's' ?></p>
<?php endif;
view_footer();
