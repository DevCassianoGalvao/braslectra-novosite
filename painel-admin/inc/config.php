<?php
// Configuração padrão. Para sobrescrever em produção, crie inc/config.local.php
// retornando um array com as chaves desejadas (esse arquivo não deve ir para o Git).
$cfg = [
    // Banco SQLite. Em produção, prefira um caminho FORA da pasta pública.
    'db_path'      => ADMIN_ROOT . '/data/painel.sqlite',
    'upload_dir'   => ADMIN_ROOT . '/uploads',          // imagens do blog (públicas)
    'private_dir'  => ADMIN_ROOT . '/data/private',     // currículos (nunca públicos)
    'timezone'     => 'America/Sao_Paulo',
    'session_name' => 'braslectra_admin',
    // URL do painel (ex.: '/painel-admin'). null = detectar automaticamente.
    'base_path'    => null,
    // URL pública do site (usada em links de e-mails). Ex.: 'https://www.braslectra.com.br'
    'site_url'     => '',
    'debug'        => false,
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $over = require $local;
    if (is_array($over)) {
        $cfg = array_merge($cfg, $over);
    }
}
return $cfg;
