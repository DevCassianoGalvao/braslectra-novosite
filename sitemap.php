<?php
/**
 * Sitemap dinâmico (servido como /sitemap.xml pelo .htaccess): páginas do site + artigos do blog
 * publicados no painel, com imagens. Se o painel não estiver disponível, usa os artigos importados.
 */
declare(strict_types=1);

require __DIR__ . '/painel-admin/inc/public_seo.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$x = static fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$site = seo_site();
$files = __DIR__ . '/';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

foreach (seo_data()['pages'] as $p) {
    if (!empty($p['nosite'])) {
        continue;
    }
    $file = $files . $p['file'] . '.dc.html';
    $mod = is_file($file) ? date('Y-m-d', (int) filemtime($file)) : date('Y-m-d');
    echo "  <url>\n    <loc>" . $x(seo_page_url($p['slug'])) . "</loc>\n    <lastmod>$mod</lastmod>\n"
        . "    <changefreq>{$p['changefreq']}</changefreq>\n    <priority>{$p['priority']}</priority>\n"
        . "    <image:image><image:loc>" . $x($site . '/midia/og/' . $p['og'] . '.jpg') . "</image:loc></image:image>\n  </url>\n";
}
foreach (seo_posts() as $post) {
    $mod = date('Y-m-d', strtotime($post['updated'] ?: $post['published']) ?: time());
    echo "  <url>\n    <loc>" . $x($site . '/blog-post?slug=' . rawurlencode($post['slug'])) . "</loc>\n    <lastmod>$mod</lastmod>\n"
        . "    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n"
        . ($post['image'] ? "    <image:image><image:loc>" . $x($post['image']) . "</image:loc></image:image>\n" : '')
        . "  </url>\n";
}
echo "</urlset>\n";
