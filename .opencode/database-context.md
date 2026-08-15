# Contexto de Base de Datos - Sistema de Postgrados

## Información General

- **Base de Datos**: `db_postgrados`
- **Sistema**: Zend Framework 3 MVC (PHP 7.4)
- **Motor**: MySQL 5.7
- **Charset**: `utf8mb4`

---

## Estructura de Scripts SQL

### 1. Script Principal del Sistema
**Archivo**: `database/20250718Postgrados.sql` (35MB)

Contiene toda la estructura y datos iniciales del sistema de postgrados. Incluye:

#### Tablas Principales del Sistema Core

| Tabla | Descripción |
|-------|-------------|
| `usuario` | Usuarios del sistema (estudiantes, staff) |
| `usuario_rol` | Asignación de roles a usuarios |
| `rol` | Catálogo de roles (Director, Asistente, Tesorero, Coordinador, Catedrático, Estudiante, etc.) |
| `accion` | Acciones/permisos del sistema para ACL |
| `estado_accion` | Estados de las acciones |
| `operacion` | Registro de operaciones/auditoría |
| `bitacora` | Bitácora de eventos |

#### Tablas Académicas

| Tabla | Descripción |
|-------|-------------|
| `carrera` | Maestrías y especialidades (Asentamientos Humanos, Restauración, Diseño Ambiental, etc.) |
| `grado_academico` | Niveles académicos (Especialización, Maestría, Doctorado) |
| `pensum` | Planes de estudio |
| `pensum_cohorte` | Asociación pensum-cohorte |
| `cohorte` | Cohortes/grupos de estudiantes |
| `curso_pensum` | Cursos asignados a pensum |
| `tipo_curso` | Tipos de cursos |
| `bloque` | Bloques/actividades de evaluación de cursos |

#### Tablas de Inscripción y Asignación

| Tabla | Descripción |
|-------|-------------|
| `inscripcion` | Inscripciones de estudiantes |
| `asignacion` | Asignaciones de cursos a estudiantes |
| `asignacion_carrera` | Asignaciones de estudiantes a carreras |
| `horario` | Horarios de cursos |
| `salon` | Salones/aulas |
| `ubicacion` | Ubicaciones físicas |

#### Tablas de Calificaciones

| Tabla | Descripción |
|-------|-------------|
| `nota_asignatura` | Notas por asignatura |
| `nota_final` | Notas finales |
| `tipo_nota_final` | Tipos de nota final |
| `estado_nota` | Estados de notas |
| `estado_nota_final` | Estados de notas finales |
| `detalle_correccion` | Detalles de correcciones |

#### Tablas de Actas

| Tabla | Descripción |
|-------|-------------|
| `acta` | Actas de examen |
| `tipo_acta` | Tipos de actas |
| `detalle_acta_oficial` | Detalles de actas oficiales |
| `detalle_acta_postgrados` | Detalles de actas de postgrados |

#### Tablas de Tesorería

| Tabla | Descripción |
|-------|-------------|
| `orden_pago` | Órdenes de pago |
| `tipo_orden` | Tipos de orden de pago |
| `cursos_orden_pago` | Cursos asociados a órdenes de pago |
| `precio` | Precios de cursos/servicios |
| `banco` | Catálogo de bancos |
| `moroso` | Registro de morosidades |

#### Tablas de Configuración

| Tabla | Descripción |
|-------|-------------|
| `parametro` | Parámetros del sistema |
| `detalle_parametro` | Detalles de parámetros |
| `pais` | Catálogo de países |
| `situacion` | Situaciones/estados |
| `fin_inscripcion` | Control de fechas de inscripción |
| `info_laboral` | Información laboral de estudiantes |
| `involucrado` | Involucrados en procesos |
| `nombre_carrera` | Nombres históricos de carreras |

---

### 2. Scripts del Módulo de Graduación

Ubicados en: `database/modulo graduacion/`

#### Orden de Ejecución Requerido

```
1. modulo_graduacion.sql (Paso 1-4: examen_privado, examen_general)
2. modulo_graduacion_carta_01_schema.sql (Paso 5: carta_examinadores)
3. modulo_autorizacion_impresion_schema.sql (Paso 6: autorizacion_impresion)
4. modulo_graduacion_carta_02_seeds.sql (Seeds adicionales)
5. migracion_fases_examen.sql (Migración - solo si aplica)
```

#### 2.1 modulo_graduacion.sql

**Tablas del Proceso de Examen de Graduación:**

| Tabla | Descripción | Dependencias |
|-------|-------------|--------------|
| `examen_tipo` | Catálogo de tipos de examen (Privado General, Privado Gerencia, Público General) | - |
| `examen_paso_catalogo` | Pasos configurados por tipo de examen y su orden | examen_tipo |
| `examen_proceso` | Registro maestro de cada proceso de examen por estudiante | usuario, examen_tipo, examen_paso_catalogo |
| `examen_proceso_paso` | Estado de cada paso dentro de cada proceso | examen_proceso, examen_paso_catalogo |
| `examen_requisito_documento` | Catálogo de documentos requeridos por paso | examen_tipo, examen_paso_catalogo |
| `examen_documento` | Archivos subidos por estudiantes | examen_proceso, examen_requisito_documento, usuario |
| `archivo_local` | Metadata de archivos locales (nombre MD5 en disk/archivos/) | examen_documento |
| `examen_revision_documento` | Decisiones del staff sobre documentos | examen_documento, examen_proceso, examen_requisito_documento |
| `examen_documento_fisico` | Checklist de recepción de documentos físicos | examen_proceso, examen_requisito_documento |
| `examen_terna` | Examinadores asignados al proceso (Paso 3) | examen_proceso, usuario |

**Fases del Proceso:**

```
examen_privado (Pasos 1-4)
    ↓
carta_examinadores (Paso 5)
    ↓
autorizacion_impresion (Paso 6)
    ↓
examen_general (Pasos 1-4)
```

**Pasos por Fase:**

| # | Nombre | Template | Descripción |
|---|--------|----------|-------------|
| 1 | Revisión de Papelería | paso1-papeleria | Documentos digitales |
| 2 | Entrega de Documentación Física | paso2-documentacion | Documentos físicos |
| 3 | Terna Examinadora | paso3-terna | Asignación de examinadores |
| 4 | Notificación al Estudiante | paso4-notificacion | Notificación final |

**Tipos de Examen:**

| Código | Nombre | Descripción |
|--------|--------|-------------|
| 1 | Privado General | Estudiantes regulares de postgrado |
| 2 | Privado Gerencia | Maestría en Gestión de Programas y Proyectos |
| 3 | Público General | Abierto a la comunidad académica |

#### 2.2 modulo_graduacion_carta_01_schema.sql

**Tablas del Paso 5 (Carta de Examinadores):**

| Tabla | Descripción | Dependencias |
|-------|-------------|--------------|
| `examen_correccion_ciclo` | Ciclo de correcciones (simplificado, una entrada por proceso) | examen_proceso, usuario |
| `examen_correccion_evidencia` | Evidencias (capturas/PDFs de correos) | examen_correccion_ciclo, usuario |
| `examen_carta_plantilla` | Catálogo de plantillas .docx | examen_tipo |
| `examen_carta_examinadores` | Cartas generadas por proceso | examen_proceso, examen_correccion_ciclo, examen_carta_plantilla |

**Nota importante**: El seguimiento de correcciones ocurre EXTERNO a la plataforma (correo electrónico). En la plataforma solo se guardan evidencias como bitácora.

#### 2.3 modulo_autorizacion_impresion_schema.sql

**Tablas del Paso 6 (Autorización de Impresión):**

| Tabla | Descripción | Dependencias |
|-------|-------------|--------------|
| `examen_autorizacion_config` | Configuración global de instrucciones (único registro) | usuario |
| `examen_autorizacion_documento_soporte` | Documentos globales (logos, escudos, guías) | usuario |
| `examen_profesional_calificado` | Catálogo de licenciados en letras calificados | usuario |
| `examen_carta_descarga` | Cartas .docx genéricas para descarga | - |
| `examen_junta_directiva` | Miembros de junta directiva (informativo) | usuario |
| `examen_autorizacion_proceso` | Estado por proceso del paso 6 | examen_proceso, examen_profesional_calificado |

**Sub-pasos del Paso 6:**
- **Parte 1** (sub_paso=1): Estudiante selecciona profesional, director aprueba revisión presencial
- **Parte 2** (sub_paso=2): Preparación final para examen general

#### 2.4 modulo_graduacion_carta_02_seeds.sql

**Seeds incluidos:**
- Plantilla inicial de carta de examinadores
- Acciones en tabla `accion` (cod_accion 68-74) para ACL

**Acciones agregadas:**
- 68: Ver paso de carta de examinadores
- 70: Adjuntar evidencia a la bitácora de correcciones
- 71: Aprobar trabajo de graduación y generar carta
- 72: Descargar carta de examinadores
- 74: Eliminar evidencia de la bitácora

#### 2.5 migracion_fases_examen.sql

**Propósito**: Migración para separar datos por fase (examen privado vs examen general)

**Cambios:**
1. Renombra `fecha_examen` → `fecha_examen_privado`
2. Renombra `hora_inicio_examen` → `hora_examen_privado`
3. Agrega `fecha_examen_general` y `hora_examen_general`
4. Migra requisitos tipo 3 a cod_paso correcto de fase examen_general

**NOTA**: Solo ejecutar en entornos con versión anterior de modulo_graduacion.sql. Para instalaciones nuevas no es necesario.

---

### 3. Seeds Iniciales

Ubicados en: `database/modulo graduacion/inserts_iniciales/`

#### profesionales_calificados_seed.sql
Catálogo inicial de licenciados en letras calificados para el paso 6.

**Campos:**
- `cod_profesional`: ID autoincremental
- `nombre_completo`: Nombre del profesional
- `correo`: Correo electrónico
- `telefono`: Teléfono de contacto
- `activo`: 1=activo, 0=inactivo
- `creado_por`: FK a usuario (cod_usuario=1)

#### junta_directiva_seed.sql
Datos de ejemplo de miembros de junta directiva (informativo).

**Campos:**
- `cod_miembro`: ID autoincremental
- `nombre_completo`: Nombre del miembro
- `puesto`: Cargo (Presidente, Secretario, Vocal I, etc.)
- `activo`: 1=activo, 0=inactivo
- `creado_por`: FK a usuario (cod_usuario=1)

---

### 4. Script de Usuario de Prueba

**Archivo**: `database/crear_usuario_graduacion.sql`

Crea un usuario estudiante de prueba para probar el módulo de graduación.

**Datos del usuario:**
- CUI: `1000000009999`
- Registro académico: `20259999`
- Nombre: Estudiante Graduacion Prueba
- Correo: estudiante.graduacion@email.com
- Contraseña: graduacion2026 (hash bcrypt)
- País: 73 (Guatemala)
- Sexo: M (Mujer)
- Rol: 6 (Estudiante)

**Roles disponibles:**
- 1: Director
- 2: Asistente
- 3: Tesorero
- 4: Coordinador
- 5: Catedrático
- 6: Estudiante
- 7: Programador
- 8: UDICA Programador
- 9: UDICA Jefe
- 10: UDICA Operador

---

## Relaciones Clave

### Jerarquía de Tablas del Módulo de Graduación

```
examen_tipo
    ↓
examen_paso_catalogo (FK: cod_tipo_examen)
examen_requisito_documento (FK: cod_tipo_examen, cod_paso)

usuario (estudiante)
    ↓
examen_proceso (FK: cod_usuario, cod_tipo_examen, cod_paso_actual)
    ↓ (1:N)
    ├── examen_proceso_paso (FK: cod_proceso, cod_paso)
    ├── examen_documento (FK: cod_proceso, cod_requisito)
    │       ↓
    │   archivo_local (FK: cod_documento)
    │   examen_revision_documento (FK: cod_documento)
    ├── examen_documento_fisico (FK: cod_proceso, cod_requisito)
    ├── examen_terna (FK: cod_proceso)
    ├── examen_correccion_ciclo (FK: cod_proceso) [Paso 5]
    │       ↓
    │   examen_correccion_evidencia (FK: cod_ciclo)
    │       ↓
    │   examen_carta_examinadores (FK: cod_ciclo_aprobacion)
    ├── examen_autorizacion_proceso (FK: cod_proceso, cod_profesional) [Paso 6]
```

### Relaciones con Tablas del Sistema Core

```
usuario
    ↓ (1:N)
    ├── usuario_rol → rol
    ├── examen_proceso (registrado_por)
    ├── examen_proceso_paso (completado_por)
    ├── examen_documento (subido_por, eliminado_por)
    ├── examen_revision_documento (revisado_por)
    ├── examen_documento_fisico (recibido_por)
    ├── examen_terna (registrado_por)
    ├── examen_correccion_ciclo (revisado_por)
    ├── examen_correccion_evidencia (subido_por)
    ├── examen_carta_examinadores (generada_por)
    ├── examen_profesional_calificado (creado_por)
    ├── examen_junta_directiva (creado_por)
    ├── examen_autorizacion_proceso (aprobado_por)
    ├── examen_autorizacion_config (updated_by)
    └── examen_autorizacion_documento_soporte (subido_por)
```

---

## Convenciones de Nomenclatura

### Prefijos de Tablas

| Prefijo | Módulo | Ejemplo |
|---------|--------|---------|
| `examen_*` | Módulo de Graduación | `examen_proceso`, `examen_terna` |
| (sin prefijo) | Sistema Core | `usuario`, `carrera`, `inscripcion` |

### Convenciones de Columnas

| Patrón | Significado | Ejemplo |
|--------|-------------|---------|
| `cod_*` | Clave primaria / Foreign key | `cod_usuario`, `cod_carrera` |
| `fecha_*` | Campos de fecha | `fecha_creacion`, `fecha_examen_privado` |
| `hora_*` | Campos de hora | `hora_examen_privado` |
| `nombre_*` | Campos de texto identificativo | `nombre_completo`, `nombre_rol` |
| `*_por` | FK a usuario que realizó la acción | `registrado_por`, `aprobado_por` |
| `activo` | Booleano de estado (1/0) | `activo` |
| `created_at` | Timestamp de creación | `created_at` |
| `updated_at` | Timestamp de última modificación | `updated_at` |
| `archivo_*` | Referencias a archivos | `archivo_nombre`, `archivo_md5` |

---

## Índices Importantes

### Índices de Búsqueda Frecuente

```sql
-- Usuario por CUI o registro académico (login)
usuario.cui (UNIQUE)
usuario.registro_academico (UNIQUE)
usuario.correo

-- Procesos por estudiante
examen_proceso.cod_usuario (INDEX)
examen_proceso.cod_tipo_examen (INDEX)
examen_proceso.cod_paso_actual (INDEX)
examen_proceso.cancelado (INDEX)

-- Documentos por proceso
examen_documento.cod_proceso (INDEX)
examen_documento.archivo_nombre (INDEX)
examen_documento.es_version_actual (INDEX compuesto)

---

## Consultas Comunes

### Verificar si un usuario tiene proceso de graduación activo

```sql
SELECT 
    ep.cod_proceso,
    ep.cod_usuario,
    et.nombre as tipo_examen,
    epc.nombre as paso_actual,
    epc.fase,
    ep.fecha_examen_privado,
    ep.fecha_examen_general,
    ep.cancelado
FROM examen_proceso ep
JOIN examen_tipo et ON ep.cod_tipo_examen = et.cod_tipo_examen
LEFT JOIN examen_paso_catalogo epc ON ep.cod_paso_actual = epc.cod_paso
WHERE ep.cod_usuario = ?
  AND ep.cancelado = 0;
```

### Obtener documentos de un proceso por paso

```sql
SELECT 
    erd.cod_requisito,
    erd.nombre as requisito,
    erd.tipo_entrega,
    erd.obligatorio,
    ed.cod_documento,
    ed.version,
    ed.archivo_nombre,
    ed.nombre_original,
    ed.fecha_subida
FROM examen_requisito_documento erd
LEFT JOIN examen_documento ed 
    ON erd.cod_requisito = ed.cod_requisito
    AND ed.cod_proceso = ?
    AND ed.es_version_actual = 1
    AND ed.eliminado = 0
WHERE erd.cod_paso = ?
  AND erd.activo = 1
ORDER BY erd.orden_display;
```

### Verificar permisos de usuario (ACL)

```sql
SELECT 
    a.cod_accion,
    a.nombre as nombre_accion,
    r.cod_rol,
    r.nombre as nombre_rol
FROM usuario u
JOIN usuario_rol ur ON u.cod_usuario = ur.cod_usuario
JOIN rol r ON ur.cod_rol = r.cod_rol
JOIN estado_accion ea ON r.cod_rol = ea.cod_rol
JOIN accion a ON ea.cod_accion = a.cod_accion
WHERE u.cod_usuario = ?
  AND (ur.fecha_fin IS NULL OR ur.fecha_fin >= CURDATE());
```
---

## Almacenamiento de Archivos

### Ubicaciones

| Tipo | Ruta Física | Tabla Metadata |
|------|-------------|----------------|
| Documentos del proceso | `public/archivos/<md5>.<ext>` | `examen_documento` + `archivo_local` |
| Evidencias de corrección | `public/archivos/<md5>.<ext>` | `examen_correccion_evidencia` |
| Documentos de soporte | `public/archivos/autorizacion_impresion/documentos_soporte/<md5>.<ext>` | `examen_autorizacion_documento_soporte` |
| Cartas de descarga | `public/archivos/autorizacion_impresion/cartas_descarga/<md5>.<ext>` | `examen_carta_descarga` |
| Plantillas de cartas | `data/plantillas/carta-examinadores/*.docx` | `examen_carta_plantilla` |
| Cartas generadas | `data/documentos/cartas-examinadores/*.docx` | `examen_carta_examinadores` |

### Convenciones de Archivos

- **Nombre físico**: Hash MD5 (32 caracteres hexadecimales) sin extensión
- **Extensión**: Guardada en columna separada (pdf, jpg, png, docx)
- **Integridad**: Checksum SHA256 opcional en `examen_documento.checksum_sha256`
- **Soft delete**: Campo `eliminado` + `fecha_eliminacion` (no se borran archivos físicos)

---

## Seguridad y Permisos

### Roles y Acciones Relevantes para Graduación

| Código | Acción | Descripción |
|--------|--------|-------------|
| 60-67 | (anteriores) | Módulo de evaluaciones |
| 68 | Ver paso de carta de examinadores | Paso 5 |
| 69 | (histórico, no usado) | - |
| 70 | Adjuntar evidencia | Estudiante - Paso 5 |
| 71 | Aprobar trabajo y generar carta | Director - Paso 5 |
| 72 | Descargar carta de examinadores | Estudiante/Director |
| 73 | (histórico, no usado) | - |
| 74 | Eliminar evidencia de bitácora | Director - Paso 5 |

### Reglas de Negocio Importantes

1. **Un proceso por estudiante**: Un estudiante solo puede tener un proceso activo (no cancelado) por tipo de examen.
2. **Terna solo para examen privado**: La terna de examinadores solo existe en la fase examen_privado. No hay terna para examen general.
3. **Versionado de documentos**: Cada resubida incrementa `version` y marca la anterior como `es_version_actual=0`.
4. **Revisión de documentos**: Solo se puede revisar la versión actual (`es_version_actual=1`).
5. **Ciclo único**: El paso 5 (carta de examinadores) solo permite un ciclo de correcciones simplificado.
6. **Secuencia de pasos**: Los pasos deben completarse en orden. No se puede saltar pasos.

---

## Configuración Docker

### Conexión a Base de Datos

```yaml
# docker-compose.yml
services:
  db:
    image: mysql:5.7
    ports:
      - "3307:3306"  # Host:Container
    environment:
      MYSQL_DATABASE: db_postgrados
      MYSQL_USER: user
      MYSQL_PASSWORD: password
      MYSQL_ROOT_PASSWORD: rootpassword
```

### Variables de Conexión (config/autoload/local.php)

```php
'db' => [
    'driver' => 'Pdo_Mysql',
    'database' => 'db_postgrados',
    'hostname' => 'db',
    'port' => 3306,
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4',
],
```

---

## Troubleshooting

### Problema: "Tabla no existe"

**Verificar orden de ejecución de scripts:**
```bash
# 1. Script principal primero
mysql -uuser -ppassword db_postgrados < database/20250718Postgrados.sql

# 2. Scripts del módulo de graduación en orden
mysql -uuser -ppassword db_postgrados < "database/modulo graduacion/modulo_graduacion.sql"
mysql -uuser -ppassword db_postgrados < "database/modulo graduacion/modulo_graduacion_carta_01_schema.sql"
mysql -uuser -ppassword db_postgrados < "database/modulo graduacion/modulo_autorizacion_impresion_schema.sql"
```

### Problema: "Foreign key constraint fails"

**Verificar:**
1. Usuario cod_usuario=1 (Director) existe antes de ejecutar seeds
2. Permisos de acciones (cod_accion) existen en tabla `accion`
3. Tipos de examen (cod_tipo_examen) existen antes de crear requisitos

### Problema: "Duplicate entry" en seeds

**Los scripts son idempotentes (usan INSERT IGNORE o REPLACE):**
- `modulo_graduacion_carta_02_seeds.sql` usa INSERT simple
- Si falla, verificar si ya existen las acciones 68-74

---

## Recursos Adicionales

- **Documentación de rutas**: `EXPLICACION_RUTAS_ZF3.md`
- **Cambios recientes**: `CAMBIOS.md`
- **Notas del proyecto**: `DOCUMENTACION_PROYECTO.md`
- **Estructura de módulos**: `module/Eep/`

---

*Última actualización: 2026-05-17*
