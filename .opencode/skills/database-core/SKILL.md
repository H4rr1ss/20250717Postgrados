---
name: database-core
description: Use ONLY when the user asks about core system database tables (NOT graduation module). Keywords include usuario, rol, carrera, inscripcion, asignacion, nota, acta, orden_pago, bloque, pensum, cohorte, curso, horario. DO NOT activate for examen_, proceso, graduacion, terna, carta examinadores, or autorizacion impresion tables.
---

# Database Context - Core System (Sistema Core)

This skill provides database schema information for the core Postgrados system (NON-graduation tables).

## When to Use

Activate when user asks about:
- Users and roles (usuario, rol, usuario_rol)
- Careers and academic programs (carrera, grado_academico)
- Enrollment (inscripcion, asignacion, asignacion_carrera)
- Grades and records (nota_asignatura, nota_final, acta)
- Study plans (pensum, curso_pensum, cohorte)
- Schedules and classrooms (horario, salon, ubicacion)
- Payment orders (orden_pago, cursos_orden_pago)
- Treasury (moroso, banco, precio)
- System configuration (parametro, accion, estado_accion)

**DO NOT use for graduation module** (examen_* tables) - use database-graduacion skill instead.

## Core Database: 20250718Postgrados.sql

**Location:** `database/20250718Postgrados.sql` (35MB)
**Database:** `db_postgrados`
**Engine:** MySQL 5.7
**Charset:** utf8mb4

## Table Categories

### 1. User Management & Security

#### usuario - Users
```sql
CREATE TABLE `usuario` (
  `cod_usuario` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cui` varchar(20) UNIQUE,
  `registro_academico` varchar(50) UNIQUE,
  `nombres` varchar(150),
  `apellidos` varchar(150),
  `fecha_nacimiento` date,
  `telefono` varchar(20),
  `correo` varchar(150),
  `contrasenia` varchar(255) COMMENT 'Bcrypt hash',
  `cod_pais` int(11),
  `sexo` enum('M','H'),
  `grado_academico` varchar(100),
  `fecha_creacion` date,
  `nombre_completo` varchar(300),
  -- Additional fields...
);
```

**Key columns for login:**
- `cui` - Unique identification number
- `registro_academico` - Academic registration number
- `correo` - Email (can be used for login)
- `contrasenia` - Password (bcrypt hash)

#### rol - Roles
```sql
CREATE TABLE `rol` (
  `cod_rol` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `descripcion` text
);
```

**Role codes:**
| cod_rol | nombre | Description |
|---------|--------|-------------|
| 1 | Director | System administrator |
| 2 | Asistente | Assistant |
| 3 | Tesorero | Treasurer |
| 4 | Coordinador | Coordinator |
| 5 | Catedrático | Teacher |
| 6 | Estudiante | Student |
| 7 | Programador | Programmer |
| 8 | UDICA Programador | UDICA Programmer |
| 9 | UDICA Jefe | UDICA Chief |
| 10 | UDICA Operador | UDICA Operator |

#### usuario_rol - User-Role Assignments
```sql
CREATE TABLE `usuario_rol` (
  `cod_usuario_rol` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11) NOT NULL,
  `cod_rol` int(11) NOT NULL,
  `fecha_inicio` date,
  `fecha_fin` date,
  FOREIGN KEY (`cod_usuario`) REFERENCES `usuario` (`cod_usuario`),
  FOREIGN KEY (`cod_rol`) REFERENCES `rol` (`cod_rol`)
);
```

#### accion - Actions for ACL
```sql
CREATE TABLE `accion` (
  `cod_accion` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100)
);
```

**Used with:** `estado_accion` (rol + accion permissions)

#### estado_accion - Role Permissions
```sql
CREATE TABLE `estado_accion` (
  `cod_estado_accion` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_rol` int(11),
  `cod_accion` int(11),
  `activo` tinyint(1),
  FOREIGN KEY (`cod_rol`) REFERENCES `rol` (`cod_rol`),
  FOREIGN KEY (`cod_accion`) REFERENCES `accion` (`cod_accion`)
);
```

### 2. Academic Structure

#### carrera - Careers/Majors
```sql
CREATE TABLE `carrera` (
  `cod_carrera` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre_actual` varchar(200),
  `alias_actual` varchar(150),
  `cod_grado` int(11),
  FOREIGN KEY (`cod_grado`) REFERENCES `grado_academico` (`cod_grado`)
);
```

**Sample careers:**
| cod_carrera | nombre_actual |
|-------------|---------------|
| 9 | Maestría en Planificación de Asentamientos Humanos y Vivienda |
| 10 | Maestría en Restauración de Monumentos |
| 11 | Maestría en Diseño, Planificación y Manejo Ambiental |
| 12 | Maestría en Diseño Arquitectónico |
| 13 | Maestría en Gestión para la Reducción del Riesgo |
| 14 | Maestría en Desarrollo Urbano y Territorio |
| 15 | Maestría en Arquitectura para la Salud |
| 16 | Maestría en Planificación y Diseño del Paisaje |
| 17 | Maestría en Mercadeo para el Diseño |
| 18 | Maestría en Patrimonio Cultural (Énfasis Conservación) |
| 19 | Maestría en Patrimonio Cultural (Énfasis Gestión) |
| 20 | Especialización en Análisis y Reducción de Riesgo |
| 21 | Especialización en Arquitectura Sostenible |
| 22 | Maestría en Enseñanza Virtual de la Arquitectura |
| 23 | Maestría en Diseño Interactivo Digital |
| 24 | Maestría en Gerencia de Proyectos Arquitectónicos |
| 25 | Maestría en Gestión Integrada (Ambiente, Calidad, Prevención) |
| 26 | Maestría en Diseño y Gestión de Proyectos Tecnológicos |
| 28 | Especialización en Dirección y Producción de Cine |
| 80 | Doctorado en Arquitectura |
| 999 | Curso de Actualización |

#### grado_academico - Academic Levels
```sql
CREATE TABLE `grado_academico` (
  `cod_grado` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100)
);
```

**Values:**
| cod_grado | nombre |
|-----------|--------|
| 3 | Maestría |
| 6 | Especialización |
| 7 | Doctorado |
| 999 | Curso |

#### pensum - Study Plans
```sql
CREATE TABLE `pensum` (
  `cod_pensum` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_carrera` int(11),
  `nombre` varchar(200),
  `anio` year,
  `activo` tinyint(1),
  FOREIGN KEY (`cod_carrera`) REFERENCES `carrera` (`cod_carrera`)
);
```

#### cohorte - Cohorts
```sql
CREATE TABLE `cohorte` (
  `cod_cohorte` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `anio` year,
  `semestre` tinyint,
  `activo` tinyint(1)
);
```

#### pensum_cohorte - Study Plan-Cohort Association
```sql
CREATE TABLE `pensum_cohorte` (
  `cod_pensum_cohorte` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_pensum` int(11),
  `cod_cohorte` int(11),
  `fecha_inicio` date,
  `fecha_fin` date,
  FOREIGN KEY (`cod_pensum`) REFERENCES `pensum` (`cod_pensum`),
  FOREIGN KEY (`cod_cohorte`) REFERENCES `cohorte` (`cod_cohorte`)
);
```

#### curso_pensum - Courses in Study Plan
```sql
CREATE TABLE `curso_pensum` (
  `cod_curso_pensum` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_pensum` int(11),
  `nombre` varchar(200),
  `creditos` decimal(4,2),
  `cod_tipo_curso` int(11),
  `semestre` tinyint,
  `activo` tinyint(1),
  FOREIGN KEY (`cod_pensum`) REFERENCES `pensum` (`cod_pensum`)
);
```

#### tipo_curso - Course Types
```sql
CREATE TABLE `tipo_curso` (
  `cod_tipo_curso` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `descripcion` text
);
```

#### bloque - Course Evaluation Activities
```sql
CREATE TABLE `bloque` (
  `cod_bloque` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_curso_pensum` int(11),
  `nombre` varchar(200),
  `porcentaje` decimal(5,2),
  FOREIGN KEY (`cod_curso_pensum`) REFERENCES `curso_pensum` (`cod_curso_pensum`)
);
```

**Example:** A course might have multiple evaluation blocks (assignments, exams, participation)

### 3. Enrollment & Assignment

#### inscripcion - Enrollments
```sql
CREATE TABLE `inscripcion` (
  `cod_inscripcion` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11),
  `cod_pensum_cohorte` int(11),
  `fecha_inscripcion` date,
  `estado` enum('activa','finalizada','cancelada'),
  FOREIGN KEY (`cod_usuario`) REFERENCES `usuario` (`cod_usuario`),
  FOREIGN KEY (`cod_pensum_cohorte`) REFERENCES `pensum_cohorte` (`cod_pensum_cohorte`)
);
```

#### asignacion - Course Assignments
```sql
CREATE TABLE `asignacion` (
  `cod_asignacion` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_inscripcion` int(11),
  `cod_curso_pensum` int(11),
  `cod_horario` int(11),
  `estado` enum('asignado','oficializado','retirado'),
  FOREIGN KEY (`cod_inscripcion`) REFERENCES `inscripcion` (`cod_inscripcion`),
  FOREIGN KEY (`cod_curso_pensum`) REFERENCES `curso_pensum` (`cod_curso_pensum`)
);
```

#### asignacion_carrera - Career Assignments
```sql
CREATE TABLE `asignacion_carrera` (
  `cod_asignacion_carrera` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11),
  `cod_carrera` int(11),
  `fecha_asignacion` date,
  FOREIGN KEY (`cod_usuario`) REFERENCES `usuario` (`cod_usuario`),
  FOREIGN KEY (`cod_carrera`) REFERENCES `carrera` (`cod_carrera`)
);
```

### 4. Schedules & Classrooms

#### horario - Schedules
```sql
CREATE TABLE `horario` (
  `cod_horario` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_curso_pensum` int(11),
  `dia_semana` tinyint COMMENT '1=Monday, 7=Sunday',
  `hora_inicio` time,
  `hora_fin` time,
  `cod_salon` int(11),
  FOREIGN KEY (`cod_salon`) REFERENCES `salon` (`cod_salon`)
);
```

#### salon - Classrooms
```sql
CREATE TABLE `salon` (
  `cod_salon` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `capacidad` int,
  `cod_ubicacion` int(11),
  FOREIGN KEY (`cod_ubicacion`) REFERENCES `ubicacion` (`cod_ubicacion`)
);
```

#### ubicacion - Locations
```sql
CREATE TABLE `ubicacion` (
  `cod_ubicacion` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(150),
  `direccion` text
);
```

### 5. Grades & Records

#### nota_asignatura - Subject Grades
```sql
CREATE TABLE `nota_asignatura` (
  `cod_nota_asignatura` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_asignacion` int(11),
  `cod_bloque` int(11),
  `nota` decimal(5,2),
  `fecha_registro` date,
  `cod_estado_nota` int(11),
  FOREIGN KEY (`cod_asignacion`) REFERENCES `asignacion` (`cod_asignacion`),
  FOREIGN KEY (`cod_bloque`) REFERENCES `bloque` (`cod_bloque`)
);
```

#### nota_final - Final Grades
```sql
CREATE TABLE `nota_final` (
  `cod_nota_final` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_asignacion` int(11),
  `nota` decimal(5,2),
  `cod_tipo_nota_final` int(11),
  `cod_estado_nota_final` int(11),
  FOREIGN KEY (`cod_asignacion`) REFERENCES `asignacion` (`cod_asignacion`)
);
```

#### tipo_nota_final - Final Grade Types
```sql
CREATE TABLE `tipo_nota_final` (
  `cod_tipo_nota_final` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `descripcion` text
);
```

#### estado_nota - Grade States
```sql
CREATE TABLE `estado_nota` (
  `cod_estado_nota` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(50),
  `descripcion` text
);
```

#### estado_nota_final - Final Grade States
```sql
CREATE TABLE `estado_nota_final` (
  `cod_estado_nota_final` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(50),
  `descripcion` text
);
```

### 6. Exam Records (Actas)

#### acta - Exam Records
```sql
CREATE TABLE `acta` (
  `cod_acta` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_asignacion` int(11),
  `cod_tipo_acta` int(11),
  `fecha_acta` date,
  `observaciones` text,
  FOREIGN KEY (`cod_asignacion`) REFERENCES `asignacion` (`cod_asignacion`)
);
```

#### tipo_acta - Record Types
```sql
CREATE TABLE `tipo_acta` (
  `cod_tipo_acta` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `descripcion` text
);
```

#### detalle_acta_oficial - Official Record Details
```sql
CREATE TABLE `detalle_acta_oficial` (
  `cod_detalle_acta_oficial` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_acta` int(11),
  -- official record details...
  FOREIGN KEY (`cod_acta`) REFERENCES `acta` (`cod_acta`)
);
```

#### detalle_acta_postgrados - Postgraduate Record Details
```sql
CREATE TABLE `detalle_acta_postgrados` (
  `cod_detalle_acta_postgrados` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_acta` int(11),
  -- postgraduate-specific details...
  FOREIGN KEY (`cod_acta`) REFERENCES `acta` (`cod_acta`)
);
```

### 7. Treasury (Tesorería)

#### orden_pago - Payment Orders
```sql
CREATE TABLE `orden_pago` (
  `cod_orden_pago` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11),
  `cod_tipo_orden` int(11),
  `monto` decimal(10,2),
  `fecha_emision` date,
  `fecha_vencimiento` date,
  `estado` enum('pendiente','pagada','vencida','anulada'),
  `codigo_barras` varchar(100),
  FOREIGN KEY (`cod_usuario`) REFERENCES `usuario` (`cod_usuario`)
);
```

#### tipo_orden - Order Types
```sql
CREATE TABLE `tipo_orden` (
  `cod_tipo_orden` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `descripcion` text
);
```

#### cursos_orden_pago - Courses in Payment Order
```sql
CREATE TABLE `cursos_orden_pago` (
  `cod_cursos_orden_pago` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_orden_pago` int(11),
  `cod_curso_pensum` int(11),
  `monto` decimal(10,2),
  FOREIGN KEY (`cod_orden_pago`) REFERENCES `orden_pago` (`cod_orden_pago`)
);
```

#### precio - Prices
```sql
CREATE TABLE `precio` (
  `cod_precio` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_carrera` int(11),
  `cod_tipo_orden` int(11),
  `monto` decimal(10,2),
  `anio` year,
  `activo` tinyint(1)
);
```

#### moroso - Delinquency Records
```sql
CREATE TABLE `moroso` (
  `cod_moroso` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11),
  `cod_orden_pago` int(11),
  `fecha_registro` date,
  `observaciones` text
);
```

#### banco - Banks
```sql
CREATE TABLE `banco` (
  `cod_banco` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `codigo` varchar(20)
);
```

### 8. Configuration

#### parametro - System Parameters
```sql
CREATE TABLE `parametro` (
  `cod_parametro` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `valor` text,
  `tipo` varchar(50),
  `descripcion` text
);
```

#### detalle_parametro - Parameter Details
```sql
CREATE TABLE `detalle_parametro` (
  `cod_detalle_parametro` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_parametro` int(11),
  `cod_carrera` int(11),
  `valor` text,
  FOREIGN KEY (`cod_parametro`) REFERENCES `parametro` (`cod_parametro`)
);
```

### 9. Additional Tables

#### pais - Countries
```sql
CREATE TABLE `pais` (
  `cod_pais` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `codigo_iso` char(2)
);
```

**Guatemala:** cod_pais = 73

#### situacion - Situations
```sql
CREATE TABLE `situacion` (
  `cod_situacion` int(11) AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100),
  `descripcion` text
);
```

#### fin_inscripcion - Enrollment Periods
```sql
CREATE TABLE `fin_inscripcion` (
  `cod_fin_inscripcion` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_carrera` int(11),
  `fecha_inicio` datetime,
  `fecha_fin` datetime,
  `activo` tinyint(1)
);
```

#### info_laboral - Work Information
```sql
CREATE TABLE `info_laboral` (
  `cod_info_laboral` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11),
  `empresa` varchar(200),
  `cargo` varchar(100),
  `direccion` text,
  `telefono` varchar(20)
);
```

#### involucrado - Stakeholders
```sql
CREATE TABLE `involucrado` (
  `cod_involucrado` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_usuario` int(11),
  `tipo_involucrado` varchar(50),
  `cod_referencia` int(11),
  `tabla_referencia` varchar(50)
);
```

#### nombre_carrera - Historical Career Names
```sql
CREATE TABLE `nombre_carrera` (
  `cod_nombre_carrera` int(11) AUTO_INCREMENT PRIMARY KEY,
  `cod_carrera` int(11),
  `nombre` varchar(200),
  `fecha_inicio` date,
  `fecha_fin` date
);
```

## Common SQL Queries

### Get student with current enrollments
```sql
SELECT 
    u.cod_usuario,
    u.cui,
    u.nombres,
    u.apellidos,
    c.nombre_actual as carrera,
    co.nombre as cohorte,
    i.fecha_inscripcion,
    i.estado
FROM usuario u
JOIN inscripcion i ON u.cod_usuario = i.cod_usuario
JOIN pensum_cohorte pc ON i.cod_pensum_cohorte = pc.cod_pensum_cohorte
JOIN cohorte co ON pc.cod_cohorte = co.cod_cohorte
JOIN pensum p ON pc.cod_pensum = p.cod_pensum
JOIN carrera c ON p.cod_carrera = c.cod_carrera
WHERE u.cod_usuario = ?
  AND i.estado = 'activa';
```

### Get student courses with grades
```sql
SELECT 
    cp.nombre as curso,
    c.nombre_actual as carrera,
    a.estado,
    nf.nota as nota_final,
    nf2.nombre as estado_nota
FROM asignacion a
JOIN inscripcion i ON a.cod_inscripcion = i.cod_inscripcion
JOIN curso_pensum cp ON a.cod_curso_pensum = cp.cod_curso_pensum
JOIN pensum p ON cp.cod_pensum = p.cod_pensum
JOIN carrera c ON p.cod_carrera = c.cod_carrera
LEFT JOIN nota_final nf ON a.cod_asignacion = nf.cod_asignacion
LEFT JOIN estado_nota_final nf2 ON nf.cod_estado_nota_final = nf2.cod_estado_nota_final
WHERE i.cod_usuario = ?
  AND a.estado IN ('asignado', 'oficializado');
```

### Get user with roles and permissions
```sql
SELECT 
    u.cod_usuario,
    u.nombres,
    u.apellidos,
    r.cod_rol,
    r.nombre as rol,
    ur.fecha_inicio,
    ur.fecha_fin,
    GROUP_CONCAT(a.nombre) as acciones
FROM usuario u
JOIN usuario_rol ur ON u.cod_usuario = ur.cod_usuario
JOIN rol r ON ur.cod_rol = r.cod_rol
LEFT JOIN estado_accion ea ON r.cod_rol = ea.cod_rol AND ea.activo = 1
LEFT JOIN accion a ON ea.cod_accion = a.cod_accion
WHERE u.cod_usuario = ?
  AND (ur.fecha_fin IS NULL OR ur.fecha_fin >= CURDATE())
GROUP BY u.cod_usuario, r.cod_rol;
```

### Get pending payment orders
```sql
SELECT 
    u.cod_usuario,
    u.nombres,
    u.apellidos,
    op.cod_orden_pago,
    op.monto,
    op.fecha_emision,
    op.fecha_vencimiento,
    top.nombre as tipo_orden
FROM orden_pago op
JOIN usuario u ON op.cod_usuario = u.cod_usuario
JOIN tipo_orden top ON op.cod_tipo_orden = top.cod_tipo_orden
WHERE op.estado = 'pendiente'
  AND op.fecha_vencimiento >= CURDATE()
ORDER BY op.fecha_vencimiento;
```

### Get courses in a study plan
```sql
SELECT 
    cp.cod_curso_pensum,
    cp.nombre as curso,
    cp.creditos,
    cp.semestre,
    tc.nombre as tipo_curso,
    COUNT(b.cod_bloque) as total_bloques
FROM curso_pensum cp
JOIN tipo_curso tc ON cp.cod_tipo_curso = tc.cod_tipo_curso
LEFT JOIN bloque b ON cp.cod_curso_pensum = b.cod_curso_pensum
WHERE cp.cod_pensum = ?
  AND cp.activo = 1
GROUP BY cp.cod_curso_pensum
ORDER BY cp.semestre, cp.nombre;
```

## Key Relationships

```
Core Entity Relationships:

usuario
  ├─► usuario_rol ───► rol
  ├─► inscripcion ────► pensum_cohorte ────┐
  │                                          │
  ├─► asignacion_carrera ────────────────────┼──► carrera
  │                                          │
  ├─► orden_pago                             │
  ├─► info_laboral                           │
  └─► moroso                                 │
                                             │
carrera ───► grado_academico                 │
  └─► pensum ───► curso_pensum ──────────────┤
       └─► pensum_cohorte ───► cohorte       │
                              │              │
                              └──────────────┘

curso_pensum
  ├─► bloque
  ├─► horario ───► salon ───► ubicacion
  └─► asignacion (through enrollment)

asignacion
  ├─► inscripcion
  ├─► curso_pensum
  ├─► nota_asignatura ───► bloque
  └─► nota_final

acta
  ├─► asignacion
  ├─► detalle_acta_oficial
  └─► detalle_acta_postgrados
```

## Docker Connection

```yaml
# docker-compose.yml
db:
  image: mysql:5.7
  ports:
    - "3307:3306"
  environment:
    MYSQL_DATABASE: db_postgrados
    MYSQL_USER: user
    MYSQL_PASSWORD: password
    MYSQL_ROOT_PASSWORD: rootpassword
```

## Important Notes

1. **Graduation Module is Separate**: Tables with `examen_*` prefix belong to the graduation module. Use `database-graduacion` skill for those.

2. **All tables reference usuario**: Most operations require joining with `usuario` table.

3. **Soft deletes**: Many tables use `activo` flag rather than physical deletion.

4. **Enrollment status**: Check `inscripcion.estado` for active enrollments.

5. **Role dates**: `usuario_rol` has `fecha_inicio` and `fecha_fin` for time-based roles.

6. **Grade calculation**: Final grades are stored in `nota_final`, individual block grades in `nota_asignatura`.

For graduation module tables (examen_*), see `.opencode/skills/database-graduacion/SKILL.md`
