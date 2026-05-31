-- ============================================================
-- MÓDULO GRADUACIÓN — SCHEMA COMPLETO (solo CREATE TABLE + ALTER)
-- Base de datos: db_postgrados
-- Fecha de actualización: 2026-06-01
-- Versión: 3.0 (Examen General simplificado a 2 pasos)
--
-- CONTENIDO:
--   Fases 1-4: Examen Privado (4 pasos) + Examen General (2 pasos)
--   Fase 5: Carta de Examinadores (corrección ciclos + evidencias)
--   Fase 6: Autorización de Impresión del Proyecto
--
-- NOTA: Ya no se generan cartas dinámicamente. Las plantillas .docx
-- se descargan directamente desde: data/graduacion/plantillas/carta-examinadores/
-- Por tanto, las tablas examen_carta_plantilla y examen_carta_examinadores
-- fueron removidas del schema.
--
-- TABLAS: 19 tablas en orden de dependencias
--
-- INSTRUCCIONES:
--   Ejecutar DESPUÉS de que ya existan las tablas base del sistema
--   (usuario, rol, accion, etc.) que están en 20250718Postgrados.sql
-- ============================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- ------------------------------------------------------------
-- 1. examen_tipo
--    Catálogo de tipos de examen.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_tipo`;
CREATE TABLE `examen_tipo` (
  `cod_tipo_examen` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `nombre`          varchar(100) NOT NULL,
  `descripcion`     text DEFAULT NULL,
  `instrucciones_entrega_fisica` text DEFAULT NULL COMMENT 'Instrucciones generales para entrega de documentos fisicos',
  `activo`          tinyint(1) NOT NULL DEFAULT 1,
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_tipo_examen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 2. examen_paso_catalogo
--    Define los pasos de cada tipo de examen.
--    examen_general: ahora solo 2 pasos (paso 2 es último).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_paso_catalogo`;
CREATE TABLE `examen_paso_catalogo` (
  `cod_paso`        tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `cod_tipo_examen` tinyint(3) unsigned DEFAULT NULL,
  `numero_orden`    tinyint(3) unsigned NOT NULL,
  `fase`            ENUM('examen_privado','carta_examinadores','autorizacion_impresion','examen_general')
                    NOT NULL DEFAULT 'examen_privado',
  `nombre`          varchar(150) NOT NULL,
  `fecha_finalizado`     text DEFAULT NULL,
  `template_parcial` varchar(100) DEFAULT NULL COMMENT 'Nombre del partial de vista (sin extensión)',
  `es_ultimo_paso`  tinyint(1) NOT NULL DEFAULT 0,
  `activo`          tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`cod_paso`),
  UNIQUE KEY `unique_tipo_fase_orden` (`cod_tipo_examen`, `fase`, `numero_orden`),
  CONSTRAINT `examen_paso_catalogo_examen_tipo_fk`
    FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 3. examen_proceso
--    Registro maestro de cada proceso de examen por estudiante.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_proceso`;
CREATE TABLE `examen_proceso` (
  `cod_proceso`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_usuario`          int(11) NOT NULL COMMENT 'FK → usuario (estudiante)',
  `cod_tipo_examen`      tinyint(3) unsigned NOT NULL,
  `cod_paso_actual`      tinyint(3) unsigned DEFAULT NULL COMMENT 'NULL = proceso cerrado',
  `fecha_examen_privado` date DEFAULT NULL COMMENT 'Fecha programada del examen privado',
  `hora_examen_privado`  time DEFAULT NULL COMMENT 'Hora de inicio del examen privado',
  `fecha_examen_general` date DEFAULT NULL COMMENT 'Fecha programada del examen general (público)',
  `hora_examen_general`  time DEFAULT NULL COMMENT 'Hora de inicio del examen general (público)',
  `fecha_solicitud`      datetime NOT NULL DEFAULT current_timestamp(),
  `cancelado`            tinyint(1) NOT NULL DEFAULT 0,
  `fecha_cancelacion` timestamp NULL DEFAULT NULL,
  `motivo_cancelacion`   text DEFAULT NULL,
  `registrado_por`       int(11) NOT NULL COMMENT 'FK → usuario (staff)',
  `created_at`           datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`       datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_proceso`),
  KEY `idx_ep_usuario`       (`cod_usuario`),
  KEY `idx_ep_tipo`          (`cod_tipo_examen`),
  KEY `idx_ep_paso_actual`   (`cod_paso_actual`),
  KEY `idx_ep_cancelado`     (`cancelado`),
  KEY `idx_ep_fecha`         (`fecha_solicitud`),
  CONSTRAINT `examen_proceso_usuario_fk`
    FOREIGN KEY (`cod_usuario`) REFERENCES `usuario` (`cod_usuario`),
  CONSTRAINT `examen_proceso_tipo_fk`
    FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`),
  CONSTRAINT `examen_proceso_paso_actual_fk`
    FOREIGN KEY (`cod_paso_actual`) REFERENCES `examen_paso_catalogo` (`cod_paso`),
  CONSTRAINT `examen_proceso_registrado_por_fk`
    FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 4. examen_proceso_paso
--    Estado de cada paso dentro de cada proceso.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_proceso_paso`;
CREATE TABLE `examen_proceso_paso` (
  `cod_proceso_paso` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`      int(11) unsigned NOT NULL,
  `cod_paso`         tinyint(3) unsigned NOT NULL,
  `estado`           enum('pendiente','en_progreso','completado','rechazado') NOT NULL DEFAULT 'pendiente',
  `fecha_inicio`     datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_completado` datetime NULL DEFAULT NULL COMMENT 'Deadline implícito: SET cuando el staff avanza al siguiente paso',
  `completado_por`   int(11) DEFAULT NULL COMMENT 'FK → usuario (staff)',
  `observaciones`    text DEFAULT NULL,
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_proceso_paso`),
  UNIQUE KEY `unique_epp_proceso_paso` (`cod_proceso`, `cod_paso`),
  KEY `idx_epp_estado`            (`estado`),
  KEY `idx_epp_fecha_completado`  (`fecha_completado`),
  CONSTRAINT `examen_proceso_paso_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_proceso_paso_catalogo_fk`
    FOREIGN KEY (`cod_paso`) REFERENCES `examen_paso_catalogo` (`cod_paso`),
  CONSTRAINT `examen_proceso_paso_usuario_fk`
    FOREIGN KEY (`completado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 5. examen_requisito_documento
--    Catálogo de documentos exigidos por paso.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_requisito_documento`;
CREATE TABLE `examen_requisito_documento` (
  `cod_requisito`    smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `cod_tipo_examen`  tinyint(3) unsigned DEFAULT NULL COMMENT 'NULL = aplica a todos los tipos',
  `cod_paso`         tinyint(3) unsigned NOT NULL,
  `nombre`           varchar(200) NOT NULL,
  `descripcion`      text DEFAULT NULL COMMENT 'Instrucciones visibles al estudiante',
  `tipo_entrega`     enum('digital','fisico') NOT NULL DEFAULT 'digital',
  `obligatorio`      tinyint(1) NOT NULL DEFAULT 1,
  `formatos_permitidos` varchar(100) DEFAULT NULL COMMENT 'Ej: pdf,jpg,png',
  `tamano_max_mb`    tinyint(3) unsigned NOT NULL DEFAULT 10,
  `archivo_apoyo`    varchar(255) DEFAULT NULL COMMENT 'Nombre del archivo de apoyo (guardado en data/graduacion/global/requisitos-apoyo/)',
  `orden_display`    tinyint(3) unsigned NOT NULL DEFAULT 1,
  `activo`           tinyint(1) NOT NULL DEFAULT 1,
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_requisito`),
  KEY `idx_erd_paso_activo` (`cod_paso`, `activo`),
  CONSTRAINT `examen_requisito_tipo_fk`
    FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`),
  CONSTRAINT `examen_requisito_paso_fk`
    FOREIGN KEY (`cod_paso`) REFERENCES `examen_paso_catalogo` (`cod_paso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 6. examen_documento
--    Archivos subidos por el estudiante (versionado).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_documento`;
CREATE TABLE `examen_documento` (
  `cod_documento`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`     int(11) unsigned NOT NULL,
  `cod_requisito`   smallint(5) unsigned NOT NULL,
  `version`         tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Se incrementa en cada resubida',
  `es_version_actual` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = versión vigente; 0 = versión histórica',
  `archivo_nombre`   varchar(200) DEFAULT NULL COMMENT 'Google Drive fileId',
  `nombre_original` varchar(255) DEFAULT NULL COMMENT 'Nombre del archivo tal como lo subió el usuario',
  `mime_type`       varchar(100) DEFAULT NULL,
  `tamano_bytes`    bigint(20) unsigned DEFAULT NULL,
  `checksum_sha256` varchar(64) DEFAULT NULL COMMENT 'Para verificar integridad',
  `subido_por`      int(11) NOT NULL COMMENT 'FK → usuario (estudiante)',
  `fecha_subida`    timestamp NOT NULL DEFAULT current_timestamp(),
  `eliminado`       tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Soft delete',
  `eliminado_por`   int(11) DEFAULT NULL,
  `fecha_eliminacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`cod_documento`),
  UNIQUE KEY `unique_ed_proceso_req_version` (`cod_proceso`, `cod_requisito`, `version`),
  KEY `idx_ed_archivo_nombre`          (`archivo_nombre`),
  KEY `idx_ed_eliminado`           (`eliminado`),
  KEY `idx_ed_version_actual`      (`cod_proceso`, `cod_requisito`, `es_version_actual`),
  CONSTRAINT `examen_documento_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_documento_requisito_fk`
    FOREIGN KEY (`cod_requisito`) REFERENCES `examen_requisito_documento` (`cod_requisito`),
  CONSTRAINT `examen_documento_subido_por_fk`
    FOREIGN KEY (`subido_por`) REFERENCES `usuario` (`cod_usuario`),
  CONSTRAINT `examen_documento_eliminado_por_fk`
    FOREIGN KEY (`eliminado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 7. archivo_local
--    Metadata de archivos locales.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `archivo_local`;
CREATE TABLE `archivo_local` (
  `cod_archivo`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_documento` int(11) unsigned NOT NULL,
  `nombre_md5`    varchar(32) NOT NULL COMMENT 'Hash MD5 = nombre físico del archivo',
  `extension`     varchar(10) NOT NULL COMMENT 'Sin punto: pdf, jpg, png',
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_archivo`),
  UNIQUE KEY `unique_al_documento` (`cod_documento`),
  UNIQUE KEY `unique_al_nombre`    (`nombre_md5`),
  CONSTRAINT `archivo_local_documento_fk`
    FOREIGN KEY (`cod_documento`) REFERENCES `examen_documento` (`cod_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ------------------------------------------------------------
-- 8. examen_revision_documento
--    Decisión del staff sobre cada documento (Paso 1).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_revision_documento`;
CREATE TABLE `examen_revision_documento` (
  `cod_revision`  int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_documento` int(11) unsigned NOT NULL,
  `cod_proceso`   int(11) unsigned NOT NULL COMMENT 'Desnormalizado para queries frecuentes',
  `cod_requisito` smallint(5) unsigned NOT NULL COMMENT 'Desnormalizado para queries frecuentes',
  `estado`        enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `motivo_rechazo` text DEFAULT NULL COMMENT 'Requerido cuando estado = rechazado',
  `revisado_por`  int(11) NOT NULL COMMENT 'FK → usuario (staff)',
  `fecha_revision` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_revision`),
  KEY `idx_erd_estado`               (`estado`),
  KEY `idx_erd_proceso_requisito`    (`cod_proceso`, `cod_requisito`),
  CONSTRAINT `examen_revision_documento_fk`
    FOREIGN KEY (`cod_documento`) REFERENCES `examen_documento` (`cod_documento`),
  CONSTRAINT `examen_revision_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_revision_requisito_fk`
    FOREIGN KEY (`cod_requisito`) REFERENCES `examen_requisito_documento` (`cod_requisito`),
  CONSTRAINT `examen_revision_usuario_fk`
    FOREIGN KEY (`revisado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 9. examen_documento_fisico
--     Checklist de recepción de documentos físicos (Paso 2).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_documento_fisico`;
CREATE TABLE `examen_documento_fisico` (
  `cod_doc_fisico`  int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`     int(11) unsigned NOT NULL,
  `cod_requisito`   smallint(5) unsigned NOT NULL COMMENT 'Solo requisitos con tipo_entrega = fisico',
  `recibido`        tinyint(1) NOT NULL DEFAULT 0,
  `fecha_recepcion` timestamp NULL DEFAULT NULL,
  `recibido_por`    int(11) DEFAULT NULL COMMENT 'FK → usuario (staff)',
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`       datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_doc_fisico`),
  UNIQUE KEY `unique_edf_proceso_req` (`cod_proceso`, `cod_requisito`),
  CONSTRAINT `examen_doc_fisico_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_doc_fisico_requisito_fk`
    FOREIGN KEY (`cod_requisito`) REFERENCES `examen_requisito_documento` (`cod_requisito`),
  CONSTRAINT `examen_doc_fisico_usuario_fk`
    FOREIGN KEY (`recibido_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 10. examen_terna
--     Examinadores asignados al proceso (Paso 3).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_terna`;
CREATE TABLE `examen_terna` (
  `cod_terna`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`        int(11) unsigned NOT NULL,
  `fase`               enum('examen_privado','examen_general') NOT NULL DEFAULT 'examen_privado' COMMENT 'Distingue la terna del examen privado vs la del examen general',
  `nombre_examinador`  varchar(200) NOT NULL,
  `numero_colegiado`   varchar(50) DEFAULT NULL,
  `correo`             varchar(150) DEFAULT NULL COMMENT 'Para notificaciones futuras',
  `tipo_examinador`    enum('interno','externo') NOT NULL DEFAULT 'externo',
  `posicion`           tinyint(1) unsigned NOT NULL,
  `registrado_por`     int(11) NOT NULL COMMENT 'FK → usuario (staff)',
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`       datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_terna`),
  UNIQUE KEY unique_proceso_fase_posicion (cod_proceso, fase, posicion),
  CONSTRAINT `examen_terna_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_terna_usuario_fk`
    FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 11. examen_historial
--     Tabla de auditoría inmutable.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_historial`;
CREATE TABLE `examen_historial` (
  `cod_historial`    bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'BIGINT para auditoría de alto volumen',
  `cod_proceso`      int(11) unsigned NOT NULL,
  `cod_usuario`      int(11) NOT NULL COMMENT 'FK → usuario (actor de la acción)',
  `tipo_evento`      enum(
                       'avance_paso',
                       'retroceso_paso',
                       'subida_documento',
                       'revision_documento',
                       'rechazo_documento',
                       'asignacion_terna',
                       'cancelacion',
                       'reactivacion',
                       'otro'
                     ) NOT NULL,
  `descripcion`      text DEFAULT NULL COMMENT 'Mensaje legible por humanos',
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
                     CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos`     longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
                     CHECK (json_valid(`datos_nuevos`)),
  `ip_address`       varchar(45) DEFAULT NULL COMMENT 'Soporta IPv4 e IPv6',
  `user_agent`       varchar(300) DEFAULT NULL,
  `created_at`       datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Sin updated_at — registro inmutable',
  PRIMARY KEY (`cod_historial`),
  KEY `idx_eh_proceso`            (`cod_proceso`),
  KEY `idx_eh_usuario`            (`cod_usuario`),
  KEY `idx_eh_tipo_evento`        (`tipo_evento`),
  KEY `idx_eh_created_at`         (`created_at`),
  KEY `idx_eh_proceso_fecha`      (`cod_proceso`, `created_at`),
  CONSTRAINT `examen_historial_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_historial_usuario_fk`
    FOREIGN KEY (`cod_usuario`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
-- PASO 5: CARTA DE EXAMINADORES
-- ============================================================

-- ------------------------------------------------------------
-- 12. examen_correccion_ciclo
--     Ciclo interno único por proceso.
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
-- 13. examen_correccion_evidencia
--     Adjuntos (capturas de correos) por ciclo.
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


-- ============================================================
-- PASO 6: AUTORIZACIÓN DE IMPRESIÓN DEL PROYECTO
-- ============================================================

-- ------------------------------------------------------------
-- 14. examen_autorizacion_config
--     Configuración GLOBAL de instrucciones (un único registro).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_autorizacion_config`;
CREATE TABLE `examen_autorizacion_config` (
  `cod_config`            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `instrucciones_parte1`  TEXT DEFAULT NULL COMMENT 'Instrucciones Parte 1: Autorización de Imprímase',
  `instrucciones_parte2`  TEXT DEFAULT NULL COMMENT 'Instrucciones Parte 2: Entrega de Proyecto de Graduación',
  `updated_at`            datetime NOT NULL DEFAULT current_timestamp()
                          ON UPDATE current_timestamp(),
  `updated_by`            INT DEFAULT NULL COMMENT 'FK → usuario que modificó',
  PRIMARY KEY (`cod_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Configuración global del paso 6: instrucciones por parte';


-- ------------------------------------------------------------
-- 15. examen_autorizacion_documento_soporte
--     Documentos GLOBALES de soporte.
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
-- 16. examen_profesional_calificado
--     Licenciados en letras calificados (catálogo GLOBAL).
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
-- 17. examen_carta_descarga
--     Cartas tipo GENÉRICAS en formato .docx.
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
-- 18. examen_junta_directiva
--     Miembros de la junta directiva (informativo).
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
-- 19. examen_autorizacion_proceso
--     Estado POR PROCESO del paso 6.
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


/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;