-- ============================================================
-- EJECUCIONES EXTRA / SEEDS Y AJUSTES AL SISTEMA
-- Base de datos: db_postgrados
-- ============================================================
--
-- INSTRUCCIONES:
--   1. Ejecutar DESPUES de tener el schema base completo
--      (20250718Postgrados.sql, evaluacion_docente.sql,
--       modulo_aspirantes_final.sql, modulo_graduacion_schema.sql).
--   2. Este archivo contiene seeds y ALTER condicionales.
-- ============================================================

/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- 0. AJUSTES AL SISTEMA CORE
-- ============================================================

-- Agregar numero_colegiado a usuario (separado de registro_personal)
SET @add_col_colegiado = IF(
    NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuario' AND column_name = 'numero_colegiado'),
    'ALTER TABLE usuario ADD COLUMN numero_colegiado VARCHAR(50) NULL COMMENT "Numero de colegiado del docente"',
    'SELECT 1'
); PREPARE stmt FROM @add_col_colegiado; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 1. SEEDS DEL MODULO DE GRADUACION
-- ============================================================

-- ------------------------------------------------------------
-- 1.1 Rol: Secretario de Examen Privado
-- ------------------------------------------------------------
INSERT INTO `rol` (`cod_rol`, `nombre`) VALUES (11, 'Secretario de Examen Privado');

INSERT INTO `usuario` (
    `nombres`, `apellidos`, `registro_academico`, `correo`, `contrasenia`, 
    `cod_pais`, `sexo`, `fecha_creacion`, `nombre_completo`
) VALUES (
    'NombreSecretario', 'ApellidoSecretario', '202600001', 
    'secretario.examen@farusac.edu.gt', 
    '$2y$10$...hash_de_password_hash...', 
    73, 'H', NOW(), 'Nombre Apellido'
);

--! ASIGNAR ROL AL USUARIO CREADO
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio)
VALUES (3568, 11, CURDATE());
-- ------------------------------------------------------------
-- 1.2 Tipos de examen
-- ------------------------------------------------------------
INSERT INTO `examen_tipo` (`cod_tipo_examen`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Privado General', 'Examen privado para estudiantes regulares de postgrado', 1),
(2, 'Privado Gerencia', 'Examen privado para la Maestría en Gestión de Programas y Proyectos de Desarrollo', 1),
(3, 'Público General', 'Examen público abierto a la comunidad académica', 1);

UPDATE `examen_tipo` SET `cod_carrera` = 18 WHERE `cod_tipo_examen` = 1;
UPDATE `examen_tipo` SET `cod_carrera` = 24 WHERE `cod_tipo_examen` = 2;

INSERT INTO `examen_tipo` (`cod_carrera`, `nombre`, `descripcion`, `activo`)
SELECT
    nc.`cod_carrera`,
    CONCAT('Privado - ', LEFT(nc.`nombre`, 89)),
    CONCAT('Examen privado para ', nc.`nombre`),
    nc.`activo`
FROM `nombre_carrera` nc
WHERE nc.`activo` = 1
  AND nc.`cod_carrera` NOT IN (999, 18, 24);

-- ------------------------------------------------------------
-- 1.3 Paso catalogo
-- ------------------------------------------------------------
INSERT INTO `examen_paso_catalogo`
  (`cod_tipo_examen`, `numero_orden`, `fase`, `nombre`, `fecha_finalizado`, `template_parcial`, `es_ultimo_paso`) VALUES
  -- Examen Privado (4 pasos)
  (NULL, 1, 'examen_privado', 'Revisión de Papelería',           '0', 'paso1-papeleria',     0),
  (NULL, 2, 'examen_privado', 'Entrega de Documentación Física', '0', 'paso2-documentacion', 0),
  (NULL, 3, 'examen_privado', 'Terna Examinadora',               '0', 'paso3-terna',         0),
  (NULL, 4, 'examen_privado', 'Notificación al Estudiante',      '0', 'paso4-notificacion',  0),
  -- Examen General (2 pasos) — paso 2 es el último del flujo completo
  (NULL, 1, 'examen_general', 'Revisión de Papelería',           '0', 'paso1-papeleria',     0),
  (NULL, 2, 'examen_general', 'Entrega de Documentación Física', '0', 'paso2-documentacion', 1),
  -- Fases transicionales
  (NULL, 5, 'carta_examinadores',     'Carta de Examinadores',                  '0', 'paso5-carta-examinadores',     0),
  (NULL, 6, 'autorizacion_impresion', 'Autorización de Impresión del Proyecto', '0', 'paso6-autorizacion-impresion', 0);

-- ------------------------------------------------------------
-- 1.4 Requisitos de documento
-- ------------------------------------------------------------
-- Tipo 1 (Privado General)
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  (1, 1, 'Recibo de Pago', 'Comprobante de pago de los derechos de examen de graduación.', 'digital', 'pdf,jpg,png', 5, 1),
  (1, 1, 'Constancia de Cierre de Pensum', 'Constancia emitida por la coordinación que acredita el cierre total del pensum de estudios.', 'digital', 'pdf', 5, 2),
  (1, 1, 'Ejemplar del Trabajo de Graduación', 'Versión digital del trabajo de graduación en formato PDF.', 'digital', 'pdf', 30, 3);

-- Tipo 2 (Privado Gerencia)
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  (2, 1, 'Factura de Impresión', 'Factura emitida por la imprenta que realizó los empastados.', 'digital', 'pdf,jpg,png', 5, 1),
  (2, 1, 'Certificación de Notas', 'Certificación oficial de todas las notas obtenidas durante el programa.', 'digital', 'pdf', 5, 2);

-- Tipo 3 (Público General)
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  (3, 5, 'Empastados (2 ejemplares)', 'Dos ejemplares empastados del trabajo de graduación.', 'digital', 'pdf', 10, 1),
  (3, 5, 'CD con versión digital', 'CD con la versión digital del trabajo de graduación.', 'digital', 'pdf', 10, 2),
  (3, 5, 'Carta de Autorización de Publicación', 'Carta de autorización para publicar el trabajo en el repositorio.', 'digital', 'pdf', 5, 3);

-- ------------------------------------------------------------
-- 1.5 Configuracion de autorizacion (Paso 6)
-- ------------------------------------------------------------
INSERT INTO `examen_autorizacion_config` (`cod_config`, `instrucciones_parte1`, `instrucciones_parte2`, `updated_by`)
VALUES (1, NULL, NULL, NULL);

-- ============================================================
-- 2. ACCIONES ACL (CONTROL DE ACCESO)
-- ============================================================

-- ------------------------------------------------------------
-- 2.1 Formulario de Admisión (67–75)
-- ------------------------------------------------------------
INSERT INTO `accion` (`cod_accion`, `nombre`) VALUES
  (67, 'Recuperar contraseña'),
  (68, 'Crear/Ver formulario de admisión'),
  (69, 'Ver respuestas de aspirantes'),
  (70, 'Editar respuesta'),
  (71, 'Archivar formulario'),
  (72, 'Eliminar formulario'),
  (73, 'Formulario de admisión público'),
  (74, 'Verificar CUI de aspirante'),
  (75, 'Descargar respuesta de aspirante')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ------------------------------------------------------------
-- 2.2 Evaluación Docente (80–86)
-- ------------------------------------------------------------
INSERT INTO `accion` (`cod_accion`, `nombre`) VALUES
  (80, 'Ver evaluación docente (pendientes)'),
  (81, 'Ver formulario de evaluación docente'),
  (82, 'Guardar evaluación docente'),
  (83, 'Ver historial de evaluaciones docentes'),
  (84, 'Ver confirmación de evaluación docente'),
  (85, 'Ver reporte de evaluación docente'),
  (86, 'Descargar reporte de evaluación docente')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ------------------------------------------------------------
-- 2.3 Módulo de Graduación (100–168)
-- ------------------------------------------------------------
INSERT INTO `accion` (`cod_accion`, `nombre`) VALUES
  (100, 'Gestión de Exámenes'),
  (101, 'Gestionar Papelería'),
  (102, 'Ver Solicitudes'),
  (103, 'Revisar Papelería'),
  (104, 'Inscripción a Examen'),
  (105, 'Iniciar proceso de graduación (director)'),
  (106, 'Buscar estudiante para graduación'),
  (107, 'Subir Documento'),
  (108, 'Guardar Revisión de documento'),
  (109, 'Recibimiento Documento Físico'),
  (110, 'Guardar Terna'),
  (111, 'Avanzar Paso'),
  (112, 'Notificar Estudiante'),
  (113, 'Guardar Requisito'),
  (114, 'Eliminar Requisito'),
  (115, 'Guardar Instrucciones'),
  (116, 'Ver paso de carta de examinadores'),
  (117, 'Ver Carta de Examinadores'),
  (118, 'Autorización de Impresión'),
  (119, 'Configurar Autorización'),
  (120, 'Guardar Instrucciones Autorización'),
  (121, 'Subir Documento Soporte'),
  (122, 'Eliminar Documento Soporte'),
  (123, 'Descargar Documento Soporte'),
  (124, 'Descargar requisito de apoyo'),
  (125, 'Guardar Profesional'),
  (126, 'Eliminar Profesional'),
  (127, 'Subir Carta Descarga'),
  (128, 'Eliminar Carta Descarga'),
  (129, 'Descargar Carta Descarga'),
  (130, 'Guardar Miembro Junta'),
  (131, 'Eliminar Miembro Junta'),
  (132, 'Aprobar Revisión Presencial'),
  (133, 'Notificación grupal de acto de graduación'),
  (134, 'Enviar notificación grupal'),
  (135, 'Ver graduación (estudiante)'),
  (136, 'Ver proceso de graduación'),
  (137, 'Solicitud de examen'),
  (138, 'Ver Terna Examinadora'),
  (139, 'Subir documento (estudiante)'),
  (140, 'Ver documento'),
  (141, 'Ver paso de carta de examinadores (est)'),
  (142, 'Adjuntar evidencia a la bitácora de correcciones'),
  (143, 'Aprobar trabajo de graduación y generar carta'),
  (144, 'Descargar carta de examinadores'),
  (145, 'Eliminar evidencia de la bitácora'),
  (146, 'Autorización de Impresión (estudiante)'),
  (147, 'Seleccionar Profesional'),
  (148, 'Vista previa de notificación de examen'),
  (149, 'Vista previa de notificación grupal'),
  (150, 'Ver listado de evaluaciones de examen privado'),
  (153, 'Ver resumen de evaluación'),
  (154, 'Guardar tema de tesis'),
  (156, 'Abrir evaluación de examen privado'),
  (157, 'Cerrar evaluación de examen privado'),
  (158, 'Evaluar examen privado (página pública)'),
  (159, 'Guardar evaluación de examinador'),
  (160, 'Reprogramar examen privado'),
  (161, 'Acta de examen privado'),
  (162, 'Generar acta de examen privado'),
  (163, 'Lista de docentes (sustitución examinador)'),
  (164, 'Sustituir examinador'),
  (165, 'Previsualizar acta de examen privado'),
  (166, 'Ver listado de actas de examen general'),
  (167, 'Generar acta de examen general'),
  (168, 'Generar acta de examen general (POST)')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Limpieza de acciones obsoletas
DELETE IGNORE FROM `accion` WHERE `cod_accion` IN (151, 152);

-- ============================================================
-- 3. SEMILLAS MINIMAS OBLIGATORIAS (configurar datos reales desde UI)
-- ============================================================

-- examen_profesional_calificado — requerido para Paso 6 (autorización de impresión)
-- NOTA: Reemplazar 'POR CONFIGURAR' con datos reales antes de usar en producción
INSERT INTO `examen_profesional_calificado`
  (`nombre_completo`, `correo`, `telefono`, `activo`, `creado_por`)
VALUES
  ('POR CONFIGURAR', NULL, NULL, 1, 1);

-- examen_junta_directiva — requerido para actas y autorizaciones
-- NOTA: Reemplazar 'POR CONFIGURAR' con datos reales antes de usar en producción
INSERT INTO `examen_junta_directiva`
  (`nombre_completo`, `puesto`, `activo`, `creado_por`)
VALUES
  ('POR CONFIGURAR', 'Secretario General', 1, 1);

-- ============================================================
-- FIN DE EJECUCIONES EXTRA
-- ============================================================
