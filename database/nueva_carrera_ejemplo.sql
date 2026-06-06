-- ============================================================
-- Ejemplo: Crear una nueva maestría/especialización/doctorado
-- Fecha: 2026-06-05
-- Propósito: Script de referencia para agregar una carrera completa
-- ============================================================

-- IMPORTANTE: Este script es un EJEMPLO. Reemplazar los valores según la nueva carrera.
-- Ejemplo: "Maestría en Gestión Urbana Sostenible" (cod_carrera ficticio 100)

-- ============================================================
-- PASO 1: Insertar en carrera (tabla base)
-- ============================================================
-- Nota: cod_carrera se genera automáticamente (AUTO_INCREMENT)
--       o se puede asignar manualmente si se conoce el siguiente disponible.

INSERT INTO carrera (nombre_actual, alias_actual, cod_grado) VALUES
('Maestría en Gestión Urbana Sostenible', 'Maestría en Gestión Urbana', 3);
-- cod_grado: 3=Maestría, 6=Especialización, 7=Doctorado

-- Obtener el cod_carrera generado:
-- SELECT LAST_INSERT_ID();
-- Para este ejemplo asumimos que generó cod_carrera = 100
SET @nueva_carrera_id = LAST_INSERT_ID();

-- ============================================================
-- PASO 2: Insertar en nombre_carrera (nombre histórico/versión)
-- ============================================================
INSERT INTO nombre_carrera (cod_carrera, nombre, alias, tiempo, activo) VALUES
(@nueva_carrera_id, 'Maestría en Gestión Urbana Sostenible', 'Maestría en Gestión Urbana', NOW(), 1);

-- ============================================================
-- PASO 3: Crear pensum (plan de estudios)
-- ============================================================
INSERT INTO pensum (descripcion, cod_carrera, creditos, fecha_creacion, fecha_inicio, fecha_fin) VALUES
('Pensum Maestría en Gestión Urbana Sostenible', @nueva_carrera_id, 60, CURDATE(), CURDATE(), NULL);

-- ============================================================
-- PASO 4: Crear tipo de examen privado (para el módulo de graduación)
-- ============================================================
INSERT INTO examen_tipo (cod_carrera, nombre, descripcion, activo) VALUES
(@nueva_carrera_id, 'Privado - Maestría en Gestión Urbana Sostenible', 'Examen privado para Maestría en Gestión Urbana Sostenible', 1);

-- ============================================================
-- PASO 5: Crear matriz de evaluación para el examen privado
-- ============================================================
INSERT INTO examen_matriz_tipo (cod_carrera, nombre) VALUES
(@nueva_carrera_id, 'Maestría en Gestión Urbana Sostenible');

SET @nueva_matriz_id = LAST_INSERT_ID();

-- ============================================================
-- PASO 6: Insertar preguntas de la matriz de evaluación
-- ============================================================
-- Ajustar preguntas, tipo_campo (numero/texto) y punteo_maximo según la carrera

INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(@nueva_matriz_id, 1, 'Aporte: Originalidad del tema y relevancia para la gestión urbana sostenible.', 'numero', '0-10'),
(@nueva_matriz_id, 2, 'Marco teórico: Dominio de teorías urbanas, sostenibilidad y políticas de desarrollo.', 'numero', '0-10'),
(@nueva_matriz_id, 3, 'Metodología: Claridad en el diseño de investigación, herramientas de análisis urbano y recolección de datos.', 'numero', '0-10'),
(@nueva_matriz_id, 4, 'Propuesta de gestión: Sustentación, viabilidad y alineación con políticas urbanas y normativas.', 'numero', '0-10'),
(@nueva_matriz_id, 5, 'Observaciones del examinador: Recomendaciones generales sobre el trabajo.', 'texto', 'N/A');

-- ============================================================
-- PASO 7: Verificar inserciones
-- ============================================================
-- SELECT * FROM carrera WHERE cod_carrera = @nueva_carrera_id;
-- SELECT * FROM nombre_carrera WHERE cod_carrera = @nueva_carrera_id;
-- SELECT * FROM pensum WHERE cod_carrera = @nueva_carrera_id;
-- SELECT * FROM examen_tipo WHERE cod_carrera = @nueva_carrera_id;
-- SELECT * FROM examen_matriz_tipo WHERE cod_carrera = @nueva_carrera_id;
-- SELECT * FROM examen_matriz_pregunta WHERE cod_matriz_tipo = @nueva_matriz_id;

-- ============================================================
-- NOTAS ADICIONALES
-- ============================================================
--
-- 1. Si la carrera NO requiere examen privado (solo curso de actualización):
--    Omitir los pasos 4, 5 y 6 (no insertar en examen_tipo ni examen_matriz_tipo).
--
-- 2. Si la carrera ya existe en carrera pero no tiene nombre_carrera:
--    Solo ejecutar el paso 2.
--
-- 3. Si la carrera ya existe pero no tiene examen_tipo ni matriz:
--    Ejecutar los pasos 4, 5 y 6 directamente.
--
-- 4. El examen público (cod_tipo_examen = 3) es universal y NO se replica por carrera.
--
-- 5. Para desactivar una carrera (sin borrarla):
--    UPDATE nombre_carrera SET activo = 0 WHERE cod_carrera = @nueva_carrera_id;
--    Esto la oculta del módulo de exámenes pero conserva el historial.
