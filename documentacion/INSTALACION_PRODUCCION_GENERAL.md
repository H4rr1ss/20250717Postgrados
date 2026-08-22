# Plan Maestro de Instalación en Producción

> **Documento de bitácora y checklist general para la instalación de todos los módulos nuevos en la plataforma de Postgrados.**
> Fecha de actualización: 2026-08-22

---

## 📋 Alcance

Este checklist maestro unifica la instalación de los tres módulos nuevos desplegados en la plataforma:

1. **Módulo de Evaluación Docente** — Cuestionarios de evaluación anónima por curso.
2. **Módulo de Formulario de Admisión** — Gestión de formularios públicos de admisión.
3. **Módulo de Graduación** — Proceso completo de examen de graduación (privado y general).

**Cada módulo mantiene su propia documentación detallada.** Este documento orquesta el **orden de ejecución**, los **pre-requisitos comunes** y las **verificaciones globales** que afectan a todos los módulos. Para detalles específicos de un módulo, consultar la guía referenciada en cada sección.

---

## 🔗 Referencias Rápidas por Módulo

| Módulo | Guía de Producción Detallada | Script SQL Principal | Script Bash |
|--------|------------------------------|----------------------|-------------|
| Evaluación Docente | `INSTALACION_PRODUCCION_EVALUACION_DOCENTE.md` | `database/evaluacion_docente.sql` | Ninguno |
| Formulario de Admisión | `INSTALACION_PRODUCCION_FORMULARIO_ADMISION.md` | `database/modulo_aspirantes_final.sql` | Ninguno |
| Graduación | `documentacion/modulo-graduacion/CHECKLIST_MODULO_GRADUACION.md` | `database/modulo graduacion/modulo_graduacion_schema.sql` | `documentacion/modulo-graduacion/inicializar-modulo-graduacion.sh` (carpetas) |
| Graduación (verificación) | `documentacion/modulo-graduacion/verificar-modulo-graduacion.sh` | `database/modulo graduacion/matriz_evaluacion_completo.sql` | `documentacion/modulo-graduacion/verificar-modulo-graduacion.sh` |
| Graduación (seeds) | `documentacion/modulo-graduacion/MODULO_GRADUACION_REQUISITOS_INICIALES.md` | `database/modulo graduacion/ejecuciones_extra.sql` | Ninguno |

---

## ✅ FASE 0: Pre-requisitos del Servidor (Comunes a todos los módulos)

> **Estado inicial:** Base de datos `db_postgrados` ya debe existir con el schema base del sistema (`database/20250718Postgrados.sql` ya aplicado).

```bash
□ PHP 7.4 con Apache (o contenedores Docker levantados)
□ MySQL 5.7 accesible (puerto 3306 interno, 3307 externo en Docker)
□ Composer instalado y dependencias base del proyecto:
  docker-compose exec web composer install

□ Extensiones PHP habilitadas:
  - gd (con soporte FreeType para gráficas PDF)
  - pdo_mysql
  - mbstring
  - fileinfo (para uploads)

□ Carpeta de sesiones con permisos correctos:
  docker-compose exec web ls -ld /var/www/data/sessiones
  # Debe mostrar: drwxrwxrwt ... www-data www-data ... mode 1733

□ Configuración de base de datos en config/autoload/local.php:
  - Host, usuario, contraseña de MySQL verificados

□ Backup de la base de datos actual ANTES de cualquier cambio:
  docker-compose exec db mysqldump -u user -ppassword db_postgrados > backup_pre_modulos_$(date +%Y%m%d_%H%M%S).sql

□ Backup de la carpeta data/:
  tar -czf backup_data_$(date +%Y%m%d).tar.gz data/
```

---

## ✅ FASE 1: Módulo de Evaluación Docente

> **Guía detallada:** `INSTALACION_PRODUCCION_EVALUACION_DOCENTE.md`
> **Dependencias:** Ninguna entre módulos. Puede instalarse primero.

```bash
□ 1.1 Desplegar archivos PHP y vistas
  Asegurar que estos archivos estén en producción:
  - module/Eep/src/Service/EvaluacionDocenteManager.php
  - module/Eep/src/Service/EvaluacionDocenteGraficaService.php
  - module/Eep/src/Controller/EvaluacionDocenteController.php
  - module/Eep/src/Controller/Factory/EvaluacionDocenteControllerFactory.php
  - module/Eep/view/eep/evaluacion-docente/*.phtml
  - module/Eep/config/module.config.php (rutas)
  - module/Eep/config/access_filter.php (permisos)
  - module/Eep/config/menus.php (menús)
  - module/Eep/src/ValueObject/View.php

□ 1.2 Ejecutar script SQL de base de datos
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < database/evaluacion_docente.sql

□ 1.3 Verificar acciones ACL (si el módulo nunca se instaló)
  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT cod_accion, nombre FROM accion WHERE cod_accion IN (80,81,82,85,86,87,140,141,142,144,145,146);"
  # Si faltan, ejecutar los INSERT documentados en INSTALACION_PRODUCCION_EVALUACION_DOCENTE.md sección 3.1

□ 1.4 Verificar gráficas PDF (si se usará reporte con gráficas)
  docker-compose exec web php -r "echo extension_loaded('gd') && function_exists('imagettftext') ? 'OK' : 'FALTA FREETYPE';"
  docker-compose exec web test -f /var/www/data/fonts/DejaVuSans.ttf && echo "Fuente OK" || echo "FALTA Fuente TTF"

□ 1.5 Verificación post-instalación
  - Menú "Reporte Evaluación Docente" visible para DIRECTOR
  - Botón de evaluación visible para estudiantes con cursos finalizados
  - Bloqueo de asignación funciona cuando hay evaluaciones pendientes
```

**Responsable Fase 1:** ___________________  
**Fecha:** ___________________  
**Verificado por:** ___________________

---

## ✅ FASE 2: Módulo de Formulario de Admisión

> **Guía detallada:** `INSTALACION_PRODUCCION_FORMULARIO_ADMISION.md`
> **Dependencias:** Ninguna entre módulos. Puede instalarse en paralelo a Fase 1.

```bash
□ 2.1 Desplegar archivos PHP y vistas
  Asegurar que estos archivos estén en producción:
  - module/Eep/src/Controller/FormularioAdmisionController.php
  - module/Eep/src/Controller/Factory/FormularioAdmisionControllerFactory.php
  - module/Eep/src/Service/FormularioAdmisionManager.php
  - module/Eep/src/Service/Factory/FormularioAdmisionManagerFactory.php
  - module/Eep/src/Form/FormularioAdmisionForm.php
  - module/Eep/src/Entity/FormularioAdmision.php
  - module/Eep/src/Entity/RespuestaAspirante.php
  - module/Eep/src/Entity/CampoFormulario.php
  - module/Eep/view/eep/formulario-admision/*.phtml
  - module/Eep/config/module.config.php (rutas)
  - module/Eep/config/access_filter.php (permisos)
  - module/Eep/config/menus.php (menú)
  - module/Eep/src/ValueObject/View.php

□ 2.2 Ejecutar script SQL de base de datos
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < database/modulo_aspirantes_final.sql

□ 2.3 Verificar acciones ACL
  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT cod_accion, nombre FROM accion WHERE cod_accion BETWEEN 68 AND 76;"
  # Si faltan, ejecutar los INSERT documentados en INSTALACION_PRODUCCION_FORMULARIO_ADMISION.md sección 3.2

□ 2.4 Preparar carpeta de uploads
  docker-compose exec web mkdir -p /var/www/data/admisiones
  docker-compose exec web chown -R www-data:www-data /var/www/data/admisiones
  docker-compose exec web chmod -R 755 /var/www/data/admisiones

□ 2.5 Verificación post-instalación
  - Menú "Formulario de Admisión" visible para DIRECTOR/ASISTENTE
  - Ruta pública /admisiones accesible sin login
  - Envío de formulario de prueba funciona
  - Respuesta aparece en panel admin
```

**Responsable Fase 2:** ___________________  
**Fecha:** ___________________  
**Verificado por:** ___________________

---

## ✅ FASE 3: Módulo de Graduación

> **Guía detallada:** `documentacion/modulo-graduacion/CHECKLIST_MODULO_GRADUACION.md`
> **Documentación técnica:** `documentacion/modulo-graduacion/MODULO_GRADUACION_REQUISITOS_INICIALES.md`
> **Nota:** Es el módulo más complejo. Recomendado instalarlo después de los otros dos.

### Paso 3.1: Infraestructura de carpetas y archivos físicos

```bash
□ Crear estructura de carpetas (automático o manual):
  # Opción automática:
  ./documentacion/modulo-graduacion/inicializar-modulo-graduacion.sh
  
  # Verificación manual:
  docker-compose exec web ls -la /var/www/data/graduacion/

□ Copiar plantilla CRÍTICA general.docx:
  cp <ruta-del-backup-o-plantilla>/general.docx data/graduacion/plantillas/carta-examinadores/
  docker-compose exec web ls -la /var/www/data/graduacion/plantillas/carta-examinadores/general.docx

□ Verificar imagen de footer de correos:
  docker-compose exec web test -f /var/www/public/img/email-footer.jpg && echo "OK" || echo "Opcional - no crítico"
```

### Paso 3.2: Base de datos (3 scripts en orden)

```bash
□ Script 1 - Schema principal (23 tablas):
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
    "database/modulo graduacion/modulo_graduacion_schema.sql"

□ Script 2 - Matriz de evaluación (4 tablas + 20 seeds):
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
    "database/modulo graduacion/matriz_evaluacion_completo.sql"

□ Script 3 - Seeds, roles, tipos, ACL, configuración inicial:
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
    "database/modulo graduacion/ejecuciones_extra.sql"

□ Verificación completa de BD:
  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SHOW TABLES LIKE 'examen_%';"
  # Debe mostrar 23+ tablas

  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT * FROM rol WHERE cod_rol = 11;"
  # Debe mostrar: Secretario de Examen Privado

  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT cod_usuario, correo, nombres, apellidos FROM usuario WHERE cod_usuario = 3568;"
  # Debe mostrar: secretario.examen@farusac.edu.gt

  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT cod_accion, nombre FROM accion WHERE cod_accion BETWEEN 100 AND 170 ORDER BY cod_accion;"
  # Debe mostrar acciones 100-170 (graduación)
```

### Paso 3.3: Dependencias y configuración

```bash
□ Instalar/verificar dependencias Composer:
  docker-compose exec web composer install
  docker-compose exec web test -d vendor/zendframework/zend-mail && echo "zend-mail OK"
  docker-compose exec web test -d vendor/phpoffice/phpword && echo "phpword OK"
  docker-compose exec web test -d vendor/ezyang/htmlpurifier && echo "htmlpurifier OK"

□ Configurar SMTP (si se usarán notificaciones por correo):
  Editar: config/autoload/local.php
  Ver ejemplo en: documentacion/modulo-graduacion/CHECKLIST_MODULO_GRADUACION.md Fase 5.3

□ Verificar fuente TTF para gráficas PDF (compartido con Evaluación Docente):
  docker-compose exec web test -f /var/www/data/fonts/DejaVuSans.ttf && echo "OK" || echo "Instalar si se usarán PDFs con gráficas"
```

### Paso 3.4: Reinicio y verificación

```bash
□ Limpiar caché y reiniciar:
  docker-compose exec web rm -rf /var/www/data/cache/*
  docker-compose restart web

□ Ejecutar script de verificación automática:
  ./documentacion/modulo-graduacion/verificar-modulo-graduacion.sh
  # Debe mostrar "TODO CORRECTO" o listar solo advertencias menores

□ Prueba funcional:
  - Login con secretario.examen@farusac.edu.gt / PostgradosUsac2024
  - Menú "Módulo de Graduación" visible
  - Iniciar un proceso de prueba y verificar que avanza entre pasos
```

**Responsable Fase 3:** ___________________  
**Fecha:** ___________________  
**Verificado por:** ___________________

---

## ✅ FASE 4: Verificaciones Globales Post-Instalación

> Verificar que los módulos coexisten sin conflictos.

```bash
□ Menús laterales visibles según rol:
  - DIRECTOR: Formulario de Admisión, Reporte Evaluación Docente, Módulo de Graduación
  - ASISTENTE: Formulario de Admisión, Módulo de Graduación
  - ESTUDIANTE: Evaluación Docente (pendientes), Graduación (si tiene proceso activo)

□ Sin duplicados en códigos de acción (tabla accion):
  docker-compose exec db mysql -u user -ppassword db_postgrados -e "
    SELECT cod_accion, COUNT(*) as c 
    FROM accion 
    GROUP BY cod_accion 
    HAVING c > 1;
  "
  # NO debe retornar filas

□ Sin tablas faltantes esperadas:
  docker-compose exec db mysql -u user -ppassword db_postgrados -e "
    SHOW TABLES LIKE 'evaluacion_%';
    SHOW TABLES LIKE 'formulario_%';
    SHOW TABLES LIKE 'examen_%';
  "

□ Sin archivos críticos faltantes:
  docker-compose exec web test -f /var/www/data/graduacion/plantillas/carta-examinadores/general.docx
  docker-compose exec web test -d /var/www/data/admisiones
  docker-compose exec web test -f /var/www/public/img/email-footer.jpg

□ phpcs limpio sobre archivos nuevos:
  docker-compose exec web composer cs-check
  # Debe pasar sin errores en los archivos desplegados
```

**Responsable Verificación Global:** ___________________  
**Fecha:** ___________________

---

## 🔙 FASE 5: Rollback General (Emergencia)

> **Advertencia:** Estos pasos eliminan datos. Usar solo con backup previo y autorización.

```bash
□ 5.1 Detener servicio web (evitar writes concurrentes):
  docker-compose stop web

□ 5.2 Rollback Módulo de Graduación (más destructivo, hacer primero):
  # Opcional: ejecutar checklist de rollback individual:
  # Ver: documentacion/modulo-graduacion/CHECKLIST_MODULO_GRADUACION.md sección "ROLLBACK"
  # Comandos resumidos:
  docker-compose exec -T db mysql -u user -ppassword db_postgrados -e "
    DROP TABLE IF EXISTS examen_matriz_respuesta, examen_matriz_evaluacion, examen_matriz_pregunta, examen_matriz_tipo;
    DROP TABLE IF EXISTS examen_acta_general, examen_acta_privado, examen_acta_correlativo;
    DROP TABLE IF EXISTS examen_autorizacion_proceso, examen_junta_directiva, examen_carta_descarga;
    DROP TABLE IF EXISTS examen_profesional_calificado, examen_autorizacion_documento_soporte, examen_autorizacion_config;
    DROP TABLE IF EXISTS examen_correccion_evidencia, examen_correccion_ciclo;
    DROP TABLE IF EXISTS examen_terna, examen_examinador;
    DROP TABLE IF EXISTS examen_documento_fisico, examen_revision_documento, archivo_local, examen_documento;
    DROP TABLE IF EXISTS examen_proceso_paso, examen_proceso;
    DROP TABLE IF EXISTS examen_requisito_documento, examen_paso_catalogo, examen_tipo;
  "
  docker-compose exec -T db mysql -u user -ppassword db_postgrados -e "
    DELETE FROM usuario_rol WHERE cod_rol = 11;
    DELETE FROM usuario WHERE cod_usuario = 3568;
    DELETE FROM rol WHERE cod_rol = 11;
    DELETE FROM accion WHERE cod_accion BETWEEN 100 AND 170;
  "
  docker-compose exec web rm -rf /var/www/data/graduacion

□ 5.3 Rollback Módulo de Formulario de Admisión:
  docker-compose exec -T db mysql -u user -ppassword db_postgrados -e "
    DROP TABLE IF EXISTS respuesta_campo;
    DROP TABLE IF EXISTS respuesta_aspirante;
    DROP TABLE IF EXISTS campo_formulario;
    DROP TABLE IF EXISTS formulario_admision;
  "
  docker-compose exec -T db mysql -u user -ppassword db_postgrados -e "
    DELETE FROM accion WHERE cod_accion BETWEEN 68 AND 76;
  "
  docker-compose exec web rm -rf /var/www/data/admisiones

□ 5.4 Rollback Módulo de Evaluación Docente:
  docker-compose exec -T db mysql -u user -ppassword db_postgrados -e "
    DROP TABLE IF EXISTS evaluacion_respuesta_detalle;
    DROP TABLE IF EXISTS evaluacion_respuesta;
    DROP TABLE IF EXISTS evaluacion_pregunta;
    DROP TABLE IF EXISTS evaluacion_seccion;
  "
  docker-compose exec -T db mysql -u user -ppassword db_postgrados -e "
    DELETE FROM accion WHERE cod_accion IN (80,81,82,85,86,87,140,141,142,144,145,146);
  "

□ 5.5 Restaurar archivos de configuración modificados:
  # Restaurar desde backup:
  # - module/Eep/config/module.config.php
  # - module/Eep/config/access_filter.php
  # - module/Eep/config/menus.php
  # - module/Eep/src/ValueObject/View.php

□ 5.6 Limpiar caché y reiniciar:
  docker-compose exec web rm -rf /var/www/data/cache/*
  docker-compose restart web
```

---

## 📊 Bitácora de Instalación

Usar esta tabla para registrar manualmente cada paso ejecutado en producción.

| Fase | Paso | Descripción | Ejecutado por | Fecha/Hora | Estado | Verificado por |
|------|------|-------------|---------------|------------|--------|----------------|
| 0 | Backup BD | Dump completo antes de cambios | | | ☐ | |
| 0 | Backup data/ | Tar.gz de carpeta data | | | ☐ | |
| 1 | 1.1 | Desplegar archivos Evaluación Docente | | | ☐ | |
| 1 | 1.2 | Ejecutar evaluacion_docente.sql | | | ☐ | |
| 1 | 1.3 | Verificar ACL 80-87, 140-146 | | | ☐ | |
| 1 | 1.5 | Verificación post-instalación EdD | | | ☐ | |
| 2 | 2.1 | Desplegar archivos Formulario Admisión | | | ☐ | |
| 2 | 2.2 | Ejecutar modulo_aspirantes_final.sql | | | ☐ | |
| 2 | 2.3 | Verificar ACL 68-76 | | | ☐ | |
| 2 | 2.4 | Crear carpeta data/admisiones | | | ☐ | |
| 2 | 2.5 | Verificación post-instalación Adm | | | ☐ | |
| 3 | 3.1.1 | Crear carpetas data/graduacion/ | | | ☐ | |
| 3 | 3.1.2 | Copiar general.docx | | | ☐ | |
| 3 | 3.2.1 | Ejecutar modulo_graduacion_schema.sql | | | ☐ | |
| 3 | 3.2.2 | Ejecutar matriz_evaluacion_completo.sql | | | ☐ | |
| 3 | 3.2.3 | Ejecutar ejecuciones_extra.sql | | | ☐ | |
| 3 | 3.2 | Verificar tablas, rol, usuario, ACL | | | ☐ | |
| 3 | 3.3 | Dependencias Composer + SMTP | | | ☐ | |
| 3 | 3.4 | Verificación automática + prueba funcional | | | ☐ | |
| 4 | Global | Verificación de coexistencia (menús, ACL, tablas) | | | ☐ | |

---

## 📝 Notas Importantes para Producción

1. **No ejecutar `CAMBIOS.md` como fuente de INSERTs SQL.** Ese archivo es un historial de desarrollo con códigos ACL que pueden estar obsoletos o reasignados. Usar únicamente los scripts SQL oficiales de cada módulo o los INSERTs documentados en las guías de producción.

2. **Hash de contraseña del Secretario:** El usuario `secretario.examen@farusac.edu.gt` (cod_usuario 3568) se crea en `database/modulo graduacion/ejecuciones_extra.sql` con contraseña `PostgradosUsac2024`. Si se recrea manualmente, generar el hash con `password_hash('PostgradosUsac2024', PASSWORD_DEFAULT)` en PHP.

3. **Orden de instalación recomendado:** Los módulos de Evaluación Docente y Formulario de Admisión no tienen dependencias cruzadas; pueden instalarse en cualquier orden o en paralelo. Graduación debe ir al final porque es el más complejo y tiene más puntos de fallo.

4. **Archivos físicos en `data/`:** Ninguno de los scripts SQL o Composer crea automáticamente las carpetas `data/graduacion/` ni `data/admisiones/`. Deben crearse manualmente o mediante los scripts bash indicados.

5. **SMTP:** La configuración SMTP en `config/autoload/local.php` es compartida entre módulos (Graduación y futuras mejoras). Configurarla una sola vez con credenciales válidas de aplicación (App Password, no contraseña principal de Google).

6. **Fuente `DejaVuSans.ttf`:** Es un requisito compartido entre Graduación y Evaluación Docente para la generación de gráficas PNG en PDFs. Si el servidor usa Docker, la fuente puede instalarse via `apt-get install fonts-dejavu-core` y copiarse a `/var/www/data/fonts/`.

---

**Documento creado:** 2026-08-22  
**Última actualización:** 2026-08-22  
**Responsable del despliegue general:** ___________________  
**Aprobación:** ___________________
