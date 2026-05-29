-- ============================================================
-- SCRIPT: Eliminar Todas las Tablas del Módulo de Graduación
-- ============================================================
-- Fecha: 2026-05-27
-- Descripción: Elimina completamente todas las tablas del módulo
--              de graduación en orden correcto (respetando FK)
--
-- USO:
--   docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
--     "database/modulo graduacion/ELIMINAR_TABLAS_GRADUACION.sql"
--
-- ADVERTENCIA:
--   ⚠️ ESTE SCRIPT ELIMINA TODOS LOS DATOS DEL MÓDULO DE GRADUACIÓN
--   ⚠️ No se puede deshacer. Hacer backup primero si hay datos importantes.
--
-- ORDEN DE ELIMINACIÓN:
--   Se eliminan primero las tablas hijas (con FK) y luego las padres
--   para evitar errores de restricciones de integridad.
-- ============================================================

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- ============================================================
-- 1. TABLAS DE TRACKING - NIVEL 3 (más hijas, dependen de otras tablas de tracking)
-- ============================================================

-- 1.1 Tablas con FK a examen_documento y examen_requisito_documento
DROP TABLE IF EXISTS `examen_revision_documento`;

-- 1.2 Tabla con FK a examen_documento y archivo_local
DROP TABLE IF EXISTS `archivo_local`;

-- ============================================================
-- 2. TABLAS DE TRACKING - NIVEL 2 (hijas de catálogos y procesos)
-- ============================================================

-- 2.1 Tablas con FK a examen_requisito_documento (dependen del catálogo)
DROP TABLE IF EXISTS `examen_documento_fisico`;
DROP TABLE IF EXISTS `examen_documento`;

-- 2.2 Tablas con FK a examen_proceso y otras tablas de tracking
DROP TABLE IF EXISTS `examen_historial`;
DROP TABLE IF EXISTS `examen_proceso_paso`;
DROP TABLE IF EXISTS `examen_terna`;
DROP TABLE IF EXISTS `examen_carta_examinadores`;
DROP TABLE IF EXISTS `examen_correccion_evidencia`;
DROP TABLE IF EXISTS `examen_correccion_ciclo`;

-- ============================================================
-- 3. TABLAS DE TRACKING Y CONFIGURACIÓN - NIVEL 1
-- ============================================================

-- 3.1 Tabla con FK a examen_profesional_calificado
DROP TABLE IF EXISTS `examen_autorizacion_proceso`;

-- 3.2 Tablas con FK a examen_carta_plantilla y examen_correccion_ciclo
-- (Nota: estas son del paso 5 y 6)
-- No hay tablas directas con FK a estas en el schema actual

-- ============================================================
-- 4. TABLAS DE CATÁLOGO Y CONFIGURACIÓN
-- ============================================================
-- Estas tablas no tienen dependencias de tracking, solo son referenciadas

-- 4.1 Catálogo de requisitos (referenciado por examen_documento y examen_documento_fisico)
DROP TABLE IF EXISTS `examen_requisito_documento`;

-- 4.2 Catálogo de plantillas de cartas (referenciado por examen_carta_examinadores)
DROP TABLE IF EXISTS `examen_carta_plantilla`;

-- 4.3 Catálogo de profesionales (referenciado por examen_autorizacion_proceso)
DROP TABLE IF EXISTS `examen_profesional_calificado`;

-- 4.4 Otras tablas de catálogo/configuración del paso 6 (sin dependencias críticas)
DROP TABLE IF EXISTS `examen_carta_descarga`;
DROP TABLE IF EXISTS `examen_autorizacion_documento_soporte`;
DROP TABLE IF EXISTS `examen_autorizacion_config`;
DROP TABLE IF EXISTS `examen_junta_directiva`;

-- ============================================================
-- 5. TABLAS PADRE PRINCIPALES (se eliminan al final)
-- ============================================================
-- Estas son las tablas maestras que tienen muchas FK apuntando a ellas

-- 5.1 Tabla maestro de procesos (FK desde: historial, proceso_paso, documento, terna, carta, correccion_ciclo, autorizacion_proceso)
DROP TABLE IF EXISTS `examen_proceso`;

-- 5.2 Catálogo de pasos (FK desde: proceso_paso, requisito_documento)
DROP TABLE IF EXISTS `examen_paso_catalogo`;

-- 5.3 Catálogo de tipos de examen (FK desde: paso_catalogo, proceso, requisito_documento, carta_plantilla)
DROP TABLE IF EXISTS `examen_tipo`;

-- ============================================================
-- 6. VERIFICACIÓN DE LIMPIEZA
-- ============================================================
-- Mostrar tablas restantes del módulo (deberían ser 0)

SELECT 'Tablas del módulo de graduación restantes:' AS mensaje;
SHOW TABLES LIKE 'examen_%';

-- ============================================================
-- 7. MENSAJE DE CONFIRMACIÓN
-- ============================================================

SELECT '✅ Todas las tablas del módulo de graduación han sido eliminadas.' AS mensaje;
SELECT '📝 Para reinstalar, ejecutar en orden:' AS instruccion;
SELECT '   1. modulo_graduacion.sql' AS paso1;
SELECT '   2. modulo_graduacion_carta_01_schema.sql' AS paso2;
SELECT '   3. modulo_graduacion_carta_02_seeds.sql' AS paso3;
SELECT '   4. modulo_autorizacion_impresion_schema.sql' AS paso4;
SELECT '   5. (Opcional) inserts_iniciales/*.sql' AS paso5;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
