# Requisitos Iniciales del Módulo de Graduación

> **Propósito:** Documento que detalla TODOS los archivos, carpetas, tablas y configuraciones necesarias para iniciar el módulo de graduación desde cero después de un reinicio de tablas.
>
> **Fecha:** 11 de julio de 2026  
> **Estado:** Documentación completa resultante del análisis del proyecto

---

## 📁 ESTRUCTURA DE CARPETAS NECESARIAS

### 1. Carpeta Base: `data/graduacion/`

**Ubicación:** `/var/www/data/graduacion/` (dentro del contenedor Docker)

**Estructura completa requerida:**

```
data/graduacion/
├── plantillas/
│   └── carta-examinadores/
│       ├── general.docx          ← ARCHIVO OBLIGATORIO
│       └── README.md             ← Documentación
│
├── procesos/
│   └── (subcarpetas creadas dinámicamente por proceso)
│
└── global/
    ├── documentos-soporte/       ← Logos, escudos, guías (paso 6)
    ├── cartas-descarga/          ← Cartas tipo .docx (paso 6)
    └── requisitos-apoyo/         ← Archivos de apoyo para requisitos
```

### 2. Comandos para crear la estructura (Docker)

```bash
# Desde el directorio raíz del proyecto
docker-compose exec web bash

# Dentro del contenedor:
mkdir -p /var/www/data/graduacion/plantillas/carta-examinadores
mkdir -p /var/www/data/graduacion/procesos
mkdir -p /var/www/data/graduacion/global/documentos-soporte
mkdir -p /var/www/data/graduacion/global/cartas-descarga
mkdir -p /var/www/data/graduacion/global/requisitos-apoyo

# Establecer permisos correctos
chown -R www-data:www-data /var/www/data/graduacion
chmod -R 755 /var/www/data/graduacion
```

---

## 📄 ARCHIVOS OBLIGATORIOS

### 1. Plantilla de Carta de Examinadores

**Archivo:** `data/graduacion/plantillas/carta-examinadores/general.docx`

**Descripción:**  
- Plantilla `.docx` usada por `CartaGenerator.php` para generar cartas dinámicas.
- Debe contener placeholders con sintaxis `${nombre_variable}`.
- **Si este archivo no existe, el paso 5 (Carta de Examinadores) fallará.**

**Placeholders obligatorios:**
```
${estudiante_nombre}
${estudiante_carnet}
${estudiante_cui}
${titulo_trabajo}
${tipo_examen}
${fecha_examen}
${hora_examen}
${asesor_nombre}
${examinador_1_nombre}
${examinador_1_colegiado}
${examinador_2_nombre}
${examinador_2_colegiado}
${examinador_3_nombre}
${examinador_3_colegiado}
${coordinador_nombre}
${fecha_emision_carta}
```

**Ubicación en código:**
- Referenciado en: `module/Eep/src/Service/CartaGenerator.php` (línea 22)
- Constante: `RUTA_PLANTILLA = 'data/graduacion/plantillas/carta-examinadores/general.docx'`

### 2. Imagen de Footer para Correos

**Archivo:** `public/img/email-footer.jpg`

**Descripción:**  
- Imagen incluida automáticamente como pie de página en todos los correos enviados por el sistema.
- Usado por `MailManager.php` para notificaciones automáticas.
- **Si no existe, los correos se envían sin footer pero no fallan.**

**Ubicación en código:**
- Referenciado en: `module/Eep/src/Service/MailManager.php`

---

## 🗄️ TABLAS DE BASE DE DATOS

### 1. Tablas Principales del Módulo (23 tablas)

**Script SQL:** `database/modulo graduacion/modulo_graduacion_schema.sql`

**Comando de instalación:**
```bash
docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/modulo graduacion/modulo_graduacion_schema.sql"
```

**Lista de tablas creadas:**

#### Catálogos (4 tablas)
1. `examen_tipo` — Tipos de examen (Privado General, Privado Gerencia, etc.)
2. `examen_paso_catalogo` — Definición de pasos por fase
3. `examen_requisito_documento` — Requisitos documentales por paso
4. `examen_examinador` — Catálogo de examinadores (internos/externos)

#### Tracking de Procesos (6 tablas)
5. `examen_proceso` — Procesos maestros por estudiante
6. `examen_proceso_paso` — Estado de cada paso dentro de cada proceso
7. `examen_documento` — Archivos digitales subidos
8. `archivo_local` — Metadata de archivos locales (MD5)
9. `examen_revision_documento` — Revisiones del staff (paso 1)
10. `examen_documento_fisico` — Checklist de recepción física (paso 2)

#### Ternas (1 tabla)
12. `examen_terna` — Examinadores asignados por proceso y fase

#### Paso 5 - Carta Examinadores (2 tablas)
13. `examen_correccion_ciclo` — Ciclos de corrección
14. `examen_correccion_evidencia` — Evidencias de correos

#### Paso 6 - Autorización Impresión (6 tablas)
15. `examen_autorizacion_config` — Configuración global del paso 6
16. `examen_autorizacion_documento_soporte` — Documentos de apoyo globales
17. `examen_profesional_calificado` — Licenciados en letras
18. `examen_carta_descarga` — Cartas tipo para descarga
19. `examen_junta_directiva` — Miembros de junta directiva
20. `examen_autorizacion_proceso` — Estado del paso 6 por proceso

#### Actas (3 tablas)
21. `examen_acta_correlativo` — Contador de correlativos por año
22. `examen_acta_privado` — Actas de examen privado
23. `examen_acta_general` — Actas de examen general (público)
24. `examen_acto_graduacion` — Datos compartidos del acto de graduación

### 2. Tablas de Matriz de Evaluación (4 tablas)

**Script SQL:** `database/matriz_evaluacion_completo.sql`

**Comando de instalación:**
```bash
docker-compose exec -T db mysql -u user -ppassword db_postgrados < "database/matriz_evaluacion_completo.sql"
```

**Lista de tablas:**
1. `examen_matriz_tipo` — Tipos de matriz por carrera
2. `examen_matriz_pregunta` — Preguntas de evaluación
3. `examen_matriz_evaluacion` — Evaluaciones por examinador
4. `examen_matriz_respuesta` — Respuestas individuales

**IMPORTANTE:** Este script incluye seeds con 20 matrices pre-configuradas para diferentes maestrías y las preguntas correspondientes.

---

## 🌱 DATOS SEMILLA (SEEDS) OBLIGATORIOS

### 1. Rol y Usuario: Secretario de Examen Privado

**Script:** `database/ejecuciones_extra.sql` (líneas 27-49)

```sql
-- Crear rol
INSERT INTO `rol` (`cod_rol`, `nombre`) VALUES (11, 'Secretario de Examen Privado');

-- Crear usuario asociado
INSERT INTO `usuario` (`cod_usuario`, `correo`, `password_hash`, `nombre`, `apellido`, `activo`, `cod_rol`)
VALUES (
    50,
    'secretario.examen@farusac.edu.gt',
    '$2y$10$0oLs72g3WDDLAukBwATQoe21mQIKijGh2OlrDkSyWvOGzOHyFhOcu',
    'Secretario',
    'Examen Privado',
    1,
    11
);
```

**Credenciales de acceso:**
- Usuario: `secretario.examen@farusac.edu.gt`
- Contraseña: `PostgradosUsac2024`

### 2. Tipos de Examen

**Script:** `database/ejecuciones_extra.sql` (líneas 51-71)

```sql
INSERT INTO `examen_tipo` (`cod_tipo_examen`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Privado General', 'Examen privado para estudiantes regulares de postgrado', 1),
(2, 'Privado Gerencia', 'Examen privado para la Maestría en Gestión de Programas y Proyectos de Desarrollo', 1),
(3, 'Público General', 'Examen público abierto a la comunidad académica', 1);

-- Vincular tipos con carreras
UPDATE `examen_tipo` SET `cod_carrera` = 18 WHERE `cod_tipo_examen` = 1;
UPDATE `examen_tipo` SET `cod_carrera` = 24 WHERE `cod_tipo_examen` = 2;

-- Crear un tipo de examen por cada carrera activa
INSERT INTO `examen_tipo` (`cod_carrera`, `nombre`, `descripcion`, `activo`)
SELECT 
    nc.`cod_carrera`,
    CONCAT('Privado - ', nc.`nombre`),
    CONCAT('Examen privado para ', nc.`nombre`),
    1
FROM nombre_carrera nc
WHERE nc.`activa` = 1
  AND nc.`cod_carrera` NOT IN (18, 24)
  AND NOT EXISTS (
    SELECT 1 FROM `examen_tipo` et WHERE et.`cod_carrera` = nc.`cod_carrera`
  );
```

### 3. Catálogo de Pasos

**Script:** `database/ejecuciones_extra.sql` (líneas 72-86)

```sql
INSERT INTO `examen_paso_catalogo`
  (`cod_tipo_examen`, `numero_orden`, `fase`, `nombre`, `fecha_finalizado`, `template_parcial`, `es_ultimo_paso`) VALUES
  -- Examen Privado (4 pasos)
  (NULL, 1, 'examen_privado', 'Revisión de Papelería',           '0', 'paso1-papeleria',     0),
  (NULL, 2, 'examen_privado', 'Entrega de Documentación Física', '0', 'paso2-documentacion', 0),
  (NULL, 3, 'examen_privado', 'Terna Examinadora',               '0', 'paso3-terna',         0),
  (NULL, 4, 'examen_privado', 'Notificación al Estudiante',      '0', 'paso4-notificacion',  1),
  -- Carta de Examinadores (1 paso)
  (NULL, 1, 'carta_examinadores', 'Carta de Examinadores', '0', 'paso5-carta-examinadores', 1),
  -- Autorización de Impresión (1 paso)
  (NULL, 1, 'autorizacion_impresion', 'Autorización de Impresión del Proyecto', '0', 'paso6-autorizacion-impresion', 1),
  -- Examen General (4 pasos)
  (NULL, 1, 'examen_general', 'Revisión de Papelería',           '0', 'paso1-papeleria',     0),
  (NULL, 2, 'examen_general', 'Entrega de Documentación Física', '0', 'paso2-documentacion', 0),
  (NULL, 3, 'examen_general', 'Terna Examinadora',               '0', 'paso3-terna',         0),
  (NULL, 4, 'examen_general', 'Notificación al Estudiante',      '0', 'paso4-notificacion',  1);
```

**Total:** 11 pasos distribuidos en 4 fases

### 4. Requisitos Documentales

**Script:** `database/ejecuciones_extra.sql` (líneas 87+)

Incluye los requisitos documentales obligatorios para cada paso (recibos de pago, constancias, ejemplares del trabajo, etc.).

### 5. Configuración Global del Paso 6

```sql
INSERT INTO `examen_autorizacion_config` (`cod_config`, `instrucciones_parte1`, `instrucciones_parte2`) 
VALUES (
  1,
  'Instrucciones para la Parte 1: Autorización de Imprímase...',
  'Instrucciones para la Parte 2: Entrega del Proyecto de Graduación...'
);
```

---

## ⚙️ CONFIGURACIÓN DE PHP

### 1. Configuración SMTP para Correos

**Archivo:** `config/autoload/local.php`

```php
<?php
return [
    'smtp' => [
        'host'              => 'smtp.gmail.com',
        'port'              => 587,
        'connection_class'  => 'login',
        'connection_config' => [
            'username' => 'tucorreo@farusac.edu.gt',
            'password' => 'tu-app-password-google',
            'ssl'      => 'tls',
        ],
        'from'      => 'tucorreo@farusac.edu.gt',
        'from_name' => 'Coordinación de Postgrados FAR-USAC',
    ],
];
```

**IMPORTANTE:**  
- En producción usar una **App Password** de Google, no la contraseña real.
- Si no se configura SMTP, el módulo NO enviará correos pero seguirá funcionando (solo falla el envío de notificaciones).

### 2. Dependencias de Composer

**Paquete requerido:** `zendframework/zend-mail`

**Comando de instalación:**
```bash
docker-compose exec web composer install
```

**Verificación en `composer.json`:**
```json
{
    "require": {
        "zendframework/zend-mail": "^2.10"
    }
}
```

---

## 🔐 ROLES Y PERMISOS

### Roles con Acceso al Módulo

Según `module/Eep/config/menus.php` y `access_filter.php`:

1. **Director** (cod_rol: 1)
2. **Asistente** (cod_rol: 7)
3. **Secretario de Examen Privado** (cod_rol: 11) ← **Nuevo rol obligatorio**
4. **UDICA Jefe** (cod_rol: 8)
5. **UDICA Operador** (cod_rol: 9)
6. **Tesorero** (cod_rol: 5)

### Permisos en `module/Eep/config/access_filter.php`

Los controladores del módulo:
- `ExamenController::class` — Gestión administrativa de procesos
- `StudentGraduationController::class` — Vista del estudiante

**IMPORTANTE:** Cada nueva acción agregada a estos controladores debe registrarse en:
1. El método del controlador (`nombreAction`)
2. `module/Eep/config/menus.php` (entrada de menú)
3. `module/Eep/config/access_filter.php` (permisos por rol)
4. Vista en `module/Eep/view/eep/(examen|student-graduation)/nombre.phtml`

---

## 🎯 SERVICIOS PHP CORE DEL MÓDULO

Ubicación: `module/Eep/src/Service/`

### Managers Principales:

1. **ExamenManager.php**  
   - Gestión central de procesos de graduación
   - Avance entre pasos y fases
   - Validaciones de requisitos

2. **StudentGraduationManager.php**  
   - Lógica específica para estudiantes
   - Subida de documentos
   - Consulta de estado del proceso

3. **CartaExaminadoresManager.php**  
   - Gestión del paso 5 (Carta de Examinadores)
   - Ciclos de corrección
   - Evidencias de correos

4. **CartaGenerator.php**  
   - Generación de cartas .docx usando PHPWord
   - Reemplaza placeholders con datos reales
   - **Requiere plantilla general.docx**

5. **AutorizacionImpresionManager.php**  
   - Gestión del paso 6
   - Documentos de soporte y cartas descargables
   - Profesionales calificados y junta directiva

6. **MailManager.php**  
   - Envío de correos HTML con footer
   - Notificaciones automáticas
   - **Requiere configuración SMTP**

### Factories:
Ubicación: `module/Eep/src/Service/Factory/`

Cada Manager tiene su Factory para inyección de dependencias.

---

## 📋 CONTROLADORES

### 1. ExamenController.php
**Ubicación:** `module/Eep/src/Controller/ExamenController.php`

**Acciones principales:**
- `indexAction()` — Listado de procesos
- `iniciarProcesoAction()` — Crear nuevo proceso
- `revisarPapeleriaAction()` — Revisión de documentos (paso 1)
- `autorizacionImpresionAction()` — Gestión del paso 6
- `evaluacionPrivadoAction()` — Panel de evaluación para examinadores
- `actasExamenGeneralAction()` — Generación de actas

### 2. StudentGraduationController.php
**Ubicación:** `module/Eep/src/Controller/StudentGraduationController.php`

**Acciones principales:**
- `indexAction()` — Dashboard del estudiante
- `procesoAction()` — Vista detallada del proceso actual
- (Manejo de subida de documentos y evidencias)

---

## 🎨 VISTAS (TEMPLATES)

### Para Staff (ExamenController)
**Ubicación:** `module/Eep/view/eep/examen/`

Vistas principales:
- `index.phtml` — Listado de procesos
- `iniciar-proceso.phtml` — Formulario de inicio
- `revisarpapeleria.phtml` — Revisión de documentos
- `autorizacion-impresion.phtml` — Gestión del paso 6
- `evaluacion-examen-privado.phtml` — Panel de evaluación
- `actas-examen-general.phtml` — Listado de actas
- `acta-examen-privado.phtml` — Vista del acta privada
- `acta-examen-general.phtml` — Vista del acta general

**Partials (sub-vistas):**
- `partial/paso3-terna.phtml` — Selección de terna
- `partial/paso5-carta-examinadores.phtml` — Gestión de carta

### Para Estudiantes (StudentGraduationController)
**Ubicación:** `module/Eep/view/eep/student-graduation/`

Vistas principales:
- `index.phtml` — Dashboard del estudiante
- `proceso.phtml` — Vista del proceso actual

**Partials:**
- `partial/paso1-solicitud-examen.phtml`
- `partial/paso2-terna.phtml`
- `partial/paso3-notificacion.phtml`
- `partial/paso5-carta-examinadores.phtml`
- `partial/paso6-autorizacion-impresion.phtml`

---

## ✅ CHECKLIST DE INSTALACIÓN COMPLETA

### Paso 1: Estructura de Carpetas
```bash
□ Crear data/graduacion/plantillas/carta-examinadores/
□ Crear data/graduacion/procesos/
□ Crear data/graduacion/global/documentos-soporte/
□ Crear data/graduacion/global/cartas-descarga/
□ Crear data/graduacion/global/requisitos-apoyo/
□ Establecer permisos www-data:www-data (755)
```

### Paso 2: Archivos Obligatorios
```bash
□ Copiar/crear data/graduacion/plantillas/carta-examinadores/general.docx
□ Verificar public/img/email-footer.jpg existe
```

### Paso 3: Base de Datos
```bash
□ Ejecutar: database/modulo graduacion/modulo_graduacion_schema.sql
□ Ejecutar: database/matriz_evaluacion_completo.sql
□ Ejecutar: database/ejecuciones_extra.sql (seeds)
□ Verificar 27 tablas creadas (23 + 4 de matriz)
□ Verificar 11 pasos en examen_paso_catalogo
□ Verificar rol cod_rol=11 (Secretario de Examen Privado)
```

### Paso 4: Configuración PHP
```bash
□ Configurar config/autoload/local.php (SMTP)
□ Ejecutar: docker-compose exec web composer install
□ Verificar zend-mail está instalado
```

### Paso 5: Permisos y Acceso
```bash
□ Verificar menús en module/Eep/config/menus.php
□ Verificar ACL en module/Eep/config/access_filter.php
□ Verificar rol 11 tiene acceso a ExamenController
```

### Paso 6: Verificación Final
```bash
□ Reiniciar contenedor web: docker-compose restart web
□ Limpiar caché: rm -rf data/cache/*
□ Acceder a http://localhost:8080
□ Login con secretario.examen@farusac.edu.gt / PostgradosUsac2024
□ Verificar menú "Módulo de Graduación" visible
□ Intentar crear un proceso de prueba
```

---

## 🔧 COMANDOS DE VERIFICACIÓN

### Verificar Carpetas
```bash
docker-compose exec web ls -la /var/www/data/graduacion/
docker-compose exec web ls -la /var/www/data/graduacion/plantillas/carta-examinadores/
```

### Verificar Tablas
```bash
docker-compose exec db mysql -u user -ppassword db_postgrados -e "SHOW TABLES LIKE 'examen_%';"
```

### Verificar Pasos
```bash
docker-compose exec db mysql -u user -ppassword db_postgrados -e "
SELECT numero_orden, fase, nombre 
FROM examen_paso_catalogo 
ORDER BY FIELD(fase, 'examen_privado', 'carta_examinadores', 'autorizacion_impresion', 'examen_general'), numero_orden;"
```

### Verificar Rol
```bash
docker-compose exec db mysql -u user -ppassword db_postgrados -e "
SELECT * FROM rol WHERE cod_rol = 11;"
```

---

## 🚨 ERRORES COMUNES Y SOLUCIONES

### Error: "No se encuentra la plantilla general.docx"
**Solución:**
```bash
# Verificar que el archivo existe
docker-compose exec web ls -la /var/www/data/graduacion/plantillas/carta-examinadores/general.docx

# Si no existe, copiar desde backup o crear nuevo
```

### Error: "Columna 'fase' no existe en examen_terna"
**Solución:**
```sql
ALTER TABLE examen_terna 
ADD COLUMN fase ENUM('examen_privado','examen_general') NOT NULL DEFAULT 'examen_privado';
```

### Error: "No se pueden enviar correos"
**Causa:** Falta configuración SMTP en `local.php`  
**Solución:** Configurar según sección "Configuración SMTP" de este documento

### Error: "Permisos denegados en data/graduacion"
**Solución:**
```bash
docker-compose exec web chown -R www-data:www-data /var/www/data/graduacion
docker-compose exec web chmod -R 755 /var/www/data/graduacion
```

---

## 📚 DOCUMENTACIÓN RELACIONADA

### Archivos clave del proyecto:
1. `../ESTRUCTURA_ARCHIVOS_GRADUACION.md` — Estructura detallada de carpetas
2. `../EXPLICACION_RUTAS_ZF3.md` — Convenciones de desarrollo
3. `../../AGENTS.md` — Arquitectura general del proyecto
4. `../CAMBIOS.md` — Historial de cambios del módulo

---

## 📊 RESUMEN CUANTITATIVO

### Archivos y Carpetas
- **6 carpetas obligatorias** en `data/graduacion/`
- **2 archivos obligatorios:** `general.docx` y `email-footer.jpg`
- **27 tablas de base de datos** (23 módulo + 4 matriz)
- **11 pasos** distribuidos en 4 fases
- **6 managers principales** en `Service/`
- **2 controladores** principales

### Datos Iniciales
- **1 rol nuevo:** Secretario de Examen Privado (cod_rol=11)
- **1 usuario seed:** secretario.examen@farusac.edu.gt
- **3+ tipos de examen** base (más uno por carrera activa)
- **20 matrices de evaluación** pre-configuradas
- **Variable:** Requisitos documentales según tipo de examen

---

## ⚠️ ADVERTENCIAS IMPORTANTES

1. **SIEMPRE hacer backup** antes de ejecutar scripts SQL de limpieza.
2. **NO borrar** la carpeta `data/graduacion/global/` si tiene archivos activos (logos, cartas).
3. **Verificar** que la plantilla `general.docx` tiene TODOS los placeholders necesarios.
4. **Proteger** el archivo `config/autoload/local.php` (contiene credenciales SMTP).
5. **Documentar** cualquier requisito documental nuevo en la tabla `examen_requisito_documento`.

---

## 🎓 PRÓXIMOS PASOS

Después de completar esta instalación:

1. **Configurar tipos de examen específicos** por carrera si es necesario
2. **Agregar requisitos documentales** personalizados
3. **Configurar profesionales calificados** para el paso 6
4. **Agregar miembros de junta directiva**
5. **Subir documentos de soporte** (logos, guías) al paso 6
6. **Crear cartas tipo** para descarga en el paso 6
7. **Capacitar a usuarios** con rol de Secretario de Examen Privado

---

**Documento generado el:** 11 de julio de 2026  
**Última revisión:** 11 de julio de 2026  
**Autor:** Sistema de análisis del proyecto  
**Estado:** ✅ Completo y verificado
