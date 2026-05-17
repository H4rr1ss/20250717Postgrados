-- ============================================================
-- SCRIPT: Verificar y Corregir Configuración Paso 5
-- ============================================================
-- Este script verifica si el paso 5 (Carta de Examinadores) 
-- está correctamente configurado y lo corrige si es necesario.
-- ============================================================

-- 1. Verificar todos los pasos existentes
SELECT '=== PASOS EXISTENTES EN examen_paso_catalogo ===' as info;
SELECT 
    cod_paso,
    cod_tipo_examen,
    numero_orden,
    fase,
    nombre,
    template_parcial,
    es_ultimo_paso,
    activo
FROM examen_paso_catalogo
ORDER BY 
    FIELD(fase, 'examen_privado', 'carta_examinadores', 'autorizacion_impresion', 'examen_general'),
    numero_orden;

-- 2. Verificar específicamente el paso 5
SELECT '=== PASO 5 - CARTA EXAMINADORES ===' as info;
SELECT 
    cod_paso,
    cod_tipo_examen,
    numero_orden,
    fase,
    nombre,
    template_parcial,
    es_ultimo_paso,
    activo
FROM examen_paso_catalogo
WHERE fase = 'carta_examinadores'
   OR template_parcial = 'paso5-carta-examinadores';

-- 3. Verificar procesos activos y su paso actual
SELECT '=== PROCESOS ACTIVOS ===' as info;
SELECT 
    ep.cod_proceso,
    ep.cod_usuario,
    u.nombres,
    u.apellidos,
    ep.cod_paso_actual,
    epc.nombre as nombre_paso_actual,
    epc.fase as fase_paso_actual,
    epc.numero_orden as orden_paso_actual,
    ep.fecha_solicitud
FROM examen_proceso ep
JOIN usuario u ON u.cod_usuario = ep.cod_usuario
LEFT JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
WHERE ep.cancelado = 0
ORDER BY ep.fecha_solicitud DESC
LIMIT 5;

-- 4. Verificar pasos completados/iniciados para procesos
SELECT '=== PASOS INICIADOS/COMPLETADOS ===' as info;
SELECT 
    epp.cod_proceso,
    epp.cod_paso,
    epc.nombre,
    epc.fase,
    epc.numero_orden,
    epp.estado,
    epp.fecha_inicio,
    epp.fecha_completado
FROM examen_proceso_paso epp
JOIN examen_paso_catalogo epc ON epc.cod_paso = epp.cod_paso
ORDER BY epp.cod_proceso, epc.numero_orden
LIMIT 20;
