-- ============================================================
-- MÓDULO GRADUACIÓN — SEEDS COMPLETOS (solo INSERT / UPDATE)
-- Base de datos: db_postgrados
-- Fecha de actualización: 2026-06-01
-- Versión: 3.0 (Examen General simplificado a 2 pasos)
--
-- INSTRUCCIONES:
--   Ejecutar DESPUÉS de modulo_graduacion_schema.sql
--   Requiere que ya existan las tablas del sistema core:
--     usuario, rol, accion (definidas en 20250718Postgrados.sql)
-- ============================================================

/*!40101 SET NAMES utf8mb4 */;


-- ------------------------------------------------------------
-- 1. examen_tipo
--    Catálogo de tipos de examen.
-- ------------------------------------------------------------
INSERT INTO `examen_tipo` (`cod_tipo_examen`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Privado General', 'Examen privado para estudiantes regulares de postgrado', 1),
(2, 'Privado Gerencia', 'Examen privado para la Maestría en Gestión de Programas y Proyectos de Desarrollo', 1),
(3, 'Público General', 'Examen público abierto a la comunidad académica', 1);


-- ------------------------------------------------------------
-- 2. examen_paso_catalogo
--    8 pasos totales:
--      - 4 pasos examen_privado (tipos 1 y 2)
--      - 2 pasos examen_general (tipo 3) — paso 2 es el último del flujo
--      - 1 paso carta_examinadores
--      - 1 paso autorizacion_impresion
--
--    NOTA: Los cod_paso se generan via AUTO_INCREMENT en orden:
--      1=Privado-Papelería, 2=Privado-Documentación, 3=Privado-Terna,
--      4=Privado-Notificación, 5=General-Papelería, 6=General-Documentación,
--      7=Carta Examinadores, 8=Autorización Impresión
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
-- 3. examen_requisito_documento
--    Requisitos por tipo de examen y paso.
--    Los cod_paso deben coincidir con los generados arriba:
--      cod_paso 1 = Privado-Papelería
--      cod_paso 2 = Privado-Documentación
--      cod_paso 5 = General-Papelería
--      cod_paso 6 = General-Documentación
-- ------------------------------------------------------------

-- ── Tipo 1 (Privado General) ──
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  -- Paso 1: Papelería Digital
  (1, 1, 'Recibo de Pago',
   'Comprobante de pago de los derechos de examen de graduación.',
   'digital', 'pdf,jpg,png', 5, 1),
  (1, 1, 'Constancia de Cierre de Pensum',
   'Constancia emitida por la coordinación que acredita el cierre total del pensum de estudios.',
   'digital', 'pdf', 5, 2),
  (1, 1, 'Ejemplar del Trabajo de Graduación',
   'Versión digital del trabajo de graduación en formato PDF.',
   'digital', 'pdf', 30, 3);

-- ── Tipo 2 (Privado Gerencia) ──
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  -- Paso 1: Papelería Digital
  (2, 1, 'Factura de Impresión',
   'Factura emitida por la imprenta que realizó los empastados.',
   'digital', 'pdf,jpg,png', 5, 1),
  (2, 1, 'Certificación de Notas',
   'Certificación oficial de todas las notas obtenidas durante el programa.',
   'digital', 'pdf', 5, 2);

-- ── Tipo 3 (Público General) ──
INSERT INTO `examen_requisito_documento`
  (`cod_tipo_examen`, `cod_paso`, `nombre`, `descripcion`, `tipo_entrega`, `formatos_permitidos`, `tamano_max_mb`, `orden_display`) VALUES
  -- Paso 1 (cod_paso=5): Papelería Digital
  (3, 5, 'Empastados (2 ejemplares)',
   'Dos ejemplares empastados del trabajo de graduación.',
   'digital', 'pdf', 10, 1),
  (3, 5, 'CD con versión digital',
   'CD con la versión digital del trabajo de graduación.',
   'digital', 'pdf', 10, 2),
  (3, 5, 'Carta de Autorización de Publicación',
   'Carta de autorización para publicar el trabajo en el repositorio.',
   'digital', 'pdf', 5, 3);


-- ------------------------------------------------------------
-- 4. examen_autorizacion_config
--    Configuración global del paso 6 (único registro).
-- ------------------------------------------------------------
INSERT INTO `examen_autorizacion_config` (`cod_config`, `instrucciones_parte1`, `instrucciones_parte2`, `updated_by`)
VALUES (1, NULL, NULL, NULL);


-- ------------------------------------------------------------
-- 5. Acciones del módulo en tabla `accion` (ACL)
--    Requiere que la tabla `accion` del sistema core ya exista.
--    Bloque consecutivo 100–147 para el módulo de graduación.
-- ------------------------------------------------------------
INSERT IGNORE INTO `accion` (`cod_accion`, `nombre`) VALUES
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
  (149, 'Vista previa de notificación grupal');


-- ============================================================
-- SEMILLAS OPCIONALES (comentadas — se configuran desde UI)
-- ============================================================
-- Descomentar si se desean datos de ejemplo iniciales:

-- examen_profesional_calificado (licenciados en letras)
INSERT INTO `examen_profesional_calificado`
  (`nombre_completo`, `correo`, `telefono`, `activo`, `creado_por`)
VALUES
  ('Lic. Virsa Valenzuela', 'virvalen@hotmail.com', '59824483', 1, 1),
  ('Lic. Carlos Antonio Mendoza Estrada', 'cmendoza@correo.edu.gt', '54218932', 1, 1);

-- examen_junta_directiva
INSERT INTO `examen_junta_directiva`
  (`nombre_completo`, `puesto`, `activo`, `creado_por`)
VALUES
  ('Dra. Ana Lucía Fernández Contreras', 'Presidenta de Junta Directiva', 1, 1),
  ('Dr. Miguel Ángel Soto Estrada', 'Secretario General', 1, 1);