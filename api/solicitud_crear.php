<?php
/** POST /api/solicitud_crear.php  (sin login · "olvidé mi contraseña")  { usuario }
 *  Crea una solicitud pendiente para que el jefe restablezca la clave.
 *  Respuesta genérica: no revela si el usuario existe. */
require_once __DIR__ . '/../config/comun.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$usuario = trim((string)($in['usuario'] ?? ''));
if ($usuario === '') error('Ingresá tu usuario.');

$st = db()->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
$st->execute([$usuario]);
$id = $st->fetchColumn();

if ($id !== false) {
    $ch = db()->prepare("SELECT COUNT(*) FROM solicitudes_password WHERE usuario_id=? AND estado='pendiente'");
    $ch->execute([$id]);
    if ((int)$ch->fetchColumn() === 0) {
        db()->prepare("INSERT INTO solicitudes_password (usuario_id, estado) VALUES (?, 'pendiente')")->execute([$id]);
    }
}
responder(['ok' => true, 'mensaje' => 'Si el usuario existe, el jefe recibirá tu solicitud.']);
