<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('fleet');

$cats = fleet_categories();
$cat = (string) ($_GET['cat'] ?? '');
if (!isset($cats[$cat])) {
    $cat = '';
}

if (is_post()) {
    csrf_check();
    $act = post('action');
    $id = (int) ($_POST['id'] ?? 0);
    $v = $id ? row('SELECT * FROM vehicles WHERE id = ?', [$id]) : null;
    $back = 'fleet.php' . ($cat !== '' ? '?cat=' . $cat : '');
    if ($v && $act === 'delete') {
        fleet_delete_file((string) $v['image']);
        foreach (json_decode((string) $v['gallery'], true) ?: [] as $g) {
            fleet_delete_file((string) $g);
        }
        q('DELETE FROM vehicles WHERE id = ?', [$id]);
        flash('ok', 'Veículo removido: ' . $v['name'] . '.');
    } elseif ($v && $act === 'toggle') {
        q('UPDATE vehicles SET active = ?, updated_at = ? WHERE id = ?', [$v['active'] ? 0 : 1, now(), $id]);
        flash('ok', $v['active'] ? '“' . $v['name'] . '” ficou oculto no site.' : '“' . $v['name'] . '” voltou a aparecer no site.');
    } elseif ($v && in_array($act, ['up', 'down'], true)) {
        $list = rows('SELECT id FROM vehicles WHERE category = ? AND grp = ? ORDER BY sort_order, id', [$v['category'], $v['grp']]);
        $ids = array_map('intval', array_column($list, 'id'));
        $i = array_search($id, $ids, true);
        $j = $act === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        foreach ($ids as $n => $vid) {
            q('UPDATE vehicles SET sort_order = ? WHERE id = ?', [($n + 1) * 10, $vid]);
        }
    }
    redirect($back);
}

view_header([
    'title'   => 'Frota',
    'active'  => 'fleet',
    'actions' => '<a class="btn sm" href="' . e(url('fleet_edit.php' . ($cat !== '' ? '?cat=' . $cat : ''))) . '">' . icon('plus') . ' Novo veículo</a>',
]);

$counts = [];
foreach (rows('SELECT category, COUNT(*) n FROM vehicles GROUP BY category') as $r) {
    $counts[$r['category']] = (int) $r['n'];
}
?>
<div class="chips">
  <a class="chip <?= $cat === '' ? 'on' : '' ?>" href="<?= e(url('fleet.php')) ?>">Toda a frota <em><?= array_sum($counts) ?></em></a>
  <?php foreach ($cats as $k => $c): ?>
    <a class="chip <?= $cat === $k ? 'on' : '' ?>" href="<?= e(url('fleet.php?cat=' . $k)) ?>"><?= e($c['label']) ?> <em><?= $counts[$k] ?? 0 ?></em></a>
  <?php endforeach; ?>
</div>
<div class="hint">As alterações aparecem no site na hora. Use <b>Ocultar</b> para tirar um veículo da página sem apagar, e as setas para mudar a ordem em que eles aparecem.</div>

<?php
$any = false;
foreach ($cats as $k => $c) {
    if ($cat !== '' && $cat !== $k) {
        continue;
    }
    foreach ($c['groups'] as $g => $gLabel) {
        $list = rows('SELECT * FROM vehicles WHERE category = ? AND grp = ? ORDER BY sort_order, id', [$k, $g]);
        if (!$list && $g !== '' && $g !== array_key_first($c['groups'])) {
            continue;
        }
        if (!$list && $cat === '') {
            continue;
        }
        $any = true;
        ?>
<h2 style="font-size:1.05rem;margin:22px 0 10px"><?= e($c['label']) ?><?= $gLabel !== '' ? ' · ' . e($gLabel) : '' ?> <small style="font-weight:500;color:var(--mute)">(<?= count($list) ?>)</small></h2>
        <?php if (!$list): ?>
<div class="card empty">Nenhum veículo neste grupo ainda.</div>
        <?php else: ?>
<div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Veículo</th><th>Informações</th><th>Fotos</th><th>No site</th><th class="right">Ações</th></tr></thead>
    <tbody>
    <?php foreach ($list as $i => $v): $specs = json_decode((string) $v['specs'], true) ?: []; $ng = count(json_decode((string) $v['gallery'], true) ?: []); ?>
      <tr style="<?= $v['active'] ? '' : 'opacity:.55' ?>">
        <td>
          <div class="cell-flex">
            <?php if ($v['image']): ?><img class="thumb-sm" style="object-fit:contain;width:84px" src="<?= e(fleet_img_url($v['image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb-sm"></span><?php endif; ?>
            <div><a class="t-main" href="<?= e(url('fleet_edit.php?id=' . $v['id'])) ?>"><?= e($v['name']) ?></a><div class="t-sub"><?= e($v['year']) ?></div></div>
          </div>
        </td>
        <td class="muted"><?= e(implode(' · ', $specs)) ?: '—' ?></td>
        <td class="muted nowrap"><?= $ng ?> foto<?= $ng === 1 ? '' : 's' ?></td>
        <td><?= $v['active'] ? '<span class="badge" style="--c:#16803C">Visível</span>' : '<span class="badge" style="--c:#8A8377">Oculto</span>' ?></td>
        <td class="right nowrap">
          <?php foreach ([['up', '↑', 'Subir', $i === 0], ['down', '↓', 'Descer', $i === count($list) - 1]] as [$a, $sym, $tt, $dis]): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><button class="btn-ghost sm" type="submit" title="<?= $tt ?>" aria-label="<?= $tt ?>" <?= $dis ? 'disabled style="opacity:.3"' : '' ?>><?= $sym ?></button></form>
          <?php endforeach; ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><button class="btn-ghost sm" type="submit"><?= $v['active'] ? 'Ocultar' : 'Mostrar' ?></button></form>
          <a class="btn-ghost sm" href="<?= e(url('fleet_edit.php?id=' . $v['id'])) ?>">Editar</a>
          <form method="post" style="display:inline" data-confirm="Remover “<?= e($v['name'] . ' ' . $v['year']) ?>” da frota? Esta ação não pode ser desfeita. (Para só tirar da página, use Ocultar.)">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <button class="btn-danger sm" type="submit" aria-label="Remover"><?= icon('trash') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
        <?php endif;
    }
}
if (!$any): ?>
<div class="card empty">Nenhum veículo cadastrado.</div>
<?php endif; ?>
<div class="hint" style="margin-top:20px">O site lê a frota pela API pública <code><?= e(abs_url('api/fleet.php')) ?></code> (use <code>?category=vans</code> para filtrar).</div>
<?php view_footer();
