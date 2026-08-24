<?php
/** POST /api/password_restablecer.php  (solo jefe)
 *  { id, password?, debe_cambiar? }
 *  Si no se manda "password", se genera una temporal automáticamente.
 *  Guarda la clave, la devuelve, y marca como resueltas las solicitudes pendientes. */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) error('Falta el id del usuario.');

$temp = trim((string)($in['password'] ?? ''));
if ($temp !== '' && strlen($temp) < 4) error('La contraseña debe tener al menos 4 caracteres.');
if ($temp === '') $temp = generar_password_temporal();
$debeCambiar = array_key_exists('debe_cambiar', $in) ? (int)!!$in['debe_cambiar'] : 1;

$st = db()->prepare('SELECT rol FROM usuarios WHERE id = ?');
$st->execute([$id]);
if ($st->fetchColumn() === false) error('No encontramos ese usuario.', 404);

$hash = password_hash($temp, PASSWORD_BCRYPT);
db()->prepare('UPDATE usuarios SET password_hash=?, password_temporal=?, debe_cambiar=? WHERE id=?')
    ->execute([$hash, $temp, $debeCambiar, $id]);
db()->prepare("UPDATE solicitudes_password SET estado='resuelta', resuelto_en=NOW() WHERE usuario_id=? AND estado='pendiente'")
    ->execute([$id]);

responder(['ok' => true, 'password_temporal' => $temp]);
