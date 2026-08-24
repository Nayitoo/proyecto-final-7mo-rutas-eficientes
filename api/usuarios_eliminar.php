<?php
/** POST /api/usuarios_eliminar.php  (solo jefe)  { id }
 *  Solo se pueden eliminar cuentas de conductor. */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) error('Falta el id del usuario.');

$st = db()->prepare('SELECT rol FROM usuarios WHERE id = ?');
$st->execute([$id]);
$rol = $st->fetchColumn();
if ($rol === false) error('No encontramos ese usuario.', 404);
if ($rol !== 'conductor') error('Solo se pueden eliminar cuentas de conductor.', 403);

db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
responder(['ok' => true]);
