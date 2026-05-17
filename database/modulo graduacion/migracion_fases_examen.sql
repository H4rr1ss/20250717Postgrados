-- ============================================================
-- MIGRACIÓN: Separación de datos por fase
--            (Examen Privado vs Examen General)
--
-- USO: SOLO para entornos que ya tienen las tablas creadas con
--      la versión ANTERIOR de modulo_graduacion.sql (la que
--      tenía fecha_examen / hora_inicio_examen como columna única).
--      Para instalaciones NUEVAS desde cero, este script NO es
--      necesario — los scripts base ya reflejan la estructura final.
--
-- Base de datos: db_postgrados
-- Fecha: 2026-05-16
--
-- CONTEXTO DEL PROBLEMA:
--   El módulo de graduación tiene 4 fases secuenciales:
--     F1: examen_privado  (pasos 1-4, cod_paso 1-4)
--     F2: carta_examinadores (paso 5, cod_paso 5)
--     F3: autorizacion_impresion (paso 6, cod_paso 10)
--     F4: examen_general  (pasos 1-4, cod_paso 6-9)
--
--   Las fases F1 y F4 comparten la misma LÓGICA de pasos (1-4)
--   pero con DATOS completamente diferentes:
--     - Requisitos de documentos diferentes
--     - Fecha/hora de examen diferentes
--     - La terna de examinadores es la misma (compartida)
--
--   Los requisitos se distinguen por cod_tipo_examen:
--     - Tipo 1 (Privado General) y Tipo 2 (Privado Gerencia)
--       son para la fase examen_privado
--     - Tipo 3 (Público General) es para la fase examen_general
--       y aplica a todas las maestrías
--
-- CAMBIOS QUE REALIZA ESTE SCRIPT:
--   1) Renombra fecha_examen → fecha_examen_privado en examen_proceso
--   2) Renombra hora_inicio_examen → hora_examen_privado
--   3) Agrega columnas fecha_examen_general y hora_examen_general
--   4) Migra los requisitos del tipo 3 (Público General)
--      de cod_paso 1 → 6 y cod_paso 2 → 7 (pasos de examen_general)
--
-- DEPENDENCIAS:
--   - modulo_graduacion.sql (debe estar cargado)
--   - modulo_graduacion_carta_01_schema.sql (debe estar cargado)
--   - modulo_autorizacion_impresion_schema.sql (debe estar cargado)
--
-- EJECUCIÓN:
--   docker compose exec -T db mysql -uuser -ppassword db_postgrados \
--     < "database/modulo graduacion/migracion_fases_examen.sql"
--
-- NOTA: No hay datos en producción, por lo que no se requiere
--       migración de datos existentes. Solo se cambia la estructura
--       y se re-vinculan los requisitos.
-- ============================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;


-- ============================================================
-- 1) MODIFICAR TABLA examen_proceso
--    Separar fecha/hora en columnas independientes por fase.
--
--    ANTES:
--      fecha_examen        DATE     (una sola fecha para todo el proceso)
--      hora_inicio_examen  TIME     (una sola hora para todo el proceso)
--
--    DESPUÉS:
--      fecha_examen_privado  DATE   (fecha del examen privado)
--      hora_examen_privado   TIME   (hora del examen privado)
--      fecha_examen_general  DATE   (fecha del examen general/público)
--      hora_examen_general   TIME   (hora del examen general/público)
-- ============================================================

-- 1a) Renombrar columnas existentes para que se refieran al examen privado.
--     Los datos existentes (si hubiera) se preservan automáticamente con CHANGE COLUMN.
ALTER TABLE `examen_proceso`
  CHANGE COLUMN `fecha_examen` `fecha_examen_privado`
    DATE DEFAULT NULL
    COMMENT 'Fecha programada del examen privado';

ALTER TABLE `examen_proceso`
  CHANGE COLUMN `hora_inicio_examen` `hora_examen_privado`
    TIME DEFAULT NULL
    COMMENT 'Hora de inicio del examen privado';

-- 1b) Agregar columnas nuevas para el examen general (público).
ALTER TABLE `examen_proceso`
  ADD COLUMN `fecha_examen_general`
    DATE DEFAULT NULL
    COMMENT 'Fecha programada del examen general (público)'
    AFTER `hora_examen_privado`,
  ADD COLUMN `hora_examen_general`
    TIME DEFAULT NULL
    COMMENT 'Hora de inicio del examen general (público)'
    AFTER `fecha_examen_general`;


-- ============================================================
-- 2) MIGRAR REQUISITOS DEL TIPO 3 (Público General)
--    AL cod_paso CORRECTO DE LA FASE examen_general.
--
--    PROBLEMA:
--      Los requisitos del tipo 3 estaban vinculados a cod_paso = 1
--      (que pertenece a la fase examen_privado). Deben estar en
--      cod_paso = 6 (paso 1 de la fase examen_general).
--
--    MAPEO DE PASOS:
--      examen_privado paso 1 (cod_paso=1) → examen_general paso 1 (cod_paso=6)
--      examen_privado paso 2 (cod_paso=2) → examen_general paso 2 (cod_paso=7)
--      (pasos 3 y 4 no tienen requisitos de documentos, son terna y notificación)
--
--    VERIFICACIÓN PREVIA (ejecutar manualmente si se desea):
--      SELECT cod_requisito, cod_tipo_examen, cod_paso, nombre
--      FROM examen_requisito_documento
--      WHERE cod_tipo_examen = 3 AND activo = 1
--      ORDER BY cod_paso, orden_display;
-- ============================================================

-- 2a) Migrar requisitos digitales: cod_paso 1 → 6
--     (Paso 1 de examen_privado → Paso 1 de examen_general)
UPDATE `examen_requisito_documento`
   SET `cod_paso` = 6
 WHERE `cod_tipo_examen` = 3
   AND `cod_paso` = 1;

-- 2b) Migrar requisitos físicos: cod_paso 2 → 7
--     (Paso 2 de examen_privado → Paso 2 de examen_general)
UPDATE `examen_requisito_documento`
   SET `cod_paso` = 7
 WHERE `cod_tipo_examen` = 3
   AND `cod_paso` = 2;


-- ============================================================
-- RESTAURAR CONFIGURACIÓN ORIGINAL
-- ============================================================
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- ============================================================
-- VERIFICACIÓN POST-MIGRACIÓN
-- Ejecutar estas consultas después para confirmar que todo quedó bien:
--
-- 1) Verificar estructura de examen_proceso:
--    DESCRIBE examen_proceso;
--    → Debe mostrar: fecha_examen_privado, hora_examen_privado,
--                    fecha_examen_general, hora_examen_general
--
-- 2) Verificar que los requisitos tipo 3 ahora apuntan a cod_paso 6 y 7:
--    SELECT cod_requisito, cod_tipo_examen, cod_paso, nombre
--    FROM examen_requisito_documento
--    WHERE cod_tipo_examen = 3 AND activo = 1;
--    → cod_paso debe ser 6 o 7, nunca 1 o 2
--
-- 3) Verificar que los requisitos tipo 1 y 2 siguen en cod_paso 1 y 2:
--    SELECT cod_requisito, cod_tipo_examen, cod_paso, nombre
--    FROM examen_requisito_documento
--    WHERE cod_tipo_examen IN (1, 2) AND activo = 1;
--    → cod_paso debe ser 1 o 2
-- ============================================================
