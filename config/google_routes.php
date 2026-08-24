<?php
/**
 * Cliente mínimo para Geocoding API y Routes API de Google (sin dependencias).
 * La API key nunca se expone al navegador: todo esto corre en el backend.
 */
declare(strict_types=1);
require_once __DIR__ . '/env.php';

class GoogleMapsError extends RuntimeException {}

function google_maps_key(): string {
    $key = env('GOOGLE_MAPS_API_KEY', '');
    if ($key === '') throw new GoogleMapsError('Falta GOOGLE_MAPS_API_KEY en .env');
    return $key;
}

/** curl_init() + bundle de certificados propio (cacert.pem al lado de este archivo),
 *  para no depender del curl.cainfo del php.ini del servidor donde corra esto —
 *  en el servidor ILM ese ini apunta a un cacert.pem de OTRO proyecto que no se
 *  puede leer, y rompe todas las llamadas a Google con "error setting certificate file". */
function google_curl_init(string $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
    return $ch;
}

/** Convierte una dirección de texto en {lat,lng}. Devuelve null si no encontró nada.
 *  Usa la Geocoding API v4 (geocode.googleapis.com), que es la que cubre la Maps Demo Key;
 *  la API de Geocoding clásica (maps.googleapis.com/.../geocode/json) exige facturación habilitada. */
function geocodificar(string $direccion): ?array {
    $url = 'https://geocode.googleapis.com/v4/geocode/address/' . rawurlencode($direccion);
    $ch = google_curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['X-Goog-Api-Key: ' . google_maps_key()],
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) throw new GoogleMapsError('No se pudo conectar con Google: ' . curl_error($ch));
    curl_close($ch);

    $data = json_decode($raw, true);
    if (empty($data['results'][0]['location'])) return null;
    $loc = $data['results'][0]['location'];
    return ['lat' => $loc['latitude'], 'lng' => $loc['longitude']];
}

/**
 * Calcula la ruta óptima entre una lista de paradas (>= 2, con lat/lng ya resueltos).
 * La primera parada es el origen y la última el destino; las del medio se reordenan
 * automáticamente (optimizeWaypointOrder) para minimizar el recorrido.
 *
 * Devuelve:
 *  - orden: índices (sobre $paradas) en el orden óptimo, origen y destino incluidos
 *  - distancia_m, duracion_seg (con tráfico en tiempo real)
 *  - polyline: polyline codificada de la ruta completa, para dibujar en el mapa
 */
function calcular_ruta_optima(array $paradas): array {
    if (count($paradas) < 2) throw new InvalidArgumentException('Se necesitan al menos 2 paradas.');

    $origen      = $paradas[0];
    $destino     = $paradas[count($paradas) - 1];
    $intermedias = array_slice($paradas, 1, -1);

    $body = [
        'origin'                    => google_waypoint($origen),
        'destination'               => google_waypoint($destino),
        'intermediates'             => array_map('google_waypoint', $intermedias),
        'travelMode'                => 'DRIVE',
        // TRAFFIC_AWARE_OPTIMAL (el modelo de tráfico más preciso, el que más se acerca a Maps)
        // NO es compatible con optimizeWaypointOrder — Google devuelve un 400 explícito
        // ("optimize_waypoint_order is not supported for RoutingPreference TRAFFIC_AWARE_OPTIMAL"),
        // confirmado probándolo. Como reordenar las paradas es el corazón de esta feature,
        // se mantiene TRAFFIC_AWARE; la corrección real del desfasaje con Maps es mapsUrlFor()
        // en el frontend, que antes omitía el origen (ver comentario ahí).
        'routingPreference'         => 'TRAFFIC_AWARE',
        'optimizeWaypointOrder'     => true,
        'computeAlternativeRoutes'  => false,
        'languageCode'              => 'es-AR',
        'units'                     => 'METRIC',
    ];

    $ch = google_curl_init('https://routes.googleapis.com/directions/v2:computeRoutes');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . google_maps_key(),
            'X-Goog-FieldMask: routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.optimizedIntermediateWaypointIndex,routes.legs.duration,routes.legs.distanceMeters',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) throw new GoogleMapsError('No se pudo conectar con Google Routes API: ' . curl_error($ch));
    curl_close($ch);

    $data = json_decode($raw, true);
    if (empty($data['routes'][0])) {
        $msg = $data['error']['message'] ?? 'Google no devolvió ninguna ruta.';
        throw new GoogleMapsError($msg);
    }
    $ruta = $data['routes'][0];

    // reconstruye el orden real de paradas: origen, intermedias reordenadas, destino
    // Google a veces devuelve [-1] (o nada) cuando no hay nada para reordenar
    // (p.ej. con una sola parada intermedia) en vez de omitir el campo: en ese
    // caso usamos el orden original.
    $ordenIntermedias = $ruta['optimizedIntermediateWaypointIndex'] ?? [];
    $validos = array_filter($ordenIntermedias, fn($i) => $i >= 0 && $i < count($intermedias));
    if (count($validos) !== count($intermedias)) $validos = range(0, count($intermedias) - 1);

    $orden = [0];
    foreach ($validos as $i) $orden[] = $i + 1; // +1 porque la 0 es el origen
    $orden[] = count($paradas) - 1;

    return [
        'orden'         => $orden,
        'distancia_m'   => (int)($ruta['distanceMeters'] ?? 0),
        'duracion_seg'  => (int)rtrim($ruta['duration'] ?? '0s', 's'),
        'polyline'      => $ruta['polyline']['encodedPolyline'] ?? null,
        'tramos'        => array_map(fn($l) => [
            'distancia_m'  => (int)($l['distanceMeters'] ?? 0),
            'duracion_seg' => (int)rtrim($l['duration'] ?? '0s', 's'),
        ], $ruta['legs'] ?? []),
    ];
}

function google_waypoint(array $p): array {
    return ['location' => ['latLng' => ['latitude' => (float)$p['lat'], 'longitude' => (float)$p['lng']]]];
}

function google_http_get(string $url, array $headers = []): array {
    $ch = google_curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) throw new GoogleMapsError('No se pudo conectar con Google: ' . curl_error($ch));
    curl_close($ch);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Sugerencias de direcciones a medida que el admin escribe (Places Autocomplete).
 *  Sirve para desambiguar calles que se repiten en distintos barrios/localidades:
 *  cada sugerencia trae su placeId, que después se resuelve con google_place_details(). */
function google_autocompletar(string $texto): array {
    if (trim($texto) === '') return [];
    $ch = google_curl_init('https://places.googleapis.com/v1/places:autocomplete');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => json_encode(['input' => $texto, 'includedRegionCodes' => ['ar']]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . google_maps_key(),
            'X-Goog-FieldMask: suggestions.placePrediction.placeId,suggestions.placePrediction.structuredFormat',
        ],
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) throw new GoogleMapsError('No se pudo conectar con Google: ' . curl_error($ch));
    curl_close($ch);

    $data = json_decode($raw, true);
    $sugerencias = [];
    foreach ($data['suggestions'] ?? [] as $s) {
        $p = $s['placePrediction'] ?? null;
        if (!$p) continue;
        $sugerencias[] = [
            'placeId'   => $p['placeId'],
            'principal' => $p['structuredFormat']['mainText']['text'] ?? '',
            'secundario'=> $p['structuredFormat']['secondaryText']['text'] ?? '',
        ];
    }
    return $sugerencias;
}

/** Resuelve un placeId (de google_autocompletar) a {lat,lng,direccion}. */
function google_place_details(string $placeId): ?array {
    $data = google_http_get(
        'https://places.googleapis.com/v1/places/' . rawurlencode($placeId),
        ['X-Goog-Api-Key: ' . google_maps_key(), 'X-Goog-FieldMask: location,formattedAddress']
    );
    if (empty($data['location'])) return null;
    return [
        'lat'       => $data['location']['latitude'],
        'lng'       => $data['location']['longitude'],
        'direccion' => $data['formattedAddress'] ?? '',
    ];
}
