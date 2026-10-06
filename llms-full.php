<?php
/**
 * /llms-full.txt (via .htaccess): o conteúdo do llms.txt + o texto completo de todos os
 * artigos publicados no blog, em Markdown — para assistentes de IA (ChatGPT, Claude, Perplexity, Gemini).
 */
declare(strict_types=1);

require __DIR__ . '/painel-admin/inc/public_seo.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex'); // material para IAs; as páginas do site é que devem aparecer no Google

$base = (string) file_get_contents(__DIR__ . '/llms.txt');
$base = preg_replace('/\n## Opcional.*$/s', "\n", $base);
echo rtrim((string) $base) . "\n\n## Artigos do blog (texto completo)\n";

foreach (seo_posts() as $p) {
    $date = date('d/m/Y', strtotime($p['published']) ?: time());
    echo "\n---\n\n### " . trim(preg_replace('/\s+/u', ' ', $p['title'])) . "\n\n";
    echo 'URL: ' . seo_site() . '/blog-post?slug=' . rawurlencode($p['slug']) . "  \nPublicado em: $date\n\n";
    echo seo_markdown((string) $p['content']) . "\n";
}
