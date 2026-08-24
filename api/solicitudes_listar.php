<?php
/** GET /api/solicitudes_listar.php  (solo jefe)  ->  solicitudes pendientes */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe']);
$sql = "SELECT s.id, s.estado, s.solicitado_en,
               u.id AS usuario_id, u.usuario, u.nombre, u.rol
        FROM solicitudes_password s
        JOIN usuarios u ON u.id = s.usuario_id
        WHERE s.estado = 'pendiente'
        ORDER BY s.solicitado_en DESC";
responder(['ok' => true, 'solicitudes' => db()->query($sql)->fetchAll()]);
