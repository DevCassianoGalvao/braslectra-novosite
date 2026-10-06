<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/blog_import.php';
start_session();

if (users_count() > 0) {
    redirect('login.php');
}

$err = '';
$vals = ['name' => '', 'email' => ''];
$seed = __DIR__ . '/tools/blog_seed.json';
$hasSeed = is_file($seed);

if (is_post()) {
    csrf_check();
    $vals['name'] = post('name');
    $vals['email'] = strtolower(post('email'));
    $pw = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password2'] ?? '');
    if ($vals['name'] === '' || !filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) {
        $err = 'Informe nome e um e-mail válido.';
    } elseif (($p = password_problem($pw)) !== null) {
        $err = $p;
    } elseif ($pw !== $pw2) {
        $err = 'As senhas não conferem.';
    } else {
        q(
            'INSERT INTO users (name, email, password_hash, role, perms, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$vals['name'], $vals['email'], password_hash($pw, PASSWORD_DEFAULT), 'admin', '{}', now()]
        );
        $adminId = insert_id();
        $msg = 'Administrador criado. Entre com seu e-mail e senha.';
        if ($hasSeed && !empty($_POST['import_blog'])) {
            $r = import_blog_seed($seed, $adminId);
            $msg .= ' ' . $r['imported'] . ' artigos do site antigo importados para o blog.';
        }
        flash('ok', $msg);
        redirect('login.php');
    }
}

view_header(['title' => 'Instalação']);
?>
<div class="login">
  <div class="brand"><span class="brand-mark">B</span><span class="brand-txt"><b>Braslectra</b><small>Instalação do painel</small></span></div>
  <h2>Criar administrador</h2>
  <p class="sub">Primeiro acesso: cadastre o usuário administrador principal.</p>
  <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label class="f"><span class="h">Nome</span><input type="text" name="name" value="<?= e($vals['name']) ?>" required></label>
    <label class="f"><span class="h">E-mail</span><input type="email" name="email" value="<?= e($vals['email']) ?>" required></label>
    <label class="f"><span class="h">Senha</span><input type="password" name="password" required autocomplete="new-password"><small>Mínimo de 10 caracteres, com letras e números.</small></label>
    <label class="f"><span class="h">Repita a senha</span><input type="password" name="password2" required autocomplete="new-password"></label>
    <?php if ($hasSeed): ?>
      <label class="chk"><input type="checkbox" name="import_blog" value="1" checked><span>Importar os artigos do site antigo para o blog<small>Os artigos ficam editáveis no painel.</small></span></label>
    <?php endif; ?>
    <button class="btn" type="submit" style="margin-top:10px">Criar e continuar</button>
  </form>
</div>
<?php view_footer();
