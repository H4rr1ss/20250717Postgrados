-- ============================================================
-- MÓDULO GRADUACIÓN — Paso 5: Carta de Examinadores (SCHEMA)
-- Base de datos: db_postgrados
-- Creado:    2026-05-15
-- Revisión:  2026-05-15 - cambio de modelo:
--   El seguimiento de correcciones se realiza por correo electrónico
--   EXTERNO a la plataforma. En la plataforma sólo se guardan evidencias
--   (capturas/PDFs de correos) como bitácora del seguimiento.
--   Esto evita almacenar versiones grandes del trabajo de graduación.
--
-- Aplica sobre: modulo_graduacion.sql (debe estar cargado primero)
-- Contiene:
--   1) Re-asignación de es_ultimo_paso (paso 4 -> 0, paso 5 -> 1)
--   2) Inserción del paso 5 en examen_paso_catalogo
--   3) Tabla examen_correccion_ciclo
--   4) Tabla examen_correccion_evidencia (capturas de correos)
--   5) Tabla examen_carta_plantilla
--   6) Tabla examen_carta_examinadores
-- ============================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

-- ------------------------------------------------------------
-- 1) Mover es_ultimo_paso del paso 4 al nuevo paso 5
-- ------------------------------------------------------------
UPDATE `examen_paso_catalogo`
   SET `es_ultimo_paso` = 0
 WHERE `numero_orden` = 4
   AND `cod_tipo_examen` IS NULL;


-- ------------------------------------------------------------
-- 2) Insertar paso 5 en el catálogo (aplica a todos los tipos)
-- ------------------------------------------------------------
INSERT INTO `examen_paso_catalogo`
  (`cod_tipo_examen`, `numero_orden`, `fase`, `nombre`,
   `template_parcial`, `es_ultimo_paso`, `activo`)
VALUES
  (NULL, 5, 'carta_examinadores', 'Carta de Examinadores', 'paso5-carta-examinadores', 0, 1);


-- ------------------------------------------------------------
-- 3) examen_correccion_ciclo
--    Ciclo interno único por proceso. El usuario NUNCA ve el
--    concepto de "ciclo" en la plataforma. Se crea automáticamente
--    el primer ciclo al entrar al paso 5 (iniciarPasoCarta) y se
--    mantiene en estado 'pendiente_revision' hasta que el director
--    aprueba el trabajo. El campo observaciones queda NULL.
--
--    El intercambio de correcciones ocurre FUERA de la plataforma
--    (correo electrónico). El estudiante registra evidencias
--    (capturas/PDFs) en examen_correccion_evidencia como bitácora
--    del seguimiento.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_correccion_ciclo`;
CREATE TABLE `examen_correccion_ciclo` (
  `cod_ciclo`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_proceso`            INT(11) UNSIGNED NOT NULL,
  `estado`                 ENUM('pendiente_revision','aprobado')
                           NOT NULL DEFAULT 'pendiente_revision',
  `revisado_por`           INT(11) DEFAULT NULL
                           COMMENT 'FK -> usuario (coordinador)',
  `fecha_revision`         datetime NULL DEFAULT NULL,
  `created_at`             datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`             datetime NOT NULL DEFAULT current_timestamp()
                           ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_ciclo`),
  UNIQUE KEY `uniq_ecc_proceso` (`cod_proceso`),
  KEY `idx_ecc_proceso_estado` (`cod_proceso`, `estado`),
  KEY `idx_ecc_estado` (`estado`),
  CONSTRAINT `examen_correccion_ciclo_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_correccion_ciclo_usuario_fk`
    FOREIGN KEY (`revisado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 4) examen_correccion_evidencia
--    Adjuntos (capturas de correos) por ciclo. Cada captura es una
--    imagen o pdf pequeño que evidencia la comunicación con el
--    estudiante. Los archivos físicos se guardan en
--    public/archivos/ con nombre MD5 (mismo patrón que el resto
--    del módulo).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_correccion_evidencia`;
CREATE TABLE `examen_correccion_evidencia` (
  `cod_evidencia`     INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_ciclo`         INT(11) UNSIGNED NOT NULL,
  `archivo_md5`       VARCHAR(32) NOT NULL
                      COMMENT 'Nombre físico (sin extensión) en public/archivos/',
  `extension`         VARCHAR(10) NOT NULL
                      COMMENT 'Sin punto: jpg, png, pdf',
  `nombre_original`   VARCHAR(255) DEFAULT NULL,
  `tamano_bytes`      INT(10) UNSIGNED DEFAULT NULL,
  `descripcion`       VARCHAR(300) DEFAULT NULL
                      COMMENT 'Nota corta del estudiante sobre la evidencia',
  `subido_por`        INT(11) NOT NULL,
  `fecha_subida`      datetime NOT NULL DEFAULT current_timestamp(),
  `eliminado`         TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cod_evidencia`),
  UNIQUE KEY `uniq_ece_archivo` (`archivo_md5`),
  KEY `idx_ece_ciclo` (`cod_ciclo`),
  CONSTRAINT `examen_correccion_evidencia_ciclo_fk`
    FOREIGN KEY (`cod_ciclo`) REFERENCES `examen_correccion_ciclo` (`cod_ciclo`),
  CONSTRAINT `examen_correccion_evidencia_usuario_fk`
    FOREIGN KEY (`subido_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 5) examen_carta_plantilla
--    Catálogo de plantillas .docx con merge fields (PHPWord
--    TemplateProcessor).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_carta_plantilla`;
CREATE TABLE `examen_carta_plantilla` (
  `cod_plantilla`     SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_tipo_examen`   TINYINT(3) UNSIGNED DEFAULT NULL,
  `nombre`            VARCHAR(150) NOT NULL,
  `archivo_plantilla` VARCHAR(255) NOT NULL,
  `descripcion`       TEXT DEFAULT NULL,
  `activo`            TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`        datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_plantilla`),
  KEY `idx_ecp_tipo_activo` (`cod_tipo_examen`, `activo`),
  CONSTRAINT `examen_carta_plantilla_tipo_fk`
    FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 6) examen_carta_examinadores
--    Carta generada (una por proceso). Se crea al aprobar el ciclo.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_carta_examinadores`;
CREATE TABLE `examen_carta_examinadores` (
  `cod_carta`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_proceso`          INT(11) UNSIGNED NOT NULL,
  `cod_ciclo_aprobacion` INT(11) UNSIGNED NOT NULL,
  `cod_plantilla`        SMALLINT(5) UNSIGNED NOT NULL,
  `archivo_generado`     VARCHAR(255) NOT NULL,
  `estado`               ENUM('generada','entregada') NOT NULL DEFAULT 'generada',
  `fecha_generacion`     datetime NOT NULL DEFAULT current_timestamp(),
  `generada_por`         INT(11) NOT NULL,
  `fecha_entrega`        datetime NULL DEFAULT NULL,
  `observaciones`        TEXT DEFAULT NULL,
  PRIMARY KEY (`cod_carta`),
  UNIQUE KEY `uniq_ece_proceso` (`cod_proceso`),
  KEY `idx_ece_estado` (`estado`),
  CONSTRAINT `examen_carta_examinadores_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_carta_examinadores_ciclo_fk`
    FOREIGN KEY (`cod_ciclo_aprobacion`) REFERENCES `examen_correccion_ciclo` (`cod_ciclo`),
  CONSTRAINT `examen_carta_examinadores_plantilla_fk`
    FOREIGN KEY (`cod_plantilla`) REFERENCES `examen_carta_plantilla` (`cod_plantilla`),
  CONSTRAINT `examen_carta_examinadores_usuario_fk`
    FOREIGN KEY (`generada_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
