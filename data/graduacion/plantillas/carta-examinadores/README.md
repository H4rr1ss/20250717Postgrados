# Plantillas — Carta de Examinadores

Carpeta de plantillas `.docx` usadas por `Eep\Service\CartaGenerator`
(PHPWord `TemplateProcessor`) para generar la carta de examinadores del
paso 5 del proceso de graduación.

## Convención

- **Una plantilla por tipo de examen** (opcional). El catálogo
  `examen_carta_plantilla` permite asociar una plantilla a un
  `cod_tipo_examen`, o dejar `NULL` (aplica a todos).
- Los archivos `.docx` deben:
  - Tener los placeholders **exactamente** como aparecen en la lista de
    abajo, con la sintaxis `${nombre_variable}`.
  - Estar guardados en formato `.docx` moderno (no `.doc`).
  - Quedar versionados aquí en git (son código, no datos de usuario).
- Las cartas **generadas** (output) van a
  `public/archivos/cartas-examinadores/proceso-{cod_proceso}.docx`
  — esa carpeta sí se sirve por HTTP pero el acceso pasa por el
  controlador con ACL.

## Plantillas registradas

Las rutas reales se guardan en la tabla `examen_carta_plantilla`. El seed
inicial (`database/modulo_graduacion_carta_02_seeds.sql`) registra:

| nombre                                 | archivo                                                 | tipo_examen |
|----------------------------------------|---------------------------------------------------------|-------------|
| Carta de Examinadores - General        | `data/plantillas/carta-examinadores/general.docx`       | (todos)     |

Para añadir variantes (por ejemplo "Privado Gerencia"), copia el `.docx`
aquí y agrega una fila a `examen_carta_plantilla` con su `cod_tipo_examen`.

## Placeholders disponibles

`CartaGenerator` rellena los siguientes campos. Si la plantilla incluye
otros placeholders no listados, quedarán literalmente como `${...}` en la
carta generada (PHPWord no falla — los ignora).

### Estudiante
- `${estudiante_nombre}` — nombres + apellidos
- `${estudiante_carnet}` — registro académico
- `${estudiante_cui}` — CUI

### Trabajo y examen
- `${titulo_trabajo}` — título del trabajo de graduación
- `${tipo_examen}` — nombre del tipo de examen (ej. "Privado General")
- `${fecha_examen}` — fecha del examen privado (dd/mm/aaaa)
- `${hora_examen}` — hora del examen (HH:MM)

### Terna
- `${asesor_nombre}`
- `${examinador_1_nombre}` — `${examinador_1_colegiado}`
- `${examinador_2_nombre}` — `${examinador_2_colegiado}`
- `${examinador_3_nombre}` — `${examinador_3_colegiado}`

### Firma / fechado
- `${coordinador_nombre}` — coordinador que aprobó el trabajo
- `${fecha_emision_carta}` — fecha en que se generó la carta

## Notas

- Si un placeholder no tiene valor en la BD, `CartaGenerator` envía cadena
  vacía (no `null`) para evitar dejar el texto `${...}` en la carta final.
- Para generar PDF a partir del docx, todavía hay que decidir la
  herramienta (LibreOffice headless o conversión separada). Por ahora se
  entrega el `.docx`.
