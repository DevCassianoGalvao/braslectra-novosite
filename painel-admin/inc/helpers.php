<?php
declare(strict_types=1);

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Caminho (URL) onde o painel está instalado, sem barra final. */
function admin_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $fixed = cfg('base_path');
    if ($fixed !== null) {
        return $base = rtrim((string) $fixed, '/');
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = str_replace('\\', '/', ADMIN_ROOT);
    $name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '' && stripos($script, $root . '/') === 0) {
        $rel = substr($script, strlen($root));
        if ($rel !== '' && substr($name, -strlen($rel)) === $rel) {
            return $base = rtrim(substr($name, 0, -strlen($rel)), '/');
        }
    }
    return $base = rtrim(dirname($name), '/');
}

function url(string $path = ''): string
{
    return admin_base() . '/' . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

function redirect(string $path, int $code = 302): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)), true, $code);
    exit;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function slugify(string $text, int $max = 80): string
{
    $map = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ];
    $s = strtr(mb_strtolower($text, 'UTF-8'), $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if (strlen($s) > $max) {
        $s = rtrim(substr($s, 0, $max), '-');
    }
    return $s !== '' ? $s : 'item';
}

function digits(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

function wa_link(string $phone, string $text = ''): string
{
    $d = digits($phone);
    if ($d === '') {
        return '';
    }
    if (strlen($d) <= 11) {
        $d = '55' . $d;
    }
    return 'https://wa.me/' . $d . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function truncate(string $s, int $n = 140): string
{
    $s = trim(preg_replace('/\s+/', ' ', strip_tags($s)) ?? '');
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
}

function fmt_date(?string $d, bool $time = true): string
{
    if (!$d) {
        return '—';
    }
    $t = strtotime($d);
    return $t ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $t) : '—';
}

function time_ago(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    if (!$t) {
        return '—';
    }
    $diff = time() - $t;
    if ($diff < 60) {
        return 'agora';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' h';
    }
    if ($diff < 86400 * 7) {
        return floor($diff / 86400) . ' d';
    }
    return fmt_date($d, false);
}

// ---------- Flash / CSRF ----------

function flash(string $type, string $msg): void
{
    start_session();
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}

function flashes(): array
{
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    start_session();
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        if (($_SERVER['HTTP_ACCEPT'] ?? '') && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            json_out(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 419);
        }
        exit('Sessão expirada ou requisição inválida. Volte e recarregue a página.');
    }
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------- Rótulos ----------

function lead_statuses(): array
{
    return [
        'novo'        => ['Novo', '#C8850F'],
        'atendimento' => ['Em atendimento', '#2563EB'],
        'proposta'    => ['Proposta enviada', '#7C3AED'],
        'ganho'       => ['Fechado (ganho)', '#16803C'],
        'perdido'     => ['Perdido', '#8A8377'],
        'spam'        => ['Spam', '#B42318'],
    ];
}

function pagination(int $total, int $page, int $per): array
{
    $pages = max(1, (int) ceil($total / $per));
    $page = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'per' => $per, 'offset' => ($page - 1) * $per, 'total' => $total];
}

function query_with(array $over = []): string
{
    $q = array_merge($_GET, $over);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        }
    }
    return $q ? '?' . http_build_query($q) : '?';
}

function client_ua(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function post_img_src(string $img): string
{
    if ($img === '') {
        return '';
    }
    return preg_match('#^https?://#i', $img) ? $img : url($img);
}
