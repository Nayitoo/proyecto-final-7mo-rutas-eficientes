<?php
/** GET /api/lugares_autocompletar.php?q=texto  (jefe, administrador)
 *  Sugerencias de direcciones a medida que se escribe, para desambiguar
 *  calles que se repiten en varios barrios/localidades.
 *  -> { sugerencias: [{placeId, principal, secundario}] } */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/google_routes.php';
requiere_rol(['jefe', 'administrador']);

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') responder(['ok' => true, 'sugerencias' => []]);

try {
    $sugerencias = google_autocompletar($q);
} catch (GoogleMapsError $e) {
    error('No se pudo buscar direcciones: ' . $e->getMessage(), 502);
}

responder(['ok' => true, 'sugerencias' => $sugerencias]);
