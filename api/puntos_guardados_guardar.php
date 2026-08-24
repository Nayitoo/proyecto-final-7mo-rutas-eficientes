<?php
/** POST /api/puntos_guardados_guardar.php  (jefe, administrador)
 *  { apodo, direccion, lat?, lng? }  (id opcional para editar)
 *  Si no vienen lat/lng, se geocodifica la dirección con Google. */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/google_routes.php';
$u = requiere_rol(['jefe', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in       = cuerpo();
$id       = isset($in['id']) ? (int)$in['id'] : 0;
$apodo    = trim((string)($in['apodo'] ?? ''));
$direccion = trim((string)($in['direccion'] ?? ''));
$lat      = isset($in['lat']) ? (float)$in['lat'] : null;
$lng      = isset($in['lng']) ? (float)$in['lng'] : null;

if ($apodo === '' || $direccion === '') error('Completá el apodo y la dirección.');

if ($lat === null || $lng === null) {
    try {
        $geo = geocodificar($direccion);
    } catch (GoogleMapsError $e) {
        error('No se pudo ubicar la dirección: ' . $e->getMessage(), 502);
    }
    if (!$geo) error('No encontramos esa dirección en el mapa. Revisala e intentá de nuevo.', 422);
    [$lat, $lng] = [$geo['lat'], $geo['lng']];
}

if ($id > 0) {
    db()->prepare('UPDATE puntos_guardados SET apodo=?, direccion=?, lat=?, lng=? WHERE id=?')
        ->execute([$apodo, $direccion, $lat, $lng, $id]);
    responder(['ok' => true, 'id' => $id, 'lat' => $lat, 'lng' => $lng]);
}

db()->prepare('INSERT INTO puntos_guardados (apodo, direccion, lat, lng, creado_por) VALUES (?,?,?,?,?)')
    ->execute([$apodo, $direccion, $lat, $lng, $u['id']]);

responder(['ok' => true, 'id' => (int)db()->lastInsertId(), 'lat' => $lat, 'lng' => $lng]);
