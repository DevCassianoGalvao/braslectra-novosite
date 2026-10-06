<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('users');

if (is_post() && post('action') === 'toggle') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $t = row('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$t) {
        flash('err', 'Usuário não encontrado.');
    } elseif ($t['id'] == $u['id']) {
        flash('err', 'Você não pode desativar o seu próprio usuário.');
    } else {
        $new = $t['active'] ? 0 : 1;
        if (!$new && $t['role'] === 'admin' && (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id != ?", [$id]) === 0) {
            flash('err', 'É preciso manter pelo menos um administrador ativo.');
        } else {
            q('UPDATE users SET active = ? WHERE id = ?', [$new, $id]);
            flash('ok', $new ? 'Usuário reativado.' : 'Usuário desativado (não consegue mais entrar).');
        }
    }
    redirect('users.php');
}

$users = rows('SELECT * FROM users ORDER BY role = \'admin\' DESC, name');
view_header([
    'title'   => 'Usuários',
    'active'  => 'users',
    'actions' => '<a class="btn sm" href="' . e(url('user_edit.php')) . '">' . icon('plus') . ' Novo usuário</a>',
]);

function perm_summary(array $x): string
{
    if ($x['role'] === 'admin') {
        return '<span class="badge" style="--c:#C8850F">Acesso total</span>';
    }
    $p = json_decode((string) $x['perms'], true) ?: [];
    $out = [];
    $l = $p['leads'] ?? [];
    if (($l['mode'] ?? 'none') === 'all') {
        $out[] = 'Todos os leads';
    } elseif (($l['mode'] ?? '') === 'sources' && !empty($l['sources'])) {
        $out[] = 'Leads: ' . implode(', ', array_map('source_label', $l['sources']));
    }
    foreach (['blog' => 'Blog', 'settings' => 'Configurações', 'scripts' => 'Scripts'] as $k => $lab) {
        if (!empty($p[$k])) {
            $out[] = $lab;
        }
    }
    return $out ? implode(' · ', array_map('e', $out)) : '<span class="muted">Sem acesso liberado</span>';
}
?>
<div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Usuário</th><th>Perfil</th><th>Acessos</th><th>Último acesso</th><th class="right">Ações</th></tr></thead>
    <tbody>
    <?php foreach ($users as $x): ?>
      <tr style="<?= $x['active'] ? '' : 'opacity:.55' ?>">
        <td><div class="cell-flex"><span class="av"><?= e(mb_strtoupper(mb_substr($x['name'], 0, 1))) ?></span><div><a class="t-main" href="<?= e(url('user_edit.php?id=' . $x['id'])) ?>"><?= e($x['name']) ?></a><div class="t-sub"><?= e($x['email']) ?></div></div></div></td>
        <td><?= $x['role'] === 'admin' ? 'Administrador' : 'Colaborador' ?><?= $x['active'] ? '' : ' <span class="badge" style="--c:#8A8377">Inativo</span>' ?></td>
        <td><?= perm_summary($x) ?></td>
        <td class="muted nowrap"><?= e($x['last_login'] ? fmt_date($x['last_login']) : 'Nunca') ?></td>
        <td class="right nowrap">
          <a class="btn-ghost sm" href="<?= e(url('user_edit.php?id=' . $x['id'])) ?>">Editar</a>
          <?php if ($x['id'] != $u['id']): ?>
          <form method="post" style="display:inline" data-confirm="<?= $x['active'] ? 'Desativar este usuário? Ele perde o acesso imediatamente.' : 'Reativar este usuário?' ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
            <button class="btn-ghost sm" type="submit"><?= $x['active'] ? 'Desativar' : 'Reativar' ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php view_footer();
