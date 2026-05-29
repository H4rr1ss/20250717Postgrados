-- ============================================================
-- SEEDS INICIALES - MÓDULO DE GRADUACIÓN
-- ============================================================
-- Archivo: seeds_iniciales.sql
-- Descripción: Datos iniciales necesarios para el funcionamiento
--              del módulo de graduación tras crear las tablas.
--              Ejecutar DESPUÉS de crear todas las tablas.
-- Orden: Respetar el orden para evitar errores de FK.
-- Fecha: Basado en estructura actual de 10 pasos en examen_paso_catalogo
-- ============================================================

-- ------------------------------------------------------------
-- 1. examen_tipo - Tipos de proceso de examen
-- ------------------------------------------------------------
-- Nota: Los códigos 1, 2, 3 son fijos y usados en el código PHP.
--       No modificar los IDs sin actualizar ExamenManager.php
-- ------------------------------------------------------------
INSERT INTO `examen_tipo` (`cod_tipo_examen`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Privado General', 'Modalidad privada para maestrías generales. Dos ternas de examinadores.', 1),
(2, 'Privado Gerencia', 'Modalidad privada exclusiva para Gerencia y Dirección de Empresas. Una terna.', 1),
(3, 'Público General', 'Modalidad pública con evaluación general. Comité calificador.', 1);

-- ------------------------------------------------------------
-- 2. examen_paso_catalogo - Pasos del proceso de graduación
-- ------------------------------------------------------------
-- Estructura de 10 pasos:
--   Pasos 1-4: examen_privado (para tipos 1 y 2)
--   Pasos 5-8: examen_general (para tipo 3)  
--   Paso 9: carta_examinadores
--   Paso 10: autorizacion_impresion
-- ------------------------------------------------------------
INSERT INTO `examen_paso_catalogo` 
(`cod_paso`, `numero_orden`, `nombre`, `descripcion`, `fase`, `requiere_revision_admin`, `activo`) 
VALUES
-- Fase: examen_privado (Tipos 1 y 2) - Pasos 1-4
(1, 1, 'Revisión de Papelería', 
 'El estudiante sube documentación digital para revisión administrativa.', 
 'examen_privado', 1, 1),

(2, 2, 'Entrega de Documentación Física', 
 'El estudiante entrega documentación física en ventanilla.', 
 'examen_privado', 1, 1),

(3, 3, 'Terna Examinadora', 
 'Asignación de examinadores para el examen privado.', 
 'examen_privado', 1, 1),

(4, 4, 'Notificación al Estudiante', 
 'Notificación al estudiante de la fecha y hora del examen privado.', 
 'examen_privado', 0, 1),

-- Fase: examen_general (Tipo 3) - Pasos 5-8
(5, 1, 'Revisión de Papelería', 
 'El estudiante sube documentación digital del trabajo empastado para revisión.', 
 'examen_general', 1, 1),

(6, 2, 'Entrega de Documentación Física', 
 'El estudiante entrega físicamente los empastados, CD y carta de autorización.', 
 'examen_general', 1, 1),

(7, 3, 'Terna Examinadora', 
 'Asignación de examinadores para el examen público general.', 
 'examen_general', 1, 1),

(8, 4, 'Notificación al Estudiante', 
 'Notificación al estudiante de la fecha y hora del examen público general.', 
 'examen_general', 0, 1),

-- Fases transicionales (entre examen privado y general)
(9, 5, 'Carta de Examinadores', 
 'Generación y envío de cartas a los examinadores designados.', 
 'carta_examinadores', 1, 1),

(10, 6, 'Autorización de Impresión del Proyecto', 
 'Autorización para impresión del trabajo de graduación tras aprobación del examen privado.', 
 'autorizacion_impresion', 1, 1);

-- ------------------------------------------------------------
-- 3. examen_requisito_documento - Requisitos de documentos
-- ------------------------------------------------------------
-- IMPORTANTE: Mapeo de requisitos a pasos:
--   - Tipo 1 y 2 (Privado): pasos 1-2 (Revisión digital, Entrega física)
--   - Tipo 3 (General): pasos 5-6 (Revisión digital, Entrega física)
-- ------------------------------------------------------------

-- Tipo 1 (Privado General) - Fase examen_privado - Pasos 1 y 2
INSERT INTO `examen_requisito_documento` 
(`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`, `activo`) 
VALUES
-- Paso 1: Revisión de Papelería (Digital)
(1, 1, 'Recibo de Pago',
 'Comprobante de pago de los derechos de examen de graduación.',
 'digital', 'pdf,jpg,png', 5, 1, 1),
(1, 1, 'Constancia de Cierre de Pensum',
 'Constancia emitida por la coordinación que acredita el cierre total del pensum de estudios.',
 'digital', 'pdf', 5, 2, 1),
(1, 1, 'Ejemplar del Trabajo de Graduación',
 'Versión digital del trabajo de graduación en formato PDF.',
 'digital', 'pdf', 30, 3, 1),

-- Paso 2: Entrega de Documentación Física (Digital - evidencia de entrega)
(1, 2, 'Comprobante de Entrega Física',
 'Documento que acredita la entrega física de la papelería en ventanilla.',
 'digital', 'pdf,jpg', 5, 1, 1);

-- Tipo 2 (Privado Gerencia) - Fase examen_privado - Pasos 1 y 2
INSERT INTO `examen_requisito_documento` 
(`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`, `activo`) 
VALUES
-- Paso 1: Revisión de Papelería (Digital)
(2, 1, 'Factura de Impresión',
 'Factura emitida por la imprenta que realizó los empastados.',
 'digital', 'pdf,jpg,png', 5, 1, 1),
(2, 1, 'Certificación de Notas',
 'Certificación oficial de todas las notas obtenidas durante el programa.',
 'digital', 'pdf', 5, 2, 1),

-- Paso 2: Entrega de Documentación Física (Digital - evidencia de entrega)
(2, 2, 'Comprobante de Entrega Física',
 'Documento que acredita la entrega física de la papelería en ventanilla.',
 'digital', 'pdf,jpg', 5, 1, 1);

-- Tipo 3 (Público General) - Fase examen_general - Pasos 5 y 6
INSERT INTO `examen_requisito_documento` 
(`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`, `activo`) 
VALUES
-- Paso 5: Revisión de Papelería (Digital) - Empastados, CD, Carta
(3, 5, 'Empastados (2 ejemplares)',
 'Dos ejemplares empastados del trabajo de graduación.',
 'digital', 'pdf', 10, 1, 1),
(3, 5, 'CD con versión digital',
 'CD con la versión digital del trabajo de graduación.',
 'digital', 'pdf', 10, 2, 1),
(3, 5, 'Carta de Autorización de Publicación',
 'Carta de autorización para publicar el trabajo en el repositorio.',
 'digital', 'pdf', 5, 3, 1),

-- Paso 6: Entrega de Documentación Física (Digital - evidencia)
(3, 6, 'Empastados (2 ejemplares)',
 'Versión digital/scan de los dos ejemplares empastados del trabajo de graduación.',
 'digital', 'pdf,jpg,png', 10, 1, 1),
(3, 6, 'CD con versión digital',
 'Imagen o scan del CD con la versión digital del trabajo de graduación.',
 'digital', 'pdf,jpg,png', 5, 2, 1),
(3, 6, 'Carta de Autorización de Publicación',
 'Carta firmada autorizando la publicación del trabajo de graduación en el repositorio institucional.',
 'digital', 'pdf', 5, 3, 1);

-- ------------------------------------------------------------
-- 4. examen_profesional_calificado - Configuración inicial
-- ------------------------------------------------------------
-- Estos valores son ejemplos/configuración inicial. 
-- En producción se configuran desde la interfaz administrativa.
INSERT INTO `examen_profesional_calificado` 
(`carnet`, `nombre_completo`, `activo`, `created_at`) 
VALUES
('20101010', 'Dr. Juan Pérez García', 1, NOW()),
('20102020', 'Dra. María López Hernández', 1, NOW()),
('20103030', 'Dr. Carlos Ruiz Mendoza', 1, NOW()),
('20104040', 'Dra. Ana Martínez Castro', 1, NOW()),
('20105050', 'Dr. Pedro Sánchez Ruiz', 1, NOW());

-- ------------------------------------------------------------
-- 5. examen_junta_directiva - Configuración inicial  
-- ------------------------------------------------------------
-- Miembros de la junta directiva para aprobaciones.
-- En producción se configuran desde la interfaz administrativa.
INSERT INTO `examen_junta_directiva` 
(`carnet`, `nombre_completo`, `cargo`, `activo`, `created_at`) 
VALUES
('JD0001', 'Dra. Laura González Vega', 'Presidenta', 1, NOW()),
('JD0002', 'Dr. Roberto Fernández López', 'Vocal I', 1, NOW()),
('JD0003', 'Dra. Carmen Díaz Torres', 'Vocal II', 1, NOW()),
('JD0004', 'Dr. Luis Hernández Cruz', 'Secretario', 1, NOW());

-- ------------------------------------------------------------
-- VERIFICACIÓN RÁPIDA (opcional - comentar en producción)
-- ------------------------------------------------------------
SELECT '=== VERIFICACIÓN DE SEEDS ===' AS 'Estado';
SELECT CONCAT('✓ Tipos de examen: ', COUNT(*), ' registros') AS 'examen_tipo' FROM examen_tipo WHERE activo = 1;
SELECT CONCAT('✓ Pasos configurados: ', COUNT(*), ' registros') AS 'examen_paso_catalogo' FROM examen_paso_catalogo WHERE activo = 1;
SELECT CONCAT('✓ Requisitos digitales: ', COUNT(*), ' registros') AS 'examen_requisito_documento' FROM examen_requisito_documento WHERE activo = 1 AND tipo_entrega = 'digital';
SELECT CONCAT('✓ Profesionales calificados: ', COUNT(*), ' registros') AS 'examen_profesional_calificado' FROM examen_profesional_calificado WHERE activo = 1;
SELECT CONCAT('✓ Miembros junta directiva: ', COUNT(*), ' registros') AS 'examen_junta_directiva' FROM examen_junta_directiva WHERE activo = 1;
SELECT '=== SEEDS COMPLETADOS ===' AS 'Estado';
