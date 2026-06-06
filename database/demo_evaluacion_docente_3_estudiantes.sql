-- ============================================================
-- DEMO EVALUACIÓN DOCENTE — 3 Estudiantes de prueba
-- Fecha:    2026-06-01
-- Objetivo: Asignar 3 estudiantes a horarios finalizados
--           para que puedan realizar evaluación docente.
-- Requiere: database/evaluacion_docente.sql ya ejecutado.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ========================================================
-- 1. DEFINIR ESTUDIANTES Y HORARIOS
-- ========================================================

-- Estudiante 1: Andrea Morales
SET @u1 = 3557; SET @cui1 = '200530001301';
SET @h1 = 570; SET @p1 = 8; SET @a1 = 2026; SET @o1 = 18661101;
SET @cohorte1 = (SELECT fecha_cohorte FROM horario WHERE cod_horario = @h1);

-- Estudiante 2: Ricardo Fuentes
SET @u2 = 3558; SET @cui2 = '300720001402';
SET @h2 = 571; SET @p2 = 8; SET @a2 = 2026; SET @o2 = 18661102;
SET @cohorte2 = (SELECT fecha_cohorte FROM horario WHERE cod_horario = @h2);

-- Estudiante 3: Valeria Castillo
SET @u3 = 3559; SET @cui3 = '401840001503';
SET @h3 = 562; SET @p3 = 24001; SET @a3 = 2025; SET @o3 = 18651820;
SET @cohorte3 = (SELECT fecha_cohorte FROM horario WHERE cod_horario = @h3);

SET @anio_actual = YEAR(CURDATE());

-- ========================================================
-- 2. ASIGNACIÓN_CARRERA (evita error de FK en inscripción)
-- ========================================================

-- Estudiante 1 (pensum 8)
INSERT INTO asignacion_carrera (cod_usuario, cod_pensum, fecha_cohorte, activa, fecha_asignacion, cod_situacion)
VALUES (@u1, @p1, @cohorte1, 1, CURDATE(), 0)
ON DUPLICATE KEY UPDATE activa = 1, fecha_asignacion = CURDATE(), fecha_cohorte = @cohorte1;

-- Estudiante 2 (pensum 8)
INSERT INTO asignacion_carrera (cod_usuario, cod_pensum, fecha_cohorte, activa, fecha_asignacion, cod_situacion)
VALUES (@u2, @p2, @cohorte2, 1, CURDATE(), 0)
ON DUPLICATE KEY UPDATE activa = 1, fecha_asignacion = CURDATE(), fecha_cohorte = @cohorte2;

-- Estudiante 3 (pensum 24001)
INSERT INTO asignacion_carrera (cod_usuario, cod_pensum, fecha_cohorte, activa, fecha_asignacion, cod_situacion)
VALUES (@u3, @p3, @cohorte3, 1, CURDATE(), 0)
ON DUPLICATE KEY UPDATE activa = 1, fecha_asignacion = CURDATE(), fecha_cohorte = @cohorte3;

SELECT 'Paso 2: asignacion_carrera OK' AS estado;

-- ========================================================
-- 3. ASIGNACIÓN A HORARIOS FINALIZADOS
-- ========================================================

INSERT INTO asignacion (cod_usuario, cod_horario, cod_orden, valida, nota_final, fecha_asignacion, cod_estado_nota, asistencia_cumplida)
VALUES (@u1, @h1, @o1, 1, NULL, CURDATE(), 1, 0)
ON DUPLICATE KEY UPDATE valida = 1;

INSERT INTO asignacion (cod_usuario, cod_horario, cod_orden, valida, nota_final, fecha_asignacion, cod_estado_nota, asistencia_cumplida)
VALUES (@u2, @h2, @o2, 1, NULL, CURDATE(), 1, 0)
ON DUPLICATE KEY UPDATE valida = 1;

INSERT INTO asignacion (cod_usuario, cod_horario, cod_orden, valida, nota_final, fecha_asignacion, cod_estado_nota, asistencia_cumplida)
VALUES (@u3, @h3, @o3, 1, NULL, CURDATE(), 1, 0)
ON DUPLICATE KEY UPDATE valida = 1;

SELECT 'Paso 3: asignacion OK' AS estado;

-- ========================================================
-- 4. INSCRIPCIÓN PARA EL AÑO DEL HORARIO (quita la "P")
-- ========================================================

INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
VALUES (@a1, @u1, @p1, CURDATE(), @o1, CURDATE())
ON DUPLICATE KEY UPDATE fecha_verificacion = CURDATE(), cod_orden = @o1;

INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
VALUES (@a2, @u2, @p2, CURDATE(), @o2, CURDATE())
ON DUPLICATE KEY UPDATE fecha_verificacion = CURDATE(), cod_orden = @o2;

INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
VALUES (@a3, @u3, @p3, CURDATE(), @o3, CURDATE())
ON DUPLICATE KEY UPDATE fecha_verificacion = CURDATE(), cod_orden = @o3;

SELECT 'Paso 4: inscripcion horario OK' AS estado;

-- ========================================================
-- 5. INSCRIPCIÓN PARA EL AÑO ACTUAL (evita consulta SOAP)
-- ========================================================

INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
VALUES (@anio_actual, @u1, @p1, CURDATE(), NULL, CURDATE())
ON DUPLICATE KEY UPDATE fecha_verificacion = CURDATE();

INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
VALUES (@anio_actual, @u2, @p2, CURDATE(), NULL, CURDATE())
ON DUPLICATE KEY UPDATE fecha_verificacion = CURDATE();

INSERT INTO inscripcion (anio, cod_usuario, cod_pensum, fecha_verificacion, cod_orden, fecha_inscripcion)
VALUES (@anio_actual, @u3, @p3, CURDATE(), NULL, CURDATE())
ON DUPLICATE KEY UPDATE fecha_verificacion = CURDATE();

SELECT 'Paso 5: inscripcion anio actual OK' AS estado;

-- ========================================================
-- 6. VERIFICACIÓN FINAL
-- ========================================================

SELECT
    u.cui,
    u.nombres,
    u.apellidos,
    h.cod_horario,
    cp.nombre AS curso,
    h.seccion,
    h.anio,
    h.mes,
    h.fecha_fin,
    CASE WHEN i_horario.anio IS NOT NULL THEN 'INSCRITO' ELSE 'NO INSCRITO' END AS estado_inscripcion_horario,
    CASE WHEN i_actual.anio IS NOT NULL THEN 'INSCRITO' ELSE 'NO INSCRITO' END AS estado_inscripcion_actual,
    CASE WHEN er.id IS NULL THEN 'PENDIENTE EVALUAR' ELSE 'YA EVALUADO' END AS estado_evaluacion
FROM asignacion a
JOIN usuario u ON a.cod_usuario = u.cod_usuario
JOIN horario h ON a.cod_horario = h.cod_horario
JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
LEFT JOIN inscripcion i_horario ON a.cod_usuario = i_horario.cod_usuario
    AND i_horario.anio = h.anio AND i_horario.cod_pensum = h.cod_pensum
LEFT JOIN inscripcion i_actual ON a.cod_usuario = i_actual.cod_usuario
    AND i_actual.anio = @anio_actual AND i_actual.cod_pensum = h.cod_pensum
LEFT JOIN evaluacion_respuesta er ON a.cod_horario = er.cod_horario AND a.cod_usuario = er.cod_usuario_estudiante
WHERE a.cod_usuario IN (@u1, @u2, @u3)
ORDER BY u.cui;

SET FOREIGN_KEY_CHECKS = 1;
