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

## Sistema de notificaciones por correo electrónico (2026-06-01)

Se implementó el envío automático de correos HTML durante el flujo de graduación usando Gmail SMTP.

### Nueva dependencia Composer

```bash
composer require zendframework/zend-mail
```

`composer.json` actualizado con `"zendframework/zend-mail": "^2.10"`.

### Archivos nuevos

- `module/Eep/src/Service/MailManager.php` — Servicio centralizado para envío de correos HTML con:
  - Footer automático con imagen inline (`cid:footer-image`).
  - Soporte para CC (copia a examinadores en paso 4).
  - Envío asíncrono vía `register_shutdown_function` para no bloquear HTTP.
- `module/Eep/src/Service/Factory/MailManagerFactory.php` — Factory que inyecta configuración SMTP y ruta de la imagen del footer.

### Archivos modificados

- `module/Eep/src/Controller/ExamenController.php`
  - `iniciarProcesoAction()` — notifica al estudiante cuando se inicia el proceso.
  - `guardarRevisionAction()` — envía resumen de revisión de papelería (Paso 1).
  - `guardarDocFisicoAction()` — notifica cuando se completa la entrega física (Paso 2).
  - `notificarEstudianteAction()` — envía notificación final con datos de la terna (Paso 4).
- `module/Eep/src/Controller/StudentGraduationController.php`
  - `aprobarTrabajoAction()` — notifica al estudiante cuando el director aprueba el trabajo y genera la carta de examinadores.

### Configuración requerida

Crear o editar `config/autoload/local.php`:

```php
return [
    'smtp' => [
        'host'             => 'smtp.gmail.com',
        'port'             => 587,
        'connection_class' => 'login',
        'connection_config' => [
            'username' => 'tucorreo@gmail.com',
            'password' => 'tu-app-password',
            'ssl'      => 'tls',
        ],
        'from'      => 'tucorreo@gmail.com',
        'from_name' => 'Coordinación de Postgrados',
    ],
];
```

> **Nota:** En producción usar una contraseña de aplicación (App Password) de Google, no la contraseña normal de la cuenta.

### Archivo de imagen

- `public/img/email-footer.jpg` — Imagen del pie de página que se incluye inline en todos los correos. Debe existir en producción.

### Asuntos de correo por fase

Los asuntos incluyen la fase actual para diferenciar entre examen privado y público:

- `Proceso de Graduacion Iniciado - Examen Privado`
- `Revision de Papeleria - Resultado ... - Examen General`
- `Documentacion fisica completada ... - Examen Privado`
- `Notificacion Examen de Graduacion - Examen General`
- `Trabajo de Graduacion Aprobado ... - Carta De Examinadores`

## Matriz de Evaluación del Examen Privado (2026-06-03)

Implementación de la evaluación del examen privado por examinador. El staff registra las calificaciones de cada uno de los 3 examinadores de la terna después de que el paso 4 (notificación) del examen privado fue completado.

**Características:**
- Cada maestría/especialización/doctorado tiene su propia matriz de preguntas (cantidad variable)
- Preguntas de tipo `numero` (0-10, 0-20) o `texto` (observaciones libres)
- Campo "Tema de tesis" en `examen_proceso`
- Resumen comparativo de las 3 evaluaciones
- Mantenimiento de matrices vía SQL directo (sin pantalla de CRUD)

**Archivos nuevos:**
- `database/matriz_evaluacion_completo.sql` — Schema + 20 matrices con preguntas de prueba
- `database/matriz_evaluacion_v2_migracion.sql` — Migración incremental si ya se ejecutó el v1
- `database/DOCUMENTACION_MATRIZ_EVALUACION.md` — Documentación completa
- `module/Eep/view/eep/examen/evaluacion-privado.phtml` — Listado de procesos evaluables
- `module/Eep/view/eep/examen/matriz-evaluacion.phtml` — Formulario de evaluación
- `module/Eep/view/eep/examen/ver-matriz.phtml` — Resumen comparativo

**Archivos modificados:**
- `module/Eep/src/Service/ExamenManager.php` — Métodos de matriz y tema de tesis
- `module/Eep/src/Controller/ExamenController.php` — Acciones evaluacionPrivado, verMatriz
- `module/Eep/src/ValueObject/View.php` — Constante EVALUACION_PRIVADO = 31
- `module/Eep/config/menus.php` — Menú "Evaluación Examen Privado"
- `module/Eep/config/access_filter.php` — Acciones 150, 153

**Tablas nuevas:**
- `examen_matriz_tipo` — Catálogo de matrices vinculadas por `cod_carrera`
- `examen_matriz_pregunta` — Preguntas por matriz (tipo numero/texto, cantidad variable)
- `examen_matriz_evaluacion` — Cabecera por examinador (1, 2, 3)
- `examen_matriz_respuesta` — Respuestas individuales

**Columna agregada:**
- `examen_proceso.tema_tesis` — Tema del trabajo de graduación

**Acciones agregadas:**
```sql
INSERT INTO accion (cod_accion, nombre) VALUES
  (150, 'Ver listado de evaluaciones de examen privado'),
  (153, 'Ver resumen de evaluación');
```

**Nota:** Las acciones 151 y 152 fueron eliminadas posteriormente (2026-06-06) porque el staff ya no edita evaluaciones; los examinadores lo hacen desde el link público.

**Nota:** Las 20 matrices cubren todas las maestrías, especializaciones y doctorado de la plataforma. Las preguntas de las 14 matrices nuevas son de prueba (genéricas) y deben ser reemplazadas en producción con las preguntas reales de cada maestría via SQL directo. Ver `database/DOCUMENTACION_MATRIZ_EVALUACION.md` para el instructivo de mantenimiento.

## Vinculación de examen_tipo con cod_carrera (2026-06-03)

Se agregó la columna `cod_carrera` a `examen_tipo` para vincular automáticamente cada tipo de examen privado con una carrera específica. Esto elimina la necesidad de mantener una lista manual de tipos de examen privado.

**Cambios en base de datos:**
- `ALTER TABLE examen_tipo ADD COLUMN cod_carrera INT UNSIGNED NULL;`
- Actualizados `cod_tipo_examen` 1 → cod_carrera 18 (Conservación) y 2 → cod_carrera 24 (Gerencia)
- Insertados 14 nuevos registros privados automáticamente desde `nombre_carrera`
- El examen público (cod_tipo_examen = 3) permanece con `cod_carrera = NULL`

**Archivos modificados:**
- `module/Eep/src/Service/ExamenManager.php` — `getTiposExamen()` ahora hace LEFT JOIN con `nombre_carrera` y devuelve `cod_carrera`
- `module/Eep/view/eep/examen/index.phtml` — Filtro de privados/públicos ahora usa `cod_carrera` en lugar de hardcoded `cod_tipo_examen = 3`
- `database/matriz_evaluacion_completo.sql` — Agregado el ALTER + UPDATE + INSERT de `examen_tipo`
- `database/DOCUMENTACION_MATRIZ_EVALUACION.md` — Actualizada sección de vinculación con carreras

**Resultado:** 18 registros en `examen_tipo` (17 privados + 1 público), cada privado vinculado a una carrera activa. El doctorado (cod_carrera 80) también fue incluido ya que tiene matriz de evaluación.

**Scripts eliminados (consolidados en el script único):**
- `database/matriz_evaluacion_schema.sql` — Reemplazado por `matriz_evaluacion_completo.sql`
- `database/matriz_evaluacion_v2_migracion.sql` — Reemplazado por `matriz_evaluacion_completo.sql`
- `database/migracion_examen_tipo_carrera.sql` — Reemplazado por `matriz_evaluacion_completo.sql`

## Proceso para agregar una nueva carrera (2026-06-05)

No hay panel de administración para crear carreras. El proceso es por SQL directo, siguiendo el orden de las claves foráneas.

**Nuevo script de referencia:** `database/nueva_carrera_ejemplo.sql`

**Orden de inserción obligatorio:**
1. `carrera` — tabla base (nombre_actual, alias_actual, cod_grado)
2. `nombre_carrera` — nombre histórico con tiempo y flag activo
3. `pensum` — plan de estudios (requerido para que los estudiantes puedan asignar cursos)
4. `examen_tipo` — tipo de examen privado vinculado a la carrera (aparece automáticamente en "Gestión de Exámenes")
5. `examen_matriz_tipo` — matriz de evaluación del examen privado
6. `examen_matriz_pregunta` — preguntas específicas de la evaluación

**Archivos actualizados:**
- `database/DOCUMENTACION_MATRIZ_EVALUACION.md` — Sección 6.5 reescrita con el proceso completo
- `database/nueva_carrera_ejemplo.sql` — Script de ejemplo paso a paso

**Nota:** Si la carrera no hace examen privado (ej: curso de actualización), se omiten los pasos 4, 5 y 6.

## Traslado de "Tema de Tesis" a vista del estudiante — 2026-06-06

**Cambio:** El campo "Tema del Trabajo de Graduación" se eliminó de la vista de matriz de evaluación (admin) y se movió a la vista del estudiante (`student-graduation/index`).

**Nueva ubicación:** Tabla con columnas "Actividad" y "Definición" arriba del "Resumen del Proceso".
- **Actividad:** "Título de trabajo de graduación"
- **Definición:** Input editable (solo en fase 1) o campo de solo lectura

**Reglas de negocio:**
1. **Primer paso obligatorio:** El estudiante debe registrar el título antes de continuar con cualquier otra actividad.
2. **Bloqueo:** Si no hay título, se muestra alerta roja y el proceso está bloqueado.
3. **Edición limitada:** Solo editable cuando el estudiante está en fase 1 (`examen_privado`, paso 1).
4. **Solo lectura:** Después de la fase 1, el título se muestra como solo lectura.

**Archivos modificados:**
- `module/Eep/src/Service/StudentGraduationManager.php` — `getProcesoEstudiante()` ahora incluye `tema_tesis`; nuevo método `guardarTemaTesis()`
- `module/Eep/src/Controller/StudentGraduationController.php` — Nuevo `guardarTemaTesisAction()`
- `module/Eep/view/eep/student-graduation/index.phtml` — Tabla de tema de tesis + lógica de bloqueo
- `module/Eep/view/eep/examen/matriz-evaluacion.phtml` — Eliminado el campo de tema de tesis
- `module/Eep/config/access_filter.php` — Acción 154 (`guardarTemaTesis`) para rol ESTUDIANTE

## Refactorización de arquitectura: examen_examinador (Catálogo de Examinadores) — 2026-06-06

**Problema:** La tabla `examen_terna` almacenaba `nombre_examinador`, `numero_colegiado`, `correo` y `tipo_examinador` directamente. Esto duplicaba datos cuando un docente interno era examinador de múltiples estudiantes.

**Solución (Opción B):** Se creó una tabla de catálogo `examen_examinador` que almacena los datos del examinador una sola vez, y `examen_terna` solo guarda la referencia (`cod_examinador`) + `posicion` + `registrado_por`.

### Nueva tabla: `examen_examinador`
- `cod_examinador` PK
- `cod_usuario` INT NULL (solo internos, UNIQUE, FK a `usuario`)
- `nombre_examinador`, `numero_colegiado`, `correo` NULL (para internos se resuelven con JOIN)
- `tipo_examinador` ENUM('interno', 'externo')

### Cambios en `examen_terna`
- Eliminados: `nombre_examinador`, `numero_colegiado`, `correo`, `tipo_examinador`
- Agregado: `cod_examinador` INT NOT NULL (FK a `examen_examinador`)
- Solo guarda: `cod_proceso`, `fase`, `cod_examinador`, `posicion`, `registrado_por`

### UX: Selección de examinador interno/externo
- **Interno:** Dropdown con docentes de la plataforma (`usuario_rol` con `cod_rol = 5`). Nombre, colegiado y correo se precargan automáticamente (solo lectura).
- **Externo:** Inputs manuales de nombre, colegiado, correo.

### Archivos modificados
- `database/modulo graduacion/modulo_graduacion_schema.sql` — Schema actualizado con `examen_examinador` y `examen_terna` refactorizada
- `database/migracion_examen_examinador.sql` — Migración de datos existentes
- `module/Eep/src/Service/ExamenManager.php` — `getTerna()`, `guardarTerna()`, `getDocentes()`, `buscarOCrearExaminador()`
- `module/Eep/src/Service/StudentGraduationManager.php` — `getTerna()` con JOIN
- `module/Eep/src/Controller/ExamenController.php` — Validaciones y paso de docentes al ViewModel
- `module/Eep/view/eep/examen/partial/paso3-terna.phtml` — UI con select de tipo + dropdown de docentes
- `module/Eep/view/eep/examen/ver-matriz.phtml` — Adaptado a la nueva estructura
- `module/Eep/view/eep/examen/matriz-evaluacion.phtml` — Adaptado a la nueva estructura

### Resultado
- **Cero duplicación** para examinadores internos
- **100% reutilización** del catálogo
- **145 docentes** disponibles para selección como examinadores internos

## Panel de Evaluación y Link Genérico para Examinadores — 2026-06-06

**Cambio:** El staff abre la evaluación desde el panel, genera un link genérico con código de 8 dígitos que comparte con los 3 examinadores (internos y externos). Cada examinador selecciona su nombre y completa la evaluación de forma independiente.

### Nuevas columnas en `examen_proceso`
- `codigo_evaluacion` — Código numérico de 8 dígitos para acceso de examinadores (ej: `86914714`)
- `hora_apertura_evaluacion` / `hora_cierre_evaluacion` — Control de tiempo (la evaluación está abierta si `hora_cierre_evaluacion` IS NULL)
- `ex1_completado` / `ex2_completado` / `ex3_completado` — Estado por examinador

### Nuevas acciones
```sql
INSERT INTO accion (cod_accion, nombre) VALUES
  (154, 'Guardar tema de tesis'),
  (156, 'Abrir evaluación de examen privado'),
  (157, 'Cerrar evaluación de examen privado'),
  (158, 'Evaluar examen privado (página pública)'),
  (159, 'Guardar evaluación de examinador');
```

### Nuevas rutas
- `/eval-privado/:cod_proceso?cod=12345678` — Página pública de evaluación (sin login)

### Archivos modificados
- `module/Eep/src/Service/ExamenManager.php` — `abrirEvaluacion()`, `cerrarEvaluacion()`, `validarToken()`, `getEstadoEvaluacion()`, `getTernaParaEvaluacion()`
- `module/Eep/src/Controller/ExamenController.php` — `evaluacionPrivadoAction()` (panel), `abrirEvaluacionAction()`, `cerrarEvaluacionAction()`, `evaluacionExamenPrivadoAction()`, `guardarEvaluacionExaminadorAction()`
- `module/Eep/view/eep/examen/evaluacion-privado.phtml` — Panel con estados de los 3 examinadores y botones Abrir/Cerrar. **URL completa:** Ahora genera URLs absolutas (`http://localhost:8080/eval-privado/...`) tanto en el alert como en el modal para copiar el link.
- `module/Eep/view/eep/examen/evaluacion-examen-privado.phtml` — Página pública con instrucciones, select de examinador y matriz
- `module/Eep/config/module.config.php` — Ruta `eval-privado`
- `module/Eep/config/access_filter.php` — Acciones 156-159

## Bloqueo del botón "Resumen" sin tema de tesis — 2026-06-06

**Cambio:** El estudiante no puede acceder al paso de revisión de papelería (Resumen) hasta que haya registrado el título de su trabajo de graduación.

**Regla de negocio:** El tema de tesis es el primer paso obligatorio del proceso de graduación. Si el campo `tema_tesis` está vacío en `examen_proceso`, el botón "Resumen" del paso 1 (Papelería) aparece bloqueado con un candado rojo y el mensaje tooltip: "Debe registrar el título de su trabajo de graduación antes de acceder a este paso.".

**Archivos modificados:**
- `module/Eep/view/eep/student-graduation/index.phtml` — Lógica de `$bloqueadoPorTema` en el botón "Resumen" del paso 1 (papelería). Si no hay tema, se muestra botón gris con icono de candado y tooltip informativo.
- `module/Eep/src/Service/StudentGraduationManager.php` — `getProcesoEstudiante()` ya incluye `tema_tesis` en el SELECT (sin cambios, ya estaba disponible)

## Guardar Madrina/Padrino (estudiante en examen general) — 2026-07-14

Nueva acción para que el estudiante registre el nombre de la madrina/padrino durante el paso de solicitud del examen general público.

### Cambios en base de datos
```sql
-- madrina_padrino ahora reside en examen_proceso (schema base), eliminado de examen_acta_general
INSERT INTO accion (cod_accion, nombre) VALUES (169, 'Guardar madrina/padrino');
```

### Consolidación de campo (2026-07-14)
- **Origen:** Inicialmente se había colocado `madrina_padrino` en `examen_acta_general` con la idea de que el secretario/director lo definiera al generar el acta.
- **Corrección:** El campo se movió permanentemente a `examen_proceso` porque el estudiante ya lo registra durante la solicitud (paso 1). Al generar el acta, el valor se lee directamente del proceso y es **solo lectura**.
- **Schema actualizado:**
  - `examen_proceso` — incluye `madrina_padrino VARCHAR(255) DEFAULT NULL` (línea ~101).
  - `examen_acta_general` — ya **no** incluye `madrina_padrino`.

### Archivos PHP modificados
- `module/Eep/src/Controller/StudentGraduationController.php` — `guardarMadrinaPadrinoAction()` (nuevo endpoint AJAX), `paso1SolicitudExamenAction()` pasa `madrinaPadrino` al ViewModel.
- `module/Eep/src/Service/StudentGraduationManager.php` — `guardarMadrinaPadrino()` para actualizar `examen_proceso`.
- `module/Eep/view/eep/student-graduation/partial/paso1-solicitud-examen.phtml` — Panel "Datos de Madrina/Padrino" visible solo en fase `examen_general`.
- `module/Eep/view/eep/examen/acta-examen-general.phtml` — Campo `madrina_padrino` ahora es **solo lectura** (`form-control-static`) tomado desde `examen_proceso`.
- `module/Eep/src/Service/ExamenManager.php` — `getDatosActaGeneral()` obtiene `ep.madrina_padrino` directamente; `crearActaGeneral()` ya no inserta el campo en `examen_acta_general`.
- `module/Eep/src/Controller/ExamenController.php` — `generarActaGeneralAction()` ya no lee ni envía `madrina_padrino` desde el POST.
- `module/Eep/config/access_filter.php` — Acción 169 agregada para rol ESTUDIANTE.

## Eliminación de vista de edición de matriz (staff) — 2026-06-06

**Cambio:** El staff ya no puede editar la evaluación de los examinadores desde el panel. La evaluación solo se completa desde el link público por cada examinador individualmente.

**Archivos eliminados:**
- `module/Eep/view/eep/examen/matriz-evaluacion.phtml` — Vista de edición de matriz por parte del staff

**Archivos modificados:**
- `module/Eep/view/eep/examen/ver-matriz.phtml` — Eliminado el botón "Editar Evaluación" (solo queda "Volver al listado")
- `module/Eep/src/Controller/ExamenController.php` — Eliminados `matrizEvaluacionAction()` y `guardarMatrizAction()` (solo se usaban desde el panel de staff)
- `module/Eep/config/access_filter.php` — Eliminadas acciones 151 (`matrizEvaluacion`) y 152 (`guardarMatriz`)

## Campo Acuerdo de Decanato en acta general — 2026-07-14

Nuevo campo opcional `acuerdo_decanato` en `examen_acta_general` para registrar el número de acuerdo de decanato que autoriza el acto de graduación.

### Cambios en base de datos
```sql
ALTER TABLE examen_acta_general ADD COLUMN acuerdo_decanato VARCHAR(255) DEFAULT NULL COMMENT 'Número de acuerdo de decanato para el acta';
```

### Archivos PHP modificados
- `database/modulo graduacion/modulo_graduacion_schema.sql` — Agregado `acuerdo_decanato VARCHAR(255) DEFAULT NULL` al `CREATE TABLE examen_acta_general`.
- `module/Eep/src/Service/ExamenManager.php` — `guardarActaGeneral()` ahora inserta `acuerdo_decanato` en `examen_acta_general`.
- `module/Eep/src/Controller/ExamenController.php` — `generarActaGeneralAction()` recibe `acuerdo_decanato` desde POST y lo incluye en el array de guardado.
- `module/Eep/view/eep/examen/acta-examen-general.phtml` — Agregado input "Acuerdo de Decanato" en el formulario de generación del acta.

## Gráficas de evaluación docente en PDF — generación nativa con PHP GD (2026-08-20)

Reemplazo de las gráficas HTML/CSS "pixel art" por imágenes PNG generadas dinámicamente con PHP GD. Esto soluciona el problema de TCPDF colapsando celdas de tabla, haciendo que las gráficas de barras (escala 1–10) y pastel (Sí/No) se rendericen correctamente en el PDF descargable por el director.

### Cambios en infraestructura / Docker
- `docker/Dockerfile` — Se agregó `libfreetype6-dev` y `--with-freetype --with-jpeg` en la configuración de GD para soporte de fuentes TrueType (`imagettftext`).
- **Rebuild obligatorio:** `docker compose up -d --build web` para compilar GD con FreeType.
- **Fuente TTF:** Se copió `DejaVuSans.ttf` a `/var/www/data/fonts/DejaVuSans.ttf` dentro del contenedor (requerido para tildes y eñes en las gráficas PNG).

### Archivos nuevos
- `module/Eep/src/Service/EvaluacionDocenteGraficaService.php`
  - `generarGraficaEscala10(array $distribucion, $promedio): string` — PNG de barras con ejes etiquetados.
  - `generarGraficaBoolean(int $si, int $no, int $total): string` — PNG de pastel (pie chart) con leyenda.
  - `limpiarGraficas(array $paths): void` — borra los PNG temporales tras generar el PDF.
  - Usa fuente TTF (DejaVuSans) si está disponible; fallback a `imagestring` si no.

### Archivos modificados
- `module/Eep/src/Controller/Factory/EvaluacionDocenteControllerFactory.php` — Inyecta `EvaluacionDocenteGraficaService` en el constructor del controller.
- `module/Eep/src/Controller/EvaluacionDocenteController.php`
  - `descargarPdfGraficasAction()` ahora genera PNGs para cada pregunta de tipo `escala10` y `boolean`, inyecta `$pregunta['grafica_path']`, y limpia los archivos temporales en un bloque `finally`.
- `module/Eep/view/eep/evaluacion-docente/descargar-pdf-graficas.phtml`
  - Reemplazadas las tablas HTML/CSS "pixel art" por `<img src="ruta_absoluta_png">`.
  - Agregada clase `.question-block { page-break-inside: avoid; }` para evitar que el título de la pregunta se separe de su gráfica entre páginas.
  - Aumentado el espaciado entre gráficas (`h2 { margin-top: 40px; }`, `.question-block { margin-bottom: 30px; }`, `img { margin-bottom: 25px; }`).
- `module/Eep/view/eep/evaluacion-docente/ver-graficas.phtml`
  - Badge de conteo de comentarios aumentado a `font-size: 20px !important;` para mejor legibilidad.
  - Eje Y de Chart.js etiquetado: **"Cantidad de respuestas"**.

### Notas para producción
- Verificar que `extension=gd` esté habilitada en PHP y compilada con soporte FreeType.
- Verificar que el archivo `/var/www/data/fonts/DejaVuSans.ttf` exista en producción (copiar manualmente si no se reconstruye la imagen Docker).
- No se agregaron nuevas acciones ACL ni dependencias de Composer.
