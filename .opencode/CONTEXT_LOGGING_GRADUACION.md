# Contexto de trabajo: Logging en Módulo de Graduación

> Archivo de notas técnicas, decisiones y hallazgos para uso interno durante la implementación.  
> No es documentación para el usuario; es memoria de trabajo del agente.

---

## Convenciones de logging adoptadas

1. **No loggear precondiciones (early returns por validación básica):**
   - Parámetro inválido (`id=0`), no autenticado, método no permitido.
   - Estos quedan implícitamente cubiertos por el listener `onDispatch` de `Module.php` cuando devuelve 403 en AJAX. En requests normales no dejan rastro, y eso es aceptable para "logging mínimo".

2. **Sí loggear:**
   - Carga exitosa de vistas (GET).
   - Resultado final de operaciones POST (éxito o fallo).
   - Excepciones (mensaje de la excepción como detalle).
   - Operaciones de archivo: subida, descarga, eliminación.

3. **Patrón de línea:**
   ```php
   $this->pg()->log($detalle, $estado, $operacion);
   ```
   - `$detalle`: `null` para vistas genéricas; string con contexto para operaciones (ej. `"cod_proceso=123 req=5"`).
   - `$estado`: `LM::SUCCESS`, `LM::FAILURE`, `LM::ERROR` (evitar `WARNING` salvo caso explícito).
   - `$operacion`: `LM::VIEW`, `LM::CREATE`, `LM::READ`, `LM::UPDATE`, `LM::DELETE`.

4. **Ubicación del log:**
   - En actions con `return new ViewModel(...)`: log justo antes del return.
   - En actions con `return $this->redirect(...)`: log justo antes del return.
   - En actions AJAX con `return new JsonModel(...)`: log justo antes del return.
   - En bloques `catch`: loggear el error antes de devolver la respuesta de error.

5. **Contexto mínimo a incluir:**
   - Siempre que exista: `cod_proceso`, `cod_usuario`, `id`, `hash`, `cod_horario`.
   - Evitar incluir datos personales sensibles (CUI, correo) en el detalle del log.

---

## Registro de cambios por fase

### Fase 0 — Verificación de base de datos

- [x] Confirmar existencia de códigos 80–168 en `accion`.
- [x] Nota: si faltan, ejecutar `database/modulo graduacion/ejecuciones_extra.sql`.
- Hallazgo: faltaban los códigos **87** y **170** en la tabla `accion`. Se insertaron manualmente en DB y se agregaron a `ejecuciones_extra.sql` para futuras instalaciones.
- `AUTO_INCREMENT` de `accion` es 256, no hay colisión.

### Fase 1 — EvaluacionDocenteController ✅ COMPLETADA

- [x] Agregar `use Eep\Service\LogManager as LM;`.
- [x] `indexAction` — log VIEW SUCCESS.
- [x] `evaluarAction` — log VIEW SUCCESS con `cod_horario`.
- [x] `guardarEvaluacionAction` — log CREATE SUCCESS/FAILURE con `cod_horario`.
- [x] `reporteDocenteAction` — log VIEW SUCCESS.
- [x] `verGraficasAction` — log VIEW SUCCESS con `cod_horario`.
- [x] `descargarReporteDocenteAction` — log READ SUCCESS.
- Sintaxis validada con `php -l` — sin errores.

### Fase 2 — StudentGraduationController ✅ COMPLETADA

- Ya importa LM (línea 15). No hay que agregar use.
- Actions a cubrir: 13 críticas (ver PLAN_LOGGING_GRADUACION.md).
- Atención especial a actions AJAX con múltiples early returns.
- Sintaxis validada con `php -l` — sin errores.

### Fase 3 — ExamenController

- Ya importa LM (línea 14). No hay que agregar use.
- Controller más grande (3,461 líneas). Dividido en 3A, 3B, 3C.
- Atención: algunas actions tienen estructuras `if/elseif/else` complejas con múltiples puntos de return.

#### Sub-fase 3A — Flujo principal (pasos 0–4) ✅ COMPLETADA
- 13 actions críticas loggeadas: `iniciarProceso`, `buscarEstudiante`, `papeleria`, `guardarRequisito`, `eliminarRequisito`, `guardarInstrucciones`, `subirDocumento`, `guardarRevision`, `guardarDocFisico`, `guardarTerna`, `notificarEstudiante`, `avanzarPaso`, `solicitudesProcess`, `solicitudes`.
- Sintaxis validada con `php -l` — sin errores.

#### Sub-fase 3B — Carta examinadores, evaluación privado, reprogramación, sustitución ✅ COMPLETADA
- 11 actions críticas loggeadas: `cartaExaminadores`, `verCarta`, `enviarNotificacionGrupal`, `evaluacionPrivado`, `abrirEvaluacion`, `cerrarEvaluacion`, `reprogramarExamenPrivado`, `verMatriz`, `sustituirExaminador`, `evaluacionExamenPrivado`, `guardarEvaluacionExaminador`.
- Sintaxis validada con `php -l` — sin errores.

#### Sub-fase 3C — Actas, autorización de impresión, soporte ✅ COMPLETADA
- 16 actions críticas loggeadas: `actaExamenPrivado`, `generarActaExamenPrivado`, `actasExamenGeneral`, `actaExamenGeneral`, `generarActaGeneral`, `autorizacionImpresion`, `guardarInstruccionesAutorizacion`, `subirDocumentoSoporte` (via `handleUploadGlobal`), `eliminarDocumentoSoporte`, `guardarProfesional`, `eliminarProfesional`, `subirCartaDescarga` (via `handleUploadGlobal`), `eliminarCartaDescarga`, `guardarMiembroJunta`, `eliminarMiembroJunta`, `aprobarRevisionPresencial`.
- Nota: durante los edits se eliminó accidentalmente `aprobarRevisionPresencialAction`; fue restaurada desde HEAD y loggeada.
- Sintaxis validada con `php -l` — sin errores.

---

## Notas y advertencias

### Riesgo: `phpcs` puede quejarse de líneas largas
El detalle del log con contexto (ej. `"cod_proceso={$codProceso} req={$codRequisito}"`) puede hacer que algunas líneas excedan el límite de caracteres de `phpcs`. Si ocurre, aplicar `composer cs-fix` y, si persiste, ajustar el string a concatenación multilinea.

### Riesgo: variables no definidas en ramas de error
En algunas actions, una variable usada en el contexto del log solo existe en la rama de éxito. Ejemplo: en `subirDocumentoAction`, `$codRequisito` está definido después de algunas validaciones. Si se quiere loggear en un catch, hay que asegurar que la variable esté disponible o usar un fallback.

### Riesgo: no romper flujos de redirect
Algunas actions hacen `return $this->redirect(...)` en múltiples puntos. El log debe ir justo antes de **cada** redirect. Si se centraliza el log al final de la action, se perderían los logs de las ramas de error que redirigen temprano.

---

## Decisión: actions NO críticas que quedan sin log por ahora

Estas actions fueron deliberadamente excluidas del plan de logging mínimo. Si en el futuro se necesita más cobertura, son candidatas:

- `ExamenController::indexAction` — GET dashboard, puramente informativo.
- `ExamenController::previewNotificacionAction` — GET preview, no muta estado.
- `ExamenController::previewNotificacionGrupalAction` — GET preview, no muta estado.
- `ExamenController::notificacionGrupalAction` — GET formulario, no muta estado.
- `ExamenController::configurarAutorizacionAction` — GET formulario.
- `ExamenController::listaDocentesAction` — GET listado.
- `ExamenController::previsualizarActaExamenPrivadoAction` — GET preview.
- Todas las descargas de archivo directo (`descargarDocumentoSoporte`, `descargarRequisitoApoyo`, `descargarCartaDescarga`) — son READ pero menos críticas que las operaciones de creación/eliminación.
- `StudentGraduationController::procesoAction` — GET vacío.
- `StudentGraduationController::configurarMadrinaPadrinoAction` — GET pura.

---

## Estado actual del trabajo

- **Fase:** 3C completada. Todas las fases del plan están terminadas.
- **Última actualización:** 2026-08-14
- **Próximo paso:** Fase 4 — Validación global y cierre (pruebas manuales en Docker, verificar tabla `bitacora`).

