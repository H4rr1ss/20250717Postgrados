-- ============================================================
-- MÓDULO GRADUACIÓN — Paso 5: Carta de Examinadores (SEEDS)
-- Base de datos: db_postgrados
-- Creado:    2026-05-15
-- Revisión:  2026-05-15 - modelo simplificado:
--   Ciclo único interno. El estudiante registra evidencias en la
--   plataforma como bitácora; el director aprueba con un clic.
--   No hay "rechazar" ni "abrir nuevo ciclo" en la plataforma.
--
-- Aplica sobre: modulo_graduacion_carta_01_schema.sql
-- Contiene:
--   1) Plantilla inicial (General)
--   2) Acciones nuevas en tabla `accion` (para ACL)
-- ============================================================

/*!40101 SET NAMES utf8mb4 */;

-- ------------------------------------------------------------
-- 1) Plantilla inicial (aplica a todos los tipos)
-- ------------------------------------------------------------
INSERT INTO `examen_carta_plantilla`
  (`cod_tipo_examen`, `nombre`, `archivo_plantilla`, `descripcion`, `activo`)
VALUES
  (NULL,
   'Carta de Examinadores - General',
   'data/plantillas/carta-examinadores/general.docx',
   'Plantilla por defecto para la carta de examinadores. Aplica a todos los tipos de examen hasta que se registren plantillas específicas.',
   1);


-- ------------------------------------------------------------
-- 2) Acciones en `accion` (cod_accion 68-74)
--    69 y 73 se conservan en BD como entradas históricas pero
--    NO están referenciadas en access_filter.php ni en ningún
--    controlador (acciones eliminadas del modelo simplificado).
-- ------------------------------------------------------------
INSERT INTO `accion` (`cod_accion`, `nombre`) VALUES
  (68, 'Ver paso de carta de examinadores'),
  (70, 'Adjuntar evidencia a la bitácora de correcciones'),
  (71, 'Aprobar trabajo de graduación y generar carta'),
  (72, 'Descargar carta de examinadores'),
  (74, 'Eliminar evidencia de la bitácora');

