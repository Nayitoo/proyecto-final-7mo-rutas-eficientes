<?php
/** POST /api/rutas_borrar_hoy.php  (jefe, administrador)
 *  Borra TODAS las rutas de la fecha de hoy (según el reloj del servidor),
 *  sin importar su estado — a diferencia de ruta_despublicar.php, que solo
 *  permite borrar una ruta todavía 'publicada'. Pensado para limpiar rutas
 *  duplicadas (ver bug de triple-clic, handoff sección 5-ter) o para que el
 *  admin pueda arrancar de cero el día. Las paradas y verificaciones se
 *  borran en cascada (FK ON DELETE CASCADE en ruta_paradas/verificaciones).
 *  -> { ok, borradas } */
declare(strict_types=1);
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$pdo = db();
$st = $pdo->prepare('SELECT COUNT(*) FROM rutas WHERE fecha = CURDATE()');
$st->execute();
$total = (int)$st->fetchColumn();

$pdo->prepare('DELETE FROM rutas WHERE fecha = CURDATE()')->execute();

responder(['ok' => true, 'borradas' => $total]);
