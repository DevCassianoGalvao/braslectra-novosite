<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('users');

$id = (int) ($_GET['id'] ?? 0);
$t = $id ? row('SELECT * FROM users WHERE id = ?', [$id]) : null;
if ($id && !$t) {
    flash('err', 'Usuário não encontrado.');
    redirect('users.php');
}
$t = $t ?: ['id' => 0, 'name' => '', 'email' => '', 'role' => 'collab', 'perms' => '{}', 'active' => 1];
$perm = json_decode((string) $t['perms'], true) ?: [];
$errs = [];

if (is_post()) {
    csrf_check();
    $name = mb_substr(post('name'), 0, 100);
    $email = strtolower(post('email'));
    $role = post('role') === 'admin' ? 'admin' : 'collab';
    $pw = (string) ($_POST['password'] ?? '');
    if ($name === '') {
        $errs[] = 'Informe o nome.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errs[] = 'Informe um e-mail válido.';
    } elseif (scalar('SELECT 1 FROM users WHERE email = ? AND id != ?', [$email, $t['id']])) {
        $errs[] = 'Já existe um usuário com esse e-mail.';
    }
    if (!$t['id'] && $pw === '') {
        $errs[] = 'Defina uma senha inicial.';
    }
    if ($pw !== '' && ($pp = password_problem($pw)) !== null) {
        $errs[] = $pp;
    }
    // não rebaixar o último admin / a si mesmo
    if ($t['id'] && $t['role'] === 'admin' && $role !== 'admin' && (int) scalar("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id != ?", [$t['id']]) === 0) {
        $errs[] = 'É preciso manter pelo menos um administrador.';
    }
    if ($t['id'] == $u['id'] && $role !== 'admin') {
        $errs[] = 'Você não pode remover o seu próprio acesso de administrador.';
    }
    $newPerm = $role === 'admin' ? [] : build_perms_from_post();
    if (!$errs) {
        if ($t['id']) {
            q('UPDATE users SET name = ?, email = ?, role = ?, perms = ? WHERE id = ?', [$name, $email, $role, json_encode($newPerm), $t['id']]);
            if ($pw !== '') {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $t['id']]);
            }
            flash('ok', 'Usuário atualizado.');
        } else {
            q('INSERT INTO users (name, email, password_hash, role, perms, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role, json_encode($newPerm), now()]);
            flash('ok', 'Usuário criado. Passe o e-mail e a senha inicial para a pessoa e peça para trocá-la em “Meu perfil”.');
        }
        redirect('users.php');
    }
    $t['name'] = $name;
    $t['email'] = $email;
    $t['role'] = $role;
    $perm = $newPerm;
}

view_header([
    'title'   => $t['id'] ? 'Editar usuário' : 'Novo usuário',
    'active'  => 'users',
    'actions' => '<a class="btn-ghost sm" href="' . e(url('users.php')) . '">← Voltar</a>',
]);
foreach ($errs as $er) {
    echo '<div class="flash flash-err">' . e($er) . '</div>';
}
$lm = $perm['leads']['mode'] ?? 'none';
$ls = (array) ($perm['leads']['sources'] ?? []);
?>
<form method="post" autocomplete="off">
  <?= csrf_field() ?>
  <div class="layout-2">
    <div class="card">
      <h2>Dados de acesso</h2>
      <div class="row" style="margin-top:12px">
        <label class="f"><span class="h">Nome</span><input type="text" name="name" value="<?= e($t['name']) ?>" required></label>
        <label class="f"><span class="h">E-mail (login)</span><input type="email" name="email" value="<?= e($t['email']) ?>" required autocomplete="off"></label>
      </div>
      <label class="f"><span class="h"><?= $t['id'] ? 'Nova senha' : 'Senha inicial' ?></span><input type="password" name="password" autocomplete="new-password" <?= $t['id'] ? '' : 'required' ?>><small><?= $t['id'] ? 'Deixe em branco para manter a senha atual. ' : '' ?>Mínimo de 10 caracteres, com letras e números.</small></label>
      <label class="f"><span class="h">Perfil</span>
        <select name="role" id="role">
          <option value="collab" <?= $t['role'] === 'collab' ? 'selected' : '' ?>>Colaborador (acessos escolhidos ao lado)</option>
          <option value="admin" <?= $t['role'] === 'admin' ? 'selected' : '' ?>>Administrador (vê e faz tudo)</option>
        </select>
      </label>
      <div class="actions"><button class="btn" type="submit">Salvar usuário</button></div>
    </div>

    <div class="card" id="perm-card">
      <h2>O que este colaborador pode acessar</h2>
      <p class="sub">Ignorado se o perfil for Administrador.</p>
      <label class="chk"><input type="checkbox" name="perm_blog" value="1" <?= !empty($perm['blog']) ? 'checked' : '' ?>><span>Blog<small>Criar, editar e excluir artigos.</small></span></label>
      <label class="chk"><input type="checkbox" name="perm_settings" value="1" <?= !empty($perm['settings']) ? 'checked' : '' ?>><span>Configurações do site<small>Telefones, e-mails, endereços e redes sociais.</small></span></label>
      <label class="chk"><input type="checkbox" name="perm_scripts" value="1" <?= !empty($perm['scripts']) ? 'checked' : '' ?>><span>Scripts e rastreamento<small>Perfil de gestor de tráfego: GTM, Pixel, GA4.</small></span></label>
      <hr style="border:0;border-top:1px solid var(--line);margin:12px 0">
      <div style="font-weight:700;margin-bottom:6px">Leads (CRM)</div>
      <label class="chk"><input type="radio" name="leads_mode" value="none" <?= $lm === 'none' ? 'checked' : '' ?>><span>Sem acesso</span></label>
      <label class="chk"><input type="radio" name="leads_mode" value="all" <?= $lm === 'all' ? 'checked' : '' ?>><span>Todos os leads</span></label>
      <label class="chk"><input type="radio" name="leads_mode" value="sources" <?= $lm === 'sources' ? 'checked' : '' ?>><span>Somente de algumas origens</span></label>
      <div id="sources-box" style="margin:4px 0 0 28px;padding:10px 14px;background:#FBF8F3;border-radius:12px;border:1px solid var(--line);display:none">
        <?php foreach (lead_sources(false) as $s): ?>
          <label class="chk" style="padding:5px 0"><input type="checkbox" name="leads_sources[]" value="<?= e($s['slug']) ?>" <?= in_array($s['slug'], $ls, true) ? 'checked' : '' ?>><span><?= e($s['label']) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</form>
<?php view_footer();
