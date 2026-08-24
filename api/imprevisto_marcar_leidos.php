<?php
/** POST /api/imprevisto_marcar_leidos.php  (jefe, administrador)
 *  { id? } — con id marca solo ese imprevisto como leído; sin id, marca todos
 *  los pendientes (comportamiento original, usado por el polling de avisos). */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in = cuerpo();
$id = (int)($in['id'] ?? 0);

if ($id > 0) {
    db()->prepare("UPDATE incidentes SET leido = 1 WHERE id = ?")->execute([$id]);
} else {
    db()->exec("UPDATE incidentes SET leido = 1 WHERE leido = 0");
}

responder(['ok' => true]);
