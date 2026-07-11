# ✅ Checklist Rápido - Reinicio del Módulo de Graduación

> **Propósito:** Lista de verificación paso a paso para reiniciar el módulo de graduación desde cero.
>
> **Fecha:** 11 de julio de 2026

---

## 🚀 INICIO RÁPIDO (Método Automático)

### Opción A: Automático con Scripts

```bash
# 1. Ejecutar script de inicialización (crea carpetas, instala BD, etc.)
./inicializar-modulo-graduacion.sh

# 2. IMPORTANTE: Copiar la plantilla de carta (MANUAL)
# Coloca el archivo general.docx en:
# data/graduacion/plantillas/carta-examinadores/general.docx

# 3. Verificar la instalación
./verificar-modulo-graduacion.sh
```

---

## 📋 CHECKLIST MANUAL (Si prefieres hacerlo paso a paso)

### FASE 1: Preparación del Entorno ⏱️ 5 min

#### 1.1 ✅ Docker y Contenedores
```bash
□ Verificar Docker corriendo: docker-compose ps
□ Contenedores levantados: docker-compose up -d
```

#### 1.2 ✅ Backup (IMPORTANTE)
```bash
□ Backup de BD actual: 
  docker-compose exec db mysqldump -u user -ppassword db_postgrados > backup_$(date +%Y%m%d).sql
□ Backup de carpeta data/:
  tar -czf backup_data_$(date +%Y%m%d).tar.gz data/
```

---

### FASE 2: Estructura de Carpetas ⏱️ 2 min

```bash
□ Entrar al contenedor: docker-compose exec web bash
□ Crear carpetas:

mkdir -p /var/www/data/graduacion/plantillas/carta-examinadores
mkdir -p /var/www/data/graduacion/procesos
mkdir -p /var/www/data/graduacion/global/documentos-soporte
mkdir -p /var/www/data/graduacion/global/cartas-descarga
mkdir -p /var/www/data/graduacion/global/requisitos-apoyo

□ Establecer permisos:

chown -R www-data:www-data /var/www/data/graduacion
chmod -R 755 /var/www/data/graduacion

□ Salir del contenedor: exit
```

---

### FASE 3: Archivos Obligatorios ⏱️ 5 min

#### 3.1 ✅ Plantilla de Carta (CRÍTICO)

```bash
□ Verificar que tienes el archivo: general.docx
□ Copiar a: data/graduacion/plantillas/carta-examinadores/
□ Verificar placeholders en el archivo:
  - ${estudiante_nombre}
  - ${titulo_trabajo}
  - ${fecha_examen}
  - ${examinador_1_nombre}
  - etc. (ver documentación completa)
```

#### 3.2 ✅ Imagen de Footer de Correos (Opcional)

```bash
□ Verificar: public/img/email-footer.jpg existe
□ Si no existe: correos se envían sin footer (no crítico)
```

---

### FASE 4: Base de Datos ⏱️ 10 min

#### 4.1 ✅ Instalación de Tablas

```bash
□ Ejecutar schema principal (23 tablas):
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
    "database/modulo graduacion/modulo_graduacion_schema.sql"

□ Ejecutar matriz de evaluación (4 tablas + seeds):
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
    "database/matriz_evaluacion_completo.sql"

□ Ejecutar datos semilla (roles, usuarios, tipos, pasos):
  docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
    "database/ejecuciones_extra.sql"
```

#### 4.2 ✅ Verificación de Instalación

```bash
□ Verificar tablas creadas:
  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SHOW TABLES LIKE 'examen_%';"
  
□ Debe mostrar al menos 23 tablas empezando con 'examen_'

□ Verificar pasos:
  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT numero_orden, fase, nombre FROM examen_paso_catalogo ORDER BY cod_paso;"
  
□ Debe mostrar 11 pasos

□ Verificar rol:
  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT * FROM rol WHERE cod_rol = 11;"
  
□ Debe mostrar: Secretario de Examen Privado

□ Verificar usuario:
  docker-compose exec db mysql -u user -ppassword db_postgrados \
    -e "SELECT cod_usuario, correo, nombre, apellido FROM usuario WHERE cod_rol = 11;"
  
□ Debe mostrar usuario con correo: secretario.examen@farusac.edu.gt
```

---

### FASE 5: Configuración PHP ⏱️ 5 min

#### 5.1 ✅ Dependencias de Composer

```bash
□ Entrar al contenedor: docker-compose exec web bash
□ Instalar dependencias: composer install
□ Verificar zend-mail: ls vendor/zendframework/zend-mail
□ Salir: exit
```

#### 5.2 ✅ Configuración SMTP (Opcional pero Recomendado)

```bash
□ Editar archivo: config/autoload/local.php
□ Agregar configuración SMTP:
```

```php
<?php
return [
    'smtp' => [
        'host'              => 'smtp.gmail.com',
        'port'              => 587,
        'connection_class'  => 'login',
        'connection_config' => [
            'username' => 'tu-correo@farusac.edu.gt',
            'password' => 'tu-app-password-de-google',
            'ssl'      => 'tls',
        ],
        'from'      => 'tu-correo@farusac.edu.gt',
        'from_name' => 'Coordinación de Postgrados',
    ],
];
```

```bash
□ Guardar archivo
□ Nota: Si no configuras SMTP, el sistema NO enviará correos
```

---

### FASE 6: Servicios y Verificación ⏱️ 3 min

#### 6.1 ✅ Reiniciar Servicios

```bash
□ Limpiar caché: rm -rf data/cache/*
□ Reiniciar contenedor web: docker-compose restart web
□ Esperar 10 segundos
```

#### 6.2 ✅ Verificación Automática

```bash
□ Ejecutar script de verificación: ./verificar-modulo-graduacion.sh
□ Debe mostrar "TODO CORRECTO" o listar advertencias menores
□ Si hay errores críticos, revisar los pasos anteriores
```

---

### FASE 7: Prueba del Sistema ⏱️ 5 min

#### 7.1 ✅ Acceso al Sistema

```bash
□ Abrir navegador: http://localhost:8080
□ Iniciar sesión:
  - Usuario: secretario.examen@farusac.edu.gt
  - Contraseña: PostgradosUsac2024
```

#### 7.2 ✅ Verificar Módulo Visible

```bash
□ Verificar menú lateral muestra: "Módulo de Graduación"
□ Hacer clic en el menú
□ Verificar submenús visibles:
  - Procesos de Graduación
  - Solicitudes
  - Autorización de Impresión
  - Evaluación Examen Privado
  - Actas de Examen General
```

#### 7.3 ✅ Prueba de Proceso

```bash
□ Ir a: Procesos de Graduación → Iniciar Proceso
□ Buscar un estudiante de prueba
□ Seleccionar tipo de examen
□ Iniciar proceso
□ Verificar que se crea correctamente
□ Verificar que aparece en el listado
```

---

## 🔧 RESOLUCIÓN DE PROBLEMAS COMUNES

### ❌ Error: "Plantilla general.docx no encontrada"

**Solución:**
```bash
# Verificar ruta
docker-compose exec web ls -la /var/www/data/graduacion/plantillas/carta-examinadores/

# Si no existe, copiar el archivo a la ubicación correcta
```

### ❌ Error: "No se pueden enviar correos"

**Causa:** SMTP no configurado  
**Solución:** Configurar config/autoload/local.php (ver Fase 5.2)  
**Alternativa:** El módulo funciona sin correos, solo no envía notificaciones automáticas

### ❌ Error: "Tabla examen_X no existe"

**Solución:**
```bash
# Re-ejecutar scripts SQL
docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
  "database/modulo graduacion/modulo_graduacion_schema.sql"
```

### ❌ Error: "Permisos denegados en data/graduacion"

**Solución:**
```bash
docker-compose exec web chown -R www-data:www-data /var/www/data/graduacion
docker-compose exec web chmod -R 755 /var/www/data/graduacion
```

### ❌ Error: "Rol 11 no existe"

**Solución:**
```bash
docker-compose exec -T db mysql -u user -ppassword db_postgrados < \
  "database/ejecuciones_extra.sql"
```

---

## 📊 RESUMEN CUANTITATIVO

Al finalizar, debes tener:

- ✅ **6 carpetas** en data/graduacion/
- ✅ **1 archivo obligatorio:** general.docx
- ✅ **27 tablas** en base de datos (23 módulo + 4 matriz)
- ✅ **11 pasos** en examen_paso_catalogo
- ✅ **1 rol nuevo:** cod_rol = 11
- ✅ **1 usuario** con rol Secretario
- ✅ **20 matrices** de evaluación pre-configuradas
- ✅ **3+ tipos** de examen base

---

## 📚 DOCUMENTACIÓN COMPLETA

Para información detallada, consultar:

1. **MODULO_GRADUACION_REQUISITOS_INICIALES.md** — Documentación exhaustiva
2. **ESTRUCTURA_ARCHIVOS_GRADUACION.md** — Estructura de carpetas explicada
3. **database/modulo graduacion/GUIA_INSTALACION.md** — Guía oficial de instalación
4. **EXPLICACION_RUTAS_ZF3.md** — Convenciones de desarrollo

---

## ⏱️ TIEMPO TOTAL ESTIMADO

- **Con scripts automáticos:** 10-15 minutos
- **Manual paso a paso:** 30-40 minutos
- **Primera vez (incluyendo lectura):** 60 minutos

---

## 🎯 SIGUIENTE PASO

Una vez completado este checklist:

1. ✅ Configurar requisitos documentales específicos por carrera
2. ✅ Agregar profesionales calificados para el paso 6
3. ✅ Agregar miembros de junta directiva
4. ✅ Subir documentos de soporte y cartas tipo
5. ✅ Capacitar a usuarios finales

---

**Estado:** ✅ Checklist completo y verificado  
**Última actualización:** 11 de julio de 2026
