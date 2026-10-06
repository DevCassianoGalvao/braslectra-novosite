<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name((string) cfg('session_name', 'braslectra_admin'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => admin_base() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_start();
}

function users_count(): int
{
    return (int) scalar('SELECT COUNT(*) FROM users');
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    start_session();
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id <= 0) {
        return $user = null;
    }
    $u = row('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
    if (!$u) {
        unset($_SESSION['uid']);
        return $user = null;
    }
    $u['perm'] = json_decode((string) $u['perms'], true) ?: [];
    return $user = $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (users_count() === 0) {
            redirect('install.php');
        }
        redirect('login.php?next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '')));
    }
    return $u;
}

function is_admin(?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u !== null && $u['role'] === 'admin';
}

/**
 * Permissões: 'blog', 'scripts', 'settings', 'leads' (qualquer acesso a leads), 'users' (somente admin).
 */
function can(string $perm, ?array $u = null): bool
{
    $u = $u ?? current_user();
    if (!$u) {
        return false;
    }
    if ($u['role'] === 'admin') {
        return true;
    }
    if ($perm === 'users') {
        return false;
    }
    $p = $u['perm'] ?? [];
    if ($perm === 'leads') {
        $mode = $p['leads']['mode'] ?? 'none';
        return $mode === 'all' || ($mode === 'sources' && !empty($p['leads']['sources']));
    }
    return !empty($p[$perm]);
}

function require_perm(string $perm): array
{
    $u = require_login();
    if (!can($perm, $u)) {
        http_response_code(403);
        view_header(['title' => 'Sem permissão', 'active' => '']);
        echo '<div class="card"><h2>Sem permissão</h2><p>Você não tem acesso a esta área. Peça ao administrador para liberar.</p></div>';
        view_footer();
        exit;
    }
    return $u;
}

/** Fontes de lead que o usuário pode ver. null = todas. */
function allowed_sources(?array $u = null): ?array
{
    $u = $u ?? current_user();
    if (!$u) {
        return [];
    }
    if ($u['role'] === 'admin') {
        return null;
    }
    $l = $u['perm']['leads'] ?? [];
    $mode = $l['mode'] ?? 'none';
    if ($mode === 'all') {
        return null;
    }
    if ($mode === 'sources') {
        return array_values(array_filter((array) ($l['sources'] ?? []), 'is_string'));
    }
    return [];
}

/** Gera cláusula SQL restringindo leads às fontes permitidas. */
function lead_scope_sql(string $col = 'source'): array
{
    $allowed = allowed_sources();
    if ($allowed === null) {
        return ['1=1', []];
    }
    if (!$allowed) {
        return ['1=0', []];
    }
    $ph = implode(',', array_fill(0, count($allowed), '?'));
    return ["$col IN ($ph)", $allowed];
}

function can_see_lead(array $lead): bool
{
    $allowed = allowed_sources();
    return $allowed === null || in_array($lead['source'], $allowed, true);
}

function login_attempt_count(string $bucket): int
{
    return (int) scalar('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND ts > ?', [$bucket, time() - 900]);
}

function attempt_login(string $email, string $password): array
{
    $ip = client_ip();
    $b1 = 'login:ip:' . $ip;
    $b2 = 'login:em:' . strtolower($email);
    if (login_attempt_count($b1) >= 10 || login_attempt_count($b2) >= 5) {
        return [false, 'Muitas tentativas. Aguarde 15 minutos e tente novamente.'];
    }
    $u = row('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
    // Verificação em tempo constante mesmo se o usuário não existir
    $hash = $u['password_hash'] ?? password_hash('x', PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash) && $u;
    if (!$ok) {
        q('INSERT INTO rate_limits (bucket, ts) VALUES (?, ?)', [$b1, time()]);
        q('INSERT INTO rate_limits (bucket, ts) VALUES (?, ?)', [$b2, time()]);
        return [false, 'E-mail ou senha incorretos.'];
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    unset($_SESSION['csrf']);
    q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
    q('DELETE FROM rate_limits WHERE bucket IN (?, ?)', [$b1, $b2]);
    return [true, ''];
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], true);
    }
    session_destroy();
}

function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 10) {
        return 'A senha precisa ter pelo menos 10 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Use letras e números na senha.';
    }
    return null;
}

function build_perms_from_post(): array
{
    $perm = [];
    foreach (['blog', 'scripts', 'settings'] as $k) {
        $perm[$k] = !empty($_POST['perm_' . $k]);
    }
    $mode = post('leads_mode', 'none');
    if (!in_array($mode, ['none', 'all', 'sources'], true)) {
        $mode = 'none';
    }
    $sources = [];
    if ($mode === 'sources') {
        $valid = array_column(lead_sources(false), 'slug');
        foreach ((array) ($_POST['leads_sources'] ?? []) as $s) {
            if (is_string($s) && in_array($s, $valid, true)) {
                $sources[] = $s;
            }
        }
        if (!$sources) {
            $mode = 'none';
        }
    }
    $perm['leads'] = ['mode' => $mode, 'sources' => $sources];
    return $perm;
}
