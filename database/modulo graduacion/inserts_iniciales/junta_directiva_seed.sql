-- ============================================================
-- SEED: Miembros de Junta Directiva (datos de ejemplo, OPCIONAL)
-- Tabla: examen_junta_directiva
-- Fecha: 2026-01-16
--
-- Esta información es estrictamente informativa: el estudiante la
-- visualiza en su vista del paso 6 y el director realiza CRUD desde
-- la sección global de la pantalla "Autorización de Impresión".
--
-- Ejecutar SÓLO si se desean datos de ejemplo iniciales. Los nombres
-- son ficticios; el director puede actualizarlos / eliminarlos desde
-- la plataforma.
--
-- Pre-requisito:
--   - Haber ejecutado modulo_autorizacion_impresion_schema.sql
--   - Existir el usuario con cod_usuario = 1 (Director) como `creado_por`
-- ============================================================

INSERT INTO `examen_junta_directiva`
  (`nombre_completo`, `puesto`, `activo`, `creado_por`)
VALUES
  ('Dra. Ana Lucía Fernández Contreras', 'Presidenta de Junta Directiva', 1, 1),
  ('Dr. Miguel Ángel Soto Estrada',      'Secretario General',            1, 1),
  ('MSc. Gabriela Michelle Pérez López', 'Vocal I',                       1, 1),
  ('Lic. Jorge Antonio Ruiz Sandoval',   'Vocal II',                      1, 1);
