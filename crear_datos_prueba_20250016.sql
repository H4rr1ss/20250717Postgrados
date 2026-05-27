-- ========================================================  
-- SCRIPT DE PRUEBA: Crear datos de evaluación docente para estudiante 20250016
-- ========================================================  
-- Este script crea cursos y asignaciones de prueba para probar el módulo
-- de evaluación docente
-- 
-- Requisitos: El estudiante 20250016 debe existir en el sistema
-- ========================================================  

USE db_postgrados;

-- Configurar variables del estudiante
SET @registro_academico = '20250016';
SET @usuario_test = (SELECT cod_usuario FROM usuario WHERE registro_academico = @registro_academico);

-- Verificar que el estudiante existe
SELECT 
    @usuario_test AS cod_usuario_encontrado,
    CASE 
        WHEN @usuario_test IS NOT NULL THEN '✓ Estudiante encontrado'
        ELSE '✗ ERROR: Estudiante no encontrado'
    END AS estado;

-- Si el estudiante no existe, detener
SET @continue = @usuario_test IS NOT NULL;

-- Obtener datos del estudiante
SET @carrera_test = NULL;
SET @cohorte_test = NULL;
SET @pensum_test = NULL;

SELECT 
    @carrera_test := ac.cod_carrera,
    @cohorte_test := ac.fecha_cohorte,
    @pensum_test := ac.cod_pensum
FROM asignacion_carrera ac
WHERE ac.cod_usuario = @usuario_test
LIMIT 1;

SELECT 
    @carrera_test AS cod_carrera,
    @cohorte_test AS fecha_cohorte,
    @pensum_test AS cod_pensum,
    CASE 
        WHEN @carrera_test IS NOT NULL THEN '✓ Datos de carrera encontrados'
        ELSE '✗ WARNING: No tiene asignación de carrera'
    END AS estado;

-- Si no tiene carrera asignada, asignar una carrera de prueba (la 73 - Tecnologías de la Información según estudiantes.sql)
-- Nota: Normalmente esto se hace desde el sistema, pero para pruebas lo hacemos manual
INSERT IGNORE INTO asignacion_carrera (cod_usuario, cod_carrera, cod_pensum, fecha_cohorte, fecha_asignacion)
SELECT 
    @usuario_test,
    73,
    (SELECT cod_pensum FROM pensum WHERE cod_carrera = 73 ORDER BY fecha_creacion DESC LIMIT 1),
    DATE_FORMAT(CURDATE(), '%Y-%m-01'),  -- Cohorte del mes actual
    CURDATE()
WHERE @carrera_test IS NULL;

-- Recalcular si se insertó
SELECT 
    @carrera_test := ac.cod_carrera,
    @cohorte_test := ac.fecha_cohorte,
    @pensum_test := ac.cod_pensum
FROM asignacion_carrera ac
WHERE ac.cod_usuario = @usuario_test
LIMIT 1;

-- ========================================================  
-- PASO 1: BUSCAR UN CURSO EXISTENTE O CREAR UNO DE PRUEBA
-- ========================================================  

-- Buscar un curso existente del pensum
SET @curso_test = NULL;
SET @cod_pensum_curso = NULL;
SELECT 
    @curso_test := pc.cod_curso,
    @cod_pensum_curso := pc.cod_pensum
FROM pensum_carrera pc
WHERE pc.cod_carrera = @carrera_test
LIMIT 1;

-- Si no hay curso, usar el primer curso disponible en curso_pensum
SET @curso_test = COALESCE(@curso_test, (SELECT cod_curso FROM curso_pensum LIMIT 1));
SET @cod_pensum_curso = COALESCE(@cod_pensum_curso, (SELECT cod_pensum FROM curso_pensum WHERE cod_curso = @curso_test LIMIT 1));

SELECT 
    @curso_test AS cod_curso_seleccionado,
    @cod_pensum_curso AS cod_pensum_seleccionado,
    (SELECT nombre FROM curso_pensum WHERE cod_curso = @curso_test AND cod_pensum = @cod_pensum_curso) AS nombre_curso;

-- ========================================================  
-- PASO 2: CREAR UN CATEDRÁTICO DE PRUEBA SI NO EXISTE
-- ========================================================  

SET @catedratico_test = (SELECT cod_usuario FROM usuario WHERE registro_academico = 'CAT999999' LIMIT 1);

-- Si no existe el catedrático de prueba, buscar cualquier docente existente
SET @catedratico_test = COALESCE(@catedratico_test, 
    (SELECT cod_usuario FROM usuario WHERE cod_rol = 76 LIMIT 1),  -- Rol DOCENTE
    (SELECT cod_usuario FROM usuario WHERE cod_rol = 1 LIMIT 1)  -- Cualquier usuario como fallback
);

SELECT 
    @catedratico_test AS cod_catedratico,
    (SELECT CONCAT(nombres, ' ', apellidos) FROM usuario WHERE cod_usuario = @catedratico_test) AS nombre_catedratico;

-- ========================================================  
-- PASO 3: CREAR HORARIO FINALIZADO DEL MES ANTERIOR
-- ========================================================  

-- Calcular fechas: curso del mes anterior que ya terminó
SET @fecha_inicio = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-05');  -- Inicio: día 5 del mes pasado
SET @fecha_fin = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-25');    -- Fin: día 25 del mes pasado (ya terminó)
SET @anio_curso = YEAR(@fecha_fin);
SET @mes_curso = MONTH(@fecha_fin);

SELECT 
    @fecha_inicio AS fecha_inicio_curso,
    @fecha_fin AS fecha_fin_curso,
    @anio_curso AS anio,
    @mes_curso AS mes,
    CASE 
        WHEN @fecha_fin < CURDATE() THEN '✓ Curso ya terminado'
        ELSE '⚠ ADVERTENCIA: Curso no ha terminado'
    END AS validacion_terminado,
    CASE 
        WHEN @fecha_fin < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN '✓ Es del mes anterior'
        ELSE '⚠ ADVERTENCIA: No es del mes anterior'
    END AS validacion_mes_anterior;

-- Buscar si ya existe un horario similar para este curso
SET @horario_existente = NULL;
SELECT 
    @horario_existente := cod_horario
FROM horario
WHERE cod_curso = @curso_test 
  AND cod_pensum = @cod_pensum_curso
  AND fecha_inicio = @fecha_inicio
  AND fecha_fin = @fecha_fin
LIMIT 1;

-- Si no existe, crear el horario
INSERT INTO horario (
    cod_curso, cod_pensum, fecha_inicio, fecha_fin, seccion, 
    cod_usuario_catedratico, anio, mes, fecha_cohorte, 
    aula, cupo, cod_bloque, activo, modalidad, sede, dias
)
SELECT 
    @curso_test,
    @cod_pensum_curso,
    @fecha_inicio,
    @fecha_fin,
    'A',  -- Sección A
    @catedratico_test,
    @anio_curso,
    @mes_curso,
    @cohorte_test,  -- Misma cohorte del estudiante
    'Aula Virtual',
    30,  -- Cupo
    1,   -- Bloque 1
    1,   -- Activo
    'Virtual',
    'Principal',
    'Lunes,Miércoles'
WHERE @horario_existente IS NULL;

-- Obtener el cod_horario (existente o recién creado)
SET @horario_test = COALESCE(@horario_existente, LAST_INSERT_ID());

-- Si LAST_INSERT_ID() es 0 (no se insertó nada), buscar el horario
SET @horario_test = COALESCE(@horario_test, 
    (SELECT cod_horario FROM horario 
     WHERE cod_curso = @curso_test AND fecha_inicio = @fecha_inicio AND fecha_fin = @fecha_fin 
     LIMIT 1)
);

SELECT 
    @horario_test AS cod_horario_creado,
    CASE 
        WHEN @horario_test IS NOT NULL AND @horario_test > 0 THEN '✓ Horario disponible'
        ELSE '✗ ERROR: No se pudo crear/obtener horario'
    END AS estado;

-- ========================================================  
-- PASO 4: CREAR ASIGNACIÓN DEL ESTUDIANTE AL CURSO
-- ========================================================  

-- Verificar si ya está asignado
SET @asignacion_existente = NULL;
SELECT 
    @asignacion_existente := cod_asignacion
FROM asignacion
WHERE cod_usuario = @usuario_test 
  AND cod_horario = @horario_test
LIMIT 1;

-- Crear asignación si no existe
INSERT INTO asignacion (cod_usuario, cod_horario, cod_orden, fecha_asignacion)
SELECT 
    @usuario_test,
    @horario_test,
    NULL,  -- Sin orden de pago para pruebas
    CURDATE()
WHERE @asignacion_existente IS NULL;

SET @asignacion_creada = COALESCE(@asignacion_existente, LAST_INSERT_ID());

SELECT 
    @asignacion_creada AS cod_asignacion_creada,
    CASE 
        WHEN @asignacion_creada IS NOT NULL AND @asignacion_creada > 0 THEN '✓ Asignación creada'
        ELSE 'ℹ INFO: Ya estaba asignado'
    END AS estado;

-- ========================================================  
-- PASO 5: VERIFICAR QUE APAREZCA PARA EVALUACIÓN
-- ========================================================  

SELECT 
    'VERIFICACIÓN FINAL' AS paso,
    a.cod_asignacion,
    a.cod_usuario AS cod_estudiante,
    h.cod_horario,
    h.fecha_inicio,
    h.fecha_fin,
    cp.nombre AS nombre_curso,
    CONCAT(cat.nombres, ' ', cat.apellidos) AS nombre_catedratico,
    h.fecha_cohorte,
    ac.fecha_cohorte AS cohorte_estudiante,
    -- Validaciones de negocio
    CASE WHEN h.fecha_fin <= CURDATE() THEN '✓' ELSE '✗' END AS curso_terminado,
    CASE WHEN h.fecha_fin < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN '✓' ELSE '✗' END AS mes_anterior,
    CASE WHEN (h.fecha_cohorte = ac.fecha_cohorte OR h.fecha_cohorte IS NULL) THEN '✓' ELSE '✗' END AS misma_cohorte,
    CASE WHEN er.id IS NULL THEN '✓ (Pendiente)' ELSE '✗ (Ya evaluado)' END AS evaluacion_pendiente,
    '---' AS resultado,
    CASE 
        WHEN h.fecha_fin <= CURDATE() 
         AND h.fecha_fin < DATE_FORMAT(CURDATE(), '%Y-%m-01')
         AND (h.fecha_cohorte = ac.fecha_cohorte OR h.fecha_cohorte IS NULL)
         AND er.id IS NULL 
        THEN '✓✓✓ APARECERÁ EN EVALUACIÓN DOCENTE ✓✓✓'
        ELSE '⚠ NO aparecerá - revisar validaciones'
    END AS estado_final
FROM asignacion a
JOIN horario h ON a.cod_horario = h.cod_horario
JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
LEFT JOIN usuario cat ON h.cod_usuario_catedratico = cat.cod_usuario
JOIN asignacion_carrera ac ON a.cod_usuario = ac.cod_usuario
LEFT JOIN evaluacion_respuesta er ON a.cod_horario = er.cod_horario AND a.cod_usuario = er.cod_usuario_estudiante
WHERE a.cod_usuario = @usuario_test
  AND a.cod_horario = @horario_test;

-- ========================================================  
-- DATOS PARA ACCEDER AL SISTEMA
-- ========================================================  
SELECT 
    'DATOS DE ACCESO' AS informacion,
    u.registro_academico,
    u.email,
    'La contraseña está hasheada. Usar login normal o contactar admin para reset' AS nota
FROM usuario u
WHERE u.cod_usuario = @usuario_test;

-- ========================================================  
-- INSTRUCCIONES DE PRUEBA
-- ========================================================  
SELECT '
INSTRUCCIONES PARA PROBAR:
============================
1. Acceder a: http://localhost:8080/login
2. Usuario: 20250016
3. Password: (según configuración local, o usar "password" si es la demo)
4. Ir a: http://localhost:8080/evaluacion-docente
5. Debería aparecer el curso creado arriba para evaluar
6. Completar evaluación
7. Verificar que al completar todas, ya se puede acceder a asignación de cursos

NOTA: Si el curso no aparece, verificar:
- Que h.fecha_fin < DATE_FORMAT(CURDATE(), "%Y-%m-01")  (mes anterior)
- Que h.fecha_cohorte = cohorte del estudiante
- Que no exista evaluacion_respuesta para ese horario+usuario
' AS instrucciones_prueba;

-- ========================================================  
-- FIN DEL SCRIPT
-- ========================================================  
