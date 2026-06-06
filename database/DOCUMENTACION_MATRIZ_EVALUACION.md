# Módulo de Graduación — Matriz de Evaluación del Examen Privado

## Fecha: 2026-06-03

---

## 1. Resumen

Se implementó la **Matriz de Evaluación del Examen Privado** en el módulo de graduación. Esta funcionalidad permite al staff administrativo registrar la evaluación que cada uno de los 3 examinadores de la terna asigna al estudiante, después de que el examen privado ha sido notificado (paso 4 completado).

### Características principales:
- Cada maestría/especialización/doctorado tiene su **propia matriz de preguntas**
- Las preguntas pueden ser de tipo **numero** (0-10, 0-20, etc.) o **texto** (observaciones libres)
- El tema de tesis del estudiante se registra en el proceso
- Se guardan las 3 evaluaciones independientes (una por examinador)
- Se puede visualizar un resumen comparativo de las evaluaciones

---

## 2. Archivos creados/modificados

### 2.1 Nuevos archivos

| Archivo | Descripción |
|---------|-------------|
| `database/matriz_evaluacion_completo.sql` | **Script maestro** — Schema completo + 20 matrices con preguntas de prueba + vinculación `examen_tipo`. Ejecutar en una sola pasada. |
| `database/nueva_carrera_ejemplo.sql` | Script de referencia para crear una carrera completa desde cero (carrera, nombre_carrera, pensum, examen_tipo, matriz). |
| `module/Eep/view/eep/examen/evaluacion-privado.phtml` | Listado de procesos de examen privado listos para evaluación |
| `module/Eep/view/eep/examen/matriz-evaluacion.phtml` | Formulario de evaluación por examinador (tabs) |
| `module/Eep/view/eep/examen/ver-matriz.phtml` | Resumen comparativo de las 3 evaluaciones |

### 2.2 Archivos modificados

| Archivo | Cambios |
|---------|---------|
| `module/Eep/src/Service/ExamenManager.php` | Nuevos métodos: `getMatrizTipoPorCarrera()`, `getMatrizPreguntas()`, `getMatrizEvaluacion()`, `guardarMatrizEvaluacion()`, `getResumenEvaluaciones()`, `getProcesosEvaluables()`, `getTemaTesis()`, `guardarTemaTesis()`. Eliminado `detectarMatrizTipoPorCarrera()`. |
| `module/Eep/src/Controller/ExamenController.php` | Nuevas acciones: `evaluacionPrivadoAction()`, `matrizEvaluacionAction()`, `guardarMatrizAction()`, `verMatrizAction()` |
| `module/Eep/src/ValueObject/View.php` | Nueva constante `EVALUACION_PRIVADO = 31` |
| `module/Eep/config/menus.php` | Nuevo menú "Evaluación Examen Privado" en el grupo Módulo de Graduación |
| `module/Eep/config/access_filter.php` | Acciones 150-153 para Director/Asistente |

---

## 3. Tablas de Base de Datos

### 3.1 Nuevas tablas

```sql
examen_matriz_tipo          -- Catálogo de maestrías con matriz (cod_matriz_tipo, cod_carrera, nombre, activo)
examen_matriz_pregunta      -- Preguntas por maestría (cod_pregunta, cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo, activo)
examen_matriz_evaluacion    -- Cabecera de evaluación por examinador (cod_evaluacion, cod_proceso, posicion_examinador, evaluado_por, fecha_evaluacion, observaciones_generales)
examen_matriz_respuesta     -- Respuestas por pregunta (cod_respuesta, cod_evaluacion, cod_pregunta, punteo, respuesta_texto)
```

### 3.2 Columna agregada a tabla existente

```sql
examen_proceso.tema_tesis   -- VARCHAR(500) NULL -- Tema del trabajo de graduación
```

### 3.3 Vinculación con carreras

La tabla `examen_matriz_tipo` tiene una columna `cod_carrera` que se vincula con `carrera.cod_carrera`. Esto permite:
- Detectar automáticamente la matriz correcta según la carrera del estudiante
- Consultar el estado activo de la carrera en `nombre_carrera.activo`

### 3.4 Vinculación de examen_tipo con carreras

La tabla `examen_tipo` ahora tiene una columna `cod_carrera` que vincula cada tipo de examen privado con una carrera específica:
- `cod_carrera IS NULL` → Examen público (aplica a todas las carreras)
- `cod_carrera IS NOT NULL` → Examen privado de esa carrera específica
- Esto permite generar automáticamente los tipos de examen privado para cada carrera activa sin mantener una lista manual.

---

## 4. Ejecución en Base de Datos

### 4.1 Instalación / ejecución

```bash
# Ejecutar el script maestro completo en una sola pasada
docker exec -i <contenedor-mysql> mysql -u user -ppassword db_postgrados < database/matriz_evaluacion_completo.sql
```

Este script es **idempotente** (puede ejecutarse múltiples veces sin errores). Crea:
- Las 4 tablas nuevas (si no existen)
- La columna `tema_tesis` en `examen_proceso` (si no existe)
- 20 matrices vinculadas por `cod_carrera`
- 105 preguntas de prueba (5-6 por cada matriz)
- La vinculación `cod_carrera` en `examen_tipo` con 17 registros privados + 1 público

### 4.2 Si ya se ejecutó un script anterior

Si ya ejecutaste un script previo (`matriz_evaluacion_schema.sql`) con solo 6 matrices, ejecuta el script completo de todos modos. El `INSERT IGNORE` y las comprobaciones `IF EXISTS` evitan duplicados y errores.

### 4.3 Acciones ACL (requeridas para que el menú funcione)

```sql
-- Insertar las acciones en tabla accion
INSERT INTO accion (cod_accion, nombre) VALUES
  (150, 'Ver listado de evaluaciones de examen privado'),
  (151, 'Acceder a matriz de evaluación'),
  (152, 'Guardar matriz de evaluación'),
  (153, 'Ver resumen de evaluación');

-- Asignar permisos al rol Director (cod_rol = 1)
INSERT INTO estado_accion (cod_accion, cod_rol, activo) VALUES
  (150, 1, 1),
  (151, 1, 1),
  (152, 1, 1),
  (153, 1, 1);

-- Asignar permisos al rol Asistente (cod_rol = 2)
INSERT INTO estado_accion (cod_accion, cod_rol, activo) VALUES
  (150, 2, 1),
  (151, 2, 1),
  (152, 2, 1),
  (153, 2, 1);
```

---

## 5. Mapeo de Maestrías (20 matrices)

| cod_matriz_tipo | cod_carrera | Maestría / Especialización / Doctorado |
|-----------------|-------------|------------------------------------------|
| 1 | 18 | Maestría en Patrimonio Cultural — Conservación |
| 2 | 24 | Maestría en Gerencia de Proyectos Arquitectónicos |
| 3 | 13 | Maestría en Gestión para la Reducción del Riesgo |
| 4 | 22 | Maestría en Enseñanza Virtual de la Arquitectura y el Diseño |
| 5 | 17 | Maestría en Mercadeo para el Diseño |
| 6 | 15 | Maestría en Arquitectura para la Salud |
| 7 | 9 | Maestría en Planificación de Asentamientos Humanos y Vivienda |
| 8 | 10 | Maestría en Restauración de Monumentos |
| 9 | 11 | Maestría en Diseño, Planificación y Manejo Ambiental |
| 10 | 12 | Maestría en Diseño Arquitectónico |
| 11 | 14 | Maestría en Desarrollo Urbano y Territorio |
| 12 | 16 | Maestría en Planificación y Diseño del Paisaje |
| 13 | 19 | Maestría en Patrimonio Cultural — Gestión |
| 14 | 20 | Especialización en Análisis y Reducción de Riesgo de Desastres |
| 15 | 21 | Especialización en Arquitectura y Construcción Sostenible |
| 16 | 23 | Maestría en Diseño Interactivo Digital |
| 17 | 25 | Maestría en Gestión Integrada: Medio Ambiente, Calidad y Prevención |
| 18 | 26 | Maestría en Diseño y Gestión de Proyectos Tecnológicos |
| 19 | 28 | Especialización en Dirección y Producción de Cine, Video y Televisión |
| 20 | 80 | Doctorado en Arquitectura |

---

## 6. Mantenimiento de Matrices (SQL directo)

No hay pantalla de CRUD. Todo el mantenimiento se realiza por SQL directo.

### 6.1 Reemplazar preguntas de una matriz

```sql
-- Ejemplo: Reemplazar preguntas de la matriz 7 (Planificación de Asentamientos Humanos)
DELETE FROM examen_matriz_pregunta WHERE cod_matriz_tipo = 7;
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(7, 1, 'Pregunta real 1 de esta maestría', 'numero', '0-10'),
(7, 2, 'Pregunta real 2', 'texto', 'N/A'),
(7, 3, 'Pregunta real 3', 'numero', '0-10'),
(7, 4, 'Pregunta real 4', 'numero', '0-10'),
(7, 5, 'Pregunta real 5', 'texto', 'N/A');
```

### 6.2 Agregar una nueva pregunta a una matriz existente

```sql
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo)
VALUES (7, 6, 'Nueva pregunta 6', 'numero', '0-10');
```

### 6.3 Desactivar una pregunta (sin borrarla)

```sql
UPDATE examen_matriz_pregunta SET activo = 0 WHERE cod_pregunta = 123;
```

### 6.4 Desactivar una matriz completa

```sql
UPDATE examen_matriz_tipo SET activo = 0 WHERE cod_matriz_tipo = 7;
```

### 6.5 Agregar una nueva carrera completa (desde cero)

No hay panel de administración para crear carreras. Todo el proceso es por SQL directo. Ver script de ejemplo: `database/nueva_carrera_ejemplo.sql`.

**Orden de inserción obligatorio (por las FK):**

```sql
-- 1. carrera (tabla base, cod_carrera se genera con AUTO_INCREMENT)
INSERT INTO carrera (nombre_actual, alias_actual, cod_grado) VALUES
('Nueva Maestría', 'Nueva Maestría', 3);  -- 3=Maestría, 6=Especialización, 7=Doctorado

SET @nueva_carrera = LAST_INSERT_ID();

-- 2. nombre_carrera (nombre histórico con tiempo y activo)
INSERT INTO nombre_carrera (cod_carrera, nombre, alias, tiempo, activo) VALUES
(@nueva_carrera, 'Nueva Maestría', 'Nueva Maestría', NOW(), 1);

-- 3. pensum (plan de estudios, requerido para asignar cursos)
INSERT INTO pensum (descripcion, cod_carrera, creditos, fecha_creacion, fecha_inicio, fecha_fin) VALUES
('Pensum Nueva Maestría', @nueva_carrera, 60, CURDATE(), CURDATE(), NULL);

-- 4. examen_tipo (examen privado para esta carrera, aparece en "Gestión de Exámenes")
INSERT INTO examen_tipo (cod_carrera, nombre, descripcion, activo) VALUES
(@nueva_carrera, 'Privado - Nueva Maestría', 'Examen privado para Nueva Maestría', 1);

-- 5. examen_matriz_tipo (matriz de evaluación del examen privado)
INSERT INTO examen_matriz_tipo (cod_carrera, nombre) VALUES
(@nueva_carrera, 'Nueva Maestría');

SET @nueva_matriz = LAST_INSERT_ID();

-- 6. examen_matriz_pregunta (preguntas de la evaluación)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(@nueva_matriz, 1, 'Pregunta 1', 'numero', '0-10'),
(@nueva_matriz, 2, 'Pregunta 2', 'texto', 'N/A'),
(@nueva_matriz, 3, 'Pregunta 3', 'numero', '0-10');
```

**Nota:** Si la carrera NO hace examen privado (ej: curso de actualización), omitir los pasos 4, 5 y 6.

---

## 7. Flujo de uso (Administrador)

1. **Iniciar sesión** como Director o Asistente
2. Ir a **Módulo de Graduación → Evaluación Examen Privado**
3. Ver listado de estudiantes con examen privado **notificado** (paso 4 completado)
4. Presionar **"Evaluar"** en el estudiante deseado
5. El sistema detecta automáticamente la matriz según la **carrera** del estudiante
6. Escribir el **"Tema de tesis"** y guardar
7. Llenar la evaluación de cada **examinador** (tab 1, 2, 3)
8. Presionar **"Ver"** para ver el resumen comparativo

---

## 8. Notas técnicas

- **Cantidad de preguntas variable:** Cada matriz puede tener diferente cantidad de preguntas (ej: 5, 6, 7, etc.)
- **Tipo de campo:** `numero` (input number 0-10/0-20) o `texto` (textarea)
- **Sin promedio:** No se calcula promedio automático. Solo se guardan las respuestas individuales.
- **Tema de tesis:** Se guarda en `examen_proceso.tema_tesis` y es editable desde el formulario de evaluación
- **Vinculación por cod_carrera:** El sistema usa `cod_carrera` del estudiante para buscar en `examen_matriz_tipo`. No depende del nombre de la carrera.

---

## 9. Dependencias del módulo

- Requiere que el módulo de graduación base esté implementado (tablas `examen_proceso`, `examen_tipo`, `examen_paso_catalogo`, etc.)
- Requiere que el paso 4 del examen privado esté completado para que el proceso aparezca en el listado
- Requiere que el estudiante tenga una carrera asignada en `asignacion_carrera`

---

## 10. Troubleshooting

### "No se encontró una matriz de evaluación configurada para esta maestría"
- Verificar que `examen_matriz_tipo` tenga un registro con el `cod_carrera` del estudiante
- Verificar que la carrera del estudiante esté activa en `nombre_carrera`

### "Unknown column 'ep.tema_tesis'"
- El script SQL no se ejecutó. Aplicar `database/matriz_evaluacion_completo.sql`

### "No hay procesos disponibles para evaluación"
- El paso 4 del examen privado debe estar completado (`examen_proceso_paso.estado = 'completado'`)
- El proceso debe estar en fase `examen_privado` o ya haber pasado a `carta_examinadores`

### "No se puede acceder al menú"
- Verificar que las acciones 150-153 estén insertadas en `accion` y `estado_accion`
- Verificar que el rol del usuario esté asignado a esas acciones
