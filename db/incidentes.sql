-- ============================================================================
--  APRILON · Tabla de imprevistos reportados por conductores
-- ----------------------------------------------------------------------------
--  Importalo dentro de la misma base donde ya corriste aprilon.sql
--  (las tablas de usuarios tienen que existir antes que esta).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `incidentes`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `incidentes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conductor_id`  INT UNSIGNED NOT NULL,
  `tipo`          VARCHAR(60)  NOT NULL,
  `detalle`       VARCHAR(500) NULL,
  `leido`         TINYINT(1)   NOT NULL DEFAULT 0,
  `creado_en`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leido` (`leido`),
  CONSTRAINT `fk_incidente_conductor`
    FOREIGN KEY (`conductor_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
