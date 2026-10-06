<?php
// Uso (linha de comando):  php tools/import_blog.php            -> importa tools/blog_seed.json
//                          php tools/import_blog.php images      -> baixa imagens que ainda estão no site antigo
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/blog_import.php';

if (($argv[1] ?? '') === 'images') {
    do {
        $r = fetch_remote_blog_images(25);
        echo "baixadas: {$r['done']}  falhas: {$r['failed']}  restantes: {$r['remaining']}\n";
        foreach ($r['errors'] as $e) {
            echo "  - $e\n";
        }
    } while ($r['done'] > 0 && $r['remaining'] > 0);
    exit(0);
}
$admin = (int) scalar("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
$r = import_blog_seed(__DIR__ . '/blog_seed.json', $admin ?: null);
echo "importados: {$r['imported']}  ignorados (já existem): {$r['skipped']}\n";
