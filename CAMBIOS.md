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
