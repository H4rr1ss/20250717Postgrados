-- ============================================================
-- SEEDS FASE 5: CARTA DE EXAMINADORES
-- ============================================================
-- Archivo: seeds_fase5_carta_examinadores.sql
-- Descripción: Seeds necesarios para el funcionamiento de la
--              Fase 5: Carta de Examinadores.
--              Ejecutar DESPUÉS de tener las tablas creadas.
-- ============================================================

-- ------------------------------------------------------------
-- 1. examen_carta_plantilla - Plantillas .docx para cartas
-- ------------------------------------------------------------
-- IMPORTANTE: Las plantillas deben existir físicamente en:
--   data/plantillas/carta-examinadores/
-- ------------------------------------------------------------

-- Plantilla genérica (aplica a todos los tipos de examen)
INSERT INTO `examen_carta_plantilla` 
(`cod_plantilla`, `cod_tipo_examen`, `nombre`, `archivo_plantilla`, `descripcion`, `activo`) 
VALUES
(1, NULL, 'Carta a Examinadores - Formato General', 
 'carta-examinadores-general.docx',
 'Plantilla genérica para carta dirigida a los examinadores del trabajo de graduación. Incluye espacios para nombres, fecha y datos del estudiante.',
 1),


-- ------------------------------------------------------------
-- NOTA SOBRE ARCHIVOS FÍSICOS
-- ------------------------------------------------------------
-- Los archivos .docx referenciados arriba deben crearse/colocarse en:
--   /var/www/data/plantillas/carta-examinadores/
--
-- Si las plantillas no existen físicamente, la generación de cartas
-- fallará. Contacte al administrador del sistema para crearlas
-- o use plantillas genéricas de Word con marcadores de posición.
--
-- Marcadores sugeridos para las plantillas:
--   {{nombre_estudiante}} - Nombre completo del estudiante
--   {{registro_academico}} - Carné del estudiante
--   {{carrera}} - Nombre de la maestría/carrera
--   {{nombre_examinador1}} - Nombre del primer examinador
--   {{nombre_examinador2}} - Nombre del segundo examinador
--   {{nombre_examinador3}} - Nombre del tercer examinador
--   {{fecha_examen}} - Fecha programada del examen
--   {{hora_examen}} - Hora programada del examen
--   {{titulo_trabajo}} - Título del trabajo de graduación
-- ------------------------------------------------------------

-- ------------------------------------------------------------
-- VERIFICACIÓN (opcional - comentar en producción)
-- ------------------------------------------------------------
SELECT '=== SEEDS FASE 5: CARTA DE EXAMINADORES ===' AS 'Estado';
SELECT CONCAT('✓ Plantillas de carta: ', COUNT(*), ' registros') AS 'examen_carta_plantilla' FROM examen_carta_plantilla WHERE activo = 1;
SELECT '=== IMPORTANTE ===' AS 'Nota';
SELECT 'Recuerde crear los archivos .docx físicos en data/plantillas/carta-examinadores/' AS 'Instruccion';
