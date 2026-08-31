<?php
/**
 * Utilidades comunes: CORS, sesión, respuestas JSON y guards de rol.
 * Incluir al inicio de cada endpoint.
 */
declare(strict_types=1);
// Servidor ILM (192.168.101.92:8091): usa conexion.ilm.php.
// Para desarrollo local con XAMPP, esta línea sería conexion.local.php;
// para producción real (AlwaysData), conexion.php.
require_once __DIR__ . '/conexion.ilm.php';

/* ---- CORS (para desarrollo con el front y el back en distinto origen) ----
   Si servís el mockup y la carpeta /api desde el mismo dominio, no hace falta. */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: application/json; charset=utf-8');

/* ---- Sesión ---- */
function iniciar_sesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

/* ---- Respuestas ---- */
function responder($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function error(string $msg, int $code = 400): void {
    responder(['ok' => false, 'error' => $msg], $code);
}

/* ---- Cuerpo de la petición (JSON o formulario) ---- */
function cuerpo(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST ?? [];
}

/* ---- Guards de autenticación / rol ---- */
function usuario_actual(): ?array {
    iniciar_sesion();
    return $_SESSION['usuario'] ?? null;
}
function requiere_login(): array {
    $u = usuario_actual();
    if (!$u) error('Necesitás iniciar sesión.', 401);
    return $u;
}
function requiere_rol(array $roles): array {
    $u = requiere_login();
    if (!in_array($u['rol'], $roles, true)) error('No tenés permiso para esta acción.', 403);
    return $u;
}
/* Libera el lock de sesión (PHP lo mantiene tomado hasta que el script termina).
 * Llamar apenas termina de leerse todo lo que hace falta de $_SESSION, en endpoints
 * de solo lectura que después hacen algo lento (ej. una llamada a una API externa) —
 * si no, cualquier otro request de la misma sesión de navegador (otra pestaña, un
 * polling en segundo plano, la tecla siguiente en un autocompletado) queda esperando
 * en cola en vez de poder correr en paralelo. No usar en un endpoint que todavía
 * necesite escribir en $_SESSION después de llamarla. */
function liberar_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}

/* ---- Utilidades ---- */
function generar_password_temporal(): string {
    $A = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; $b = 'abcdefghijkmnpqrstuvwxyz'; $d = '23456789';
    $p = $A[random_int(0, strlen($A) - 1)];
    for ($i = 0; $i < 5; $i++) $p .= $b[random_int(0, strlen($b) - 1)];
    for ($i = 0; $i < 3; $i++) $p .= $d[random_int(0, strlen($d) - 1)];
    return $p;
}
function usuario_publico(array $r): array {
    return [
        'id'           => (int)$r['id'],
        'usuario'      => $r['usuario'],
        'nombre'       => $r['nombre'],
        'rol'          => $r['rol'],
        'zona'         => $r['zona'],
        'activo'       => (int)$r['activo'] === 1,
        'foto'         => $r['foto_perfil'],
        'debe_cambiar' => (int)$r['debe_cambiar'] === 1,
    ];
}
