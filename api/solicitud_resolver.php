<?php
/** POST /api/solicitud_resolver.php  (solo jefe)  { id }  ->  marca resuelta */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);
$in = cuerpo();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) error('Falta el id de la solicitud.');
db()->prepare("UPDATE solicitudes_password SET estado='resuelta', resuelto_en=NOW() WHERE id=?")->execute([$id]);
responder(['ok' => true]);
