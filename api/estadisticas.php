<?php
/** GET /api/estadisticas.php?periodo=semana|mes|anio|todo  (jefe)
 *  Kilómetros recorridos, combustible, peajes y demás datos de la operación.
 *  "Recorrido" = rutas completadas. El combustible se calcula con la configuración
 *  de flota actual (config_flota.php): km × consumo / 100 × precio del combustible elegido. */
declare(strict_types=1);
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/flota.php';
requiere_rol(['jefe']);
liberar_sesion();
date_default_timezone_set('America/Argentina/Buenos_Aires');
asegurar_esquema();

$periodo = (string)($_GET['periodo'] ?? 'mes');
$hoy     = new DateTimeImmutable('today');
switch ($periodo) {
    case 'semana': $desde = $hoy->modify('-6 days');   break;
    case 'mes':    $desde = $hoy->modify('-29 days');  break;
    case 'anio':   $desde = $hoy->modify('-364 days'); break;
    case 'todo':   $desde = new DateTimeImmutable('2000-01-01'); break;
    default: error('Período no válido.');
}
$d = $desde->format('Y-m-d');
$h = $hoy->format('Y-m-d');
$pdo = db();

$cfg   = flota_config();
$comb  = COMBUSTIBLES[$cfg['combustible']];
$precio = $cfg['precios'][$cfg['combustible']];
/** [unidades de combustible, costo] para una distancia en metros */
$costoDe = function (int $metros) use ($cfg, $precio): array {
    $unidades = $metros / 1000 * $cfg['consumo_100km'] / 100;
    return [round($unidades, 1), round($unidades * $precio, 2)];
};

/* ---- totales de rutas completadas ---- */
$st = $pdo->prepare(
    "SELECT COUNT(*) AS rutas, COALESCE(SUM(distancia_m),0) AS dist, COALESCE(SUM(duracion_seg),0) AS dur,
            COALESCE(SUM(peajes_costo),0) AS peajes, SUM(peajes_costo IS NOT NULL) AS con_peaje_cargado
     FROM rutas WHERE estado = 'completada' AND fecha BETWEEN ? AND ?"
);
$st->execute([$d, $h]);
$t = $st->fetch();

/* ---- paradas entregadas en esas rutas ---- */
$st = $pdo->prepare(
    "SELECT COUNT(*) FROM ruta_paradas p JOIN rutas r ON r.id = p.ruta_id
     WHERE r.estado = 'completada' AND r.fecha BETWEEN ? AND ? AND p.estado = 'completada'"
);
$st->execute([$d, $h]);
$paradas = (int)$st->fetchColumn();

/* ---- rutas del período que todavía no se completaron + opciones usadas ---- */
$st = $pdo->prepare(
    "SELECT SUM(estado <> 'completada') AS pendientes, COUNT(*) AS total,
            COALESCE(SUM(evitar_peajes),0) AS evitan_peajes,
            COALESCE(SUM(evitar_autopistas),0) AS evitan_autopistas,
            COALESCE(SUM(evitar_transbordadores),0) AS evitan_transb
     FROM rutas WHERE fecha BETWEEN ? AND ?"
);
$st->execute([$d, $h]);
$o = $st->fetch();

/* ---- imprevistos reportados ---- */
$st = $pdo->prepare('SELECT COUNT(*) FROM incidentes WHERE DATE(creado_en) BETWEEN ? AND ?');
$st->execute([$d, $h]);
$imprevistos = (int)$st->fetchColumn();

/* ---- serie para el gráfico: por día (semana/mes) o por mes (año/todo) ---- */
$porMes = in_array($periodo, ['anio', 'todo'], true);
$clave  = $porMes ? "DATE_FORMAT(fecha, '%Y-%m')" : 'fecha';
$st = $pdo->prepare(
    "SELECT $clave AS k, SUM(distancia_m) AS dist, COUNT(*) AS rutas
     FROM rutas WHERE estado = 'completada' AND fecha BETWEEN ? AND ?
     GROUP BY k ORDER BY k"
);
$st->execute([$d, $h]);
$serie = array_map(function ($r) use ($costoDe) {
    [$u, $c] = $costoDe((int)$r['dist']);
    return ['k' => $r['k'], 'km' => round((int)$r['dist'] / 1000, 1), 'rutas' => (int)$r['rutas'], 'combustible' => $c];
}, $st->fetchAll());

/* ---- últimas rutas del período (para cargar peajes a mano) ---- */
$st = $pdo->prepare(
    "SELECT r.id, r.fecha, r.estado, r.distancia_m, r.duracion_seg, r.peajes_costo,
            r.evitar_peajes, r.evitar_autopistas, r.evitar_transbordadores,
            COUNT(p.id) AS paradas, SUM(p.estado = 'completada') AS hechas
     FROM rutas r LEFT JOIN ruta_paradas p ON p.ruta_id = r.id
     WHERE r.fecha BETWEEN ? AND ?
     GROUP BY r.id ORDER BY r.fecha DESC, r.id DESC LIMIT 12"
);
$st->execute([$d, $h]);
$recientes = array_map(function ($r) use ($costoDe) {
    [$u, $c] = $costoDe((int)$r['distancia_m']);
    return [
        'id' => (int)$r['id'], 'fecha' => $r['fecha'], 'estado' => $r['estado'],
        'km' => round((int)$r['distancia_m'] / 1000, 1),
        'duracion_seg' => (int)$r['duracion_seg'],
        'combustible' => $c,
        'peajes_costo' => $r['peajes_costo'] !== null ? (float)$r['peajes_costo'] : null,
        'evita' => ['peajes' => (bool)$r['evitar_peajes'], 'autopistas' => (bool)$r['evitar_autopistas'], 'transbordadores' => (bool)$r['evitar_transbordadores']],
        'paradas' => (int)$r['paradas'], 'hechas' => (int)$r['hechas'],
    ];
}, $st->fetchAll());

$distM = (int)$t['dist'];
[$unidades, $costoComb] = $costoDe($distM);
$rutas = (int)$t['rutas'];
$peajes = round((float)$t['peajes'], 2);
$total = round($costoComb + $peajes, 2);
$km = round($distM / 1000, 1);

responder(['ok' => true,
    'periodo' => $periodo, 'desde' => $d, 'hasta' => $h,
    'flota' => [
        'combustible' => $cfg['combustible'], 'nombre' => $comb['nombre'], 'unidad' => $comb['unidad'],
        'precio' => $precio, 'consumo_100km' => $cfg['consumo_100km'],
    ],
    'totales' => [
        'rutas_completadas' => $rutas,
        'rutas_pendientes'  => (int)$o['pendientes'],
        'km'                => $km,
        'duracion_seg'      => (int)$t['dur'],
        'paradas_entregadas'=> $paradas,
        'combustible_unidades' => $unidades,
        'combustible_costo' => $costoComb,
        'peajes_costo'      => $peajes,
        'rutas_con_peaje_cargado' => (int)$t['con_peaje_cargado'],
        'costo_total'       => $total,
        'costo_por_km'      => $km > 0 ? round($total / $km, 2) : null,
        'costo_por_parada'  => $paradas > 0 ? round($total / $paradas, 2) : null,
        'km_promedio_ruta'  => $rutas > 0 ? round($km / $rutas, 1) : null,
        'imprevistos'       => $imprevistos,
        'rutas_evitan_peajes'      => (int)$o['evitan_peajes'],
        'rutas_evitan_autopistas'  => (int)$o['evitan_autopistas'],
        'rutas_evitan_transbordadores' => (int)$o['evitan_transb'],
    ],
    'serie' => $serie, 'por_mes' => $porMes,
    'recientes' => $recientes,
]);
