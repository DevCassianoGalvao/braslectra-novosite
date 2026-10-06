<?php
/**
 * /blog renderizado no servidor (SEO/GEO): a página Blog.dc.html + a lista de todos os artigos
 * publicados em HTML puro e em JSON-LD (ItemList), para robôs que não executam JavaScript.
 */
declare(strict_types=1);

$template = (string) file_get_contents(__DIR__ . '/Blog.dc.html');
header('Content-Type: text/html; charset=utf-8');
try {
    require __DIR__ . '/painel-admin/inc/public_seo.php';
    $posts = seo_posts();
} catch (Throwable $e) {
    echo $template;
    return;
}

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = [];
$list = '';
foreach ($posts as $i => $p) {
    $url = seo_site() . '/blog-post?slug=' . rawurlencode($p['slug']);
    $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $url, 'name' => $p['title']];
    $list .= '<li><a href="blog-post?slug=' . $h(rawurlencode($p['slug'])) . '">' . $h($p['title']) . '</a>'
        . ($p['excerpt'] ? ' — ' . $h(mb_substr(trim($p['excerpt']), 0, 200)) : '') . '</li>';
}
$ld = seo_json_ld(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Artigos do blog do Grupo Braslectra', 'numberOfItems' => count($items), 'itemListElement' => $items]);
$noscript = '<noscript><section style="max-width:900px;margin:40px auto;padding:0 20px;font-family:sans-serif;line-height:1.7">'
    . '<h1>Blog do Grupo Braslectra</h1><ul>' . $list . '</ul></section></noscript>';

$html = str_replace('<!-- /SEO -->', $ld . "\n<!-- /SEO -->", $template);
$html = preg_replace('/<body>/', '<body>' . str_replace(['\\', '$'], ['\\\\', '\\$'], $noscript), $html, 1);
echo $html;
