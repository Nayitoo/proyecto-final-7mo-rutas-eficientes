<?php
/** GET /api/lugares_detalle.php?placeId=  (jefe, administrador)
 *  Resuelve una sugerencia de lugares_autocompletar.php a {lat,lng,direccion}. */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/google_routes.php';
requiere_rol(['jefe', 'administrador']);

$placeId = trim((string)($_GET['placeId'] ?? ''));
if ($placeId === '') error('Falta el placeId.');

try {
    $detalle = google_place_details($placeId);
} catch (GoogleMapsError $e) {
    error('No se pudo obtener la dirección: ' . $e->getMessage(), 502);
}
if (!$detalle) error('No encontramos esa dirección.', 404);

responder(['ok' => true] + $detalle);
