# ============================================================
# GUÍA DE INSTALACIÓN - MÓDULO DE GRADUACIÓN
# ============================================================
# Fecha: 2026-05-27
# Versión: 2.0 (Ternas independientes por fase)
#
# Esta guía describe el proceso completo de instalación del
# módulo de graduación, incluyendo base de datos y archivos PHP.
# ============================================================

## ============================================================
## SECCIÓN 1: INSTALACIÓN NUEVA (Desde Cero)
## ============================================================
## Cuando: Base de datos limpia o primera instalación
## Tiempo estimado: 5 minutos
## ============================================================

### PASO 1: Pre-requisitos
# Asegúrate de tener:
# - Base de datos db_postgrados creada
# - Tablas base del sistema (usuario, rol, etc.) - archivo 20250718Postgrados.sql
# - Docker/contenedor MySQL corriendo

### PASO 2: Ejecutar Script Principal (ÚNICO NECESARIO)
# Este script contiene TODAS las tablas del módulo de graduación:
# - 11 tablas base (pasos 1-4, examen_privado y examen_general)
# - 4 tablas paso 5 (Carta de Examinadores)
# - 6 tablas paso 6 (Autorización de Impresión)
# - Total: 22 tablas
#
# IMPORTANTE: Ya está consolidado todo en un solo archivo

docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/modulo graduacion/modulo_graduacion.sql"

### PASO 3: Insertar Datos de Semilla (OPCIONAL)
# Estos datos son ejemplos para empezar a trabajar.
# En producción se configuran desde la interfaz administrativa.

# 3.1 Licenciados en Letras Calificados (para paso 6)
docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/modulo graduacion/inserts_iniciales/profesionales_calificados_seed.sql"

# 3.2 Miembros de Junta Directiva (para paso 6)
docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/modulo graduacion/inserts_iniciales/junta_directiva_seed.sql"

### PASO 4: Verificar Instalación
# Ejecutar script de verificación:
docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/creacion_usuarios/verificar_pasos_graduacion.sql"

# Deberías ver:
# - 9 pasos en examen_paso_catalogo (4 privado + 1 carta + 1 autorizacion + 4 general)
# - 22 tablas del módulo creadas
# - Columna 'fase' en examen_terna con valores ENUM correctos

### PASO 5: Reiniciar Servidor Web
docker-compose restart web

### PASO 6: Verificar Archivos PHP
# Asegúrate de que estos archivos estén actualizados:
# (Deberían estar ya si seguiste el desarrollo)

# Managers:
# - module/Eep/src/Service/ExamenManager.php
#   * guardarTerna() - ahora recibe parámetro $fase
#   * getTerna() - ahora filtra por fase
#
# - module/Eep/src/Service/StudentGraduationManager.php
#   * getTerna() - ahora filtra por fase
#
# - module/Eep/src/Service/CartaGenerator.php
#   * Ahora usa fase = 'examen_privado' para cartas

# Controladores:
# - module/Eep/src/Controller/ExamenController.php
#   * Llamadas a guardarTerna() ahora pasan $fase
#   * Llamadas a getTerna() pasan fase correcta
#
# - module/Eep/src/Controller/StudentGraduationController.php
#   * Ya estaba correcto (pasa $faseActual)

### ¡LISTO! La instalación está completa.


## ============================================================
## SECCIÓN 2: MIGRACIÓN (Actualización de Versión Anterior)
## ============================================================
## Cuando: Ya tienes el módulo instalado y necesitas actualizar
## a la versión con ternas independientes
## Tiempo estimado: 10 minutos
## ¡¡¡HACER BACKUP ANTES!!!
## ============================================================

### PASO 1: BACKUP (IMPORTANTE)
docker-compose exec db mysqldump -u user -ppassword db_postgrados > backup_pre_migracion_$(date +%Y%m%d_%H%M%S).sql

### PASO 2: Migrar Ternas a Estructura Nueva
# Este script agrega la columna 'fase' a examen_terna y actualiza índices
docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/creacion_usuarios/migracion_terna_fase.sql"

### PASO 3: Verificar Migración
docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/creacion_usuarios/verificar_ternas_fase.sql"

### PASO 4: Actualizar Código PHP
# Asegúrate de tener los archivos PHP actualizados (ver Sección 1, Paso 6)
# Si usas git:
# git pull origin main

### PASO 5: Limpiar Caché (si aplica)
docker-compose exec web rm -rf data/cache/*

### PASO 6: Reiniciar Servidor
docker-compose restart web

### ¡LISTO! La migración está completa.


## ============================================================
## SECCIÓN 3: RESUMEN DE TABLAS CREADAS
## ============================================================

# Catálogos (4 tablas):
# 1. examen_tipo                    - Tipos de examen
# 2. examen_paso_catalogo           - Definición de pasos
# 3. examen_requisito_documento     - Requisitos por paso
# 4. examen_carta_plantilla         - Plantillas de cartas

# Tracking de Procesos (6 tablas):
# 5. examen_proceso                 - Procesos maestros
# 6. examen_proceso_paso            - Estado por paso
# 7. examen_documento               - Archivos digitales
# 8. examen_revision_documento      - Revisiones staff
# 9. examen_documento_fisico        - Checklist físico
# 10. examen_historial              - Auditoría

# Ternas (1 tabla):
# 11. examen_terna                  - Examinadores (¡AHORA CON FASE!)

# Paso 5 - Carta Examinadores (4 tablas):
# 12. examen_correccion_ciclo       - Ciclos de corrección
# 13. examen_correccion_evidencia   - Evidencias de correos
# 14. examen_carta_examinadores     - Cartas generadas
# 15. archivo_local                 - Archivos locales (usada en todo el módulo)

# Paso 6 - Autorización Impresión (6 tablas):
# 16. examen_autorizacion_config              - Config global
# 17. examen_autorizacion_documento_soporte   - Docs soporte
# 18. examen_profesional_calificado           - Licenciados
# 19. examen_carta_descarga                   - Cartas genéricas
# 20. examen_junta_directiva                  - Junta directiva
# 21. examen_autorizacion_proceso             - Estado por proceso

# Total: 21 tablas (archivo_local es compartida)


## ============================================================
## SECCIÓN 4: ESTRUCTURA DE FASES Y PASOS
## ============================================================

# FLUJO COMPLETO:
#
# FASE 1: examen_privado (Pasos 1-4)
#   Paso 1: Revisión de Papelería
#   Paso 2: Entrega de Documentación Física  
#   Paso 3: Terna Examinadora (fase = 'examen_privado')
#   Paso 4: Notificación al Estudiante
#
# FASE 2: carta_examinadores (Paso 5)
#   Paso 5: Carta de Examinadores
#           - Usa terna del examen privado
#           - Genera carta .docx
#
# FASE 3: autorizacion_impresion (Paso 6)
#   Paso 6: Autorización de Impresión del Proyecto
#           - Selección de profesional calificado
#           - 2 sub-pasos (partes)
#
# FASE 4: examen_general (Pasos 1-4)
#   Paso 1: Revisión de Papelería
#   Paso 2: Entrega de Documentación Física
#   Paso 3: Terna Examinadora (fase = 'examen_general')
#           - ¡DIFERENTE a la del examen privado!
#   Paso 4: Notificación al Estudiante


## ============================================================
## SECCIÓN 5: TROUBLESHOOTING
## ============================================================

# PROBLEMA: "Columna 'fase' no existe en examen_terna"
# SOLUCIÓN: Ejecutar migración_terna_fase.sql (Sección 2, Paso 2)

# PROBLEMA: "No se encuentra el paso 5 (Carta de Examinadores)"
# SOLUCIÓN: Ejecutar:
docker-compose exec db mysql -u user -ppassword db_postgrados -e "
INSERT INTO examen_paso_catalogo 
  (cod_tipo_examen, numero_orden, fase, nombre, template_parcial, es_ultimo_paso, activo)
VALUES 
  (NULL, 5, 'carta_examinadores', 'Carta de Examinadores', 'paso5-carta-examinadores', 0, 1)
ON DUPLICATE KEY UPDATE activo = 1;"

# PROBLEMA: Error de FOREIGN KEY al crear tablas
# SOLUCIÓN: Asegurarse de que las tablas base (usuario, rol) existen:
docker-compose exec db mysql -u user -ppassword db_postgrados < database/20250718Postgrados.sql

# PROBLEMA: "No se reconoce la fase 'carta_examinadores'"
# SOLUCIÓN: Verificar ENUM en examen_paso_catalogo:
docker-compose exec db mysql -u user -ppassword db_postgrados -e "
ALTER TABLE examen_paso_catalogo 
MODIFY COLUMN fase ENUM('examen_privado','carta_examinadores','autorizacion_impresion','examen_general')
NOT NULL DEFAULT 'examen_privado';"


## ============================================================
## SECCIÓN 6: VERIFICACIÓN RÁPIDA
## ============================================================

# Comando para verificar estado completo:
docker-compose exec db mysql -u user -ppassword db_postgrados -e "
SELECT '=== TABLAS DEL MÓDULO ===' as info;
SHOW TABLES LIKE 'examen_%';

SELECT '=== PASOS CONFIGURADOS ===' as info;
SELECT numero_orden, fase, nombre, template_parcial 
FROM examen_paso_catalogo 
ORDER BY FIELD(fase, 'examen_privado', 'carta_examinadores', 'autorizacion_impresion', 'examen_general'), numero_orden;

SELECT '=== ESTRUCTURA DE examen_terna ===' as info;
DESCRIBE examen_terna;"


## ============================================================
## CONTACTO Y SOPORTE
## ============================================================

# Para reportar problemas o sugerencias:
# - Revisar logs: docker-compose logs web
# - Verificar logs de Apache: docker-compose exec web tail -f /var/log/apache2/error.log
# - Estado de la BD: docker-compose exec db mysql -u user -ppassword db_postgrados -e "SHOW TABLES;"

# ============================================================
# FIN DE LA GUÍA
# ============================================================
