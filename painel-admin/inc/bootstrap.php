<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('PHP 8.0 ou superior é necessário.');
}

define('ADMIN_ROOT', dirname(__DIR__));
$GLOBALS['CFG'] = require __DIR__ . '/config.php';

function cfg(string $key, $default = null)
{
    return $GLOBALS['CFG'][$key] ?? $default;
}

date_default_timezone_set((string) cfg('timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

$dataDir = dirname((string) cfg('db_path'));
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}
if (cfg('debug')) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $dataDir . '/php-errors.log');
}
error_reporting(E_ALL);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/sanitize.php';
require __DIR__ . '/brevo.php';
require __DIR__ . '/view.php';

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store');
}
