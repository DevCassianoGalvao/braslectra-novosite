<?php
/**
 * Roteador para testar o site localmente com URLs amigáveis (o .htaccess faz isso no Apache):
 *   php -S 127.0.0.1:8000 router.php
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$routes = [
    '/' => 'Braslectra Home.dc.html', '/sobre' => 'Sobre.dc.html', '/frota' => 'Frota.dc.html',
    '/frota-carros' => 'Frota-Carros.dc.html', '/frota-vans' => 'Frota-Vans.dc.html',
    '/frota-micro-onibus' => 'Frota-Micro-Onibus.dc.html', '/frota-onibus' => 'Frota-Onibus.dc.html',
    '/servicos' => 'Servicos.dc.html', '/servicos-executivo' => 'Servicos-Executivo.dc.html',
    '/servicos-fretamento' => 'Servicos-Fretamento.dc.html', '/servicos-turismo' => 'Servicos-Turismo.dc.html',
    '/servicos-rodoviario' => 'Servicos-Rodoviario.dc.html', '/sustentabilidade' => 'Sustentabilidade.dc.html',
    '/treinamentos' => 'Treinamentos.dc.html', '/blog' => 'blog.php', '/blog-post' => 'blog-post.php',
    '/contato' => 'Contato.dc.html', '/trabalhe-conosco' => 'Trabalhe-Conosco.dc.html',
    '/politica-de-privacidade' => 'Politica-de-Privacidade.dc.html',
    '/sitemap.xml' => 'sitemap.php', '/llms-full.txt' => 'llms-full.php',
];
$key = rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
if (isset($routes[$key])) {
    $file = __DIR__ . '/' . $routes[$key];
    if (str_ends_with($file, '.php')) { require $file; return true; }
    header('Content-Type: text/html; charset=utf-8');
    readfile($file);
    return true;
}
return false; // arquivos reais (imagens, js, painel-admin/*.php) seguem o fluxo normal
