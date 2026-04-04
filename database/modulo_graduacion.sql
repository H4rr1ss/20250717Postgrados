-- ============================================================
-- MÓDULO EXAMEN — Esquema de base de datos
-- Base de datos: db_postgrados
-- Creado: 2026-02-22
-- Tablas: 12 (excluye examen_notificacion)
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
--    Catálogo de tipos de examen (Privado General, Privado
--    Gerencia, Público General). Permite tipos distintos con
--    sus propios pasos y requisitos.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_tipo`;
CREATE TABLE `examen_tipo` (
  `cod_tipo_examen` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `nombre`          varchar(100) NOT NULL,
  `descripcion`     text DEFAULT NULL,
  `activo`          tinyint(1) NOT NULL DEFAULT 1,
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_tipo_examen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `examen_tipo` WRITE;
INSERT INTO `examen_tipo` (`nombre`, `descripcion`) VALUES
  ('Privado General',  'Examen privado para estudiantes regulares de postgrado'),
  ('Privado Gerencia', 'Examen privado para la Maestría en Gestión de Programas y Proyectos de Desarrollo'),
  ('Público General',  'Examen público abierto a la comunidad académica');
UNLOCK TABLES;


-- ------------------------------------------------------------
-- 2. examen_paso_catalogo
--    Define los pasos de cada tipo de examen y su orden de
--    ejecución. cod_tipo_examen NULL significa que el paso
--    aplica a todos los tipos.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_paso_catalogo`;
CREATE TABLE `examen_paso_catalogo` (
  `cod_paso`        tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `cod_tipo_examen` tinyint(3) unsigned DEFAULT NULL,
  `numero_orden`    tinyint(3) unsigned NOT NULL,
  `nombre`          varchar(150) NOT NULL,
  `fecha_finalizado`     text DEFAULT NULL,
  `template_parcial` varchar(100) DEFAULT NULL COMMENT 'Nombre del partial de vista (sin extensión)',
  `es_ultimo_paso`  tinyint(1) NOT NULL DEFAULT 0,
  `activo`          tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`cod_paso`),
  UNIQUE KEY `unique_tipo_orden` (`cod_tipo_examen`, `numero_orden`),
  CONSTRAINT `examen_paso_catalogo_examen_tipo_fk`
    FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `examen_paso_catalogo` WRITE;
INSERT INTO `examen_paso_catalogo`
  (`cod_tipo_examen`, `numero_orden`, `nombre`, `fecha_finalizado`, `template_parcial`, `es_ultimo_paso`) VALUES
  (NULL, 1, 'Revisión de Papelería', '0', 'paso1-papeleria', 0),
  (NULL, 2, 'Entrega de Documentación Física', '0', 'paso2-documentacion', 0),
  (NULL, 3, 'Terna Examinadora', '0', 'paso3-terna', 0),
  (NULL, 4, 'Notificación al Estudiante', '0', 'paso4-notificacion', 1);
UNLOCK TABLES;


-- ------------------------------------------------------------
-- 3. examen_proceso
--    Registro maestro de cada proceso de examen por estudiante.
--    Contiene el folderId de Google Drive y el paso actual.
--    cod_paso_actual = NULL indica proceso cerrado o cancelado.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_proceso`;
CREATE TABLE `examen_proceso` (
  `cod_proceso`        int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_usuario`        int(11) NOT NULL COMMENT 'FK → usuario (estudiante)',
  `cod_tipo_examen`    tinyint(3) unsigned NOT NULL,
  `cod_paso_actual`    tinyint(3) unsigned DEFAULT NULL COMMENT 'NULL = proceso cerrado',
  `drive_folder_id`    varchar(200) DEFAULT NULL COMMENT 'Google Drive folder ID',
  `fecha_solicitud`    date NOT NULL,
  `fecha_solicitud`    timestamp NOT NULL DEFAULT current_timestamp(),
  `cancelado`          tinyint(1) NOT NULL DEFAULT 0,
  `fecha_cancelacion`  timestamp NULL DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL,
  `registrado_por`     int(11) NOT NULL COMMENT 'FK → usuario (staff)',
  `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
-- 4. drive_carpeta
--    Una carpeta de Google Drive por proceso. El backend
--    (Service Account) la crea al iniciar el proceso. Esta
--    tabla es la fuente de verdad; Drive es sólo almacenamiento.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `drive_carpeta`;
CREATE TABLE `drive_carpeta` (
  `cod_carpeta`            int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`            int(11) unsigned NOT NULL,
  `drive_folder_id`        varchar(200) NOT NULL COMMENT 'Google Drive folder ID',
  `drive_folder_name`      varchar(300) DEFAULT NULL,
  `drive_parent_folder_id` varchar(200) DEFAULT NULL COMMENT 'Carpeta raíz del sistema en Drive',
  `creado_por`             int(11) NOT NULL COMMENT 'FK → usuario (staff o sistema)',
  `estado_sincronizacion`  enum('activo','error','eliminado') NOT NULL DEFAULT 'activo',
  `ultima_sincronizacion`  timestamp NULL DEFAULT NULL,
  `created_at`             timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_carpeta`),
  UNIQUE KEY `unique_dc_proceso`    (`cod_proceso`),
  UNIQUE KEY `unique_dc_folder_id`  (`drive_folder_id`),
  CONSTRAINT `drive_carpeta_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `drive_carpeta_usuario_fk`
    FOREIGN KEY (`creado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 5. examen_proceso_paso
--    Estado de cada paso dentro de cada proceso. Gobierna el
--    control de subidas: cuando fecha_completado IS NOT NULL,
--    ese paso está bloqueado y no acepta nuevas subidas.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_proceso_paso`;
CREATE TABLE `examen_proceso_paso` (
  `cod_proceso_paso` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`      int(11) unsigned NOT NULL,
  `cod_paso`         tinyint(3) unsigned NOT NULL,
  `estado`           enum('pendiente','en_progreso','completado','rechazado') NOT NULL DEFAULT 'pendiente',
  `fecha_inicio`     timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_completado` timestamp NULL DEFAULT NULL COMMENT 'Deadline implícito: SET cuando el staff avanza al siguiente paso',
  `completado_por`   int(11) DEFAULT NULL COMMENT 'FK → usuario (staff)',
  `observaciones`    text DEFAULT NULL,
  `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
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
-- 6. examen_requisito_documento
--    Catálogo de documentos exigidos por paso. tipo_entrega
--    distingue documentos digitales (subidos vía plataforma)
--    de documentos físicos (recepción en ventanilla).
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
  `orden_display`    tinyint(3) unsigned NOT NULL DEFAULT 1,
  `activo`           tinyint(1) NOT NULL DEFAULT 1,
  `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_requisito`),
  KEY `idx_erd_paso_activo` (`cod_paso`, `activo`),
  CONSTRAINT `examen_requisito_tipo_fk`
    FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`),
  CONSTRAINT `examen_requisito_paso_fk`
    FOREIGN KEY (`cod_paso`) REFERENCES `examen_paso_catalogo` (`cod_paso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Semilla: documentos digitales del Paso 1 (cod_paso = 1)
LOCK TABLES `examen_requisito_documento` WRITE;
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  (NULL, 1, 'Carta de Solicitud',
   'Carta dirigida al Coordinador solicitando la realización del examen de graduación.',
   'digital', 'pdf', 5, 1),
  (NULL, 1, 'Recibo de Pago',
   'Comprobante de pago de los derechos de examen de graduación.',
   'digital', 'pdf,jpg,png', 5, 2),
  (NULL, 1, 'Constancia de Cierre de Pensum',
   'Constancia emitida por la coordinación que acredita el cierre total del pensum de estudios.',
   'digital', 'pdf', 5, 3),
  (NULL, 1, 'Ejemplar del Trabajo de Graduación',
   'Versión digital del trabajo de graduación en formato PDF.',
   'digital', 'pdf', 30, 4),
  (NULL, 1, 'Factura de Impresión',
   'Factura emitida por la imprenta que realizó los empastados.',
   'digital', 'pdf,jpg,png', 5, 5),
  (NULL, 1, 'Certificación de Notas',
   'Certificación oficial de todas las notas obtenidas durante el programa.',
   'digital', 'pdf', 5, 6),
-- Semilla: documentos físicos del Paso 2 (cod_paso = 2)
  (NULL, 2, 'Empastados (3 ejemplares)',
   'Tres copias empastadas del trabajo de graduación según normas del programa.',
   'fisico', NULL, 0, 1),
  (NULL, 2, 'CD con versión digital',
   'Dos CDs con la versión digital del trabajo de graduación.',
   'fisico', NULL, 0, 2),
  (NULL, 2, 'Carta de Autorización de Publicación',
   'Carta firmada por el estudiante autorizando la publicación del trabajo.',
   'fisico', NULL, 0, 3),
  (NULL, 2, 'Constancia de Cierre de Pensum (original)',
   'Documento original de la constancia de cierre de pensum para el expediente.',
   'fisico', NULL, 0, 4);
UNLOCK TABLES;


-- ------------------------------------------------------------
-- 7. examen_documento
--    Archivos subidos por el estudiante. Soporta versiones:
--    una nueva subida incrementa `version` y los registros
--    anteriores quedan con es_version_actual = 0 para auditoría.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_documento`;
CREATE TABLE `examen_documento` (
  `cod_documento`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`     int(11) unsigned NOT NULL,
  `cod_requisito`   smallint(5) unsigned NOT NULL,
  `version`         tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Se incrementa en cada resubida',
  `es_version_actual` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = versión vigente; 0 = versión histórica',
  `drive_file_id`   varchar(200) DEFAULT NULL COMMENT 'Google Drive fileId',
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
  KEY `idx_ed_drive_file`          (`drive_file_id`),
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
-- 8. drive_archivo
--    Metadata de Drive separada de la lógica de negocio. Las
--    URLs de Drive pueden cambiar/expirar sin afectar la tabla
--    examen_documento. El backend inyecta el enlace en la
--    respuesta HTTP; nunca se expone directamente al usuario.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `drive_archivo`;
CREATE TABLE `drive_archivo` (
  `cod_drive_archivo`      int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_documento`          int(11) unsigned NOT NULL,
  `drive_file_id`          varchar(200) NOT NULL COMMENT 'Google Drive fileId',
  `drive_folder_id`        varchar(200) NOT NULL COMMENT 'Carpeta contenedora en Drive',
  `drive_mime_type`        varchar(100) DEFAULT NULL,
  `drive_web_view_link`    varchar(500) DEFAULT NULL COMMENT 'URL del visor de Drive — solo expuesta vía proxy del backend',
  `drive_web_content_link` varchar(500) DEFAULT NULL COMMENT 'URL de descarga — solo expuesta vía proxy del backend',
  `created_at`             timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`             timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_drive_archivo`),
  UNIQUE KEY `unique_da_documento`   (`cod_documento`),
  UNIQUE KEY `unique_da_file_id`     (`drive_file_id`),
  KEY `idx_da_folder_id`             (`drive_folder_id`),
  CONSTRAINT `drive_archivo_documento_fk`
    FOREIGN KEY (`cod_documento`) REFERENCES `examen_documento` (`cod_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 9. examen_revision_documento
--    Decisión del staff sobre cada documento (Paso 1). Si se
--    rechaza, motivo_rechazo es obligatorio a nivel de negocio.
--    Cada resubida genera una nueva revisión sobre la nueva
--    versión del documento.
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
-- 10. examen_documento_fisico
--     Checklist de recepción de documentos físicos (Paso 2).
--     No involucra subida de archivos — el staff marca
--     cada ítem como recibido al momento de la entrega en
--     ventanilla.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_documento_fisico`;
CREATE TABLE `examen_documento_fisico` (
  `cod_doc_fisico`  int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`     int(11) unsigned NOT NULL,
  `cod_requisito`   smallint(5) unsigned NOT NULL COMMENT 'Solo requisitos con tipo_entrega = fisico',
  `recibido`        tinyint(1) NOT NULL DEFAULT 0,
  `fecha_recepcion` timestamp NULL DEFAULT NULL,
  `recibido_por`    int(11) DEFAULT NULL COMMENT 'FK → usuario (staff)',
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
-- 11. examen_terna
--     Examinadores asignados al proceso (Paso 3). Un registro
--     por rol. También almacena la fecha y hora del examen.
--     Diseñado para 3 examinadores activos (vocal1, secretario,
--     presidente) con roles adicionales preparados para los
--     pasos guardados (vocal2, asesor).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_terna`;
CREATE TABLE `examen_terna` (
  `cod_terna`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`        int(11) unsigned NOT NULL,
  `rol`                enum('presidente','secretario','vocal1','vocal2','asesor') NOT NULL,
  `nombre_examinador`  varchar(200) NOT NULL,
  `numero_colegiado`   varchar(50) DEFAULT NULL,
  `correo`             varchar(150) DEFAULT NULL COMMENT 'Para notificaciones futuras',
  `fecha_examen`       date DEFAULT NULL COMMENT 'Fecha programada del examen oral',
  `hora_inicio`        time DEFAULT NULL,
  `registrado_por`     int(11) NOT NULL COMMENT 'FK → usuario (staff)',
  `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_terna`),
  UNIQUE KEY `unique_et_proceso_rol` (`cod_proceso`, `rol`),
  CONSTRAINT `examen_terna_proceso_fk`
    FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  CONSTRAINT `examen_terna_usuario_fk`
    FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ------------------------------------------------------------
-- 12. examen_historial
--     Tabla de auditoría inmutable. Registra toda acción del
--     proceso. Los campos JSON permiten reconstruir cualquier
--     estado anterior sin tablas adicionales de versiones.
--     Sin campo updated_at para garantizar inmutabilidad.
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
  `created_at`       timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Sin updated_at — registro inmutable',
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


/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;