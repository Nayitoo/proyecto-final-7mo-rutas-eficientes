<?php
/** POST /api/ruta_guardar.php  (jefe, administrador)
 *  Persiste una ruta ya calculada (la respuesta de ruta_calcular.php) que el
 *  jefe decidió aceptar.
 *  { fecha, distancia_m, duracion_seg, polyline?,
 *    paradas: [{orden, nombre, direccion, lat, lng, distancia_tramo_m?, duracion_tramo_seg?, email_verificacion?}] } */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/verificaciones.php';
$u = requiere_rol(['jefe', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in           = cuerpo();
$fecha        = trim((string)($in['fecha'] ?? ''));
$distanciaM   = (int)($in['distancia_m'] ?? 0);
$duracionSeg  = (int)($in['duracion_seg'] ?? 0);
$polyline     = $in['polyline'] ?? null;
$paradas      = is_array($in['paradas'] ?? null) ? $in['paradas'] : [];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) error('Falta una fecha válida (AAAA-MM-DD).');
if (count($paradas) < 2) error('La ruta necesita al menos 2 paradas.');

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO rutas (fecha, distancia_m, duracion_seg, polyline, creado_por)
         VALUES (?,?,?,?,?)'
    )->execute([$fecha, $distanciaM, $duracionSeg, $polyline, $u['id']]);
    $rutaId = (int)$pdo->lastInsertId();

    $stP = $pdo->prepare(
        'INSERT INTO ruta_paradas
            (ruta_id, orden, nombre, direccion, lat, lng, distancia_tramo_m, duracion_tramo_seg, estado)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $verificacionesAEnviar = []; // [paradaId, email, nombre] — el email se manda recién después del commit
    foreach ($paradas as $i => $p) {
        $orden  = (int)($p['orden'] ?? ($i + 1));
        $nombre = trim((string)($p['nombre'] ?? '')) ?: 'Parada ' . $orden;
        $stP->execute([
            $rutaId,
            $orden,
            $nombre,
            trim((string)($p['direccion'] ?? '')),
            (float)($p['lat'] ?? 0),
            (float)($p['lng'] ?? 0),
            isset($p['distancia_tramo_m']) ? (int)$p['distancia_tramo_m'] : null,
            isset($p['duracion_tramo_seg']) ? (int)$p['duracion_tramo_seg'] : null,
            $orden === 1 ? 'activa' : 'pendiente',
        ]);
        $email = trim((string)($p['email_verificacion'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $verificacionesAEnviar[] = [(int)$pdo->lastInsertId(), $email, $nombre];
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error('No se pudo guardar la ruta: ' . $e->getMessage(), 500);
}

foreach ($verificacionesAEnviar as [$paradaId, $email, $nombre]) {
    crear_verificacion_para_parada($paradaId, $email, $nombre);
}

responder(['ok' => true, 'id' => $rutaId]);
