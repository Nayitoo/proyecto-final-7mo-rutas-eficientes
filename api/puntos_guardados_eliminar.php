<?php
/** POST /api/puntos_guardados_eliminar.php  (jefe, administrador)
 *  { id } -> borra un punto guardado de la agenda de lugares. */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$id = isset($in['id']) ? (int)$in['id'] : 0;
if ($id <= 0) error('Falta el id del punto a eliminar.');

db()->prepare('DELETE FROM puntos_guardados WHERE id = ?')->execute([$id]);

responder(['ok' => true]);
