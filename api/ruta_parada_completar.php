<?php
/** POST /api/ruta_parada_completar.php  (conductor)
 *  { parada_id, palabra_verificacion? }
 *  Si la parada tiene una verificación de entrega pendiente (ver
 *  config/verificaciones.php), hay que mandar la palabra correcta o esto
 *  devuelve 422 sin completar nada.
 *  Marca la parada como completada y activa la siguiente pendiente.
 *  Si no queda ninguna pendiente, marca la ruta como completada. */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/verificaciones.php';
requiere_rol(['conductor']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in       = cuerpo();
$paradaId = (int)($in['parada_id'] ?? 0);
$palabra  = trim((string)($in['palabra_verificacion'] ?? ''));
if ($paradaId <= 0) error('Falta el id de la parada.');

$st = db()->prepare('SELECT * FROM ruta_paradas WHERE id = ?');
$st->execute([$paradaId]);
$parada = $st->fetch();
if (!$parada) error('No encontramos esa parada.', 404);

if (parada_requiere_verificacion($paradaId)) {
    if ($palabra === '') error('Esta parada necesita la palabra de verificación para completarse.', 422);
    if (!validar_verificacion($paradaId, $palabra)) {
        error('Esa palabra no es correcta. Pedísela de nuevo al encargado del negocio.', 422);
    }
}

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE ruta_paradas SET estado='completada', completado_en=NOW() WHERE id=?")
        ->execute([$paradaId]);
    $pdo->prepare("UPDATE rutas SET estado='en_curso' WHERE id=? AND estado='publicada'")
        ->execute([$parada['ruta_id']]);

    $st = $pdo->prepare(
        "SELECT id FROM ruta_paradas WHERE ruta_id=? AND estado='pendiente' ORDER BY orden LIMIT 1"
    );
    $st->execute([$parada['ruta_id']]);
    $siguiente = $st->fetch();

    if ($siguiente) {
        $pdo->prepare("UPDATE ruta_paradas SET estado='activa' WHERE id=?")->execute([$siguiente['id']]);
    } else {
        $pdo->prepare("UPDATE rutas SET estado='completada' WHERE id=?")->execute([$parada['ruta_id']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error('No se pudo completar la parada: ' . $e->getMessage(), 500);
}

responder(['ok' => true, 'siguiente_id' => $siguiente ? (int)$siguiente['id'] : null]);
