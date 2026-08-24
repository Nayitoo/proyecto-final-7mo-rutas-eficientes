<?php
/** GET /api/rutas_historial.php?limit=30  (jefe, administrador)
 *  Últimas rutas publicadas, de cualquier fecha (no solo la de hoy), más
 *  nuevas primero — para la pantalla "Historial de rutas" del admin.
 *  -> { rutas: [{id, fecha, estado, distancia_m, duracion_seg, paradas_total, paradas_hechas}] } */
declare(strict_types=1);
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe', 'administrador']);

$limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));

$st = db()->prepare(
    "SELECT r.id, r.fecha, r.estado, r.distancia_m, r.duracion_seg,
            COUNT(p.id) AS paradas_total,
            SUM(p.estado='completada') AS paradas_hechas
     FROM rutas r
     LEFT JOIN ruta_paradas p ON p.ruta_id = r.id
     GROUP BY r.id
     ORDER BY r.fecha DESC, r.id DESC
     LIMIT $limit"
);
$st->execute();

responder(['ok' => true, 'rutas' => array_map(fn($r) => [
    'id'             => (int)$r['id'],
    'fecha'          => $r['fecha'],
    'estado'         => $r['estado'],
    'distancia_m'    => (int)$r['distancia_m'],
    'duracion_seg'   => (int)$r['duracion_seg'],
    'paradas_total'  => (int)$r['paradas_total'],
    'paradas_hechas' => (int)$r['paradas_hechas'],
], $st->fetchAll())]);
