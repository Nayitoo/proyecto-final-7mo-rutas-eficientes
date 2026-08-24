-- ============================================================================
--  APRILON · Tablas de usuarios
-- ----------------------------------------------------------------------------
--  Este archivo NO crea la base de datos, solo las tablas y los datos.
--  Importalo dentro de una base ya existente:
--    · XAMPP local  → creá la base "aprilon" desde phpMyAdmin y luego importá acá.
--    · AlwaysData   → creá la base desde el panel (Bases de datos → MySQL),
--                      abrí phpMyAdmin sobre esa base y ejecutá este archivo.
--
--  USUARIOS DE PRUEBA (contraseña única para todos: aprilon2026)
--    · usuario "jefe"       · nombre "Ricardo Herrera"  · rol jefe
--    · usuario "ventas"     · nombre "ventas"           · rol administrador
--    · usuario "expedicion" · nombre "expedicion"       · rol conductor
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `solicitudes_password`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `usuarios` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario`           VARCHAR(50)  NOT NULL COMMENT 'Nombre de usuario para el login (único)',
  `password_hash`     VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt generado con password_hash() de PHP',
  `nombre`            VARCHAR(120) NOT NULL COMMENT 'Nombre visible / de la cuenta',
  `foto_perfil`       VARCHAR(255) NULL     COMMENT 'Ruta de la foto de perfil',
  `rol`               ENUM('jefe','administrador','conductor') NOT NULL,
  `zona`              VARCHAR(120) NULL     COMMENT 'Zona / ruta asignada (aplica a conductor)',
  `activo`            TINYINT(1)   NOT NULL DEFAULT 1,
  `password_temporal` VARCHAR(255) NULL     COMMENT 'Clave temporal en texto plano para mostrársela al usuario',
  `debe_cambiar`      TINYINT(1)   NOT NULL DEFAULT 0,
  `ultimo_acceso`     DATETIME     NULL,
  `creado_en`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Garantiza que exista COMO MÁXIMO UNA cuenta de administrador
  `rol_unico_admin`   VARCHAR(13)
      AS (IF(`rol` = 'administrador', 'administrador', NULL)) STORED,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario` (`usuario`),
  UNIQUE KEY `uq_admin_unica` (`rol_unico_admin`),
  KEY `idx_rol` (`rol`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `solicitudes_password` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `estado`        ENUM('pendiente','resuelta') NOT NULL DEFAULT 'pendiente',
  `solicitado_en` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_en`   DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fk_solicitud_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Datos iniciales: contraseña "aprilon2026" hasheada con bcrypt para los 3 usuarios
-- ----------------------------------------------------------------------------
INSERT INTO `usuarios`
  (`usuario`, `password_hash`, `nombre`, `foto_perfil`, `rol`, `zona`, `activo`, `password_temporal`, `debe_cambiar`)
VALUES
  ('jefe',
   '$2y$10$XRcFFUL2KtZfX2tCAcdZaOVgTT2ZBfFn6DRZf2EvJLGyb5VSk1VK6',
   'jefe', NULL, 'jefe', NULL, 1, NULL, 0),

  ('ventas',
   '$2y$10$XRcFFUL2KtZfX2tCAcdZaOVgTT2ZBfFn6DRZf2EvJLGyb5VSk1VK6',
   'ventas', NULL, 'administrador', 'Todas las zonas', 1, NULL, 0),

  ('expedicion',
   '$2y$10$XRcFFUL2KtZfX2tCAcdZaOVgTT2ZBfFn6DRZf2EvJLGyb5VSk1VK6',
   'expedicion', NULL, 'conductor', 'Ruta asignada', 1, NULL, 0);
