-- ============================================================
-- SCRIPT: Extender ENUM fase para incluir carta_examinadores
-- ============================================================
-- Este script extiende la columna ENUM 'fase' en examen_paso_catalogo
-- para incluir todos los valores necesarios del módulo de graduación.
--
-- Ejecutar si hay problemas con la inserción del paso 5 o si
-- la columna fase no tiene todos los valores necesarios.
-- ============================================================

-- Verificar la estructura actual de la columna fase
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'examen_paso_catalogo' 
  AND COLUMN_NAME = 'fase'
  AND TABLE_SCHEMA = DATABASE();

-- Extender el ENUM para incluir todas las fases necesarias
-- (Si alguna fase ya existe, no habrá problema - MySQL ignorará los duplicados)
ALTER TABLE examen_paso_catalogo 
MODIFY COLUMN fase ENUM('examen_privado', 
                        'carta_examinadores', 
                        'autorizacion_impresion', 
                        'examen_general') 
NOT NULL DEFAULT 'examen_privado';

-- Verificar el cambio
SELECT COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'examen_paso_catalogo' 
  AND COLUMN_NAME = 'fase'
  AND TABLE_SCHEMA = DATABASE();

-- ============================================================
-- NOTA IMPORTANTE:
-- ============================================================
-- Si el paso 5 ya existe pero con una fase incorrecta o NULL,
-- ejecutar esta corrección:
--
-- UPDATE examen_paso_catalogo 
-- SET fase = 'carta_examinadores'
-- WHERE numero_orden = 5 
--   AND (fase IS NULL OR fase = '' OR fase = 'examen_privado');
-- ============================================================
