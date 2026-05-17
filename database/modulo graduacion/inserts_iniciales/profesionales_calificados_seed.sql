-- ============================================================
-- SEED: Licenciados en Letras Calificados (catálogo inicial)
-- Tabla: examen_profesional_calificado
-- Fecha: 2026-01-16
--
-- Por requerimiento, NO se registra número de colegiado.
-- Sólo nombre, correo y teléfono.
--
-- Pre-requisito:
--   - Haber ejecutado modulo_autorizacion_impresion_schema.sql
--   - Existir el usuario con cod_usuario = 1 (Director) como `creado_por`
-- ============================================================

INSERT INTO `examen_profesional_calificado`
  (`nombre_completo`, `correo`, `telefono`, `activo`, `creado_por`)
VALUES
  -- Profesional real proporcionado por el cliente
  ('Lic. Virsa Valenzuela',
   'virvalen@hotmail.com',
   '5982-4483',
   1, 1),

  -- Profesionales de muestra para iniciar el catálogo
  ('Lic. Carlos Antonio Mendoza Estrada',
   'cmendoza@correo.edu.gt',
   '5421-8932',
   1, 1),

  ('Lic. María Elena Rosales de León',
   'mrosales@textosprofesionales.net',
   '4789-2156',
   1, 1),

  ('Lic. Roberto Alejandro Castellanos Vargas',
   'rcastellanos@edicionescademia.com',
   '5632-1478',
   1, 1);
