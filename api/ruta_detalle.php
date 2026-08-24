<?php
/** GET /api/ruta_detalle.php?id=  (logueado)
 *  -> { ruta: {...}, paradas: [{id, orden, nombre, direccion, lat, lng, estado,
 *       requiere_verificacion, ...}] } */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/verificaciones.php';
requiere_login();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) error('Falta el id de la ruta.');

$st = db()->prepare('SELECT * FROM rutas WHERE id = ?');
$st->execute([$id]);
$ruta = $st->fetch();
if (!$ruta) error('No encontramos esa ruta.', 404);

$st = db()->prepare(
    'SELECT p.*, (v.id IS NOT NULL AND v.verificado_en IS NULL) AS pendiente_verificacion
     FROM ruta_paradas p
     LEFT JOIN verificaciones v ON v.ruta_parada_id = p.id
     WHERE p.ruta_id = ? ORDER BY p.orden'
);
$st->execute([$id]);

responder(['ok' => true,
    'ruta' => [
        'id'           => (int)$ruta['id'],
        'fecha'        => $ruta['fecha'],
        'estado'       => $ruta['estado'],
        'distancia_m'  => (int)$ruta['distancia_m'],
        'duracion_seg' => (int)$ruta['duracion_seg'],
        'polyline'     => $ruta['polyline'],
    ],
    'paradas' => array_map(fn($p) => [
        'id'                     => (int)$p['id'],
        'orden'                  => (int)$p['orden'],
        'nombre'                 => $p['nombre'],
        'direccion'              => $p['direccion'],
        'lat'                    => (float)$p['lat'],
        'lng'                    => (float)$p['lng'],
        'distancia_tramo_m'      => $p['distancia_tramo_m'] !== null ? (int)$p['distancia_tramo_m'] : null,
        'duracion_tramo_seg'     => $p['duracion_tramo_seg'] !== null ? (int)$p['duracion_tramo_seg'] : null,
        'estado'                 => $p['estado'],
        'completado_en'          => $p['completado_en'],
        'requiere_verificacion'  => (bool)$p['pendiente_verificacion'],
    ], $st->fetchAll()),
]);
