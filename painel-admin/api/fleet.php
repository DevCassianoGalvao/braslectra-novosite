<?php
/**
 * API pública da frota (somente leitura).
 *   GET api/fleet.php                       -> todos os veículos visíveis
 *   GET api/fleet.php?category=vans         -> só uma categoria (carros | vans | micro-onibus | onibus)
 */
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/public_api.php';

public_cors(['GET', 'OPTIONS']);
header('Cache-Control: public, max-age=5');

$cat = (string) ($_GET['category'] ?? '');
$cats = fleet_categories();
if ($cat !== '' && !isset($cats[$cat])) {
    json_out(['ok' => false, 'error' => 'Categoria inválida.'], 400);
}
$groups = [];
foreach ($cats as $k => $c) {
    $groups[$k] = array_map(static fn ($g, $l) => ['id' => $g, 'title' => $l], array_keys($c['groups']), array_values($c['groups']));
}
json_out(['ok' => true, 'groups' => $cat !== '' ? $groups[$cat] : $groups, 'vehicles' => fleet_public_list($cat !== '' ? $cat : null)]);
