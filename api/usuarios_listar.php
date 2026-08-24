<?php
/** GET /api/usuarios_listar.php  (solo jefe)
 *  ->  { jefe, administrador (cuenta compartida), conductores[] } */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe']);

$rows = db()->query(
    "SELECT id, usuario, nombre, foto_perfil, rol, zona, activo, password_temporal, debe_cambiar
     FROM usuarios
     ORDER BY FIELD(rol,'jefe','administrador','conductor'), nombre"
)->fetchAll();

$jefe = null; $administrador = null; $conductores = [];
foreach ($rows as $r) {
    $pub = usuario_publico($r);
    // la contraseña temporal se muestra en la pantalla de contraseñas del jefe
    $pub['password_temporal'] = $r['password_temporal'];
    if ($r['rol'] === 'administrador')   $administrador = $pub;
    elseif ($r['rol'] === 'conductor')   $conductores[] = $pub;
    else                                 $jefe = $pub;
}
responder(['ok' => true, 'jefe' => $jefe, 'administrador' => $administrador, 'conductores' => $conductores]);
