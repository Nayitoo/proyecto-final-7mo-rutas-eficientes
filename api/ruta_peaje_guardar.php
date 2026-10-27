<?php
/** POST /api/ruta_peaje_guardar.php  (jefe)
 *  { id, monto }  — carga (o corrige) lo gastado en peajes en una ruta. monto null/"" lo borra.
 *  Google no siempre informa el precio de los peajes de Argentina, por eso el jefe puede cargarlo a mano. */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/esquema.php';
requiere_rol(['jefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) error('Falta el id de la ruta.');

$monto = $in['monto'] ?? null;
if ($monto === '' || $monto === null) {
    $monto = null;
} else {
    $monto = (float)str_replace(',', '.', (string)$monto);
    if ($monto < 0 || $monto > 10000000) error('El monto de peajes no es válido.');
    $monto = round($monto, 2);
}

asegurar_esquema();
$st = db()->prepare('UPDATE rutas SET peajes_costo = ? WHERE id = ?');
$st->execute([$monto, $id]);
$existe = db()->prepare('SELECT COUNT(*) FROM rutas WHERE id = ?');
$existe->execute([$id]);
if (!(int)$existe->fetchColumn()) error('No encontramos esa ruta.', 404);

responder(['ok' => true, 'id' => $id, 'peajes_costo' => $monto]);
