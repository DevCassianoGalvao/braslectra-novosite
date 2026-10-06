<?php
/** Dados públicos do site (telefones, e-mails, endereços, redes sociais). */
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/public_api.php';
require dirname(__DIR__) . '/inc/public_settings.php';

public_cors(['GET', 'OPTIONS']);
header('Cache-Control: public, max-age=120');
json_out(['ok' => true, 'site' => public_settings()]);
