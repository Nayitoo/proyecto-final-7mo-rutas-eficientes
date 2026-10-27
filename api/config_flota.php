<?php
/** GET  /api/config_flota.php  (jefe)  -> { config: {combustible, consumo_100km, precios:{...}}, combustibles: {...} }
 *  POST /api/config_flota.php  (jefe)  { combustible, consumo_100km, precios: {nafta_super: n, ...} }
 *  Combustible, consumo y precios con los que se calculan las estadísticas. */
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/flota.php';
requiere_rol(['jefe']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in          = cuerpo();
    $combustible = (string)($in['combustible'] ?? '');
    $consumo     = (float)($in['consumo_100km'] ?? 0);
    $precios     = is_array($in['precios'] ?? null) ? $in['precios'] : [];

    if (!isset(COMBUSTIBLES[$combustible])) error('Elegí un combustible válido.');
    if ($consumo <= 0 || $consumo > 100)    error('El consumo tiene que estar entre 0,1 y 100 cada 100 km.');
    $limpios = [];
    foreach (COMBUSTIBLES as $k => $_) {
        if (!isset($precios[$k])) continue;
        $p = (float)$precios[$k];
        if ($p < 0 || $p > 1000000) error('El precio de ' . COMBUSTIBLES[$k]['nombre'] . ' no es válido.');
        $limpios[$k] = round($p, 2);
    }
    flota_guardar($combustible, round($consumo, 2), $limpios);
}

responder(['ok' => true, 'config' => flota_config(), 'combustibles' => COMBUSTIBLES]);
