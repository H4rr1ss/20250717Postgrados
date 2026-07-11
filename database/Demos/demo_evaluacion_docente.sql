-- ============================================================
-- DEMO EVALUACION DOCENTE — Script maestro de setup
-- Fecha:    2026-05-18
-- Uso:      Cambiar @usuario_test y ejecutar completo.
-- Requiere: database/evaluacion_docente.sql ya ejecutado.
-- ============================================================

SET @usuario_test = 3530;

-- --------------------------------------------------
-- 0. BUSCAR HORARIO FINALIZADO CON ESTUDIANTE ASIGNADO
-- --------------------------------------------------
SET @horario_test = (
    SELECT a.cod_horario
    FROM asignacion a
    JOIN horario h ON a.cod_horario = h.cod_horario
    WHERE h.fecha_fin <= CURDATE()
      AND a.cod_usuario != @usuario_test
      AND h.cod_horario NOT IN (
          SELECT cod_horario FROM asignacion WHERE cod_usuario = @usuario_test
      )
    ORDER BY h.fecha_fin DESC
    LIMIT 1
);

SELECT @horario_test AS horario_elegido;

-- Abortar si no hay horario disponible
SELECT CASE
    WHEN @horario_test IS NULL THEN 'ERROR: No hay horario finalizado disponible para asignar'
    ELSE 'OK - Procediendo'
END AS status;

-- --------------------------------------------------
-- 1. OBTENER DATOS DEL HORARIO Y DEL ESTUDIANTE ORIGINAL
-- --------------------------------------------------
SET @anio_test      = (SELECT anio          FROM horario WHERE cod_horario = @horario_test);
SET @pensum_test    = (SELECT cod_pensum    FROM horario WHERE cod_horario = @horario_test);
SET @curso_test     = (SELECT cod_curso     FROM horario WHERE cod_horario = @horario_test);
SET @cod_orden_test = (SELECT cod_orden     FROM asignacion WHERE cod_horario = @horario_test LIMIT 1);
SET @anio_actual    = YEAR(CURDATE());

SELECT @horario_test AS horario, @anio_test AS anio, @pensum_test AS pensum, @curso_test AS curso, @cod_orden_test AS orden;

-- --------------------------------------------------
-- 2. ASIGNACION_CARRERA (requerido por FK de inscripcion)
--    Solo inserta si el usuario NO tiene ninguna asignacion_carrera
-- --------------------------------------------------
INSERT INTO asignacion_carrera (cod_usuario, cod_pensum, fecha_cohorte, activa, fecha_asignacion, cod_situacion)
SELECT
    @usuario_test,
    @pensum_test,
    COALESCE((SELECT MIN(fecha_cohorte) FROM asignacion_carrera WHERE cod_pensum = @pensum_test), '2010-01-01'),
    1,
    CURDATE(),
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM asignacion_carrera ac2 WHERE ac2.cod_usuario = @usuario_test
);

SELECT 'Paso 2: asignacion_carrera OK' AS estado;

-- --------------------------------------------------
-- 3. ASIGNACION AL HORARIO (si no existe)
-- --------------------------------------------------
INSERT INTO asignacion (cod_usuario, cod_horario, cod_orden, valida, nota_final, fecha_asignacion, cod_estado_nota, asistencia_cumplida)
SELECT
    @usuario_test, @horario_test, @cod_orden_test, 1, NULL, CURDATE(), 1, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM asignacion a2 WHERE a2.cod_usuario = @usuario_test AND a2.cod_horario = @horario_test
);

SELECT 'Paso 3: asignacion OK' AS estado;

-- --------------------------------------------------
-- 4. INSCRIPCION PARA EL ANIO DEL HORARIO (quita la "P" en Cursos Asignados)
-- --------------------------------------------------
INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
SELECT
    @anio_test,
    @usuario_test,
    @pensum_test,
    CURDATE(),
    @cod_orden_test,
    CURDATE()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM inscripcion i2
    WHERE i2.anio = @anio_test AND i2.cod_usuario = @usuario_test AND i2.cod_pensum = @pensum_test
);

SELECT 'Paso 4: inscripcion horario OK' AS estado;

-- --------------------------------------------------
-- 5. INSCRIPCION PARA EL ANIO ACTUAL (evita consulta SOAP a RyE)
-- --------------------------------------------------
INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
SELECT
    @anio_actual,
    @usuario_test,
    @pensum_test,
    CURDATE(),
    NULL,
    CURDATE()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM inscripcion i2
    WHERE i2.anio = @anio_actual AND i2.cod_usuario = @usuario_test
);

SELECT 'Paso 5: inscripcion anio actual OK' AS estado;

-- --------------------------------------------------
-- 6. VERIFICACION FINAL
-- --------------------------------------------------
SELECT
    a.cod_usuario,
    a.cod_horario,
    h.anio,
    h.cod_pensum,
    cp.nombre AS nombre_curso,
    h.seccion,
    CASE WHEN i_horario.anio IS NOT NULL THEN 'INSCRITO' ELSE 'NO INSCRITO' END AS estado_inscripcion_horario,
    CASE WHEN i_actual.anio IS NOT NULL THEN 'INSCRITO' ELSE 'NO INSCRITO' END AS estado_inscripcion_actual,
    CASE WHEN er.id IS NULL THEN 'PENDIENTE EVALUAR' ELSE 'YA EVALUADO' END AS estado_evaluacion
FROM asignacion a
JOIN horario h ON a.cod_horario = h.cod_horario
JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
LEFT JOIN inscripcion i_horario ON a.cod_usuario = i_horario.cod_usuario
    AND i_horario.anio = h.anio AND i_horario.cod_pensum = h.cod_pensum
LEFT JOIN inscripcion i_actual ON a.cod_usuario = i_actual.cod_usuario
    AND i_actual.anio = @anio_actual AND i_actual.cod_pensum = h.cod_pensum
LEFT JOIN evaluacion_respuesta er ON a.cod_horario = er.cod_horario AND a.cod_usuario = er.cod_usuario_estudiante
WHERE a.cod_usuario = @usuario_test AND a.cod_horario = @horario_test;
