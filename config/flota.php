<?php
/**
 * Configuración de la flota para las estadísticas del jefe: qué combustible
 * usa el vehículo, cuánto consume y a qué precio está cada combustible.
 * Se guarda en la tabla `configuracion` (clave/valor).
 */
declare(strict_types=1);
require_once __DIR__ . '/esquema.php';

/** Combustibles disponibles. Los precios son valores iniciales de referencia: el jefe los edita. */
const COMBUSTIBLES = [
    'nafta_super'   => ['nombre' => 'Nafta súper',   'unidad' => 'litro', 'precio' => 1600.0],
    'nafta_premium' => ['nombre' => 'Nafta premium', 'unidad' => 'litro', 'precio' => 1900.0],
    'diesel'        => ['nombre' => 'Diésel',        'unidad' => 'litro', 'precio' => 1700.0],
    'gnc'           => ['nombre' => 'GNC',           'unidad' => 'm³',    'precio' => 700.0],
];
const CONSUMO_INICIAL = 12.0; // cada 100 km, en la unidad del combustible

function flota_config(): array {
    asegurar_esquema();
    $guardado = db()->query('SELECT clave, valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);

    $combustible = $guardado['combustible'] ?? 'nafta_super';
    if (!isset(COMBUSTIBLES[$combustible])) $combustible = 'nafta_super';

    $precios = [];
    foreach (COMBUSTIBLES as $k => $c) {
        $precios[$k] = isset($guardado["precio_$k"]) ? (float)$guardado["precio_$k"] : $c['precio'];
    }
    return [
        'combustible'   => $combustible,
        'consumo_100km' => isset($guardado['consumo_100km']) ? (float)$guardado['consumo_100km'] : CONSUMO_INICIAL,
        'precios'       => $precios,
    ];
}

function flota_guardar(string $combustible, float $consumo, array $precios): void {
    asegurar_esquema();
    $st = db()->prepare('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    $st->execute(['combustible', $combustible]);
    $st->execute(['consumo_100km', (string)$consumo]);
    foreach (COMBUSTIBLES as $k => $_) {
        if (isset($precios[$k])) $st->execute(["precio_$k", (string)$precios[$k]]);
    }
}
