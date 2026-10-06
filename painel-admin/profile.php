<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'name') {
        $name = mb_substr(post('name'), 0, 100);
        if ($name !== '') {
            q('UPDATE users SET name = ? WHERE id = ?', [$name, $u['id']]);
            flash('ok', 'Nome atualizado.');
        }
    }
    if ($action === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $rep = (string) ($_POST['password2'] ?? '');
        if (!password_verify($cur, (string) $u['password_hash'])) {
            flash('err', 'A senha atual está incorreta.');
        } elseif (($p = password_problem($new)) !== null) {
            flash('err', $p);
        } elseif ($new !== $rep) {
            flash('err', 'As senhas novas não conferem.');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('ok', 'Senha alterada com sucesso.');
        }
    }
    redirect('profile.php');
}

view_header(['title' => 'Meu perfil', 'active' => 'profile']);
?>
<div class="grid g2">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="name">
    <h2>Meus dados</h2>
    <label class="f" style="margin-top:12px"><span class="h">Nome</span><input type="text" name="name" value="<?= e($u['name']) ?>" required></label>
    <label class="f"><span class="h">E-mail</span><input type="email" value="<?= e($u['email']) ?>" disabled><small>Para trocar o e-mail, peça a um administrador.</small></label>
    <button class="btn sm" type="submit">Salvar</button>
  </form>
  <form method="post" class="card" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2>Trocar senha</h2>
    <label class="f" style="margin-top:12px"><span class="h">Senha atual</span><input type="password" name="current" required autocomplete="current-password"></label>
    <label class="f"><span class="h">Nova senha</span><input type="password" name="password" required autocomplete="new-password"><small>Mínimo de 10 caracteres, com letras e números.</small></label>
    <label class="f"><span class="h">Repita a nova senha</span><input type="password" name="password2" required autocomplete="new-password"></label>
    <button class="btn sm" type="submit">Alterar senha</button>
  </form>
</div>
<?php view_footer();
