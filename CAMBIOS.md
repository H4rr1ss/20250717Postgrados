# Aca se registraran los cambios a tener en cuenta al desarrollar las nuevas funcionalidades

Se agrego una nueva acción, "Recupeción de contraseña"
'''
INSERT INTO accion (cod_accion, nombre) VALUES (67, 'Recuperar contraseña');
'''

Módulo de Graduación — Paso 5 "Carta de Examinadores" (2026-05-15).
Modelo: el seguimiento de correcciones se hace por correo externo; en la
plataforma solo se guardan observaciones por ciclo y evidencias (capturas
de correos). No se almacena el trabajo de graduación corregido.
Scripts: `database/modulo_graduacion_carta_01_schema.sql` y
`database/modulo_graduacion_carta_02_seeds.sql`. Acciones agregadas:
```
INSERT INTO accion (cod_accion, nombre) VALUES
  (68, 'Ver paso de carta de examinadores'),
  (69, 'Registrar correcciones al trabajo de graduación'),
  (70, 'Adjuntar evidencia de correo'),
  (71, 'Aprobar trabajo de graduación'),
  (72, 'Descargar carta de examinadores'),
  (73, 'Abrir nuevo ciclo de revisión'),
  (74, 'Eliminar evidencia de correo');
```

Módulo de Graduación — Paso 6 "Autorización de Impresión del Proyecto"
(2026-01-16). Fase nueva insertada entre `carta_examinadores` y
`examen_general`. Recursos GLOBALES gestionados por el director
(instrucciones, documentos de soporte, profesionales calificados,
cartas .docx y junta directiva) más el estado por proceso (profesional
elegido por el estudiante, confirmación de descargas y aprobación
presencial del director).

Scripts SQL (ejecutar en orden):
- `database/modulo graduacion/modulo_autorizacion_impresion_schema.sql`
- `database/modulo graduacion/inserts_iniciales/profesionales_calificados_seed.sql`
- `database/modulo graduacion/inserts_iniciales/junta_directiva_seed.sql` (opcional)

El schema extiende el ENUM `examen_paso_catalogo.fase` para incluir
`autorizacion_impresion`, inserta el paso 6 y crea 6 tablas nuevas:
`examen_autorizacion_config`, `examen_autorizacion_documento_soporte`,
`examen_profesional_calificado`, `examen_carta_descarga`,
`examen_junta_directiva`, `examen_autorizacion_proceso`.

Archivos físicos: `public/archivos/autorizacion-impresion/documentos-soporte/`
y `public/archivos/autorizacion-impresion/cartas-descarga/` (nombre `<md5>.<ext>`,
propietario `www-data`).

Flujo modificado: `CartaExaminadoresManager::aprobarTrabajo` ya no cierra
el proceso al aprobar el paso 5; avanza al paso 6. `ExamenManager::avanzarPaso`
ahora rutea `carta_examinadores → autorizacion_impresion → examen_general`.

Permisos / acciones agregadas:
```
INSERT INTO accion (cod_accion, nombre) VALUES
  (110, 'Ver módulo Autorización de Impresión'),
  (111, 'Configurar autorización de un proceso'),
  (112, 'Guardar instrucciones de autorización'),
  (113, 'Subir documento de soporte'),
  (114, 'Eliminar documento de soporte'),
  (115, 'Descargar documento de soporte'),
  (116, 'Guardar profesional calificado'),
  (117, 'Eliminar profesional calificado'),
  (118, 'Subir carta para descarga'),
  (119, 'Eliminar carta para descarga'),
  (120, 'Descargar carta tipo'),
  (121, 'Guardar miembro de junta directiva'),
  (122, 'Eliminar miembro de junta directiva'),
  (123, 'Aprobar revisión presencial (avanzar a Examen General)'),
  (130, 'Ver paso 6 (estudiante)'),
  (131, 'Seleccionar profesional calificado (estudiante)'),
  (132, 'Confirmar descargas de autorización (estudiante)');
```

## Nueva dependencia Composer: `ezyang/htmlpurifier` (2026-05-16)

Se instaló `ezyang/htmlpurifier ^4.19` para sanitizar HTML en el
módulo de Autorización de Impresión (`AutorizacionImpresionManager`).

```bash
docker compose exec web composer require ezyang/htmlpurifier
```

Versión instalada: v4.19.0. El `composer.json` y `composer.lock` fueron
actualizados automáticamente.

## Módulo de Evaluación Docente (2026-05-17)

Implementación de evaluación docente por curso finalizado. El estudiante evalúa
al catedrático de cada curso que culminó mediante un cuestionario anónimo con
3 tipos de preguntas: escala 1-10, booleano (Sí/No) y texto libre. Las secciones
y preguntas se administran únicamente por scripts SQL (sin vista de admin).

Script SQL: `database/evaluacion_docente.sql` (tablas + seeds + acciones).
Tablas creadas:
- `evaluacion_seccion` — secciones del cuestionario.
- `evaluacion_pregunta` — preguntas con tipo (`escala10`, `boolean`, `texto`).
- `evaluacion_respuesta` — registro maestro por estudiante y horario.
- `evaluacion_respuesta_detalle` — respuestas individuales.

Archivos PHP nuevos/modificados:
- `module/Eep/src/Service/EvaluacionDocenteManager.php`
- `module/Eep/src/Service/Factory/EvaluacionDocenteManagerFactory.php`
- `module/Eep/src/Controller/EvaluacionDocenteController.php`
- `module/Eep/src/Controller/Factory/EvaluacionDocenteControllerFactory.php`
- `module/Eep/config/module.config.php` (ruta `evaluacion-docente`)
- `module/Eep/config/menus.php` (menú estudiante)
- `module/Eep/config/access_filter.php` (permisos estudiante)
- `module/Eep/view/eep/evaluacion-docente/*.phtml`

Acciones agregadas:
```
INSERT INTO accion (cod_accion, nombre) VALUES
  (140, 'Ver evaluación docente (pendientes)'),
  (141, 'Ver formulario de evaluación docente'),
  (142, 'Guardar evaluación docente'),
  (144, 'Ver confirmación de evaluación docente');
```

Nota: La acción 143 (Ver historial) fue eliminada en la Fase 3.

## Fase 3 — Filtro por período y cohorte, eliminación de historial (2026-05-18)

Ajustes al módulo de evaluación docente para mostrar solo evaluaciones del período
reciente y eliminar la funcionalidad de historial:

1. **Filtro temporal**: Solo se muestran cursos que terminaron en meses anteriores
   al actual (`h.fecha_fin < DATE_FORMAT(CURDATE(), "%Y-%m-01")`). Esto evita que
   aparezcan todas las evaluaciones históricas de una vez.

2. **Filtro por cohorte**: Se agregó JOIN con `asignacion_carrera` para obtener la
   cohorte actual del estudiante. Al cambiar de cohorte, las evaluaciones pendientes
   de la cohorte anterior ya no bloquean la asignación.

3. **Eliminación de historial**: Se eliminó la vista de historial de evaluaciones
   (action `historial`, vista `historial.phtml`, acción 143). El método
   `getHistorial()` se mantiene en el manager para uso interno/reportes.

4. **Actualización de mensajes**: Se actualizaron las vistas para informar que las
   evaluaciones aparecen automáticamente para cursos del mes anterior.

Archivos modificados:
- `module/Eep/src/Service/EvaluacionDocenteManager.php`
  - `getCursosPendientes()`: Agregados filtros por período y cohorte
  - `getResumenEstudiante()`: Actualizado para usar consulta directa sin historial
- `module/Eep/src/Controller/EvaluacionDocenteController.php`
  - Eliminado método `historialAction()`
- `module/Eep/view/eep/evaluacion-docente/index.phtml`
  - Actualizado mensaje informativo sobre disponibilidad de evaluaciones
- `module/Eep/view/eep/evaluacion-docente/confirmacion.phtml`
  - Eliminado panel de "Tu Participación" basado en historial
  - Agregado mensaje sobre requisito para asignar cursos
- `module/Eep/config/access_filter.php`
  - Eliminada entrada 'historial' (código 143)
- `database/evaluacion_docente.sql`
  - Eliminada inserción de acción 143

Archivos eliminados:
- `module/Eep/view/eep/evaluacion-docente/historial.phtml`

## Fase 2 — Bloqueo de asignación por evaluaciones pendientes (2026-05-17)

Implementación del bloqueo en `AssignmentController::assignmentAction()`: antes de
mostrar el formulario de asignación de cursos, se verifica si el estudiante tiene
cursos finalizados sin evaluar. Si los hay, se redirige a `evaluacion-docente`
con un mensaje flash.

Archivos modificados:
- `module/Eep/src/Controller/AssignmentController.php`
  - Constructor actualizado para recibir `EvaluacionDocenteManager`.
  - `assignmentAction()` ahora bloquea cuando hay evaluaciones pendientes.
  - Se eliminó el código viejo de evaluación que estaba embebido en este
    controller (métodos `formatEvaluationCourses`, `getEvaluationQuestions`,
    `getEvaluationHistory`).
- `module/Eep/config/module.config.php`
  - `AssignmentController` ahora usa `AssignmentControllerFactory` en lugar de
    `LazyControllerAbstractFactory`.
- `module/Eep/src/Controller/Factory/AssignmentControllerFactory.php` (nuevo)
  - Inyecta `EvaluacionDocenteManager` junto con el resto de dependencias.

### Scripts de prueba / QA (actualizados 2026-05-18)

**Script maestro recomendado:**

`database/demo_evaluacion_docente.sql`
- Propósito: setup completo de un usuario de prueba para el módulo de
  Evaluación Docente. Crea todo lo necesario en un solo paso.
- El script realiza automáticamente:
  1. Busca un `horario` ya finalizado (`fecha_fin <= CURDATE()`) donde otro
     estudiante esté asignado.
  2. Inserta `asignacion_carrera` (requerido por FK de `inscripcion`).
  3. Inserta `asignacion` del usuario al horario encontrado.
  4. Inserta `inscripcion` para el **año del curso** (evita la "P" en
     Cursos Asignados).
  5. Inserta `inscripcion` para el **año actual** (evita que el sistema
     consulte el SOAP de Registro y Estadística, que no está disponible en dev).
  6. Muestra verificación final con estado de inscripción y evaluación.
- Uso:
  ```bash
  # 1. Schema del módulo (solo la primera vez)
  docker exec -i <contenedor-mysql> mysql -u user -ppassword db_postgrados < database/evaluacion_docente.sql

  # 2. Setup del usuario de prueba (repetir por cada usuario nuevo)
  docker exec -i <contenedor-mysql> mysql -u user -ppassword db_postgrados < database/demo_evaluacion_docente.sql
  ```
- Cambiar `SET @usuario_test = 3530;` al `cod_usuario` deseado antes de ejecutar.
- Si el usuario ya tiene asignaciones previas, el script las conserva y solo
  agrega lo que falta.

**Scripts intermedios (obsoletos):**
Los siguientes scripts fueron reemplazados por `demo_evaluacion_docente.sql`.
Consérvelos solo como referencia histórica:
- `database/evaluacion_docente_test_data.sql`
- `database/inscribir_usuario_rye.sql`
- `database/fix_inscripcion_evaluacion_docente.sql`
- `database/diagnostico_evaluacion_docente.sql`
- `database/diagnostico_completo_inscripcion.sql`
- `database/inscripcion_anio_actual.sql`
- `database/limpiar_asignacion_carrera.sql`
- `database/quitar_pensum_24001.sql`

## Fix: Columnas de usuario en EvaluacionDocenteManager (2026-05-18)

Corregido el JOIN con tabla `usuario` en `EvaluacionDocenteManager`:
la estructura real usa `nombres` y `apellidos` (no `nombre1`, `nombre2`,
`apellido1`, `apellido2`). Esto generaba un error SQL silencioso que hacía
que `getCursosPendientes()` y `getHistorial()` retornaran `failure`.
Archivo afectado: `module/Eep/src/Service/EvaluacionDocenteManager.php`.

## Reporte de Evaluación Docente para Director (2026-05-27)

Nueva funcionalidad para que el director descargue un reporte CSV con los
resultados agregados de la evaluación docente. El reporte se organiza por
docente y curso (horario), mostrando promedios de escalas, porcentajes de
respuestas Sí/No y comentarios textuales (anónimos).

Archivos nuevos/modificados:
- `module/Eep/src/Service/EvaluacionDocenteManager.php`
  - `getPeriodosEvaluacion()`: devuelve años/mes distintos con evaluaciones.
  - `getReportePorDocente($anio, $mes)`: genera el reporte agrupado.
- `module/Eep/src/Controller/EvaluacionDocenteController.php`
  - `reporteDocenteAction()`: vista con filtros de período y tabla resumen.
  - `descargarReporteDocenteAction()`: genera y descarga CSV con BOM UTF-8.
- `module/Eep/view/eep/evaluacion-docente/reporte-docente.phtml` (nueva)
- `module/Eep/config/access_filter.php`
  - Acciones 145 y 146 agregadas (rol DIRECTOR).
- `module/Eep/config/menus.php`
  - Menú "Reporte Evaluación Docente" agregado para DIRECTOR.
- `module/Eep/src/ValueObject/View.php`
  - Constante `EVALUACION_DOCENTE_REPORTE = 30` agregada.
- `database/evaluacion_docente.sql`
  - Acciones 145 y 146 documentadas.

Acciones agregadas:
```
INSERT INTO accion (cod_accion, nombre) VALUES
  (145, 'Ver reporte de evaluación docente'),
  (146, 'Descargar reporte de evaluación docente');
```

Nota: ejecutar los INSERT anteriores manualmente en la base de datos si el
schema ya está aplicado.

## Simplificación de Carta de Examinadores — sin autorelleno de placeholders (2026-05-30)

Se eliminó el reemplazo automático de placeholders en la generación de la carta de examinadores. Ahora la plantilla `.docx` se copia tal cual y se descarga para que el estudiante o el staff completen los datos manualmente.

### Motivación
La coordinación requiere flexibilidad para ajustar nombres, colegiados y demás información directamente en el documento, sin depender de la estructura rígida de placeholders predefinidos.

### Archivos modificados
- `module/Eep/src/Service/CartaGenerator.php`
  - Eliminada la dependencia `PhpOffice\PhpWord\TemplateProcessor` y toda la lógica de reemplazo de placeholders.
  - Eliminados los métodos privados `getDatosProceso()`, `construirValores()` y `getNombreUsuarioGenerador()`.
  - Agregado método privado `getTipoExamenDeProceso()` (consulta mínima para resolver la plantilla).
  - El método `generar()` ahora usa `copy()` para duplicar la plantilla sin alterar su contenido.

### Uso
1. El staff sube una plantilla `.docx` a la carpeta deseada (por ejemplo `data/graduacion/plantillas/carta-examinadores/mi-plantilla.docx`).
2. Registra la plantilla en `examen_carta_plantilla` vía SQL.
3. Al presionar **"Aprobar trabajo y generar carta"**, el sistema registra en `examen_carta_examinadores` que la carta fue generada, pero **no copia el archivo** a la carpeta del proceso.
4. El estudiante descarga la **plantilla original** directamente y la completa manualmente en Word.

### Nota técnica
Al no personalizar la carta automáticamente, no es necesario duplicar la plantilla por cada proceso. El campo `archivo_generado` en `examen_carta_examinadores` ahora apunta directamente a la ruta de la plantilla original registrada en `examen_carta_plantilla`.

## Instrucciones para entrega de documentos físicos — columna en `examen_tipo` (2026-05-30)

Se agregó la columna `instrucciones_entrega_fisica` a `examen_tipo` para permitir que la coordinación escriba instrucciones generales sobre documentos de vigencia corta o restricciones especiales para la entrega física.

### Cambios en base de datos
```sql
ALTER TABLE examen_tipo
ADD COLUMN instrucciones_entrega_fisica TEXT NULL
COMMENT 'Instrucciones generales para entrega de documentos fisicos (vigencias, restricciones, etc.)';
```
Script actualizado: `database/modulo graduacion/modulo_graduacion.sql`.

### Archivos PHP modificados
- `module/Eep/src/Service/ExamenManager.php`
  - `getInstruccionesEntregaFisica()` — lee las instrucciones por tipo de examen.
  - `guardarInstruccionesEntregaFisica()` — guarda las instrucciones por tipo de examen.
- `module/Eep/src/Service/StudentGraduationManager.php`
  - `getInstruccionesEntregaFisica()` — lee las instrucciones para mostrarlas al estudiante.
- `module/Eep/src/Controller/ExamenController.php`
  - `papeleriaAction()` — pasa las instrucciones a la vista de gestión de papelería.
  - `guardarInstruccionesAction()` — endpoint AJAX para guardar instrucciones.
  - `solicitudesAction()` — pasa las instrucciones a la vista de revisión paso 2.
- `module/Eep/src/Controller/StudentGraduationController.php`
  - `paso1SolicitudExamenAction()` — pasa las instrucciones a la vista del estudiante en paso físico.

### Vistas modificadas
- `module/Eep/view/eep/examen/papeleria.phtml` — textarea con botón "Guardar instrucciones" debajo de la tabla de requisitos.
- `module/Eep/view/eep/examen/partial/paso2-documentacion.phtml` — muestra recuadro amarillo con instrucciones especiales en el paso 2 de revisión.
- `module/Eep/view/eep/examen/revisarpapeleria.phtml` — pasa la variable `instruccionesEntrega` al partial.
- `module/Eep/view/eep/student-graduation/partial/paso1-solicitud-examen.phtml` — muestra recuadro amarillo con instrucciones especiales en la vista del estudiante durante entrega física.

### Permisos
- `module/Eep/config/access_filter.php` — agregada acción `guardarInstrucciones` (código 108) para roles DIRECTOR y ASISTENTE.

### Uso
El administrador accede a *Gestión de Exámenes > Requisitos de Papelería*, edita el tipo de examen deseado y escribe en el panel inferior las instrucciones que el estudiante debe seguir para la entrega física de documentos (por ejemplo: vigencia de órdenes de pago, documentos que no deben digitalizarse, etc.).

## Fix: Visualización de evidencias en bitácora (paso 5) — error 404 (2026-05-30)

Las evidencias subidas a la bitácora del paso 5 (Carta de Examinadores) daban error 404 al intentar visualizarlas porque las vistas apuntaban a la ruta directa `/archivos/{md5}.{ext}`, pero los archivos se almacenan fuera de la web root en `data/graduacion/procesos/{cod_proceso}/`.

### Archivos modificados
- `module/Eep/src/Service/StudentGraduationManager.php`
  - `getArchivoByHash()` — ahora busca primero en `archivo_local` (documentos del paso 1) y, si no encuentra, realiza un fallback a `examen_correccion_evidencia` (evidencias de la bitácora del paso 5).
- `module/Eep/src/Controller/StudentGraduationController.php`
  - `subirEvidenciaAction()` — revirtió ruta de subida a `data/graduacion/procesos/{cod_proceso}/`.
  - `eliminarEvidenciaAction()` — revirtió ruta de borrado a `data/graduacion/procesos/{cod_proceso}/`.
- `module/Eep/view/eep/student-graduation/partial/paso5-carta-examinadores.phtml`
  - Los links de imagen y botones "Ver" ahora apuntan a `/student-graduation/ver-documento?h={md5}` en lugar de `/archivos/{md5}.{ext}`.
- `module/Eep/view/eep/examen/partial/paso5-carta-examinadores.phtml`
  - Idem para la vista del staff (admin).

### Nota técnica
El action `verDocumentoAction` ya servía archivos de forma segura leyendo de `data/graduacion/procesos/`; solo faltaba que las vistas de bitácora apuntaran a él en lugar de intentar acceso directo al directorio público.

## Archivo de apoyo por requisito de papelería (2026-05-30)

Se agregó la posibilidad de adjuntar un **documento de apoyo** (formulario, instructivo, etc.) a cada requisito de papelería. El estudiante puede descargarlo directamente desde la tarjeta del documento antes de subir su archivo.

### Cambios en base de datos
```sql
ALTER TABLE examen_requisito_documento
ADD COLUMN archivo_apoyo VARCHAR(255) NULL
COMMENT 'Ruta relativa al archivo de apoyo (formulario, instructivo, etc.)';
```
Script actualizado: `database/modulo graduacion/modulo_graduacion.sql`.

### Archivos PHP modificados
- `module/Eep/src/Service/ExamenManager.php`
  - `getDocumentosYRequisitos()` — incluye `archivo_apoyo` en el SELECT.
  - `getRequisitosDocumento()` — incluye `archivo_apoyo` en el SELECT.
  - `getTodosRequisitos()` — incluye `archivo_apoyo` en el SELECT.
  - `upsertRequisito()` — soporta guardar/actualizar `archivo_apoyo` de forma condicional.
- `module/Eep/src/Controller/ExamenController.php`
  - `guardarRequisitoAction()` — procesa `$_FILES['archivo_apoyo']`, valida formato (pdf, doc, docx, jpg, png) y tamaño (10 MB), mueve el archivo a `public/archivos/requisitos-apoyo/` y guarda la ruta relativa.

### Vistas modificadas
- `module/Eep/view/eep/examen/papeleria.phtml`
  - Modal de creación/edición: nuevo campo file input "Archivo de apoyo (opcional)" con enlace al archivo actual en modo edición.
  - Tabla de requisitos: ícono de clip (`fa-paperclip`) cuando un requisito tiene archivo adjunto.
  - JavaScript: usa `FormData` + `$.ajax` para enviar el archivo vía POST.
- `module/Eep/view/eep/student-graduation/partial/paso1-solicitud-examen.phtml`
  - En la tarjeta de cada documento, si el requisito tiene `archivo_apoyo`, aparece un recuadro azul con botón **"Descargar formulario"**.

### Uso
En *Gestión de Exámenes > Requisitos de Papelería*, al crear o editar un requisito se puede subir opcionalmente un archivo de apoyo. El estudiante lo verá destacado en su vista de solicitud de examen, facilitando la entrega de formularios oficiales.
