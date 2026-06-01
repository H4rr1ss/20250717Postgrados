-- ============================================================
-- MÓDULO GRADUACIÓN — Esquema completo de base de datos
-- Base de datos: db_postgrados
-- Fecha de actualización: 2026-05-27
-- Versión: 2.0 (Ternas independientes por fase)
--
-- CONTENIDO:
--   - Paso 1-4: Examen Privado (papelería, documentación, terna, notificación)
--   - Paso 5: Carta de Examinadores (corrección ciclos, cartas)
--   - Paso 6: Autorización de Impresión del Proyecto
--   - Paso 1-4: Examen General (papelería, documentación, terna, notificación)
--
-- Tablas incluidas: 22 tablas
--   - 12 tablas base (examen_tipo, examen_paso_catalogo, examen_proceso, etc.)
--   - 4 tablas paso 5 (carta examinadores)
--   - 6 tablas paso 6 (autorización impresión)
--
-- NOTA IMPORTANTE:
--   La terna de examinadores ahora es INDEPENDIENTE por fase:
--   - fase = 'examen_privado' → terna del examen privado
--   - fase = 'examen_general' → terna del examen general
--   Ver tabla examen_terna, columna 'fase'
--
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
  `instrucciones_entrega_fisica` text DEFAULT NULL COMMENT 'Instrucciones generales para entrega de documentos fisicos (vigencias, restricciones, etc.)',
  `activo`          tinyint(1) NOT NULL DEFAULT 1,
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
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

LOCK TABLES `examen_paso_catalogo` WRITE;
INSERT INTO `examen_paso_catalogo`
  (`cod_tipo_examen`, `numero_orden`, `fase`, `nombre`, `fecha_finalizado`, `template_parcial`, `es_ultimo_paso`) VALUES
  -- Examen Privado
  (NULL, 1, 'examen_privado', 'Revisión de Papelería',           '0', 'paso1-papeleria',     0),
  (NULL, 2, 'examen_privado', 'Entrega de Documentación Física', '0', 'paso2-documentacion', 0),
  (NULL, 3, 'examen_privado', 'Terna Examinadora',               '0', 'paso3-terna',         0),
  (NULL, 4, 'examen_privado', 'Notificación al Estudiante',      '0', 'paso4-notificacion',  0),
  -- Examen General (carta_examinadores se inserta en modulo_graduacion_carta_01_schema.sql)
  (NULL, 1, 'examen_general', 'Revisión de Papelería',           '0', 'paso1-papeleria',     0),
  (NULL, 2, 'examen_general', 'Entrega de Documentación Física', '0', 'paso2-documentacion', 0),
  (NULL, 3, 'examen_general', 'Terna Examinadora',               '0', 'paso3-terna',         0),
  (NULL, 4, 'examen_general', 'Notificación al Estudiante',      '0', 'paso4-notificacion',  1);
UNLOCK TABLES;


-- ------------------------------------------------------------
-- 3. examen_proceso
--    Registro maestro de cada proceso de examen por estudiante.
--    Contiene el paso actual y las fechas de ambos exámenes.
--    cod_paso_actual = NULL indica proceso cerrado o cancelado.
--
--    Las fechas/horas están separadas por fase porque un proceso
--    de graduación tiene dos exámenes con fechas diferentes:
--      - Examen privado (fase examen_privado, pasos 1-4)
--      - Examen general/público (fase examen_general, pasos 1-4)
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
  `archivo_apoyo`    varchar(255) DEFAULT NULL COMMENT 'Ruta relativa al archivo de apoyo (formulario, instructivo, etc.)',
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

-- Semilla: requisitos por tipo de examen y fase.
--
-- IMPORTANTE - Relación entre tipos de examen y fases:
--   - Tipo 1 (Privado General) y Tipo 2 (Privado Gerencia):
--     Sus requisitos se vinculan a cod_paso 1 y 2 (fase examen_privado).
--   - Tipo 3 (Público General):
--     Sus requisitos se vinculan a cod_paso 6 y 7 (fase examen_general,
--     que corresponde a numero_orden 1 y 2 dentro de esa fase).
--     El tipo 3 aplica a todas las maestrías en la fase examen_general.
--
-- Las siguientes son semillas de ejemplo. En producción, el director
-- configura los requisitos desde la interfaz administrativa.
--
LOCK TABLES `examen_requisito_documento` WRITE;
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  -- ── Tipo 1 (Privado General) — Fase examen_privado ─────────
  -- Documentos digitales: paso 1 de examen_privado (cod_paso = 1)
  (1, 1, 'Recibo de Pago',
   'Comprobante de pago de los derechos de examen de graduación.',
   'digital', 'pdf,jpg,png', 5, 1),
  (1, 1, 'Constancia de Cierre de Pensum',
   'Constancia emitida por la coordinación que acredita el cierre total del pensum de estudios.',
   'digital', 'pdf', 5, 2),
  (1, 1, 'Ejemplar del Trabajo de Graduación',
   'Versión digital del trabajo de graduación en formato PDF.',
   'digital', 'pdf', 30, 3),

  -- ── Tipo 2 (Privado Gerencia) — Fase examen_privado ────────
  -- Documentos digitales: paso 1 de examen_privado (cod_paso = 1)
  (2, 1, 'Factura de Impresión',
   'Factura emitida por la imprenta que realizó los empastados.',
   'digital', 'pdf,jpg,png', 5, 1),
  (2, 1, 'Certificación de Notas',
   'Certificación oficial de todas las notas obtenidas durante el programa.',
   'digital', 'pdf', 5, 2),

  -- ── Tipo 3 (Público General) — Fase examen_general ─────────
  -- Paso 1 (cod_paso = 5): Revisión de Papelería — Requisitos digitales
  (3, 5, 'Empastados (2 ejemplares)',
   'Dos ejemplares empastados del trabajo de graduación.',
   'digital', 'pdf', 10, 1),
  (3, 5, 'CD con versión digital',
   'CD con la versión digital del trabajo de graduación.',
   'digital', 'pdf', 10, 2),
  (3, 5, 'Carta de Autorización de Publicación',
   'Carta de autorización para publicar el trabajo en el repositorio.',
   'digital', 'pdf', 5, 3),
  -- Paso 2 (cod_paso = 6): Entrega de Documentación Física — Mismos documentos, entrega física
  -- (3, 6, 'Empastados (2 ejemplares)',
  --  'Versión digital de los empastados del trabajo de graduación.',
  --  'digital', 'pdf,jpg,png', 10, 1),
  -- (3, 6, 'CD con versión digital',
  --  'Imagen o scan del CD con la versión digital del trabajo.',
  --  'digital', 'pdf,jpg,png', 5, 2),
  -- (3, 6, 'Carta de Autorización de Publicación',
  --  'Carta firmada autorizando la publicación del trabajo de graduación.',
  --  'digital', 'pdf', 5, 3);
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
-- 8. archivo_local
--    Metadata de archivos locales separada de la lógica de negocio. Las
--    URLs de los archivos pueden cambiar/expirar sin afectar la tabla
--    examen_documento. El backend inyecta el enlace en la
--    respuesta HTTP; nunca se expone directamente al usuario.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `archivo_local`;
CREATE TABLE `archivo_local` (
  `cod_archivo`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_documento` int(11) unsigned NOT NULL,
  `nombre_md5`    varchar(32) NOT NULL COMMENT 'Hash MD5 = nombre físico del archivo en disk/archivos/',
  `extension`     varchar(10) NOT NULL COMMENT 'Sin punto: pdf, jpg, png',
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_archivo`),
  UNIQUE KEY `unique_al_documento` (`cod_documento`),
  UNIQUE KEY `unique_al_nombre`    (`nombre_md5`),
  CONSTRAINT `archivo_local_documento_fk`
    FOREIGN KEY (`cod_documento`) REFERENCES `examen_documento` (`cod_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


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
-- 11. examen_terna
--     Examinadores asignados al proceso (Paso 3). Un registro
--     por posición y por fase. Ahora las ternas son INDEPENDIENTES:
--     - Examen privado tiene su propia terna (fase = 'examen_privado')
--     - Examen general tiene su propia terna (fase = 'examen_general')
--     Esto permite que sean examinadores diferentes en cada fase.
--     Las fechas/horas de cada examen se almacenan por separado
--     en examen_proceso (fecha_examen_privado, fecha_examen_general).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `examen_terna`;
CREATE TABLE `examen_terna` (
  `cod_terna`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`        int(11) unsigned NOT NULL,
  `fase`               enum('examen_privado','examen_general') NOT NULL DEFAULT 'examen_privado' COMMENT 'Distingue la terna del examen privado vs la del examen general',
  `nombre_examinador`  varchar(200) NOT NULL,
  `numero_colegiado`   varchar(50) DEFAULT NULL,
  `correo`             varchar(150) DEFAULT NULL COMMENT 'Para notificaciones futuras',
  `tipo_examinador`    enum('interno','externo') NOT NULL DEFAULT 'externo' COMMENT 'Distingue si es personal de la institución o externo',
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
-- El seguimiento de correcciones se realiza por correo electrónico
-- EXTERNO a la plataforma. En la plataforma sólo se guardan evidencias
-- (capturas/PDFs de correos) como bitácora del seguimiento.

-- ------------------------------------------------------------
-- 13. examen_correccion_ciclo
--     Ciclo interno único por proceso. El usuario NUNCA ve el
--     concepto de "ciclo" en la plataforma. Se crea automáticamente
--     el primer ciclo al entrar al paso 5 (iniciarPasoCarta) y se
--     mantiene en estado 'pendiente_revision' hasta que el director
--     aprueba el trabajo. El campo observaciones queda NULL.
--
--     El intercambio de correcciones ocurre FUERA de la plataforma
--     (correo electrónico). El estudiante registra evidencias
--     (capturas/PDFs) en examen_correccion_evidencia como bitácora
--     del seguimiento.
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
-- 14. examen_correccion_evidencia
--     Adjuntos (capturas de correos) por ciclo. Cada captura es una
--     imagen o pdf pequeño que evidencia la comunicación con el
--     estudiante. Los archivos físicos se guardan en
--     public/archivos/ con nombre MD5 (mismo patrón que el resto
--     del módulo).
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
-- 15. examen_carta_plantilla
--     Catálogo de plantillas .docx con merge fields (PHPWord
--     TemplateProcessor).
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
-- 16. examen_carta_examinadores
--     Carta generada (una por proceso). Se crea al aprobar el ciclo.
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


-- ============================================================
-- PASO 6: AUTORIZACIÓN DE IMPRESIÓN DEL PROYECTO
-- ============================================================

-- ------------------------------------------------------------
-- 17. examen_autorizacion_config
--     Configuración GLOBAL de instrucciones (un único registro).
--     El director edita los bloques de texto que el estudiante visualiza
--     en cada parte del paso 6:
--       - Parte 1: Autorización de Imprímase
--       - Parte 2: Instructivo para pagos correspondientes a examen público y procedimiento para solicitud de fecha.
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

-- Seed: fila única (cod_config = 1) que se editará vía UPDATE
INSERT INTO `examen_autorizacion_config` (`cod_config`, `instrucciones_parte1`, `instrucciones_parte2`, `updated_by`)
VALUES (1, NULL, NULL, NULL);


-- ------------------------------------------------------------
-- 18. examen_autorizacion_documento_soporte
--     Documentos GLOBALES de soporte que el estudiante puede descargar.
--     Ejemplos: logotipo de universidad, escudo, guía visual.
--     Archivos físicos se guardan en:
--       public/archivos/autorizacion_impresion/documentos_soporte/<md5>.<ext>
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
-- 19. examen_profesional_calificado
--     Licenciados en letras calificados (catálogo GLOBAL).
--     El estudiante selecciona uno de esta lista durante el paso 6.
--     NOTA: por requerimiento, NO se almacena número de colegiado.
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
-- 20. examen_carta_descarga
--     Cartas tipo GENÉRICAS en formato .docx que el director sube y
--     el estudiante descarga. NO se generan dinámicamente.
--     Archivos físicos se guardan en:
--       public/archivos/autorizacion_impresion/cartas_descarga/<md5>.<ext>
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
-- 21. examen_junta_directiva
--     Miembros de la junta directiva (información extra GLOBAL).
--     El director realiza CRUD. El estudiante sólo los visualiza.
--     No se usa en lógica de proceso; es estrictamente informativo.
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
-- 22. examen_autorizacion_proceso
--     Estado POR PROCESO del paso 6.
--     Registra: profesional seleccionado por el estudiante y
--     aprobación final del director (revisión presencial).
--
--     El paso 6 tiene 2 sub-pasos (partes):
--       - Parte 1 (sub_paso=1): Estudiante selecciona profesional,
--                               director aprueba revisión presencial.
--       - Parte 2 (sub_paso=2): Preparación final para examen general,
--                               director confirma culminación y avanza.
--
--     Reglas de negocio para aprobar Parte 1:
--       - El proceso debe estar en fase 'autorizacion_impresion'
--       - cod_profesional IS NOT NULL (estudiante ya seleccionó)
--     Reglas de negocio para aprobar Parte 2:
--       - El proceso debe estar en fase 'autorizacion_impresion'
--       - sub_paso = 2
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


-- ============================================================
-- SEMILLAS OPCIONALES (ejemplos de datos iniciales)
-- ============================================================
-- Nota: Estos seeds son opcionales. En producción el director
-- configurará estos catálogos desde la interfaz administrativa.

-- Licenciados en Letras Calificados (catálogo inicial)
-- INSERT INTO `examen_profesional_calificado`
--   (`nombre_completo`, `correo`, `telefono`, `activo`, `creado_por`)
-- VALUES
--   ('Lic. Virsa Valenzuela', 'virvalen@hotmail.com', '5982-4483', 1, 1),
--   ('Lic. Carlos Antonio Mendoza Estrada', 'cmendoza@correo.edu.gt', '5421-8932', 1, 1);

-- Miembros de Junta Directiva (ejemplo)
-- INSERT INTO `examen_junta_directiva`
--   (`nombre_completo`, `puesto`, `activo`, `creado_por`)
-- VALUES
--   ('Dra. Ana Lucía Fernández Contreras', 'Presidenta de Junta Directiva', 1, 1),
--   ('Dr. Miguel Ángel Soto Estrada', 'Secretario General', 1, 1);


/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;