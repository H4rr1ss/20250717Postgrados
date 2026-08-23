-- ============================================================
-- Recuperación de Contraseña
-- Token de un solo uso, expira en 30 minutos, invalida anteriores
-- ============================================================

CREATE TABLE IF NOT EXISTS `password_reset_token` (
    `token` VARCHAR(64) NOT NULL,
    `cod_usuario` INT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token`),
    KEY `idx_cod_usuario` (`cod_usuario`),
    CONSTRAINT `fk_reset_token_usuario`
        FOREIGN KEY (`cod_usuario`) REFERENCES `usuario`(`cod_usuario`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tokens de recuperación de contraseña (uso único, 30 minutos)';

-- ------------------------------------------------------------
-- Acción ACL para la pantalla pública de restablecer contraseña
-- ------------------------------------------------------------
INSERT INTO `accion` (`cod_accion`, `nombre`) VALUES
  (171, 'Restablecer contraseña');
