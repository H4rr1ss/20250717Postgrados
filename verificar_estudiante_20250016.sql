-- ========================================================  
-- SCRIPT DE PRUEBA: Evaluación Docente - Estudiante 20250016
-- ========================================================  
-- Este script verifica si el estudiante tiene cursos para evaluar
-- y crea datos de prueba si es necesario
-- 
-- IMPORTANTE: Ejecutar en MySQL (puerto 3307 en host, 3306 en contenedor)
-- ========================================================  

USE db_postgrados;

-- 1. OBTENER INFORMACIÓN DEL ESTUDIANTE
-- ========================================================  
SELECT 
    u.cod_usuario,
    u.registro_academico,
    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
    u.email,
    ac.cod_carrera,
    c.nombre AS nombre_carrera,
    ac.fecha_cohorte
FROM usuario u
LEFT JOIN asignacion_carrera ac ON u.cod_usuario = ac.cod_usuario
LEFT JOIN carrera c ON ac.cod_carrera = c.cod_carrera
WHERE u.registro_academico = '20250016';

-- 2. VERIFICAR SI TIENE ASIGNACIONES DE CURSOS
-- ========================================================  
SELECT 
    a.cod_asignacion,
    a.cod_usuario,
    a.cod_horario,
    h.fecha_inicio,
    h.fecha_fin,
    h.seccion,
    h.anio,
    h.mes,
    cp.nombre AS nombre_curso,
    CONCAT(cat.nombres, ' ', cat.apellidos) AS nombre_catedratico,
    h.fecha_cohorte,
    -- Verificar si ya existe evaluación
    (SELECT COUNT(*) FROM evaluacion_respuesta er 
     WHERE er.cod_horario = a.cod_horario AND er.cod_usuario_estudiante = a.cod_usuario) AS ya_evaluado
FROM asignacion a
JOIN horario h ON a.cod_horario = h.cod_horario
JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
LEFT JOIN usuario cat ON h.cod_usuario_catedratico = cat.cod_usuario
WHERE a.cod_usuario = (SELECT cod_usuario FROM usuario WHERE registro_academico = '20250016')
ORDER BY h.fecha_fin DESC;

-- 3. VERIFICAR CURSOS QUE DEBERÍAN APARECER PARA EVALUACIÓN
-- (según la nueva lógica: curso terminado, mes anterior o anterior, no evaluado, cohorte actual)
-- ========================================================  
SELECT 
    'CURSOS QUE DEBERÍAN APARECER PARA EVALUACIÓN' AS informacion,
    a.cod_asignacion,
    a.cod_horario,
    h.fecha_inicio,
    h.fecha_fin,
    cp.nombre AS nombre_curso,
    h.fecha_cohorte,
    ac.fecha_cohorte AS cohorte_estudiante,
    -- Validaciones
    CASE WHEN h.fecha_fin <= CURDATE() THEN '✓ Terminado' ELSE '✗ No terminado' END AS validacion_terminado,
    CASE WHEN h.fecha_fin < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN '✓ Mes anterior' ELSE '✗ Mes actual o futuro' END AS validacion_mes,
    CASE WHEN h.fecha_cohorte = ac.fecha_cohorte OR h.fecha_cohorte IS NULL THEN '✓ Misma cohorte' ELSE '✗ Diferente cohorte' END AS validacion_cohorte
FROM asignacion a
JOIN horario h ON a.cod_horario = h.cod_horario
JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
JOIN asignacion_carrera ac ON a.cod_usuario = ac.cod_usuario
LEFT JOIN evaluacion_respuesta er ON a.cod_horario = er.cod_horario AND a.cod_usuario = er.cod_usuario_estudiante
WHERE a.cod_usuario = (SELECT cod_usuario FROM usuario WHERE registro_academico = '20250016')
  AND h.fecha_fin <= CURDATE()
  AND h.fecha_fin < DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND (h.fecha_cohorte = ac.fecha_cohorte OR h.fecha_cohorte IS NULL)
  AND er.id IS NULL;

-- 4. INSTRUCCIONES PARA CREAR DATOS DE PRUEBA (si no hay resultados en la consulta anterior)
-- ========================================================  
-- Descomenta y ejecuta las siguientes secciones si el estudiante no tiene cursos para evaluar:

/*
-- PASO 1: Verificar que exista el estudiante y obtener sus datos
SET @usuario_test = (SELECT cod_usuario FROM usuario WHERE registro_academico = '20250016');
SET @carrera_test = (SELECT cod_carrera FROM asignacion_carrera WHERE cod_usuario = @usuario_test LIMIT 1);
SET @cohorte_test = (SELECT fecha_cohorte FROM asignacion_carrera WHERE cod_usuario = @usuario_test LIMIT 1);
SET @pensum_test = (SELECT cod_pensum FROM asignacion_carrera WHERE cod_usuario = @usuario_test LIMIT 1);

SELECT 
    @usuario_test AS cod_usuario,
    @carrera_test AS cod_carrera,
    @cohorte_test AS fecha_cohorte,
    @pensum_test AS cod_pensum;

-- PASO 2: Buscar o crear un curso con horario finalizado del mes anterior
-- Primero ver si existe algún horario finalizado del mes pasado
SELECT 
    h.cod_horario,
    h.cod_curso,
    h.cod_pensum,
    h.fecha_inicio,
    h.fecha_fin,
    h.seccion,
    h.cod_usuario_catedratico
FROM horario h
WHERE h.fecha_fin < DATE_FORMAT(CURDATE(), '%Y-%m-01')  -- Terminó mes anterior
  AND h.fecha_fin >= DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01')  -- No más viejo de 2 meses
  AND h.cod_pensum = @pensum_test
LIMIT 1;

-- PASO 3: Si no existe, buscar cualquier curso finalizado recientemente
-- y crear asignación para el estudiante de prueba
*/

-- ========================================================  
-- RESUMEN PARA EJECUCIÓN
-- ========================================================  
-- 1. Ejecutar este script para verificar estado actual
-- 2. Si no hay cursos para evaluar, ejecutar datos_prueba_evaluacion_20250016.sql
-- 3. Acceder a http://localhost:8080/evaluacion-docente con el usuario
--    Email: alvaro.perez@email.com (según estudiantes.sql)
-- ========================================================  
