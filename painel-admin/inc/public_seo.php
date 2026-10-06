<?php
/**
 * Funções de SEO/GEO usadas pelos arquivos públicos da raiz do site
 * (blog-post.php, sitemap.php, llms-full.php). Nunca exige login e nunca cria o banco.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function seo_data(): array
{
    static $d = null;
    return $d ??= json_decode((string) file_get_contents(__DIR__ . '/seo_pages.json'), true);
}

/** URL pública canônica do site (sem barra no fim). */
function seo_site(): string
{
    $u = trim((string) cfg('site_url', ''));
    return rtrim($u !== '' ? $u : seo_data()['site'], '/');
}

function seo_page_url(string $slug): string
{
    return seo_site() . '/' . $slug;
}

/** Imagem de post (caminho relativo ao painel) -> URL absoluta. */
function seo_post_image(string $img): string
{
    if ($img === '' || preg_match('#^https?://#i', $img)) {
        return $img;
    }
    return seo_site() . '/painel-admin/' . ltrim($img, '/');
}

/** Artigos publicados: do banco do painel, ou do arquivo de importação se o painel ainda não foi instalado. */
function seo_posts(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $out = [];
    try {
        if (is_file((string) cfg('db_path'))) {
            $list = rows("SELECT * FROM posts WHERE status = 'published' AND (published_at IS NULL OR published_at <= ?) ORDER BY published_at DESC, id DESC", [now()]);
            foreach ($list as $p) {
                $out[] = [
                    'title' => $p['title'], 'slug' => $p['slug'], 'excerpt' => $p['excerpt'], 'content' => $p['content'],
                    'image' => seo_post_image((string) $p['image']), 'published' => $p['published_at'] ?: $p['created_at'],
                    'updated' => $p['updated_at'], 'seo_title' => $p['seo_title'], 'seo_description' => $p['seo_description'],
                ];
            }
        }
    } catch (Throwable $e) {
        $out = [];
    }
    if (!$out) {
        $seed = json_decode((string) @file_get_contents(dirname(__DIR__) . '/tools/blog_seed.json'), true) ?: [];
        foreach (($seed['posts'] ?? $seed) as $p) {
            $out[] = [
                'title' => $p['title'], 'slug' => $p['slug'], 'excerpt' => $p['excerpt'] ?? '', 'content' => $p['content'] ?? '',
                'image' => seo_post_image((string) ($p['image'] ?? '')), 'published' => $p['date'] ?? '', 'updated' => $p['date'] ?? '',
                'seo_title' => $p['seo_title'] ?? '', 'seo_description' => $p['seo_description'] ?? '',
            ];
        }
    }
    return $cache = $out;
}

function seo_post(string $slug): ?array
{
    foreach (seo_posts() as $p) {
        if ($p['slug'] === $slug) {
            return $p;
        }
    }
    return null;
}

/** Data ISO 8601 com fuso de Brasília. */
function seo_iso(string $d): string
{
    $t = strtotime($d);
    return $t ? date('c', $t) : date('c');
}

/** HTML do artigo -> texto puro. */
function seo_text(string $html): string
{
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    $html = preg_replace('#<(br|/p|/h[1-6]|/li|/div)\s*/?>#i', "$0\n", (string) $html);
    $t = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/[ \t]+/u", ' ', preg_replace("/\n\s*\n+/u", "\n\n", $t)));
}

/** HTML do artigo -> Markdown simples (para llms-full.txt). */
function seo_markdown(string $html): string
{
    $h = preg_replace('/<!--.*?-->/s', '', $html);
    $h = preg_replace_callback('#<h([1-6])[^>]*>(.*?)</h\1>#is', fn ($m) => "\n\n" . str_repeat('#', min(6, (int) $m[1] + 1)) . ' ' . trim(strip_tags($m[2])) . "\n\n", (string) $h);
    $h = preg_replace('#<li[^>]*>(.*?)</li>#is', "\n- $1", (string) $h);
    $h = preg_replace('#<(strong|b)>(.*?)</\1>#is', '**$2**', (string) $h);
    $h = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '[$2]($1)', (string) $h);
    $h = preg_replace('#<img[^>]*>#i', '', (string) $h);
    $h = preg_replace('#</p>|<br\s*/?>#i', "\n\n", (string) $h);
    $t = html_entity_decode(strip_tags((string) $h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace("/[ \t]+/u", ' ', $t)));
}

function seo_json_ld(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
}
