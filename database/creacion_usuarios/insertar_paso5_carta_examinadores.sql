-- ============================================================
-- SCRIPT: Insertar Paso 5 - Carta de Examinadores (si no existe)
-- ============================================================
-- Este script inserta el paso 5 en el catálogo si no existe.
-- 
-- Ejecutar este script si el paso 5 no aparece en la verificación:
-- database/creacion_usuarios/verificar_pasos_graduacion.sql
-- ============================================================

-- Verificar si el paso 5 ya existe
SET @paso5_existe = (SELECT COUNT(*) FROM examen_paso_catalogo 
                      WHERE numero_orden = 5 
                         OR fase = 'carta_examinadores'
                         OR template_parcial = 'paso5-carta-examinadores');

-- Solo insertar si no existe
INSERT INTO examen_paso_catalogo
  (`cod_tipo_examen`, `numero_orden`, `fase`, `nombre`,
   `template_parcial`, `es_ultimo_paso`, `activo`)
SELECT NULL, 5, 'carta_examinadores', 'Carta de Examinadores', 
       'paso5-carta-examinadores', 0, 1
WHERE @paso5_existe = 0;

-- Verificar el resultado
SELECT 
    CASE 
        WHEN @paso5_existe > 0 THEN 'El paso 5 ya existía - No se realizaron cambios'
        ELSE 'Paso 5 insertado correctamente'
    END as resultado;

-- Mostrar el estado final de los pasos
SELECT 
    cod_paso,
    numero_orden,
    fase,
    nombre,
    template_parcial,
    activo
FROM examen_paso_catalogo
ORDER BY numero_orden;
