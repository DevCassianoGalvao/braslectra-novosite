<?php
declare(strict_types=1);

/**
 * Importa os artigos do site antigo (WordPress) a partir de tools/blog_seed.json.
 * Idempotente: artigos já existentes (mesmo slug) são ignorados.
 * @return array{imported:int,skipped:int}
 */
function import_blog_seed(string $file, ?int $authorId = null): array
{
    if (!is_file($file)) {
        return ['imported' => 0, 'skipped' => 0];
    }
    $items = json_decode((string) file_get_contents($file), true);
    if (!is_array($items)) {
        return ['imported' => 0, 'skipped' => 0];
    }
    $imported = 0;
    $skipped = 0;
    foreach ($items as $it) {
        $title = trim((string) ($it['title'] ?? ''));
        $slug = slugify((string) ($it['slug'] ?? $title));
        if ($title === '' || scalar('SELECT 1 FROM posts WHERE slug = ?', [$slug])) {
            $skipped++;
            continue;
        }
        $content = sanitize_html((string) ($it['content'] ?? ''));
        $excerpt = trim((string) ($it['excerpt'] ?? ''));
        if ($excerpt === '') {
            $excerpt = truncate($content, 180);
        }
        $date = (string) ($it['date'] ?? now());
        q(
            'INSERT INTO posts (title, slug, excerpt, content, image, status, seo_title, seo_description, published_at, old_id, author_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $slug,
                $excerpt,
                $content,
                (string) ($it['image'] ?? ''),
                'published',
                trim((string) ($it['seo_title'] ?? '')),
                trim((string) ($it['seo_description'] ?? '')),
                $date,
                $it['old_id'] ?? null,
                $authorId,
                $date,
                now(),
            ]
        );
        $imported++;
    }
    return ['imported' => $imported, 'skipped' => $skipped];
}

const OLD_UPLOADS_RE = '#https?://(?:www\.)?braslectra\.com\.br/wp-content/uploads/(\d{4}/\d{2}/[^"\'\s<>()]+)#i';

/** URLs de imagens do site antigo ainda referenciadas pelos artigos. @return array<string,string> url => caminho relativo (2025/06/x.png) */
function remote_blog_images(): array
{
    $found = [];
    foreach (rows('SELECT image, content FROM posts') as $p) {
        foreach ([$p['image'], $p['content']] as $text) {
            if (preg_match_all(OLD_UPLOADS_RE, (string) $text, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $found[$x[0]] = $x[1];
                }
            }
        }
    }
    return $found;
}

/**
 * Baixa para uploads/blog as imagens que os artigos ainda buscam no site antigo e troca as URLs.
 * Processa em lotes ($limit) para não estourar o tempo do servidor.
 * @return array{done:int,failed:int,remaining:int,errors:string[]}
 */
function fetch_remote_blog_images(int $limit = 20): array
{
    $todo = remote_blog_images();
    $done = 0;
    $failed = 0;
    $errors = [];
    $root = rtrim((string) cfg('upload_dir'), '/\\');
    foreach ($todo as $url => $rel) {
        if ($done + $failed >= $limit) {
            break;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'Mozilla/5.0 (PainelBraslectra)',
            CURLOPT_MAXFILESIZE => 12 * 1024 * 1024,
        ]);
        $bin = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $info = is_string($bin) && $code === 200 ? @getimagesizefromstring($bin) : false;
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
        if (!$info || !isset($types[$info[2]])) {
            $failed++;
            $errors[] = $rel . ' (HTTP ' . $code . ')';
            continue;
        }
        [$y, $mo, $file] = explode('/', $rel, 3);
        $file = preg_replace('/[^A-Za-z0-9_.\-]/', '-', $file);
        $dir = $root . '/blog/' . $y . '/' . $mo;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $failed++;
            $errors[] = $rel . ' (sem permissão de escrita)';
            continue;
        }
        file_put_contents($dir . '/' . $file, $bin);
        $new = 'uploads/blog/' . $y . '/' . $mo . '/' . $file;
        foreach (rows('SELECT id, image, content FROM posts WHERE image LIKE ? OR content LIKE ?', ['%' . $rel . '%', '%' . $rel . '%']) as $p) {
            q(
                'UPDATE posts SET image = ?, content = ? WHERE id = ?',
                [str_replace($url, $new, (string) $p['image']), str_replace($url, $new, (string) $p['content']), $p['id']]
            );
        }
        $done++;
    }
    return ['done' => $done, 'failed' => $failed, 'remaining' => max(0, count(remote_blog_images())), 'errors' => $errors];
}
