<?php
/** POST /api/perfil_foto_quitar.php  (usuario logueado)  ->  quita la foto de perfil */
require_once __DIR__ . '/../config/comun.php';
$u = requiere_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

db()->prepare('UPDATE usuarios SET foto_perfil = NULL WHERE id = ?')->execute([$u['id']]);
$_SESSION['usuario']['foto'] = null;

responder(['ok' => true]);
