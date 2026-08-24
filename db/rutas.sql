-- ============================================================================
--  APRILON · Tablas de puntos de entrega y rutas
-- ----------------------------------------------------------------------------
--  Importalo dentro de la misma base donde ya corriste aprilon.sql
--  (las tablas de usuarios tienen que existir antes que estas).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `verificaciones`;
DROP TABLE IF EXISTS `ruta_paradas`;
DROP TABLE IF EXISTS `rutas`;
DROP TABLE IF EXISTS `puntos_guardados`;

SET FOREIGN_KEY_CHECKS = 1;

-- Direcciones frecuentes guardadas por el jefe/administrador ("apodo" reutilizable)
CREATE TABLE `puntos_guardados` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `apodo`       VARCHAR(120) NOT NULL,
  `direccion`   VARCHAR(255) NOT NULL,
  `lat`         DECIMAL(10,7) NOT NULL,
  `lng`         DECIMAL(10,7) NOT NULL,
  `creado_por`  INT UNSIGNED NULL,
  `creado_en`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apodo` (`apodo`),
  CONSTRAINT `fk_punto_creador`
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una ruta calculada y aprobada por el jefe/administrador para un día.
-- No tiene conductor asignado: el proyecto opera con un solo conductor
-- (usuarios.rol='conductor'), así que cualquier ruta del día es "la ruta"
-- de quien esté logueado como conductor. No agregar de nuevo una columna
-- de conductor acá sin que el usuario lo pida explícitamente — se descartó
-- a propósito.
CREATE TABLE `rutas` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha`          DATE NOT NULL,
  `estado`         ENUM('publicada','en_curso','completada') NOT NULL DEFAULT 'publicada',
  `distancia_m`    INT UNSIGNED NOT NULL,
  `duracion_seg`   INT UNSIGNED NOT NULL,
  `polyline`       TEXT NULL COMMENT 'Polyline codificada de Google Routes API para dibujar el camino',
  `creado_por`     INT UNSIGNED NOT NULL,
  `creado_en`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `fk_ruta_creador`
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paradas de una ruta, ya en el orden óptimo devuelto por Google Routes API
CREATE TABLE `ruta_paradas` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruta_id`             INT UNSIGNED NOT NULL,
  `orden`               SMALLINT UNSIGNED NOT NULL COMMENT 'Posición 1..N en el recorrido óptimo',
  `nombre`              VARCHAR(120) NOT NULL,
  `direccion`           VARCHAR(255) NOT NULL,
  `lat`                 DECIMAL(10,7) NOT NULL,
  `lng`                 DECIMAL(10,7) NOT NULL,
  `distancia_tramo_m`   INT UNSIGNED NULL COMMENT 'Distancia desde la parada anterior',
  `duracion_tramo_seg`  INT UNSIGNED NULL COMMENT 'Duración desde la parada anterior (con tráfico)',
  `estado`              ENUM('pendiente','activa','completada') NOT NULL DEFAULT 'pendiente',
  `completado_en`       DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ruta_orden` (`ruta_id`, `orden`),
  CONSTRAINT `fk_parada_ruta`
    FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Palabra de verificación de entrega por parada ("Verificaciones"): el admin
-- carga un email opcional al armar la ruta, se genera una palabra al azar al
-- publicar y se manda por correo. El conductor la pide al dueño/encargado del
-- negocio y la ingresa para poder completar esa parada. Es temporal por
-- diseño: una fila por parada (se reemplaza si se vuelve a publicar).
CREATE TABLE `verificaciones` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruta_parada_id`      INT UNSIGNED NOT NULL,
  `email`               VARCHAR(160) NOT NULL,
  `palabra`             VARCHAR(40) NOT NULL,
  `enviado_en`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verificado_en`       DATETIME NULL,
  `intentos_fallidos`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_verificacion_parada` (`ruta_parada_id`),
  CONSTRAINT `fk_verificacion_parada`
    FOREIGN KEY (`ruta_parada_id`) REFERENCES `ruta_paradas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
