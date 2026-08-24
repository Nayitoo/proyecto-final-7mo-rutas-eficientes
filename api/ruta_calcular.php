<?php
/** POST /api/ruta_calcular.php  (jefe, administrador)
 *  { paradas: [{nombre, direccion, lat?, lng?}, ...] }  (mínimo 2)
 *  Geocodifica las que falten y le pide a Google Routes API el orden óptimo.
 *  No persiste nada: es la "ruta temporal" que el jefe puede aceptar o no. */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/google_routes.php';
requiere_rol(['jefe', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in      = cuerpo();
$paradas = is_array($in['paradas'] ?? null) ? $in['paradas'] : [];
if (count($paradas) < 2) error('Necesitás al menos 2 puntos para calcular una ruta.');

foreach ($paradas as $i => &$p) {
    $nombre    = trim((string)($p['nombre'] ?? ''));
    $direccion = trim((string)($p['direccion'] ?? ''));
    if ($direccion === '') error('Falta la dirección de la parada #' . ($i + 1) . '.');

    $lat = isset($p['lat']) ? (float)$p['lat'] : null;
    $lng = isset($p['lng']) ? (float)$p['lng'] : null;
    if ($lat === null || $lng === null) {
        try {
            $geo = geocodificar($direccion);
        } catch (GoogleMapsError $e) {
            error('No se pudo ubicar "' . $direccion . '": ' . $e->getMessage(), 502);
        }
        if (!$geo) error('No encontramos la dirección "' . $direccion . '" en el mapa.', 422);
        [$lat, $lng] = [$geo['lat'], $geo['lng']];
    }
    $email = trim((string)($p['email_verificacion'] ?? ''));
    $p = [
        'nombre'              => ($nombre ?: $direccion),
        'direccion'           => $direccion,
        'lat'                 => $lat,
        'lng'                 => $lng,
        'email_verificacion'  => ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null,
    ];
}
unset($p);

try {
    $ruta = calcular_ruta_optima($paradas);
} catch (GoogleMapsError $e) {
    error('No se pudo calcular la ruta: ' . $e->getMessage(), 502);
} catch (InvalidArgumentException $e) {
    error($e->getMessage(), 422);
}

$paradasOrdenadas = [];
foreach ($ruta['orden'] as $pos => $idxOriginal) {
    $tramo = $ruta['tramos'][$pos] ?? null;
    $paradasOrdenadas[] = $paradas[$idxOriginal] + [
        'orden'              => $pos + 1,
        'distancia_tramo_m'  => $tramo['distancia_m']  ?? null,
        'duracion_tramo_seg' => $tramo['duracion_seg'] ?? null,
    ];
}

responder([
    'ok'            => true,
    'paradas'       => $paradasOrdenadas,
    'distancia_m'   => $ruta['distancia_m'],
    'duracion_seg'  => $ruta['duracion_seg'],
    'polyline'      => $ruta['polyline'],
]);
