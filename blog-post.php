<?php
/**
 * Artigo do blog renderizado no servidor (SEO/GEO): mesmo layout de Blog-Post.dc.html,
 * mas com título, descrição, canonical, Open Graph, JSON-LD (BlogPosting) e o texto
 * completo do artigo já no HTML — legível por Google e por robôs de IA que não executam JavaScript.
 */
declare(strict_types=1);

$template = (string) file_get_contents(__DIR__ . '/Blog-Post.dc.html');
$slug = (string) ($_GET['slug'] ?? '');
header('Content-Type: text/html; charset=utf-8');

try {
    require __DIR__ . '/painel-admin/inc/public_seo.php';
    $post = $slug !== '' ? seo_post($slug) : null;
} catch (Throwable $e) {
    echo $template; // painel indisponível: entrega a página normal (o JavaScript carrega o artigo)
    return;
}

if (!$post) {
    if ($slug !== '') {
        http_response_code(404);
        $template = str_replace('<meta name="robots" content="index, follow', '<meta name="robots" content="noindex, follow', $template);
    }
    echo $template;
    return;
}

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$url = seo_site() . '/blog-post?slug=' . rawurlencode($post['slug']);
$title = ($post['seo_title'] ?: $post['title']) . ' | Grupo Braslectra';
$desc = $post['seo_description'] ?: ($post['excerpt'] ?: mb_substr(seo_text($post['content']), 0, 160));
$desc = trim(preg_replace('/\s+/u', ' ', $desc));
$img = $post['image'] ?: seo_site() . '/midia/og/home.jpg';
$org = seo_site() . '/#organizacao';

$ld = [
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'Organization', '@id' => $org, 'name' => 'Grupo Braslectra', 'url' => seo_site() . '/', 'logo' => seo_site() . '/midia/logo-grupo-braslectra.png'],
        ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => seo_site() . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => seo_site() . '/blog'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $url],
        ]],
        ['@type' => 'BlogPosting', '@id' => $url . '#artigo', 'mainEntityOfPage' => $url, 'headline' => mb_substr($post['title'], 0, 110),
         'description' => $desc, 'image' => [$img], 'datePublished' => seo_iso($post['published']), 'dateModified' => seo_iso($post['updated'] ?: $post['published']),
         'inLanguage' => 'pt-BR', 'author' => ['@id' => $org], 'publisher' => ['@id' => $org], 'isPartOf' => ['@type' => 'Blog', 'name' => 'Blog do Grupo Braslectra', 'url' => seo_site() . '/blog'],
         'articleBody' => mb_substr(seo_text($post['content']), 0, 20000)],
    ],
];

$seo = "<!-- SEO -->\n"
    . '<title>' . $h($title) . "</title>\n"
    . '<meta name="description" content="' . $h($desc) . "\">\n"
    . '<link rel="canonical" href="' . $h($url) . "\">\n"
    . "<meta name=\"robots\" content=\"index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1\">\n"
    . "<meta name=\"author\" content=\"Grupo Braslectra\">\n<meta name=\"theme-color\" content=\"#C8850F\">\n"
    . "<meta property=\"og:type\" content=\"article\">\n<meta property=\"og:locale\" content=\"pt_BR\">\n<meta property=\"og:site_name\" content=\"Grupo Braslectra\">\n"
    . '<meta property="og:title" content="' . $h($post['title']) . "\">\n"
    . '<meta property="og:description" content="' . $h($desc) . "\">\n"
    . '<meta property="og:url" content="' . $h($url) . "\">\n"
    . '<meta property="og:image" content="' . $h($img) . "\">\n"
    . '<meta property="article:published_time" content="' . $h(seo_iso($post['published'])) . "\">\n"
    . '<meta property="article:modified_time" content="' . $h(seo_iso($post['updated'] ?: $post['published'])) . "\">\n"
    . "<meta name=\"twitter:card\" content=\"summary_large_image\">\n"
    . '<meta name="twitter:title" content="' . $h($post['title']) . "\">\n"
    . '<meta name="twitter:description" content="' . $h($desc) . "\">\n"
    . '<meta name="twitter:image" content="' . $h($img) . "\">\n"
    . "<link rel=\"icon\" href=\"favicon.ico\" sizes=\"any\">\n<link rel=\"apple-touch-icon\" href=\"apple-touch-icon.png\">\n<link rel=\"manifest\" href=\"site.webmanifest\">\n"
    . seo_json_ld($ld) . "\n<!-- /SEO -->\n";

$html = preg_replace('/<!-- SEO -->.*?<!-- \/SEO -->\n?/s', str_replace(['\\', '$'], ['\\\\', '\\$'], $seo), $template, 1);

// conteúdo do artigo em HTML puro, para quem não executa JavaScript
$body = preg_replace('#<script\b.*?</script>#is', '', (string) $post['content']);
$body = preg_replace_callback('#(src|href)="(uploads/[^"]+)"#', static fn ($m) => $m[1] . '="' . seo_post_image($m[2]) . '"', (string) $body);
$noscript = '<noscript><article style="max-width:780px;margin:40px auto;padding:0 20px;font-family:sans-serif;line-height:1.7">'
    . '<p><a href="blog">← Blog do Grupo Braslectra</a></p>'
    . '<h1>' . $h($post['title']) . '</h1>'
    . '<p><time datetime="' . $h(seo_iso($post['published'])) . '">' . $h(date('d/m/Y', strtotime($post['published']) ?: time())) . '</time></p>'
    . ($post['image'] ? '<img src="' . $h($post['image']) . '" alt="' . $h($post['title']) . '" style="max-width:100%;height:auto">' : '')
    . $body . '</article></noscript>';
$html = preg_replace('/<body>/', '<body>' . str_replace(['\\', '$'], ['\\\\', '\\$'], $noscript), (string) $html, 1);

echo $html;
