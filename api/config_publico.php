<?php
/** GET /api/config_publico.php  (público, sin login)
 *  Config no sensible que el frontend necesita antes de loguearse — por ahora,
 *  solo si este entorno es de demo (para mostrar u ocultar la caja de
 *  "usuarios de prueba" en el login). Nada de esto es secreto: no confundir
 *  con las claves/contraseñas de config/conexion*.php, que nunca se exponen acá.
 *  -> { demo: bool } */
declare(strict_types=1);
require_once __DIR__ . '/../config/comun.php';
require_once __DIR__ . '/../config/env.php';

responder(['ok' => true, 'demo' => env('APP_DEMO', 'true') === 'true']);
