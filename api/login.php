<?php
/** POST /api/login.php  { usuario, password }  ->  inicia sesión */
require_once __DIR__ . '/../config/comun.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$usuario  = trim((string)($in['usuario'] ?? ''));
$password = (string)($in['password'] ?? '');
if ($usuario === '' || $password === '') error('Completá usuario y contraseña.');

// El cotejo de usuario es insensible a mayúsculas por la collation utf8mb4_unicode_ci.
$st = db()->prepare('SELECT * FROM usuarios WHERE usuario = ? LIMIT 1');
$st->execute([$usuario]);
$u = $st->fetch();

if (!$u || (int)$u['activo'] !== 1 || !password_verify($password, $u['password_hash'])) {
    error('Usuario o contraseña incorrectos.', 401);
}

db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$u['id']]);

iniciar_sesion();
session_regenerate_id(true);
$_SESSION['usuario'] = usuario_publico($u);

responder(['ok' => true, 'usuario' => $_SESSION['usuario']]);
