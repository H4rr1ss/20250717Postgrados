---
name: database-graduacion
description: Use ONLY when the user asks about the graduation module (Módulo de Graduación), exam processes (examen_proceso), document uploads (examen_documento), examiners (examen_terna), or any tables with 'examen_' prefix. Keywords to trigger include graduacion, examen, proceso, terna, carta examinadores, autorizacion impresion, paso 5, paso 6, or any table starting with examen_.
---

# Database Context - Módulo de Graduación (Graduation Module)

This skill provides database schema information for the graduation module only.

## When to Use

Activate when user asks about:
- Graduation process (proceso de graduacion)
- Exam tables (examen_*)
- Document uploads (examen_documento)
- Examiners selection (examen_terna)
- Phase 5: Carta de Examinadores (examen_carta_*, examen_correccion_*)
- Phase 6: Autorización de Impresión (examen_autorizacion_*)
- Steps catalog (examen_paso_catalogo)
- Process flow and requirements

## Module Structure

```
Fases del Proceso de Graduación:

┌─────────────────────────────────────────────────────────────┐
│ FASE 1: Examen Privado (Pasos 1-4)                          │
│   - Paso 1: Revisión de Papelería (documentos digitales)    │
│   - Paso 2: Entrega de Documentación Física                 │
│   - Paso 3: Terna Examinadora                               │
│   - Paso 4: Notificación al Estudiante                    │
├─────────────────────────────────────────────────────────────┤
│ FASE 2: Carta de Examinadores (Paso 5)                      │
│   - Ciclo de correcciones (simplificado)                    │
│   - Evidencias de correos                                   │
│   - Generación de carta .docx                               │
├─────────────────────────────────────────────────────────────┤
│ FASE 3: Autorización de Impresión (Paso 6)                  │
│   - Parte 1: Selección profesional calificado               │
│   - Parte 2: Preparación para examen general                │
├─────────────────────────────────────────────────────────────┤
│ FASE 4: Examen General/Público (Pasos 1-4 repetidos)        │
│   - Mismos pasos que fase 1 pero con requisitos diferentes  │
└─────────────────────────────────────────────────────────────┘
```

## Database Scripts Location

```
database/
├── 20250718Postgrados.sql (35MB - main system DB)
└── modulo graduacion/
    ├── modulo_graduacion.sql (Phase 1-4)
    ├── modulo_graduacion_carta_01_schema.sql (Phase 5 - schema)
    ├── modulo_graduacion_carta_02_seeds.sql (Phase 5 - seeds)
    ├── modulo_autorizacion_impresion_schema.sql (Phase 6)
    ├── migracion_fases_examen.sql (migration - legacy)
    ├── crear_usuario_graduacion.sql (test user creation)
    └── inserts_iniciales/
        ├── profesionales_calificados_seed.sql
        └── junta_directiva_seed.sql
```

## Execution Order for New Installations

```sql
-- 1. Core database (must be loaded first)
source database/20250718Postgrados.sql;

-- 2. Phase 1-4: Private & General Exam
source "database/modulo graduacion/modulo_graduacion.sql";

-- 3. Phase 5: Examiners Letter (Carta de Examinadores) - Schema
source "database/modulo graduacion/modulo_graduacion_carta_01_schema.sql";

-- 4. Phase 6: Print Authorization - Schema
source "database/modulo graduacion/modulo_autorizacion_impresion_schema.sql";

-- 5. Phase 5: Seeds (actions and templates)
source "database/modulo graduacion/modulo_graduacion_carta_02_seeds.sql";

-- 6. Optional: Initial seeds
source "database/modulo graduacion/inserts_iniciales/profesionales_calificados_seed.sql";
source "database/modulo graduacion/inserts_iniciales/junta_directiva_seed.sql";
```

## Core Tables (Phase 1-4)

### examen_tipo - Exam Types Catalog
```sql
CREATE TABLE `examen_tipo` (
  `cod_tipo_examen` tinyint(3) unsigned AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT current_timestamp()
);
```
**Values:**
| cod | nombre | description |
|-----|--------|-------------|
| 1 | Privado General | Regular postgraduate students |
| 2 | Privado Gerencia | Master's in Project Management |
| 3 | Público General | Open to academic community |

### examen_paso_catalogo - Step Catalog
```sql
CREATE TABLE `examen_paso_catalogo` (
  `cod_paso` tinyint(3) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_tipo_examen` tinyint(3) unsigned NULL,  -- NULL = applies to all
  `numero_orden` tinyint(3) unsigned NOT NULL,
  `fase` ENUM('examen_privado','carta_examinadores','autorizacion_impresion','examen_general') NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `fecha_finalizado` text DEFAULT '0',
  `template_parcial` varchar(100),  -- View partial name (e.g., 'paso1-papeleria')
  `es_ultimo_paso` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  UNIQUE KEY `unique_tipo_fase_orden` (`cod_tipo_examen`, `fase`, `numero_orden`)
);
```

**Step Mapping:**
| cod_paso | fase | numero_orden | nombre | template_parcial |
|----------|------|--------------|--------|------------------|
| 1 | examen_privado | 1 | Revisión de Papelería | paso1-papeleria |
| 2 | examen_privado | 2 | Entrega de Documentación Física | paso2-documentacion |
| 3 | examen_privado | 3 | Terna Examinadora | paso3-terna |
| 4 | examen_privado | 4 | Notificación al Estudiante | paso4-notificacion |
| 5 | carta_examinadores | 5 | Carta de Examinadores | paso5-carta-examinadores |
| 6 | examen_general | 1 | Revisión de Papelería | paso1-papeleria |
| 7 | examen_general | 2 | Entrega de Documentación Física | paso2-documentacion |
| 8 | examen_general | 3 | Terna Examinadora | paso3-terna |
| 9 | examen_general | 4 | Notificación al Estudiante | paso4-notificacion |
| 10 | autorizacion_impresion | 6 | Autorización de Impresión | paso6-autorizacion-impresion |

### examen_proceso - Master Process Record
```sql
CREATE TABLE `examen_proceso` (
  `cod_proceso` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11) NOT NULL COMMENT 'FK → usuario (student)',
  `cod_tipo_examen` tinyint(3) unsigned NOT NULL,
  `cod_paso_actual` tinyint(3) unsigned NULL COMMENT 'NULL = closed/cancelled',
  `fecha_examen_privado` date NULL,
  `hora_examen_privado` time NULL,
  `fecha_examen_general` date NULL,
  `hora_examen_general` time NULL,
  `fecha_solicitud` timestamp DEFAULT current_timestamp(),
  `cancelado` tinyint(1) DEFAULT 0,
  `fecha_cancelacion` timestamp NULL,
  `motivo_cancelacion` text,
  `registrado_por` int(11) NOT NULL COMMENT 'FK → usuario (staff)',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  KEY `idx_ep_usuario` (`cod_usuario`),
  KEY `idx_ep_tipo` (`cod_tipo_examen`),
  KEY `idx_ep_paso_actual` (`cod_paso_actual`),
  KEY `idx_ep_cancelado` (`cancelado`),
  
  FOREIGN KEY (`cod_usuario`) REFERENCES `usuario` (`cod_usuario`),
  FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`),
  FOREIGN KEY (`cod_paso_actual`) REFERENCES `examen_paso_catalogo` (`cod_paso`)
);
```

**Key Rules:**
- One active process per student per exam type (`cancelado = 0`)
- `cod_paso_actual = NULL` means process is closed/cancelled
- Separate dates for private vs general exam

### examen_proceso_paso - Step Status per Process
```sql
CREATE TABLE `examen_proceso_paso` (
  `cod_proceso_paso` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int(11) unsigned NOT NULL,
  `cod_paso` tinyint(3) unsigned NOT NULL,
  `estado` enum('pendiente','en_progreso','completado','rechazado') DEFAULT 'pendiente',
  `fecha_inicio` timestamp DEFAULT current_timestamp(),
  `fecha_completado` timestamp NULL COMMENT 'Deadline when staff advances step',
  `completado_por` int(11) NULL COMMENT 'FK → usuario (staff)',
  `observaciones` text,
  `created_at` timestamp DEFAULT current_timestamp(),
  
  UNIQUE KEY `unique_epp_proceso_paso` (`cod_proceso`, `cod_paso`),
  KEY `idx_epp_estado` (`estado`),
  
  FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  FOREIGN KEY (`cod_paso`) REFERENCES `examen_paso_catalogo` (`cod_paso`)
);
```

**States:** `pendiente` → `en_progreso` → `completado` (or `rechazado`)

### examen_requisito_documento - Document Requirements Catalog
```sql
CREATE TABLE `examen_requisito_documento` (
  `cod_requisito` smallint(5) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_tipo_examen` tinyint(3) unsigned NULL COMMENT 'NULL = all types',
  `cod_paso` tinyint(3) unsigned NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text COMMENT 'Instructions visible to student',
  `tipo_entrega` enum('digital','fisico') DEFAULT 'digital',
  `obligatorio` tinyint(1) DEFAULT 1,
  `formatos_permitidos` varchar(100) COMMENT 'e.g., pdf,jpg,png',
  `tamano_max_mb` tinyint(3) unsigned DEFAULT 10,
  `orden_display` tinyint(3) unsigned DEFAULT 1,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT current_timestamp(),
  
  KEY `idx_erd_paso_activo` (`cod_paso`, `activo`),
  
  FOREIGN KEY (`cod_tipo_examen`) REFERENCES `examen_tipo` (`cod_tipo_examen`),
  FOREIGN KEY (`cod_paso`) REFERENCES `examen_paso_catalogo` (`cod_paso`)
);
```

**Sample Requirements (from seeds):**
| tipo | paso | nombre | formatos | tamaño |
|------|------|--------|----------|--------|
| 1 (Privado) | 1 | Recibo de Pago | pdf,jpg,png | 5MB |
| 1 (Privado) | 1 | Constancia de Cierre de Pensum | pdf | 5MB |
| 1 (Privado) | 1 | Ejemplar del Trabajo de Graduación | pdf | 30MB |
| 2 (Gerencia) | 1 | Factura de Impresión | pdf,jpg,png | 5MB |
| 3 (Público) | 6 | Empastados (2 ejemplares) | pdf,jpg,png | 10MB |
| 3 (Público) | 6 | CD con versión digital | pdf,jpg,png | 5MB |

### examen_documento - Student Uploaded Files
```sql
CREATE TABLE `examen_documento` (
  `cod_documento` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int(11) unsigned NOT NULL,
  `cod_requisito` smallint(5) unsigned NOT NULL,
  `version` tinyint(3) unsigned DEFAULT 1 COMMENT 'Incremented on resubmission',
  `es_version_actual` tinyint(1) DEFAULT 1 COMMENT '1=current, 0=historical',
  `archivo_nombre` varchar(200) NULL COMMENT 'Google Drive fileId',
  `nombre_original` varchar(255) COMMENT 'Original filename',
  `mime_type` varchar(100),
  `tamano_bytes` bigint(20) unsigned,
  `checksum_sha256` varchar(64),
  `subido_por` int(11) NOT NULL COMMENT 'FK → usuario (student)',
  `fecha_subida` timestamp DEFAULT current_timestamp(),
  `eliminado` tinyint(1) DEFAULT 0 COMMENT 'Soft delete',
  `eliminado_por` int(11) NULL,
  `fecha_eliminacion` timestamp NULL,
  
  UNIQUE KEY `unique_ed_proceso_req_version` (`cod_proceso`, `cod_requisito`, `version`),
  KEY `idx_ed_archivo_nombre` (`archivo_nombre`),
  KEY `idx_ed_eliminado` (`eliminado`),
  
  FOREIGN KEY (`cod_proceso`) REFERENCES `examen_proceso` (`cod_proceso`),
  FOREIGN KEY (`cod_requisito`) REFERENCES `examen_requisito_documento` (`cod_requisito`)
);
```

**Versioning:** New upload → increment version, mark old as `es_version_actual = 0`

### archivo_local - Local File Metadata
```sql
CREATE TABLE `archivo_local` (
  `cod_archivo` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_documento` int(11) unsigned NOT NULL,
  `nombre_md5` varchar(32) NOT NULL COMMENT 'Physical filename in disk/archivos/',
  `extension` varchar(10) NOT NULL COMMENT 'Without dot: pdf, jpg, png',
  `created_at` timestamp DEFAULT current_timestamp(),
  
  UNIQUE KEY `unique_al_documento` (`cod_documento`),
  UNIQUE KEY `unique_al_nombre` (`nombre_md5`),
  
  FOREIGN KEY (`cod_documento`) REFERENCES `examen_documento` (`cod_documento`)
);
```

**Physical storage:** `public/archivos/<md5>.<ext>`

### examen_revision_documento - Staff Document Decisions
```sql
CREATE TABLE `examen_revision_documento` (
  `cod_revision` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_documento` int(11) unsigned NOT NULL,
  `cod_proceso` int(11) unsigned NOT NULL COMMENT 'Denormalized for queries',
  `cod_requisito` smallint(5) unsigned NOT NULL COMMENT 'Denormalized for queries',
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `motivo_rechazo` text NULL COMMENT 'Required when estado=rechazado',
  `revisado_por` int(11) NOT NULL COMMENT 'FK → usuario (staff)',
  `fecha_revision` timestamp DEFAULT current_timestamp(),
  
  KEY `idx_erd_estado` (`estado`),
  KEY `idx_erd_proceso_requisito` (`cod_proceso`, `cod_requisito`),
  
  FOREIGN KEY (`cod_documento`) REFERENCES `examen_documento` (`cod_documento`)
);
```

### examen_documento_fisico - Physical Document Checklist
```sql
CREATE TABLE `examen_documento_fisico` (
  `cod_doc_fisico` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int(11) unsigned NOT NULL,
  `cod_requisito` smallint(5) unsigned NOT NULL COMMENT 'Only tipo_entrega=fisico',
  `recibido` tinyint(1) DEFAULT 0,
  `fecha_recepcion` timestamp NULL,
  `recibido_por` int(11) NULL COMMENT 'FK → usuario (staff)',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  UNIQUE KEY `unique_edf_proceso_req` (`cod_proceso`, `cod_requisito`)
);
```

### examen_terna - Assigned Examiners (Step 3)
```sql
CREATE TABLE `examen_terna` (
  `cod_terna` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int(11) unsigned NOT NULL,
  `nombre_examinador` varchar(200) NOT NULL,
  `numero_colegiado` varchar(50),
  `correo` varchar(150) COMMENT 'For future notifications',
  `tipo_examinador` enum('interno','externo') DEFAULT 'externo',
  `posicion` tinyint(1) unsigned NOT NULL COMMENT '1, 2, or 3',
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  UNIQUE KEY `unique_proceso_posicion` (`cod_proceso`, `posicion`)
);
```

**Note:** Same terna (examiners) is used for both private AND general exam

### examen_historial - Immutable Audit Trail
```sql
CREATE TABLE `examen_historial` (
  `cod_historial` bigint(20) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int(11) unsigned NOT NULL,
  `cod_usuario` int(11) NOT NULL COMMENT 'FK → usuario (actor)',
  `tipo_evento` enum(
    'avance_paso',
    'retroceso_paso',
    'subida_documento',
    'revision_documento',
    'rechazo_documento',
    'asignacion_terna',
    'cancelacion',
    'reactivacion',
    'otro'
  ) NOT NULL,
  `descripcion` text COMMENT 'Human-readable message',
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin 
    CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin 
    CHECK (json_valid(`datos_nuevos`)),
  `ip_address` varchar(45) COMMENT 'IPv4 or IPv6',
  `user_agent` varchar(300),
  `created_at` timestamp DEFAULT current_timestamp(),
  
  KEY `idx_eh_proceso` (`cod_proceso`),
  KEY `idx_eh_usuario` (`cod_usuario`),
  KEY `idx_eh_tipo_evento` (`tipo_evento`)
);
```

## Phase 5: Carta de Examinadores Tables

### examen_correccion_ciclo - Simplified Correction Cycle
```sql
CREATE TABLE `examen_correccion_ciclo` (
  `cod_ciclo` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int(11) unsigned NOT NULL,
  `estado` enum('pendiente_revision','aprobado') DEFAULT 'pendiente_revision',
  `revisado_por` int(11) NULL COMMENT 'FK → usuario (coordinator)',
  `fecha_revision` timestamp NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  UNIQUE KEY `uniq_ecc_proceso` (`cod_proceso`)
);
```

**Model:** One cycle per process (simplified). Corrections happen via EMAIL outside platform.

### examen_correccion_evidencia - Email Evidence (Screenshots)
```sql
CREATE TABLE `examen_correccion_evidencia` (
  `cod_evidencia` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_ciclo` int(11) unsigned NOT NULL,
  `archivo_md5` varchar(32) NOT NULL COMMENT 'Physical filename in public/archivos/',
  `extension` varchar(10) NOT NULL COMMENT 'Without dot: jpg, png, pdf',
  `nombre_original` varchar(255),
  `tamano_bytes` int(10) unsigned,
  `descripcion` varchar(300) COMMENT 'Short note from student',
  `subido_por` int(11) NOT NULL,
  `fecha_subida` timestamp DEFAULT current_timestamp(),
  `eliminado` tinyint(1) DEFAULT 0,
  
  UNIQUE KEY `uniq_ece_archivo` (`archivo_md5`),
  KEY `idx_ece_ciclo` (`cod_ciclo`)
);
```

### examen_carta_plantilla - .docx Template Catalog
```sql
CREATE TABLE `examen_carta_plantilla` (
  `cod_plantilla` smallint(5) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_tipo_examen` tinyint(3) unsigned NULL COMMENT 'NULL = all types',
  `nombre` varchar(150) NOT NULL,
  `archivo_plantilla` varchar(255) NOT NULL,
  `descripcion` text,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT current_timestamp(),
  
  KEY `idx_ecp_tipo_activo` (`cod_tipo_examen`, `activo`)
);
```

**Templates location:** `data/plantillas/carta-examinadores/*.docx`

### examen_carta_examinadores - Generated Letters
```sql
CREATE TABLE `examen_carta_examinadores` (
  `cod_carta` int(11) unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int(11) unsigned NOT NULL,
  `cod_ciclo_aprobacion` int(11) unsigned NOT NULL,
  `cod_plantilla` smallint(5) unsigned NOT NULL,
  `archivo_generado` varchar(255) NOT NULL,
  `estado` enum('generada','entregada') DEFAULT 'generada',
  `fecha_generacion` timestamp DEFAULT current_timestamp(),
  `generada_por` int(11) NOT NULL,
  `fecha_entrega` timestamp NULL,
  `observaciones` text,
  
  UNIQUE KEY `uniq_ece_proceso` (`cod_proceso`),
  KEY `idx_ece_estado` (`estado`)
);
```

**Generated files:** `data/documentos/cartas-examinadores/*.docx`

## Phase 6: Autorización de Impresión Tables

### examen_autorizacion_config - Global Instructions
```sql
CREATE TABLE `examen_autorizacion_config` (
  `cod_config` tinyint unsigned AUTO_INCREMENT PRIMARY KEY,
  `instrucciones_parte1` text COMMENT 'Part 1: Print Authorization instructions',
  `instrucciones_parte2` text COMMENT 'Part 2: Final Project Delivery instructions',
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) NULL COMMENT 'FK → usuario who modified'
) COMMENT='Global step 6 configuration (single record)';

-- Seed: INSERT INTO examen_autorizacion_config VALUES (1, NULL, NULL, NOW(), NULL);
```

### examen_autorizacion_documento_soporte - Support Documents
```sql
CREATE TABLE `examen_autorizacion_documento_soporte` (
  `cod_documento` int unsigned AUTO_INCREMENT PRIMARY KEY,
  `titulo` varchar(200) NOT NULL COMMENT 'Title visible to student',
  `descripcion` varchar(500),
  `archivo_md5` varchar(32) NOT NULL,
  `extension` varchar(10) NOT NULL COMMENT 'Without dot: jpg, png, pdf, docx',
  `nombre_original` varchar(255),
  `tamano_bytes` int unsigned,
  `activo` tinyint(1) DEFAULT 1,
  `subido_por` int(11) NOT NULL,
  `fecha_subida` timestamp DEFAULT current_timestamp(),
  
  UNIQUE KEY `uniq_eads_md5` (`archivo_md5`)
) COMMENT='Global support docs (logos, seals, visual guides)';
```

**Storage:** `public/archivos/autorizacion_impresion/documentos_soporte/<md5>.<ext>`

### examen_profesional_calificado - Literature Professionals Catalog
```sql
CREATE TABLE `examen_profesional_calificado` (
  `cod_profesional` int unsigned AUTO_INCREMENT PRIMARY KEY,
  `nombre_completo` varchar(200) NOT NULL,
  `correo` varchar(150),
  `telefono` varchar(20),
  `activo` tinyint(1) DEFAULT 1,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp DEFAULT current_timestamp(),
  
  KEY `idx_epc_activo` (`activo`),
  KEY `idx_epc_nombre` (`nombre_completo`)
) COMMENT='Qualified literature professionals (no license number stored)';
```

**Note:** By requirement, NO professional license number is stored.

### examen_carta_descarga - Generic Download Letters
```sql
CREATE TABLE `examen_carta_descarga` (
  `cod_carta` int unsigned AUTO_INCREMENT PRIMARY KEY,
  `titulo` varchar(200) NOT NULL COMMENT 'Title visible to student',
  `descripcion` varchar(500) COMMENT 'What the letter is for',
  `archivo_md5` varchar(32) NOT NULL,
  `extension` varchar(10) DEFAULT 'docx',
  `nombre_original` varchar(255) NOT NULL,
  `tamano_bytes` int unsigned,
  `activo` tinyint(1) DEFAULT 1,
  `subido_por` int(11) NOT NULL,
  `fecha_subida` timestamp DEFAULT current_timestamp(),
  
  UNIQUE KEY `uniq_ecd_md5` (`archivo_md5`)
) COMMENT='Generic .docx letters for student download';
```

**Storage:** `public/archivos/autorizacion_impresion/cartas_descarga/<md5>.<ext>`

### examen_junta_directiva - Board Members (Informational)
```sql
CREATE TABLE `examen_junta_directiva` (
  `cod_miembro` int unsigned AUTO_INCREMENT PRIMARY KEY,
  `nombre_completo` varchar(200) NOT NULL,
  `puesto` varchar(100) NOT NULL COMMENT 'e.g., President, Secretary, Vocal I',
  `activo` tinyint(1) DEFAULT 1,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp DEFAULT current_timestamp(),
  
  KEY `idx_ejd_activo_fecha` (`activo`, `fecha_creacion`)
) COMMENT='Board members (informational, read-only for students)';
```

### examen_autorizacion_proceso - Phase 6 Process Status
```sql
CREATE TABLE `examen_autorizacion_proceso` (
  `cod_autorizacion` int unsigned AUTO_INCREMENT PRIMARY KEY,
  `cod_proceso` int unsigned NOT NULL,
  `cod_profesional` int unsigned NULL COMMENT 'FK → professional selected by student',
  `sub_paso` tinyint unsigned DEFAULT 1 COMMENT '1=Part 1 (selection), 2=Part 2 (completion)',
  `estado` enum('pendiente','aprobado') DEFAULT 'pendiente',
  `fecha_aprobacion` timestamp NULL,
  `aprobado_por` int(11) NULL COMMENT 'FK → user who approved (director)',
  `observaciones` text COMMENT 'Notes about in-person review',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  UNIQUE KEY `uniq_eap_proceso` (`cod_proceso`),
  KEY `idx_eap_estado` (`estado`),
  KEY `idx_eap_subpaso` (`sub_paso`)
);
```

**Two sub-steps:**
1. **Part 1** (`sub_paso=1`): Student selects professional, director approves in-person review
2. **Part 2** (`sub_paso=2`): Final preparation for general exam, director confirms completion

## ACL Actions for Graduation Module

New actions added in `modulo_graduacion_carta_02_seeds.sql`:

| cod_accion | nombre | Who uses it |
|------------|--------|-------------|
| 68 | Ver paso de carta de examinadores | Students, Staff |
| 69 | (unused - historical) | - |
| 70 | Adjuntar evidencia a la bitácora de correcciones | Students |
| 71 | Aprobar trabajo de graduación y generar carta | Director |
| 72 | Descargar carta de examinadores | Students, Director |
| 73 | (unused - historical) | - |
| 74 | Eliminar evidencia de la bitácora | Director |

**Inserted by:**
```sql
INSERT INTO accion (cod_accion, nombre) VALUES
  (68, 'Ver paso de carta de examinadores'),
  (70, 'Adjuntar evidencia a la bitácora de correcciones'),
  (71, 'Aprobar trabajo de graduación y generar carta'),
  (72, 'Descargar carta de examinadores'),
  (74, 'Eliminar evidencia de la bitácora');
```

## Common SQL Queries

### Get active process with current step
```sql
SELECT 
    ep.cod_proceso,
    u.nombres,
    u.apellidos,
    et.nombre as tipo_examen,
    epc.nombre as paso_actual,
    epc.fase,
    ep.fecha_examen_privado,
    ep.fecha_examen_general,
    ep.cancelado
FROM examen_proceso ep
JOIN usuario u ON ep.cod_usuario = u.cod_usuario
JOIN examen_tipo et ON ep.cod_tipo_examen = et.cod_tipo_examen
LEFT JOIN examen_paso_catalogo epc ON ep.cod_paso_actual = epc.cod_paso
WHERE ep.cancelado = 0
  AND ep.cod_usuario = ?;
```

### Get documents for a specific step
```sql
SELECT 
    erd.cod_requisito,
    erd.nombre as requisito_nombre,
    erd.tipo_entrega,
    erd.obligatorio,
    ed.cod_documento,
    ed.version,
    ed.nombre_original,
    ed.fecha_subida,
    erd2.estado as revision_estado,
    erd2.motivo_rechazo
FROM examen_requisito_documento erd
LEFT JOIN examen_documento ed 
    ON erd.cod_requisito = ed.cod_requisito
    AND ed.cod_proceso = ?
    AND ed.es_version_actual = 1
    AND ed.eliminado = 0
LEFT JOIN examen_revision_documento erd2
    ON ed.cod_documento = erd2.cod_documento
WHERE erd.cod_paso = ?
  AND erd.activo = 1
ORDER BY erd.orden_display;
```

### Get process history
```sql
SELECT 
    eh.tipo_evento,
    eh.descripcion,
    eh.datos_anteriores,
    eh.datos_nuevos,
    u.nombres,
    u.apellidos,
    eh.created_at
FROM examen_historial eh
JOIN usuario u ON eh.cod_usuario = u.cod_usuario
WHERE eh.cod_proceso = ?
ORDER BY eh.created_at DESC;
```

### Get examiners for a process
```sql
SELECT 
    et.posicion,
    et.nombre_examinador,
    et.numero_colegiado,
    et.correo,
    et.tipo_examinador
FROM examen_terna et
WHERE et.cod_proceso = ?
ORDER BY et.posicion;
```

### Get pending processes needing director attention
```sql
SELECT 
    ep.cod_proceso,
    u.nombres,
    u.apellidos,
    u.correo,
    et.nombre as tipo_examen,
    epc.nombre as paso_actual,
    epp.estado,
    epp.fecha_inicio
FROM examen_proceso ep
JOIN usuario u ON ep.cod_usuario = u.cod_usuario
JOIN examen_tipo et ON ep.cod_tipo_examen = et.cod_tipo_examen
JOIN examen_paso_catalogo epc ON ep.cod_paso_actual = epc.cod_paso
JOIN examen_proceso_paso epp 
    ON ep.cod_proceso = epp.cod_proceso 
    AND ep.cod_paso_actual = epp.cod_paso
WHERE ep.cancelado = 0
  AND epp.estado IN ('pendiente', 'rechazado')
ORDER BY epp.fecha_inicio;
```

## Key Business Rules

1. **One active process per student per type**: `cancelado = 0` constraint
2. **Sequential steps**: Must complete in order (1→2→3→4→5→6→1→2→3→4)
3. **Shared terna**: Same 3 examiners for both private and general exam
4. **Document versioning**: Each resubmission creates new version
5. **Soft deletes**: `eliminado` flag, files not physically removed
6. **External corrections**: Phase 5 corrections happen via email, platform stores only evidence
7. **Two-phase Phase 6**: `sub_paso` tracks progress within step 6

## File Storage Summary

| Table | Physical Path | File Naming |
|-------|---------------|-------------|
| examen_documento + archivo_local | `public/archivos/<md5>.<ext>` | MD5 hash |
| examen_correccion_evidencia | `public/archivos/<md5>.<ext>` | MD5 hash |
| examen_autorizacion_documento_soporte | `public/archivos/autorizacion_impresion/documentos_soporte/<md5>.<ext>` | MD5 hash |
| examen_carta_descarga | `public/archivos/autorizacion_impresion/cartas_descarga/<md5>.<ext>` | MD5 hash |
| examen_carta_plantilla | `data/plantillas/carta-examinadores/*.docx` | Original name |
| examen_carta_examinadores | `data/documentos/cartas-examinadores/*.docx` | Generated name |

## Foreign Key Dependencies

```
Core dependencies from 20250718Postgrados.sql:
- usuario (cod_usuario)
- rol (cod_rol)
- accion (cod_accion)

Module dependencies:
examen_tipo
  └─► examen_paso_catalogo
       ├─► examen_requisito_documento
       └─► examen_proceso
            ├─► examen_proceso_paso
            ├─► examen_documento ──► archivo_local
            │       └─► examen_revision_documento
            ├─► examen_documento_fisico
            ├─► examen_terna
            ├─► examen_historial
            ├─► examen_correccion_ciclo ──► examen_correccion_evidencia
            │       └─► examen_carta_examinadores
            │              └─► examen_carta_plantilla
            └─► examen_autorizacion_proceso
                   └─► examen_profesional_calificado
```

## Connection to Core Tables

```sql
-- examen_proceso links to core system:
examen_proceso.cod_usuario ────────► usuario.cod_usuario (student)
examen_proceso.registrado_por ─────► usuario.cod_usuario (staff)
examen_proceso_paso.completado_por ► usuario.cod_usuario (staff)
examen_revision_documento.revisado_por ► usuario.cod_usuario

-- ACL permissions require:
accion.cod_accion (68-74)
rol.cod_rol (1=Director has access to all)
estado_accion.cod_rol + cod_accion
```

For full system context, see `.opencode/database-context.md` or `database/20250718Postgrados.sql`
