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
-- 1. SE AGREGAN COLUMNAS A TABLAS YA EXISTENTES
-- ============================================================
ALTER TABLE `usuario`
ADD COLUMN `titulo_profesional` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
ADD COLUMN `numero_colegiado` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL;

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
-- 1.2 Tipos de examen — 20 tipos privados con cod_carrera vinculado (alineado con matriz_evaluacion_completo.sql)
-- ------------------------------------------------------------
INSERT INTO examen_tipo (cod_tipo_examen, cod_carrera, nombre, descripcion, activo) VALUES
(1, 18, 'Examen Privado — Patrimonio Cultural (Conservación)', 'Examen privado para Maestría en Patrimonio Cultural — Conservación', 1),
(2, 24, 'Examen Privado — Gerencia de Proyectos Arquitectónicos', 'Examen privado para Maestría en Gerencia de Proyectos Arquitectónicos', 1),
(3, 13, 'Examen Privado — Gestión para la Reducción del Riesgo', 'Examen privado para Maestría en Gestión para la Reducción del Riesgo', 1),
(4, 22, 'Examen Privado — Enseñanza Virtual de Arquitectura y Diseño', 'Examen privado para Maestría en Enseñanza Virtual de la Arquitectura y el Diseño', 1),
(5, 17, 'Examen Privado — Mercadeo para el Diseño', 'Examen privado para Maestría en Mercadeo para el Diseño', 1),
(6, 15, 'Examen Privado — Arquitectura para la Salud', 'Examen privado para Maestría en Arquitectura para la Salud', 1),
(7, 9,  'Examen Privado — Asentamientos Humanos y Vivienda', 'Examen privado para Maestría en Planificación de Asentamientos Humanos y Vivienda', 1),
(8, 10, 'Examen Privado — Restauración de Monumentos', 'Examen privado para Maestría en Restauración de Monumentos, Especialidad en Bienes Inmuebles y Centros Históricos', 1),
(9, 11, 'Examen Privado — Diseño, Planificación y Manejo Ambiental', 'Examen privado para Maestría en Diseño, Planificación y Manejo Ambiental', 1),
(10, 12, 'Examen Privado — Diseño Arquitectónico', 'Examen privado para Maestría en Diseño Arquitectónico', 1),
(11, 14, 'Examen Privado — Desarrollo Urbano y Territorio', 'Examen privado para Maestría en Desarrollo Urbano y Territorio', 1),
(12, 16, 'Examen Privado — Planificación y Diseño del Paisaje', 'Examen privado para Maestría en Planificación y Diseño del Paisaje', 1),
(13, 19, 'Examen Privado — Patrimonio Cultural (Gestión)', 'Examen privado para Maestría en Patrimonio Cultural para el Desarrollo — Gestión', 1),
(14, 20, 'Examen Privado — Análisis y Reducción de Riesgo', 'Examen privado para Especialización en Análisis y Reducción de Riesgo de Desastres', 1),
(15, 21, 'Examen Privado — Arquitectura y Construcción Sostenible', 'Examen privado para Especialización en Arquitectura y Construcción Sostenible', 1),
(16, 23, 'Examen Privado — Diseño Interactivo Digital', 'Examen privado para Maestría en Diseño Interactivo Digital', 1),
(17, 25, 'Examen Privado — Gestión Integrada', 'Examen privado para Maestría en Gestión Integrada: Medio Ambiente, Calidad y Prevención', 1),
(18, 26, 'Examen Privado — Diseño y Gestión de Proyectos Tecnológicos', 'Examen privado para Maestría en Diseño y Gestión de Proyectos Tecnológicos', 1),
(19, 28, 'Examen Privado — Dirección y Producción de Cine', 'Examen privado para Especialización en Dirección y Producción de Cine, Video y Televisión', 1),
(20, 80, 'Examen Privado — Doctorado en Arquitectura', 'Examen privado para Doctorado en Arquitectura', 1);

-- Tipo público general (sin carrera específica)
INSERT INTO `examen_tipo` (`cod_tipo_examen`, `cod_carrera`, `nombre`, `descripcion`, `activo`) VALUES
(99, NULL, 'Examen Público General', 'Examen público abierto a la comunidad académica', 1);


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
-- 1.4 Requisitos de documento — Ejemplos para algunos tipos específicos
-- ------------------------------------------------------------
-- NOTA: Los requisitos deben configurarse por tipo de examen desde la UI.
-- Estos son ejemplos de seeds iniciales para testing/desarrollo.

-- Tipo 1 (Patrimonio Cultural — Conservación, carrera 18)
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  (1, 1, 'Recibo de Pago', 'Comprobante de pago de los derechos de examen de graduación.', 'digital', 'pdf,jpg,png', 5, 1),
  (1, 1, 'Constancia de Cierre de Pensum', 'Constancia emitida por la coordinación que acredita el cierre total del pensum de estudios.', 'digital', 'pdf', 5, 2),
  (1, 1, 'Ejemplar del Trabajo de Graduación', 'Versión digital del trabajo de graduación en formato PDF.', 'digital', 'pdf', 30, 3);

-- Tipo 2 (Gerencia de Proyectos Arquitectónicos, carrera 24)
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  (2, 1, 'Recibo de Pago', 'Comprobante de pago de los derechos de examen de graduación.', 'digital', 'pdf,jpg,png', 5, 1),
  (2, 1, 'Constancia de Cierre de Pensum', 'Constancia emitida por la coordinación que acredita el cierre total del pensum de estudios.', 'digital', 'pdf', 5, 2),
  (2, 1, 'Ejemplar del Proyecto de Graduación', 'Versión digital del proyecto de graduación en formato PDF.', 'digital', 'pdf', 30, 3);

-- Tipo 99 (Público General — sin carrera específica)
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  (99, 5, 'Empastados (2 ejemplares)', 'Dos ejemplares empastados del trabajo de graduación.', 'digital', 'pdf', 10, 1),
  (99, 5, 'CD con versión digital', 'CD con la versión digital del trabajo de graduación.', 'digital', 'pdf', 10, 2),
  (99, 5, 'Carta de Autorización de Publicación', 'Carta de autorización para publicar el trabajo en el repositorio.', 'digital', 'pdf', 5, 3);

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
  (75, 'Descargar respuesta de aspirante'),
  (76, 'Registrar aspirante desde formulario de admisión')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ------------------------------------------------------------
-- 2.2 Evaluación Docente (80–87)
-- ------------------------------------------------------------
INSERT INTO `accion` (`cod_accion`, `nombre`) VALUES
  (80, 'Ver evaluación docente (pendientes)'),
  (81, 'Ver formulario de evaluación docente'),
  (82, 'Guardar evaluación docente'),
  (83, 'Ver historial de evaluaciones docentes'),
  (84, 'Ver confirmación de evaluación docente'),
  (85, 'Ver reporte de evaluación docente'),
  (86, 'Descargar reporte de evaluación docente'),
  (87, 'Ver gráficas de evaluación docente')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ------------------------------------------------------------
-- 2.3 Módulo de Graduación (100–170)
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
  (168, 'Generar acta de examen general (POST)'),
  (169, 'Guardar madrina/padrino'),
  (170, 'Configurar madrina/padrino')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);


-- ============================================================
-- 4. SEMILLAS MINIMAS OBLIGATORIAS (configurar datos reales desde UI)
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
