<?php
/** POST /api/password_cambiar.php  (usuario logueado)
 *  { password_actual, password_nueva }  ->  cambia la propia contraseña */
require_once __DIR__ . '/../config/comun.php';
$u = requiere_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in     = cuerpo();
$actual = (string)($in['password_actual'] ?? '');
$nueva  = (string)($in['password_nueva']  ?? '');
if (strlen($nueva) < 6) error('La nueva contraseña debe tener al menos 6 caracteres.');

$st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
$st->execute([$u['id']]);
$hash = $st->fetchColumn();
if ($hash === false) error('Sesión inválida.', 401);
if (!password_verify($actual, $hash)) error('La contraseña actual no es correcta.', 403);

db()->prepare('UPDATE usuarios SET password_hash=?, password_temporal=NULL, debe_cambiar=0 WHERE id=?')
    ->execute([password_hash($nueva, PASSWORD_BCRYPT), $u['id']]);

$_SESSION['usuario']['debe_cambiar'] = false;
responder(['ok' => true]);
