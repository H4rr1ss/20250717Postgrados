-- ============================================================
-- SETUP COMPLETO: Demo de Evaluación Docente
-- Objetivo: preparar un usuario de prueba con un curso finalizado,
--           asignación de carrera e inscripción correctas.
-- Fecha:    2026-05-17
-- Uso:      Cambiar @usuario_test al cod_usuario deseado y ejecutar.
-- ============================================================

SET @usuario_test = 3530;

-- --------------------------------------------------
-- 1. ELEGIR HORARIO FINALIZADO CON ESTUDIANTE ASIGNADO
-- --------------------------------------------------
SET @horario_test = (
    SELECT a.cod_horario
    FROM asignacion a
    JOIN horario h ON a.cod_horario = h.cod_horario
    WHERE h.fecha_fin <= CURDATE()
    ORDER BY h.fecha_fin DESC
    LIMIT 1
);

SELECT @horario_test AS horario_elegido;

-- --------------------------------------------------
-- 2. OBTENER DATOS DEL HORARIO Y DEL ESTUDIANTE ORIGINAL
-- --------------------------------------------------
SET @pensum_test    = (SELECT cod_pensum    FROM horario WHERE cod_horario = @horario_test);
SET @curso_test     = (SELECT cod_curso     FROM horario WHERE cod_horario = @horario_test);
SET @anio_test      = (SELECT anio          FROM horario WHERE cod_horario = @horario_test);
SET @cod_orden_test = (SELECT cod_orden     FROM asignacion WHERE cod_horario = @horario_test LIMIT 1);

SELECT @pensum_test AS pensum, @curso_test AS curso, @anio_test AS anio, @cod_orden_test AS orden;

-- --------------------------------------------------
-- 3. INSERTAR ASIGNACION DEL USUARIO DE PRUEBA (si no existe)
-- --------------------------------------------------
INSERT INTO asignacion (
    cod_usuario, cod_horario, cod_orden, valida,
    nota_final, fecha_asignacion, cod_estado_nota, asistencia_cumplida
)
SELECT
    @usuario_test,
    @horario_test,
    @cod_orden_test,
    1,
    NULL,
    CURDATE(),
    1,
    0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM asignacion a2
    WHERE a2.cod_usuario = @usuario_test AND a2.cod_horario = @horario_test
);

SELECT 'Asignacion insertada/ya existia' AS paso3;

-- --------------------------------------------------
-- 4. INSERTAR ASIGNACION_CARRERA (si no existe)
-- --------------------------------------------------
-- Copiamos datos de cohorte, fecha y situacion de otro estudiante del mismo pensum
INSERT INTO asignacion_carrera (
    cod_usuario, cod_pensum, fecha_cohorte, activa, fecha_asignacion, cod_situacion
)
SELECT
    @usuario_test,
    ac.cod_pensum,
    ac.fecha_cohorte,
    1,
    CURDATE(),
    ac.cod_situacion
FROM asignacion_carrera ac
WHERE ac.cod_pensum = @pensum_test
LIMIT 1
ON DUPLICATE KEY UPDATE
    activa = 1,
    fecha_asignacion = CURDATE();

SELECT 'Asignacion_carrera insertada/actualizada' AS paso4;

-- --------------------------------------------------
-- 5. INSERTAR INSCRIPCION (si no existe)
-- --------------------------------------------------
INSERT INTO inscripcion (
    anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion
)
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
    WHERE i2.cod_usuario = @usuario_test
      AND i2.anio = @anio_test
      AND i2.cod_pensum = @pensum_test
);

SELECT 'Inscripcion insertada/ya existia' AS paso5;

-- --------------------------------------------------
-- 6. VERIFICACION FINAL
-- --------------------------------------------------
SELECT
    'Datos del estudiante ' AS titulo,
    @usuario_test AS cod_usuario,
    @horario_test AS cod_horario,
    cp.nombre AS nombre_curso,
    h.seccion,
    h.anio,
    h.mes,
    h.fecha_inicio,
    h.fecha_fin,
    CASE WHEN i.anio IS NOT NULL THEN 'INSCRITO' ELSE 'NO INSCRITO' END AS estado_inscripcion,
    CASE WHEN er.id IS NULL THEN 'PENDIENTE EVALUAR' ELSE 'YA EVALUADO' END AS estado_evaluacion
FROM asignacion a
JOIN horario h ON a.cod_horario = h.cod_horario
JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
LEFT JOIN inscripcion i ON a.cod_usuario = i.cod_usuario AND i.anio = h.anio AND i.cod_pensum = h.cod_pensum
LEFT JOIN evaluacion_respuesta er ON a.cod_horario = er.cod_horario AND a.cod_usuario = er.cod_usuario_estudiante
WHERE a.cod_usuario = @usuario_test
  AND a.cod_horario = @horario_test;
