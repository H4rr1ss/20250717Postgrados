-- ============================================================
-- VERIFICACIÓN POST-MIGRACIÓN: Columna fase en examen_terna
-- ============================================================
-- Este script verifica que la migración de ternas se aplicó
-- correctamente y muestra el estado actual de las ternas.
--
-- Ejecutar después de: migracion_terna_fase.sql
-- ============================================================

-- 1. VERIFICAR ESTRUCTURA DE LA TABLA
-- ------------------------------------------------------------
SELECT '=== ESTRUCTURA DE examen_terna ===' as verificacion;

SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT,
    ORDINAL_POSITION
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'examen_terna'
AND TABLE_SCHEMA = DATABASE()
ORDER BY ORDINAL_POSITION;

-- 2. VERIFICAR ÍNDICES
-- ------------------------------------------------------------
SELECT '=== ÍNDICES DE examen_terna ===' as verificacion;

SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'examen_terna'
AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- 3. CONTAR TERNAS POR FASE
-- ------------------------------------------------------------
SELECT '=== CONTADOR DE TERNAS POR FASE ===' as verificacion;

SELECT 
    COALESCE(fase, 'NULL (sin asignar)') as fase,
    COUNT(*) as cantidad_registros,
    COUNT(DISTINCT cod_proceso) as cantidad_procesos_distintos
FROM examen_terna
GROUP BY fase
ORDER BY fase;

-- 4. VERIFICAR PROCESOS CON TERNAS EN AMBAS FASES
-- ------------------------------------------------------------
SELECT '=== PROCESOS CON TERNAS EN AMBAS FASES ===' as verificacion;

SELECT 
    cod_proceso,
    COUNT(DISTINCT fase) as cantidad_fases,
    GROUP_CONCAT(DISTINCT fase ORDER BY fase) as fases_disponibles,
    COUNT(*) as total_examinadores
FROM examen_terna
GROUP BY cod_proceso
HAVING cantidad_fases > 1
ORDER BY cod_proceso;

-- 5. VERIFICAR PROCESOS CON TERNAS INCOMPLETAS
-- ------------------------------------------------------------
SELECT '=== PROCESOS CON TERNAS INCOMPLETAS (menos de 3 examinadores) ===' as verificacion;

SELECT 
    cod_proceso,
    fase,
    COUNT(*) as cantidad_examinadores,
    GROUP_CONCAT(nombre_examinador ORDER BY posicion SEPARATOR ' | ') as examinadores
FROM examen_terna
GROUP BY cod_proceso, fase
HAVING cantidad_examinadores < 3
ORDER BY cod_proceso, fase;

-- 6. VERIFICAR DETALLE DE TERNAS (últimos 5 procesos)
-- ------------------------------------------------------------
SELECT '=== DETALLE DE TERNAS (últimos 5 procesos) ===' as verificacion;

SELECT 
    t.cod_proceso,
    t.fase,
    t.posicion,
    t.nombre_examinador,
    t.numero_colegiado,
    t.tipo_examinador,
    t.correo
FROM examen_terna t
ORDER BY t.cod_proceso DESC, t.fase, t.posicion
LIMIT 15;

-- 7. VERIFICAR INTEGRIDAD: No deben haber posiciones duplicadas por proceso y fase
-- ------------------------------------------------------------
SELECT '=== VERIFICACIÓN DE INTEGRIDAD (posiciones duplicadas) ===' as verificacion;

SELECT 
    cod_proceso,
    fase,
    posicion,
    COUNT(*) as cantidad,
    GROUP_CONCAT(cod_terna) as cod_ternas
FROM examen_terna
GROUP BY cod_proceso, fase, posicion
HAVING cantidad > 1
ORDER BY cod_proceso, fase, posicion;

-- ============================================================
-- RESULTADO ESPERADO:
-- ============================================================
-- 1. La columna 'fase' debe existir con tipo ENUM
-- 2. El índice 'unique_proceso_fase_posicion' debe existir
-- 3. Las ternas migradas deben tener fase = 'examen_privado'
-- 4. No deben haber posiciones duplicadas por proceso y fase
-- 5. Los nuevos registros pueden tener fase = 'examen_general'
--
-- Si todo está correcto, la migración fue exitosa.
-- ============================================================
