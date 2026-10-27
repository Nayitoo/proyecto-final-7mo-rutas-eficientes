<?php
/**
 * Completa el esquema de la base con lo que agregaron las versiones nuevas
 * (opciones de ruta, peajes y configuración de flota), sin pisar datos.
 * Es idempotente: se puede llamar en cada request, hace una sola consulta
 * de chequeo y recién ahí altera si falta algo. db/rutas.sql ya trae lo mismo
 * para instalaciones desde cero.
 */
declare(strict_types=1);

function asegurar_esquema(): void {
    static $hecho = false;
    if ($hecho) return;
    $hecho = true;
    $pdo = db();

    $cols = $pdo->query("SHOW COLUMNS FROM rutas LIKE 'evitar_peajes'")->fetchAll();
    if (!$cols) {
        $pdo->exec(
            "ALTER TABLE rutas
               ADD COLUMN evitar_peajes         TINYINT(1)    NOT NULL DEFAULT 0,
               ADD COLUMN evitar_autopistas     TINYINT(1)    NOT NULL DEFAULT 0,
               ADD COLUMN evitar_transbordadores TINYINT(1)   NOT NULL DEFAULT 0,
               ADD COLUMN peajes_costo          DECIMAL(12,2) NULL COMMENT 'Gasto en peajes de la ruta (estimado por Google o cargado por el jefe)'"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS configuracion (
           clave VARCHAR(60)  NOT NULL,
           valor VARCHAR(255) NOT NULL,
           PRIMARY KEY (clave)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
