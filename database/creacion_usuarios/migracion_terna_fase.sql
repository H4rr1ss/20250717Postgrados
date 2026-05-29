-- ============================================================
-- MIGRACIÓN: Agregar columna 'fase' a examen_terna
-- ============================================================
-- Fecha: 2026-05-17
-- Descripción: Permite tener ternas diferentes para examen_privado
--              y examen_general en el mismo proceso de graduación.
--
-- Cambios:
-- 1. Agrega columna ENUM 'fase' con valores 'examen_privado' y 'examen_general'
-- 2. Actualiza índice único de (cod_proceso, posicion) a (cod_proceso, fase, posicion)
-- 3. Migra datos existentes asignándolos a 'examen_privado'
-- ============================================================

-- Verificar que estamos en la BD correcta
SELECT DATABASE() as base_datos_actual;

-- ------------------------------------------------------------
-- 1. AGREGAR COLUMNA 'fase' A examen_terna
-- ------------------------------------------------------------
-- Verificar si la columna ya existe
SET @existe_columna = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'examen_terna' 
    AND COLUMN_NAME = 'fase'
    AND TABLE_SCHEMA = DATABASE()
);

-- Solo agregar si no existe
SET @sql_add_column = IF(@existe_columna = 0, 
    'ALTER TABLE examen_terna ADD COLUMN fase ENUM("examen_privado", "examen_general") NOT NULL DEFAULT "examen_privado" AFTER cod_proceso',
    'SELECT "Columna fase ya existe - omitiendo" as mensaje'
);

PREPARE stmt FROM @sql_add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar que se creó la columna
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'examen_terna' 
AND COLUMN_NAME = 'fase'
AND TABLE_SCHEMA = DATABASE();

-- ------------------------------------------------------------
-- 2. ACTUALIZAR ÍNDICE ÚNICO
-- ------------------------------------------------------------
-- Verificar índices actuales
SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'examen_terna'
AND TABLE_SCHEMA = DATABASE();

-- Eliminar índice antiguo si existe
SET @existe_idx_antiguo = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_NAME = 'examen_terna' 
    AND INDEX_NAME = 'unique_proceso_posicion'
    AND TABLE_SCHEMA = DATABASE()
);

SET @sql_drop_idx = IF(@existe_idx_antiguo > 0,
    'ALTER TABLE examen_terna DROP INDEX unique_proceso_posicion',
    'SELECT "Índice antiguo no existe - omitiendo" as mensaje'
);

PREPARE stmt FROM @sql_drop_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si el nuevo índice ya existe
SET @existe_idx_nuevo = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_NAME = 'examen_terna' 
    AND INDEX_NAME = 'unique_proceso_fase_posicion'
    AND TABLE_SCHEMA = DATABASE()
);

-- Crear nuevo índice único si no existe
SET @sql_add_idx = IF(@existe_idx_nuevo = 0,
    'ALTER TABLE examen_terna ADD UNIQUE KEY unique_proceso_fase_posicion (cod_proceso, fase, posicion)',
    'SELECT "Índice nuevo ya existe - omitiendo" as mensaje'
);

PREPARE stmt FROM @sql_add_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar índices después del cambio
SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'examen_terna'
AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- ------------------------------------------------------------
-- 3. MIGRAR DATOS EXISTENTES
-- ------------------------------------------------------------
-- Actualizar registros existentes que tengan fase NULL (si hubiera)
UPDATE examen_terna 
SET fase = 'examen_privado' 
WHERE fase IS NULL OR fase = '';

-- ------------------------------------------------------------
-- 4. VERIFICACIÓN FINAL
-- ------------------------------------------------------------
SELECT 
    '=== ESTRUCTURA FINAL DE examen_terna ===' as verificacion;

SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'examen_terna'
AND TABLE_SCHEMA = DATABASE()
ORDER BY ORDINAL_POSITION;

-- Verificar datos migrados
SELECT 
    '=== DATOS MIGRADOS ===' as verificacion;

SELECT 
    fase,
    COUNT(*) as cantidad_registros
FROM examen_terna
GROUP BY fase;

-- ------------------------------------------------------------
-- 5. NOTAS DE IMPLEMENTACIÓN
-- ------------------------------------------------------------
-- 
-- IMPORTANTE: Después de ejecutar esta migración:
-- 
-- 1. El código PHP debe ser actualizado para:
--    - Pasar el parámetro $fase en guardarTerna()
--    - Usar $fase en el WHERE de getTerna()
--    - Actualizar todas las llamadas en controladores
--
-- 2. Los registros existentes se asignan automáticamente a 
--    'examen_privado' para mantener compatibilidad
--
-- 3. Ahora se pueden tener 6 registros por proceso:
--    - 3 para examen_privado (posiciones 1, 2, 3)
--    - 3 para examen_general (posiciones 1, 2, 3)
--
-- ============================================================
