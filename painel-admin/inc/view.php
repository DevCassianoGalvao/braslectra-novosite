<?php
declare(strict_types=1);

function icon(string $name): string
{
    static $i = [
        'home'     => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'leads'    => '<path d="M3 5h18v12H8l-5 4V5z"/><path d="M8 10h8M8 13h5"/>',
        'blog'     => '<path d="M5 3h14v18H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z"/>',
        'scripts'  => '<path d="M8 8l-5 4 5 4M16 8l5 4-5 4M14 5l-4 14"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5S15 16.4 15.6 20"/><path d="M16 5a3.2 3.2 0 0 1 0 6M18 14.8c2 .6 3.2 2.3 3.5 5.2"/>',
        'logout'   => '<path d="M9 4H4v16h5M16 8l4 4-4 4M20 12H9"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4-4"/>',
        'download' => '<path d="M12 3v12M7 11l5 5 5-5M4 20h16"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
        'trash'    => '<path d="M4 7h16M10 3h4M6 7l1 14h10l1-14M10 11v6M14 11v6"/>',
        'check'    => '<path d="M5 12l5 5 9-10"/>',
        'whatsapp' => '<path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 18.8L4 20z"/><path d="M9 9c.3 2.2 2.8 4.7 5 5l1.3-1.2-1.8-1-.8.7c-.8-.3-1.6-1.2-2-2l.7-.8-1-1.8L9 9z"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M4 7l8 6 8-6"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'key'      => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($i[$name] ?? '') . '</svg>';
}

function view_header(array $o = []): void
{
    send_security_headers();
    $u = current_user();
    $title = $o['title'] ?? 'Painel';
    $active = $o['active'] ?? '';
    $newLeads = 0;
    if ($u && can('leads', $u)) {
        [$w, $p] = lead_scope_sql();
        $newLeads = (int) scalar("SELECT COUNT(*) FROM leads WHERE status = 'novo' AND $w", $p);
    }
    $nav = [
        ['dashboard', 'Visão geral', 'dashboard.php', 'home', true],
        ['leads', 'Leads', 'leads.php', 'leads', $u && can('leads', $u)],
        ['blog', 'Blog', 'blog.php', 'blog', $u && can('blog', $u)],
        ['settings', 'Configurações', 'settings.php', 'settings', $u && can('settings', $u)],
        ['scripts', 'Scripts e rastreamento', 'scripts.php', 'scripts', $u && can('scripts', $u)],
        ['users', 'Usuários', 'users.php', 'users', $u && can('users', $u)],
    ];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · Painel Braslectra</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>?v=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body class="app<?= $u ? '' : ' app-guest' ?>">
<?php if ($u): ?>
<div class="shell">
  <aside class="side" id="side">
    <a class="brand" href="<?= e(url('dashboard.php')) ?>">
      <span class="brand-mark">B</span>
      <span class="brand-txt"><b>Braslectra</b><small>Painel de gestão</small></span>
    </a>
    <nav class="nav">
      <?php foreach ($nav as [$key, $label, $href, $ic, $show]): if (!$show) continue; ?>
        <a href="<?= e(url($href)) ?>" class="<?= $active === $key ? 'on' : '' ?>">
          <?= icon($ic) ?><span><?= e($label) ?></span>
          <?php if ($key === 'leads' && $newLeads > 0): ?><em class="pill-n"><?= $newLeads > 99 ? '99+' : $newLeads ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= e(url('profile.php')) ?>" class="me <?= $active === 'profile' ? 'on' : '' ?>">
        <span class="av"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
        <span class="me-t"><b><?= e($u['name']) ?></b><small><?= $u['role'] === 'admin' ? 'Administrador' : 'Colaborador' ?></small></span>
      </a>
      <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn-ghost sm" type="submit"><?= icon('logout') ?> Sair</button></form>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="burger" id="burger" type="button" aria-label="Menu"><?= icon('menu') ?></button>
      <h1><?= e($title) ?></h1>
      <div class="top-actions"><?= $o['actions'] ?? '' ?></div>
    </header>
    <main class="content">
<?php else: ?>
<main class="guest">
<?php endif;
    foreach (flashes() as $f) {
        echo '<div class="flash flash-' . e($f['t']) . '" role="status">' . e($f['m']) . '</div>';
    }
}

function view_footer(): void
{
    $u = current_user();
    if ($u) {
        echo "</main></div></div>\n";
    } else {
        echo "</main>\n";
    }
    echo '<script src="' . e(url('assets/admin.js')) . '?v=1"></script>' . "\n";
    foreach ($GLOBALS['extra_scripts'] ?? [] as $s) {
        echo '<script src="' . e(url($s)) . '?v=1"></script>' . "\n";
    }
    echo "</body></html>";
}

function status_badge(string $status): string
{
    $s = lead_statuses()[$status] ?? [$status, '#8A8377'];
    return '<span class="badge" style="--c:' . e($s[1]) . '">' . e($s[0]) . '</span>';
}

function pager_html(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $h = '<nav class="pager" aria-label="Paginação">';
    $link = static fn (int $p, string $txt, bool $on = false, bool $dis = false) => $dis
        ? '<span class="dis">' . $txt . '</span>'
        : '<a class="' . ($on ? 'on' : '') . '" href="' . e(query_with(['p' => $p === 1 ? '' : $p])) . '">' . $txt . '</a>';
    $h .= $link($pg['page'] - 1, '‹', false, $pg['page'] <= 1);
    $start = max(1, $pg['page'] - 2);
    $end = min($pg['pages'], $pg['page'] + 2);
    if ($start > 1) {
        $h .= $link(1, '1') . ($start > 2 ? '<span class="dis">…</span>' : '');
    }
    for ($i = $start; $i <= $end; $i++) {
        $h .= $link($i, (string) $i, $i === $pg['page']);
    }
    if ($end < $pg['pages']) {
        $h .= ($end < $pg['pages'] - 1 ? '<span class="dis">…</span>' : '') . $link($pg['pages'], (string) $pg['pages']);
    }
    $h .= $link($pg['page'] + 1, '›', false, $pg['page'] >= $pg['pages']);
    return $h . '</nav>';
}
