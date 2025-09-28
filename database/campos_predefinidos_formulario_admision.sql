-- Script para inicializar campos predefinidos de formularios de admisión
-- Paso 9: Inicialización de campos predefinidos
-- Fecha: 31 de agosto de 2025

-- ========================================
-- PROCEDIMIENTO PARA CREAR CAMPOS PREDEFINIDOS
-- ========================================

DELIMITER //

CREATE PROCEDURE CrearCamposPredefinidos(IN formulario_id INT)
BEGIN
    -- INFORMACIÓN PERSONAL
    INSERT INTO campo_formulario (id_formulario, nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, activo) VALUES
    (formulario_id, 'nombres', 'Nombres', 'texto', NULL, 1, 1, 1),
    (formulario_id, 'apellidos', 'Apellidos', 'texto', NULL, 1, 2, 1),
    (formulario_id, 'cui', 'DPI/CUI', 'texto', NULL, 1, 3, 1),
    (formulario_id, 'correo_electronico', 'Correo Electrónico', 'email', NULL, 1, 4, 1),
    (formulario_id, 'telefono', 'Teléfono', 'telefono', NULL, 1, 5, 1),
    (formulario_id, 'fecha_nacimiento', 'Fecha de Nacimiento', 'fecha', NULL, 1, 6, 1),
    (formulario_id, 'genero', 'Género', 'select', 'Masculino,Femenino', 1, 7, 1),
    (formulario_id, 'estado_civil', 'Estado Civil', 'select', 'Soltero/a,Casado/a,Divorciado/a,Viudo/a', 0, 8, 1);

    -- DIRECCIÓN
    INSERT INTO campo_formulario (id_formulario, nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, activo) VALUES
    (formulario_id, 'direccion', 'Dirección', 'textarea', NULL, 1, 9, 1),
    (formulario_id, 'municipio', 'Municipio', 'texto', NULL, 1, 10, 1),
    (formulario_id, 'departamento', 'Departamento', 'texto', NULL, 1, 11, 1);

    -- INFORMACIÓN ACADÉMICA
    INSERT INTO campo_formulario (id_formulario, nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, activo) VALUES
    (formulario_id, 'universidad_pregrado', 'Universidad de Pregrado', 'texto', NULL, 1, 12, 1),
    (formulario_id, 'carrera_pregrado', 'Carrera de Pregrado', 'texto', NULL, 1, 13, 1),
    (formulario_id, 'año_graduacion', 'Año de Graduación', 'texto', NULL, 1, 14, 1),
    (formulario_id, 'colegiado_profesional', 'Número de Colegiado Profesional', 'texto', NULL, 0, 15, 1);

    -- DOCUMENTOS
    INSERT INTO campo_formulario (id_formulario, nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, activo) VALUES
    (formulario_id, 'photo_dpi', 'Foto del DPI (archivo)', 'archivo', NULL, 1, 16, 1),
    (formulario_id, 'pasaporte', 'Número de Pasaporte', 'texto', NULL, 0, 17, 1);

    -- MOTIVACIÓN
    INSERT INTO campo_formulario (id_formulario, nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, activo) VALUES
    (formulario_id, 'motivo_estudio', 'Motivación para estudiar el postgrado', 'textarea', NULL, 1, 18, 1);

    -- INFORMACIÓN ACADÉMICA ESPECÍFICA
    INSERT INTO campo_formulario (id_formulario, nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, activo) VALUES
    (formulario_id, 'maestria_solicitada', 'Maestría a la cual solicitar ingresar', 'select', 'Maestría en Diseño Arquitectónico,Diseño, planificación y manejo Ambiental,Restauración de Monumentos,Gestión para la reducción del riesgo,Desarrollo Urbano y territorio,Mercadeo para el diseño,Patrimonio Cultural para el desarrollo énfasis en Gestión y Conservación,Gerencia de Proyectos arquitectónicos,Enseñanza virtual de la Arquitectura y el Diseño,Diseño interactivo y digital,Especialización de Gestión de Riesgos,DOCTORADO (Con énfasis *Diseño Arquitectónico *Conservación del Patrimonio Cultural *Conservación del Medio Ambiente),Maestría Planificación y Diseño del Paisaje', 1, 19, 1);

END //

DELIMITER ;

-- ========================================
-- INSTRUCCIONES DE USO:
-- ========================================
-- Para usar este procedimiento después de crear un formulario:
-- CALL CrearCamposPredefinidos(ID_DEL_FORMULARIO);
-- 
-- Ejemplo:
-- CALL CrearCamposPredefinidos(1);
-- CALL CrearCamposPredefinidos(2);

-- ========================================
-- CONSULTA PARA VER CAMPOS DE UN FORMULARIO
-- ========================================
-- SELECT 
--     orden_campo as orden,
--     nombre_campo,
--     etiqueta,
--     tipo_campo,
--     CASE WHEN requerido = 1 THEN 'SÍ' ELSE 'NO' END as requerido,
--     COALESCE(opciones, '(sin opciones)') as opciones
-- FROM campo_formulario 
-- WHERE id_formulario = ID_AQUI
-- ORDER BY orden_campo;
