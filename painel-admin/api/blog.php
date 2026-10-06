<?php
/**
 * API pública do blog (somente leitura).
 *   GET api/blog.php                 -> lista paginada  (?page=1&per=9&q=termo)
 *   GET api/blog.php?slug=meu-artigo -> artigo completo
 */
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/public_api.php';

public_cors(['GET', 'OPTIONS']);
header('Cache-Control: public, max-age=60');

function post_public(array $p, bool $full): array
{
    $img = (string) $p['image'];
    $out = [
        'id'              => (int) $p['id'],
        'title'           => $p['title'],
        'slug'            => $p['slug'],
        'excerpt'         => $p['excerpt'],
        'image'           => $img !== '' && !preg_match('#^https?://#i', $img) ? abs_url($img) : $img,
        'published_at'    => $p['published_at'],
        'seo_title'       => $p['seo_title'] ?: $p['title'],
        'seo_description' => $p['seo_description'] ?: $p['excerpt'],
    ];
    if ($full) {
        // imagens enviadas pelo painel são guardadas com caminho relativo; devolve absoluto p/ o site
        $out['content'] = preg_replace_callback('#(src|href)="(uploads/[^"]+)"#', static fn ($m) => $m[1] . '="' . abs_url($m[2]) . '"', (string) $p['content']);
    }
    return $out;
}

$slug = (string) ($_GET['slug'] ?? '');
if ($slug !== '') {
    $p = row("SELECT * FROM posts WHERE slug = ? AND status = 'published' AND (published_at IS NULL OR published_at <= ?)", [$slug, now()]);
    if (!$p) {
        json_out(['ok' => false, 'error' => 'Artigo não encontrado.'], 404);
    }
    $prev = row("SELECT title, slug FROM posts WHERE status = 'published' AND published_at < ? ORDER BY published_at DESC LIMIT 1", [$p['published_at']]);
    $next = row("SELECT title, slug FROM posts WHERE status = 'published' AND published_at > ? AND published_at <= ? ORDER BY published_at ASC LIMIT 1", [$p['published_at'], now()]);
    json_out(['ok' => true, 'post' => post_public($p, true), 'prev' => $prev, 'next' => $next]);
}

$per = max(1, min(50, (int) ($_GET['per'] ?? 9)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = "status = 'published' AND (published_at IS NULL OR published_at <= ?)";
$params = [now()];
$term = trim((string) ($_GET['q'] ?? ''));
if ($term !== '') {
    $where .= ' AND (title LIKE ? OR excerpt LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
    array_push($params, $like, $like);
    $where = str_replace('(title LIKE ? OR excerpt LIKE ?)', "(title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\')", $where);
}
$total = (int) scalar("SELECT COUNT(*) FROM posts WHERE $where", $params);
$pg = pagination($total, $page, $per);
$list = rows("SELECT * FROM posts WHERE $where ORDER BY published_at DESC, id DESC LIMIT $per OFFSET {$pg['offset']}", $params);
json_out([
    'ok'    => true,
    'total' => $total,
    'page'  => $pg['page'],
    'pages' => $pg['pages'],
    'posts' => array_map(static fn ($p) => post_public($p, false), $list),
]);
