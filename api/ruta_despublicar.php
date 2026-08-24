<?php
/** POST /api/ruta_despublicar.php  (jefe, administrador)
 *  { id }
 *  Borra una ruta publicada (todavía sin ninguna parada completada) para que
 *  el admin pueda recalcularla y publicarla de nuevo. Las paradas y las
 *  verificaciones de esa ruta se borran en cascada (FK ON DELETE CASCADE en
 *  ruta_paradas/verificaciones, ver db/rutas.sql). No se puede despublicar una
 *  ruta que ya arrancó (estado 'en_curso') o que ya terminó ('completada'). */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) error('Falta el id de la ruta.');

$pdo = db();
$st = $pdo->prepare('SELECT id, estado FROM rutas WHERE id = ?');
$st->execute([$id]);
$ruta = $st->fetch();
if (!$ruta) error('No encontramos esa ruta.', 404);
if ($ruta['estado'] !== 'publicada') {
    error('Esta ruta ya tiene paradas en curso o completadas, no se puede despublicar.', 409);
}

$pdo->prepare('DELETE FROM rutas WHERE id = ?')->execute([$id]);

responder(['ok' => true]);
