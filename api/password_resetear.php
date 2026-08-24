<?php
/** POST /api/password_resetear.php  (sin login · "olvidé mi contraseña")
 *  { usuario, password_temporal, password_nueva }
 *  Valida la clave temporal contra el hash guardado (la misma clave que el jefe
 *  entregó) y, si coincide, establece la nueva contraseña definitiva. */
require_once __DIR__ . '/../config/comun.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in       = cuerpo();
$usuario  = trim((string)($in['usuario'] ?? ''));
$temporal = (string)($in['password_temporal'] ?? '');
$nueva    = (string)($in['password_nueva']    ?? '');
if ($usuario === '' || $temporal === '' || $nueva === '') {
    error('Completá usuario, contraseña temporal y nueva contraseña.');
}
if (strlen($nueva) < 6) error('La nueva contraseña debe tener al menos 6 caracteres.');

$st = db()->prepare('SELECT id, password_hash FROM usuarios WHERE usuario = ? LIMIT 1');
$st->execute([$usuario]);
$u = $st->fetch();

// Mensaje genérico: no revela si el usuario existe o si la clave temporal es incorrecta.
if (!$u || !password_verify($temporal, $u['password_hash'])) {
    error('Usuario o contraseña temporal incorrectos.', 401);
}

db()->prepare('UPDATE usuarios SET password_hash=?, password_temporal=NULL, debe_cambiar=0 WHERE id=?')
    ->execute([password_hash($nueva, PASSWORD_BCRYPT), $u['id']]);

responder(['ok' => true]);
