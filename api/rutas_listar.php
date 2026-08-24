<?php
/** GET /api/rutas_listar.php?fecha=AAAA-MM-DD  (logueado)
 *  Cualquier rol logueado ve todas las rutas de esa fecha — no hay noción de
 *  "conductor asignado" (proyecto pensado para un solo conductor).
 *  -> { rutas: [{id, fecha, estado, distancia_m, duracion_seg, paradas_total, paradas_hechas}] } */
require_once __DIR__ . '/../config/comun.php';
requiere_login();

$fecha = trim((string)($_GET['fecha'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) error('Fecha inválida.');

$st = db()->prepare(
    "SELECT r.id, r.fecha, r.estado, r.distancia_m, r.duracion_seg,
            COUNT(p.id) AS paradas_total,
            SUM(p.estado='completada') AS paradas_hechas
     FROM rutas r
     LEFT JOIN ruta_paradas p ON p.ruta_id = r.id
     WHERE r.fecha = ?
     GROUP BY r.id ORDER BY r.id"
);
$st->execute([$fecha]);

responder(['ok' => true, 'rutas' => array_map(fn($r) => [
    'id'             => (int)$r['id'],
    'fecha'          => $r['fecha'],
    'estado'         => $r['estado'],
    'distancia_m'    => (int)$r['distancia_m'],
    'duracion_seg'   => (int)$r['duracion_seg'],
    'paradas_total'  => (int)$r['paradas_total'],
    'paradas_hechas' => (int)$r['paradas_hechas'],
], $st->fetchAll())]);
