-- ============================================================
-- SCRIPT DE DIAGNÓSTICO: Verificar estado del Módulo de Graduación
-- ============================================================
-- Ejecutar para verificar qué datos existen en las tablas del módulo
--
-- USO:
--   docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
--     "database/modulo graduacion/VERIFICAR_ESTADO.sql"
-- ============================================================

SELECT '========================================' AS 'DIAGNÓSTICO DEL MÓDULO DE GRADUACIÓN';
SELECT 'Fecha: ' || NOW() AS 'Timestamp';
SELECT '========================================' AS '========================================';

-- 1. Verificar examen_tipo
SELECT '' AS '';
SELECT '--- 1. examen_tipo (Debe tener 3 registros) ---' AS 'Tabla';
SELECT COUNT(*) AS total_registros FROM examen_tipo;
SELECT cod_tipo_examen, nombre, descripcion, activo FROM examen_tipo ORDER BY cod_tipo_examen;

-- 2. Verificar examen_paso_catalogo
SELECT '' AS '';
SELECT '--- 2. examen_paso_catalogo (Debe tener 8+ registros) ---' AS 'Tabla';
SELECT COUNT(*) AS total_registros FROM examen_paso_catalogo;
SELECT cod_paso, cod_tipo_examen, numero_orden, fase, nombre, es_ultimo_paso, activo 
FROM examen_paso_catalogo 
ORDER BY FIELD(fase, 'examen_privado', 'carta_examinadores', 'autorizacion_impresion', 'examen_general'), numero_orden;

-- 3. Verificar examen_requisito_documento
SELECT '' AS '';
SELECT '--- 3. examen_requisito_documento (Semillas de ejemplo) ---' AS 'Tabla';
SELECT COUNT(*) AS total_registros FROM examen_requisito_documento;
SELECT cod_requisito, cod_tipo_examen, cod_paso, nombre, tipo_entrega, activo 
FROM examen_requisito_documento 
ORDER BY cod_tipo_examen, cod_paso;

-- 4. Verificar otras tablas de catálogo
SELECT '' AS '';
SELECT '--- 4. Otras tablas de catálogo ---' AS 'Categoría';

SELECT 'examen_autorizacion_config' AS tabla, COUNT(*) AS registros FROM examen_autorizacion_config
UNION ALL
SELECT 'examen_carta_plantilla', COUNT(*) FROM examen_carta_plantilla
UNION ALL
SELECT 'examen_profesional_calificado', COUNT(*) FROM examen_profesional_calificado
UNION ALL
SELECT 'examen_junta_directiva', COUNT(*) FROM examen_junta_directiva
UNION ALL
SELECT 'examen_carta_descarga', COUNT(*) FROM examen_carta_descarga
UNION ALL
SELECT 'examen_autorizacion_documento_soporte', COUNT(*) FROM examen_autorizacion_documento_soporte;

-- 5. Verificar tablas de tracking (deben estar vacías en instalación nueva)
SELECT '' AS '';
SELECT '--- 5. Tablas de tracking (deben estar vacías o con pocos datos) ---' AS 'Categoría';

SELECT 'examen_proceso' AS tabla, COUNT(*) AS registros FROM examen_proceso
UNION ALL
SELECT 'examen_proceso_paso', COUNT(*) FROM examen_proceso_paso
UNION ALL
SELECT 'examen_documento', COUNT(*) FROM examen_documento
UNION ALL
SELECT 'examen_terna', COUNT(*) FROM examen_terna
UNION ALL
SELECT 'examen_carta_examinadores', COUNT(*) FROM examen_carta_examinadores
UNION ALL
SELECT 'examen_historial', COUNT(*) FROM examen_historial;

-- 6. Resumen de estado
SELECT '' AS '';
SELECT '========================================' AS '========================================';
SELECT 'RESUMEN' AS 'Sección';
SELECT '========================================' AS '========================================';

SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM examen_tipo) = 3 THEN '✅ examen_tipo: OK (3 registros)'
        ELSE '❌ examen_tipo: ERROR - Se esperaban 3 registros, hay ' || (SELECT COUNT(*) FROM examen_tipo)
    END AS verificacion;

SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM examen_paso_catalogo) >= 8 THEN '✅ examen_paso_catalogo: OK (' || (SELECT COUNT(*) FROM examen_paso_catalogo) || ' registros)'
        ELSE '❌ examen_paso_catalogo: ERROR - Se esperaban 8+ registros, hay ' || (SELECT COUNT(*) FROM examen_paso_catalogo)
    END AS verificacion;

SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM examen_requisito_documento) > 0 THEN '✅ examen_requisito_documento: OK (' || (SELECT COUNT(*) FROM examen_requisito_documento) || ' registros)'
        ELSE '⚠️  examen_requisito_documento: VACÍA - Necesita insertar semillas'
    END AS verificacion;

SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM examen_carta_plantilla) >= 1 THEN '✅ examen_carta_plantilla: OK (' || (SELECT COUNT(*) FROM examen_carta_plantilla) || ' registros)'
        ELSE '❌ examen_carta_plantilla: VACÍA - Necesita ejecutar modulo_graduacion_carta_02_seeds.sql'
    END AS verificacion;

SELECT '' AS '';
SELECT '========================================' AS '========================================';
