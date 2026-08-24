<?php
/** GET /api/puntos_guardados_listar.php  (jefe, administrador)
 *  -> { puntos: [{id, apodo, direccion, lat, lng}] } */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe', 'administrador']);

$rows = db()->query(
    'SELECT id, apodo, direccion, lat, lng FROM puntos_guardados ORDER BY apodo'
)->fetchAll();

responder(['ok' => true, 'puntos' => array_map(fn($r) => [
    'id'        => (int)$r['id'],
    'apodo'     => $r['apodo'],
    'direccion' => $r['direccion'],
    'lat'       => (float)$r['lat'],
    'lng'       => (float)$r['lng'],
], $rows)]);
