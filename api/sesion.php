<?php
/** GET /api/sesion.php  ->  ¿hay sesión activa? devuelve el usuario */
require_once __DIR__ . '/../config/comun.php';
$u = usuario_actual();
responder(['ok' => true, 'autenticado' => $u !== null, 'usuario' => $u]);
