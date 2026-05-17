-- ============================================================
-- SCRIPT DE VERIFICACIÓN: Pasos del Módulo de Graduación
-- ============================================================
-- Este script verifica si todos los pasos del catálogo están 
-- correctamente configurados en la base de datos.
-- 
-- Ejecutar para diagnosticar problemas con la visualización 
-- del paso 5 (Carta de Examinadores)
-- ============================================================

-- 1. Verificar todos los pasos en el catálogo
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

-- 2. Verificar específicamente el paso 5 (Carta de Examinadores)
-- Debe tener fase = 'carta_examinadores' y numero_orden = 5
SELECT 
    'PASO 5 - CARTA EXAMINADORES' as verificacion,
    cod_paso,
    cod_tipo_examen,
    numero_orden,
    fase,
    nombre,
    template_parcial
FROM examen_paso_catalogo
WHERE numero_orden = 5 
   OR fase = 'carta_examinadores'
   OR template_parcial = 'paso5-carta-examinadores';

-- 3. Verificar si hay procesos activos y en qué paso están
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
LIMIT 10;

-- 4. Verificar los pasos completados/iniciados para un proceso específico
-- (Descomenta y modifica el cod_proceso según necesites)
-- SELECT 
--     epp.cod_proceso,
--     epp.cod_paso,
--     epc.nombre,
--     epc.fase,
--     epc.numero_orden,
--     epp.estado,
--     epp.fecha_inicio,
--     epp.fecha_completado
-- FROM examen_proceso_paso epp
-- JOIN examen_paso_catalogo epc ON epc.cod_paso = epp.cod_paso
-- WHERE epp.cod_proceso = 1  -- Cambiar por el ID de proceso que quieras verificar
-- ORDER BY epc.numero_orden;

-- 5. Verificar si la columna fase tiene todos los valores ENUM necesarios
-- (Para diagnosticar si falta el valor 'carta_examinadores' en el ENUM)
SHOW COLUMNS FROM examen_paso_catalogo WHERE Field = 'fase';
