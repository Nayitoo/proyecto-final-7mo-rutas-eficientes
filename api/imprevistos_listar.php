<?php
/** GET /api/imprevistos_listar.php  (jefe, administrador)
 *  -> { incidentes: [{id, conductor, tipo, detalle, leido, creado_en}] } */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe', 'administrador']);

$rows = db()->query(
    "SELECT i.id, i.tipo, i.detalle, i.leido, i.creado_en, u.nombre AS conductor
     FROM incidentes i
     JOIN usuarios u ON u.id = i.conductor_id
     ORDER BY i.creado_en DESC
     LIMIT 100"
)->fetchAll();

responder(['ok' => true, 'incidentes' => array_map(fn($r) => [
    'id'        => (int)$r['id'],
    'conductor' => $r['conductor'],
    'tipo'      => $r['tipo'],
    'detalle'   => $r['detalle'],
    'leido'     => (bool)$r['leido'],
    'creado_en' => $r['creado_en'],
], $rows)]);
