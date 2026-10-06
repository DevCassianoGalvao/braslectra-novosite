<?php
require __DIR__ . '/inc/bootstrap.php';
start_session();

if (users_count() === 0) {
    redirect('install.php');
}
if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$email = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));
    [$ok, $msg] = attempt_login($email, (string) ($_POST['password'] ?? ''));
    if ($ok) {
        $next = (string) ($_GET['next'] ?? '');
        // só aceita redirecionamento interno ao painel
        if ($next !== '' && strpos($next, admin_base() . '/') === 0 && strpos($next, '//') !== 0 && strpos($next, 'login.php') === false) {
            redirect($next);
        }
        redirect('dashboard.php');
    }
    $error = $msg;
}

view_header(['title' => 'Entrar']);
?>
<div class="login">
  <div class="brand"><span class="brand-mark">B</span><span class="brand-txt"><b>Braslectra</b><small>Painel de gestão</small></span></div>
  <h2>Acessar o painel</h2>
  <p class="sub">Entre com seu e-mail e senha.</p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label class="f"><span class="h">E-mail</span><input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
    <label class="f"><span class="h">Senha</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Entrar</button>
  </form>
</div>
<?php view_footer();
