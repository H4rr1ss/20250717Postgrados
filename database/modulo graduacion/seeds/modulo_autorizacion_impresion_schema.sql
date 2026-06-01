-- ============================================================
-- MÓDULO GRADUACIÓN — Paso 6: Autorización de Impresión del Proyecto
-- Base de datos: db_postgrados
-- Fecha de creación: 2026-01-16
-- Última actualización: 2026-01-16 - Eliminadas columnas orden_display de documentos/cartas/junta,
--                          y columnas descargas_completadas/fecha_descargas de examen_autorizacion_proceso
--
-- Dependencias (ejecutar primero):
--   1) modulo_graduacion.sql                  (paso 1-4: examen_privado, examen_general)
--   2) modulo_graduacion_carta_01_schema.sql  (paso 5: carta_examinadores)
--
-- Contenido:
--   0) Extensión del ENUM `fase` en examen_paso_catalogo
--   1) Inserción del paso 6 en examen_paso_catalogo
--   2) Tabla examen_autorizacion_config              (instrucciones globales)
--   3) Tabla examen_autorizacion_documento_soporte   (logos/escudos globales)
--   4) Tabla examen_profesional_calificado           (licenciados en letras)
--   5) Tabla examen_carta_descarga                   (cartas .docx genéricas)
--   6) Tabla examen_junta_directiva                  (miembros - informativo)
--   7) Tabla examen_autorizacion_proceso             (estado por proceso)
--
-- Flujo de fases (después de aplicar este script):
--   examen_privado  →  carta_examinadores  →  autorizacion_impresion  →  examen_general
-- ============================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- ------------------------------------------------------------
-- 0) EXTENDER ENUM `fase` EN examen_paso_catalogo
--    NOTA: Desde la versión actual de modulo_graduacion.sql, el
--    ENUM ya incluye 'autorizacion_impresion' desde la creación
--    de la tabla. Este ALTER se conserva como idempotente para
--    compatibilidad con instalaciones previas.
-- ------------------------------------------------------------
ALTER TABLE `examen_paso_catalogo`
  MODIFY COLUMN `fase`
    ENUM('examen_privado','carta_examinadores','autorizacion_impresion','examen_general')
    NOT NULL DEFAULT 'examen_privado';


-- ------------------------------------------------------------
-- 1) INSERTAR PASO 6 EN examen_paso_catalogo
--    Aplica a todos los tipos de examen (cod_tipo_examen IS NULL)
--    Es paso 6, no es el último (examen_general sigue siendo último).
-- ------------------------------------------------------------
INSERT INTO `examen_paso_catalogo`
  (`cod_tipo_examen`, `numero_orden`, `fase`, `nombre`, `template_parcial`, `es_ultimo_paso`, `activo`)
VALUES
  (NULL, 6, 'autorizacion_impresion', 'Autorización de Impresión del Proyecto',
   'paso6-autorizacion-impresion', 0, 1)
ON DUPLICATE KEY UPDATE
  `activo`           = 1,
  `nombre`           = 'Autorización de Impresión del Proyecto',
  `template_parcial` = 'paso6-autorizacion-impresion';


-- ------------------------------------------------------------
-- 2) examen_autorizacion_config
--    Configuración GLOBAL de instrucciones (un único registro).
--    El director edita los bloques de texto que el estudiante visualiza
--    en cada parte del paso 6:
--      - Parte 1: Autorización de Imprímase
--      - Parte 2: Instructivo para pagos correspondientes a examen público y procedimiento para solicitud de fecha.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_autorizacion_config`;
CREATE TABLE `examen_autorizacion_config` (
  `cod_config`            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `instrucciones_parte1`  TEXT DEFAULT NULL COMMENT 'Instrucciones Parte 1: Autorización de Imprímase',
  `instrucciones_parte2`  TEXT DEFAULT NULL COMMENT 'Instrucciones Parte 2: Instructivo para pagos correspondientes a examen público y procedimiento para solicitud de fecha',
  `updated_at`            datetime NOT NULL DEFAULT current_timestamp()
                          ON UPDATE current_timestamp(),
  `updated_by`            INT DEFAULT NULL COMMENT 'FK → usuario que modificó',
  PRIMARY KEY (`cod_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Configuración global del paso 6: instrucciones por parte';

-- Seed: fila única (cod_config = 1) que se editará vía UPDATE
INSERT INTO `examen_autorizacion_config` (`cod_config`, `instrucciones_parte1`, `instrucciones_parte2`, `updated_by`)
VALUES (1, NULL, NULL, NULL);


-- ------------------------------------------------------------
-- 3) examen_autorizacion_documento_soporte
--    Documentos GLOBALES de soporte que el estudiante puede descargar.
--    Ejemplos: logotipo de universidad, escudo, guía visual.
--    Archivos físicos se guardan en:
--      public/archivos/autorizacion_impresion/documentos_soporte/<md5>.<ext>
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_autorizacion_documento_soporte`;
CREATE TABLE `examen_autorizacion_documento_soporte` (
  `cod_documento`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`           VARCHAR(200) NOT NULL  COMMENT 'Título descriptivo visible al estudiante',
  `descripcion`      VARCHAR(500) DEFAULT NULL,
  `archivo_md5`      VARCHAR(32)  NOT NULL  COMMENT 'Nombre físico (hash MD5)',
  `extension`        VARCHAR(10)  NOT NULL  COMMENT 'Sin punto: jpg, png, pdf, docx',
  `nombre_original`  VARCHAR(255) DEFAULT NULL,
  `tamano_bytes`     INT UNSIGNED DEFAULT NULL,
  `activo`           TINYINT(1) NOT NULL DEFAULT 1,
  `subido_por`       INT NOT NULL COMMENT 'FK → usuario (director/asistente)',
  `fecha_subida`     datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_documento`),
  UNIQUE KEY `uniq_eads_md5` (`archivo_md5`),
  KEY `idx_eads_activo_fecha` (`activo`, `fecha_subida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Documentos de soporte globales (logos, escudos, guías visuales)';


-- ------------------------------------------------------------
-- 4) examen_profesional_calificado
--    Licenciados en letras calificados (catálogo GLOBAL).
--    El estudiante selecciona uno de esta lista durante el paso 6.
--    NOTA: por requerimiento, NO se almacena número de colegiado.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_profesional_calificado`;
CREATE TABLE `examen_profesional_calificado` (
  `cod_profesional`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_completo`   VARCHAR(200) NOT NULL,
  `correo`            VARCHAR(150) DEFAULT NULL,
  `telefono`          VARCHAR(20)  DEFAULT NULL,
  `activo`            TINYINT(1) NOT NULL DEFAULT 1,
  `creado_por`        INT NOT NULL COMMENT 'FK → usuario (director/asistente)',
  `fecha_creacion`    datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_profesional`),
  KEY `idx_epc_activo` (`activo`),
  KEY `idx_epc_nombre` (`nombre_completo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Catálogo global de licenciados en letras (sin nº colegiado)';


-- ------------------------------------------------------------
-- 5) examen_carta_descarga
--    Cartas tipo GENÉRICAS en formato .docx que el director sube y
--    el estudiante descarga. NO se generan dinámicamente.
--    Archivos físicos se guardan en:
--      public/archivos/autorizacion_impresion/cartas_descarga/<md5>.<ext>
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_carta_descarga`;
CREATE TABLE `examen_carta_descarga` (
  `cod_carta`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`           VARCHAR(200) NOT NULL  COMMENT 'Título visible para el estudiante',
  `descripcion`      VARCHAR(500) DEFAULT NULL COMMENT 'Para qué sirve la carta',
  `archivo_md5`      VARCHAR(32)  NOT NULL,
  `extension`        VARCHAR(10)  NOT NULL DEFAULT 'docx',
  `nombre_original`  VARCHAR(255) NOT NULL,
  `tamano_bytes`     INT UNSIGNED DEFAULT NULL,
  `activo`           TINYINT(1) NOT NULL DEFAULT 1,
  `subido_por`       INT NOT NULL,
  `fecha_subida`     datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_carta`),
  UNIQUE KEY `uniq_ecd_md5` (`archivo_md5`),
  KEY `idx_ecd_activo_fecha` (`activo`, `fecha_subida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Cartas genéricas .docx para descarga del estudiante';


-- ------------------------------------------------------------
-- 6) examen_junta_directiva
--    Miembros de la junta directiva (información extra GLOBAL).
--    El director realiza CRUD. El estudiante sólo los visualiza.
--    No se usa en lógica de proceso; es estrictamente informativo.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_junta_directiva`;
CREATE TABLE `examen_junta_directiva` (
  `cod_miembro`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_completo` VARCHAR(200) NOT NULL,
  `puesto`          VARCHAR(100) NOT NULL COMMENT 'Ej: Presidente, Secretario, Vocal I',
  `activo`          TINYINT(1) NOT NULL DEFAULT 1,
  `creado_por`      INT NOT NULL,
  `fecha_creacion`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_miembro`),
  KEY `idx_ejd_activo_fecha` (`activo`, `fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Miembros de junta directiva (informativo, sólo lectura para estudiante)';


-- ------------------------------------------------------------
-- 7) examen_autorizacion_proceso
--    Estado POR PROCESO del paso 6.
--    Registra: profesional seleccionado por el estudiante y
--    aprobación final del director (revisión presencial).
--
--    El paso 6 tiene 2 sub-pasos (partes):
--      - Parte 1 (sub_paso=1): Estudiante selecciona profesional,
--                              director aprueba revisión presencial.
--      - Parte 2 (sub_paso=2): Preparación final para examen general,
--                              director confirma culminación y avanza.
--
--    Reglas de negocio para aprobar Parte 1:
--      - El proceso debe estar en fase 'autorizacion_impresion'
--      - cod_profesional IS NOT NULL (estudiante ya seleccionó)
--    Reglas de negocio para aprobar Parte 2:
--      - El proceso debe estar en fase 'autorizacion_impresion'
--      - sub_paso = 2
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_autorizacion_proceso`;
CREATE TABLE `examen_autorizacion_proceso` (
  `cod_autorizacion`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_proceso`            INT UNSIGNED NOT NULL,
  `cod_profesional`        INT UNSIGNED DEFAULT NULL
                           COMMENT 'FK → profesional seleccionado por el estudiante',
  `sub_paso`               TINYINT UNSIGNED NOT NULL DEFAULT 1
                           COMMENT '1=Parte1 (selección profesional), 2=Parte2 (culminación)',
  `estado`                 ENUM('pendiente','aprobado') NOT NULL DEFAULT 'pendiente',
  `fecha_aprobacion`       datetime NULL DEFAULT NULL,
  `aprobado_por`           INT DEFAULT NULL COMMENT 'FK → usuario que aprobó (director)',
  `observaciones`          TEXT DEFAULT NULL COMMENT 'Notas sobre la revisión presencial',
  `created_at`             datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`             datetime NOT NULL DEFAULT current_timestamp()
                           ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_autorizacion`),
  UNIQUE KEY `uniq_eap_proceso` (`cod_proceso`),
  KEY `idx_eap_estado` (`estado`),
  KEY `idx_eap_subpaso` (`sub_paso`),
  KEY `idx_eap_profesional` (`cod_profesional`),
  CONSTRAINT `examen_autorizacion_proceso_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`)
    ON DELETE CASCADE,
  CONSTRAINT `examen_autorizacion_proceso_profesional_fk`
    FOREIGN KEY (`cod_profesional`) REFERENCES `examen_profesional_calificado` (`cod_profesional`)
    ON DELETE SET NULL,
  CONSTRAINT `examen_autorizacion_proceso_aprobado_fk`
    FOREIGN KEY (`aprobado_por`) REFERENCES `usuario` (`cod_usuario`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Estado del paso 6 por proceso de graduación (2 sub-pasos)';


-- ------------------------------------------------------------
-- RESTAURAR CONFIGURACIÓN ORIGINAL
-- ------------------------------------------------------------
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
