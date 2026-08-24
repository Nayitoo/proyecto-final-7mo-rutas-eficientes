<?php
/**
 * Plantilla de conexión PDO a la base de datos Aprilon.
 *
 * Copiá este archivo a uno de los siguientes según el entorno y completá tus
 * propias credenciales (ninguno de esos nombres se sube al repo, ver .gitignore):
 *   - config/conexion.local.php  (desarrollo local con XAMPP)
 *   - config/conexion.ilm.php    (servidor ILM del instituto)
 *   - config/conexion.php        (producción)
 *
 * Luego apuntá el require_once correspondiente en config/comun.php.
 */
declare(strict_types=1);

const DB_HOST    = 'localhost';
const DB_NAME    = 'aprilon';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'No se pudo conectar a la base de datos.',
            'detalle' => $e->getMessage(),
        ]);
        exit;
    }
    return $pdo;
}
