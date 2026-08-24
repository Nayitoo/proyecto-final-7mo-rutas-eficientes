<?php
/** POST /api/imprevisto_crear.php  (conductor)
 *  { tipo, detalle? } */
require_once __DIR__ . '/../config/comun.php';
$u = requiere_rol(['conductor']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in      = cuerpo();
$tipo    = trim((string)($in['tipo'] ?? ''));
$detalle = trim((string)($in['detalle'] ?? ''));
if ($tipo === '') error('Elegí el tipo de imprevisto.');

db()->prepare('INSERT INTO incidentes (conductor_id, tipo, detalle) VALUES (?,?,?)')
    ->execute([$u['id'], $tipo, $detalle !== '' ? $detalle : null]);

responder(['ok' => true, 'id' => (int)db()->lastInsertId()]);
