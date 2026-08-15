# Plan de implementación: Logging (bitácora) en Módulo de Graduación

> Archivo de seguimiento y bitácora de trabajo.  
> Creado: 2026-08-14  
> Objetivo: Agregar logging mínimo + contexto a las actions críticas de los 3 controllers del módulo de graduación, sin romper el sistema.

---

## Convenciones usadas en este documento

- `[ ]` Pendiente  
- `[x]` Completado  
- `🔄` En progreso  
- `⏸️` Bloqueado / Pendiente de validación externa  

---

## Fase 0 — Verificación de base de datos (pre-requisito)

**Objetivo:** Asegurar que la tabla `accion` contiene los códigos 80–168 antes de que `LogManager::addLog()` intente insertar en `bitacora`.

- [x] **F0.1** Verificar en DB local (`db_postgrados`) que existen los registros de `accion` con `cod_accion` entre 80 y 168.
- [x] **F0.2** Si faltan, ejecutar `database/modulo graduacion/ejecuciones_extra.sql` (sección 2.2 y 2.3).
- [x] **F0.3** Confirmar que `AUTO_INCREMENT` de `accion` no collisiona con los códigos manuales (debe ser > 168).
- [x] **F0.4** Validar que la aplicación no lanza excepción de FK al llamar `$this->pg()->log()` con códigos de graduación.

---

## Fase 1 — `EvaluacionDocenteController` (6 actions críticas)

**Controller:** `module/Eep/src/Controller/EvaluacionDocenteController.php`  
**Estado actual:** No importa `LogManager`. Hay que agregar `use Eep\Service\LogManager as LM;`.  
**Patrón:** Mínimo — 1 log por action, con contexto básico.

| # | Action | HTTP | `cod_accion` | Tipo op. | Estado | Contexto del log |
|---|--------|------|--------------|----------|--------|------------------|
| 1 | `indexAction` | GET | 80 | VIEW | SUCCESS | `null` |
| 2 | `evaluarAction` | GET | 81 | VIEW | SUCCESS | `cod_horario={$idCursoProgramado}` |
| 3 | `guardarEvaluacionAction` | POST | 82 | CREATE | SUCCESS / FAILURE | `cod_horario={$codHorario}` |
| 4 | `reporteDocenteAction` | GET | 85 | VIEW | SUCCESS | `null` |
| 5 | `verGraficasAction` | GET | 87 | VIEW | SUCCESS | `cod_horario={$codHorario}` |
| 6 | `descargarReporteDocenteAction` | GET | 86 | READ | SUCCESS | `"Reporte CSV descargado"` |

### Tareas detalladas

- [x] **F1.1** Agregar `use Eep\Service\LogManager as LM;` al inicio del archivo.
- [x] **F1.2** `indexAction`: agregar `$this->pg()->log(null, LM::SUCCESS, LM::VIEW);` antes del `return new ViewModel(...)`.
- [x] **F1.3** `evaluarAction`: agregar log antes del `return new ViewModel(...)` con `cod_horario`.
- [x] **F1.4** `guardarEvaluacionAction`: agregar log antes de **cada** `return $this->redirect(...)` (éxito y fallo), usando el resultado del manager.
- [x] **F1.5** `reporteDocenteAction`: agregar log antes del `return new ViewModel(...)`.
- [x] **F1.6** `verGraficasAction`: agregar log antes del `return new ViewModel(...)` con `cod_horario`.
- [x] **F1.7** `descargarReporteDocenteAction`: agregar log antes de devolver el `$response`.
- [x] **F1.8** Ejecutar `composer cs-check` y corregir errores.
- [x] **F1.9** Probar en Docker que las 6 actions carguen sin fatal error (vistas y descarga).
- [ ] **F1.10** Verificar en tabla `bitacora` que los 6 registros aparecen con `cod_accion` correcto.

---

## Fase 2 — `StudentGraduationController` (16 actions, 13 críticas)

**Controller:** `module/Eep/src/Controller/StudentGraduationController.php`  
**Estado actual:** Ya importa `LogManager` (línea 15). Solo hay que usar `$this->pg()->log()`.

### Actions con logging (13 críticas)

| # | Action | HTTP | `cod_accion` | Tipo op. | Estado | Contexto del log |
|---|--------|------|--------------|----------|--------|------------------|
| 1 | `indexAction` | GET | 135 | VIEW | SUCCESS | `null` |
| 2 | `paso1SolicitudExamenAction` | GET | 137 | VIEW | SUCCESS | `null` |
| 3 | `paso2TernaAction` | GET | 138 | VIEW | SUCCESS | `null` |
| 4 | `subirDocumentoAction` | POST AJAX | 139 | CREATE | SUCCESS / FAILURE | `cod_proceso={$codProceso} req={$codRequisito}` |
| 5 | `verDocumentoAction` | GET | 140 | READ | SUCCESS / ERROR | `hash={$hash}` |
| 6 | `paso5CartaExaminadoresAction` | GET | 141 | VIEW | SUCCESS | `null` |
| 7 | `subirEvidenciaAction` | POST AJAX | 142 | CREATE | SUCCESS / FAILURE | `cod_ciclo={$codCiclo}` |
| 8 | `eliminarEvidenciaAction` | POST AJAX | 145 | DELETE | SUCCESS / FAILURE | `cod_evidencia={$codEvidencia}` |
| 9 | `aprobarTrabajoAction` | POST AJAX | 143 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 10 | `descargarCartaAction` | GET | 144 | READ | SUCCESS / ERROR | `cod_proceso={$codProceso}` |
| 11 | `paso6AutorizacionImpresionAction` | GET | 146 | VIEW | SUCCESS | `null` |
| 12 | `seleccionarProfesionalAction` | POST AJAX | 147 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso} prof={$codProfesional}` |
| 13 | `guardarTemaTesisAction` | POST AJAX | 154 | CREATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 14 | `guardarMadrinaPadrinoAction` | POST AJAX | 169 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso} tipo={$tipo}` |

### Actions SIN logging (3 vistas puras / redirecciones simples)

- `procesoAction` — GET vacío, no crítico.
- `configurarMadrinaPadrinoAction` — GET pura de configuración.

### Tareas detalladas

- [x] **F2.1** `indexAction`: log antes del `return new ViewModel(...)`.
- [x] **F2.2** `paso1SolicitudExamenAction`: log antes del `return $view`.
- [x] **F2.3** `paso2TernaAction`: log antes del `return $view`.
- [x] **F2.4** `subirDocumentoAction`: log antes del `return new JsonModel([...])` final (éxito). Si hay catch de excepción, loggear FAILURE antes del return de error.
- [x] **F2.5** `verDocumentoAction`: log antes de devolver `$response` (éxito) y en las ramas de error (`403`, `404`, `400`).
- [x] **F2.6** `paso5CartaExaminadoresAction`: log antes del `return $view`.
- [x] **F2.7** `subirEvidenciaAction`: log antes del `return new JsonModel([...])` final. Log en catch si existe.
- [x] **F2.8** `eliminarEvidenciaAction`: log antes del `return new JsonModel([...])` final.
- [x] **F2.9** `aprobarTrabajoAction`: log antes del `return new JsonModel([...])` final (éxito). Log en catch con mensaje de excepción.
- [x] **F2.10** `descargarCartaAction`: log antes de devolver `$response`. Logs en ramas de error (`403`, `400`, `404`).
- [x] **F2.11** `paso6AutorizacionImpresionAction`: log antes del `return $view`.
- [x] **F2.12** `seleccionarProfesionalAction`: log antes del `return new JsonModel([...])` final. Log en catch.
- [x] **F2.13** `guardarTemaTesisAction`: log antes del `return new JsonModel([...])` final. Log en catch.
- [x] **F2.14** `guardarMadrinaPadrinoAction`: log antes del `return new JsonModel([...])` final. Log en catch.
- [x] **F2.15** Ejecutar `composer cs-check` y corregir errores.
- [x] **F2.16** Probar en Docker: subir documento, ver documento, aprobar trabajo, descargar carta.
- [ ] **F2.17** Verificar en `bitacora` que aparecen registros con `cod_accion` 135–170.

---

## Fase 3 — `ExamenController` (51 actions, ~41 críticas) ✅ COMPLETADA

**Controller:** `module/Eep/src/Controller/ExamenController.php`  
**Estado actual:** Importa `LogManager` (línea 14) y ahora se usa en 40+ actions críticas.  
**Nota:** Este es el controller más grande (~3,461 líneas). Se divide en 3 sub-fases lógicas para facilitar testing.

---

### Sub-fase 3A — Proceso inicial, papelería, solicitudes, documentos, terna (actions 1–14)

| # | Action | HTTP | `cod_accion` | Tipo op. | Estado | Contexto |
|---|--------|------|--------------|----------|--------|----------|
| 1 | `iniciarProcesoAction` | POST | 105 | CREATE | SUCCESS / FAILURE | `cod_usuario={$codUsuario} tipo={$codTipoExamen}` |
| 2 | `buscarEstudianteAction` | AJAX | 106 | READ | SUCCESS / FAILURE | `query={$busqueda}` |
| 3 | `papeleriaAction` | GET | 101 | VIEW | SUCCESS | `null` |
| 4 | `guardarRequisitoAction` | POST | 113 | CREATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 5 | `eliminarRequisitoAction` | POST | 114 | DELETE | SUCCESS / FAILURE | `cod_proceso={$codProceso} req={$codRequisito}` |
| 6 | `guardarInstruccionesAction` | POST | 115 | UPDATE | SUCCESS / FAILURE | `cod_paso={$codPaso}` |
| 7 | `subirDocumentoAction` | POST AJAX | 107 | CREATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 8 | `guardarRevisionAction` | POST AJAX | 108 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 9 | `guardarDocFisicoAction` | POST | 109 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 10 | `guardarTernaAction` | POST | 110 | CREATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 11 | `notificarEstudianteAction` | POST/GET | 112 | CREATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 12 | `avanzarPasoAction` | POST AJAX | 111 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 13 | `solicitudesProcessAction` | GET | 102 | VIEW | SUCCESS | `idProceso={$idProceso}` |
| 14 | `solicitudesAction` | GET | 102 | VIEW | SUCCESS | `idProceso={$idProceso} paso={$paso}` |

#### Tareas 3A

- [x] **F3A.1** `iniciarProcesoAction`: log antes del `return $this->redirect(...)` en éxito. Log en catch si aplica.
- [x] **F3A.2** `buscarEstudianteAction`: log antes del `return new JsonModel([...])` final.
- [x] **F3A.3** `papeleriaAction`: log antes del `return new ViewModel(...)`.
- [x] **F3A.4** `guardarRequisitoAction`: log antes del `return $this->redirect(...)` / `return new JsonModel(...)`.
- [x] **F3A.5** `eliminarRequisitoAction`: log antes del return final.
- [x] **F3A.6** `guardarInstruccionesAction`: log antes del return final.
- [x] **F3A.7** `subirDocumentoAction`: log antes del return final (éxito). Log en catch.
- [x] **F3A.8** `guardarRevisionAction`: log antes del return final. Log en catch.
- [x] **F3A.9** `guardarDocFisicoAction`: log antes del return final.
- [x] **F3A.10** `guardarTernaAction`: log antes del return final.
- [x] **F3A.11** `notificarEstudianteAction`: log antes del return final.
- [x] **F3A.12** `avanzarPasoAction`: log antes del return final.
- [x] **F3A.13** `solicitudesProcessAction`: log antes del return final.
- [x] **F3A.14** `solicitudesAction`: log antes del return final.
- [x] **F3A.15** `php -l` sobre `ExamenController.php` — sin errores.

---

### Sub-fase 3B — Carta de examinadores, notificaciones, evaluación privado (actions 15–25)

| # | Action | HTTP | `cod_accion` | Tipo op. | Estado | Contexto |
|---|--------|------|--------------|----------|--------|----------|
| 15 | `cartaExaminadoresAction` | GET | 116 | VIEW | SUCCESS | `null` |
| 16 | `verCartaAction` | GET | 117 | VIEW | SUCCESS | `idProceso={$idProceso}` |
| 17 | `enviarNotificacionGrupalAction` | POST | 134 | CREATE | SUCCESS / FAILURE | `enviados={$n}` |
| 18 | `evaluacionPrivadoAction` | GET | 150 | VIEW | SUCCESS | `cod_proceso={$codProceso}` |
| 19 | `abrirEvaluacionAction` | POST AJAX | 156 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 20 | `cerrarEvaluacionAction` | POST AJAX | 157 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 21 | `reprogramarExamenPrivadoAction` | POST | 160 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 22 | `verMatrizAction` | GET | 153 | VIEW | SUCCESS | `cod_proceso={$codProceso}` |
| 23 | `sustituirExaminadorAction` | POST AJAX | 164 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |
| 24 | `evaluacionExamenPrivadoAction` | GET | 158 | VIEW | SUCCESS | `cod_proceso={$codProceso}` |
| 25 | `guardarEvaluacionExaminadorAction` | POST AJAX | 159 | CREATE | SUCCESS / FAILURE | `cod_proceso={$codProceso} pos={$posExaminador}` |

#### Tareas 3B

- [x] **F3B.1** `cartaExaminadoresAction`: log antes del return final.
- [x] **F3B.2** `verCartaAction`: log antes del return final.
- [x] **F3B.3** `enviarNotificacionGrupalAction`: log antes del return final.
- [x] **F3B.4** `evaluacionPrivadoAction`: log antes del return final.
- [x] **F3B.5** `abrirEvaluacionAction`: log antes del return final.
- [x] **F3B.6** `cerrarEvaluacionAction`: log antes del return final.
- [x] **F3B.7** `reprogramarExamenPrivadoAction`: log antes del return final.
- [x] **F3B.8** `verMatrizAction`: log antes del return final.
- [x] **F3B.9** `sustituirExaminadorAction`: log antes del return final.
- [x] **F3B.10** `evaluacionExamenPrivadoAction`: log antes del return final.
- [x] **F3B.11** `guardarEvaluacionExaminadorAction`: log antes del return final.
- [x] **F3B.12** `php -l` sobre `ExamenController.php` — sin errores.

---

### Sub-fase 3C — Actas, autorización de impresión, soporte (actions 26–41)

| # | Action | HTTP | `cod_accion` | Tipo op. | Estado | Contexto |
|---|--------|------|--------------|----------|--------|----------|
| 26 | `actaExamenPrivadoAction` | GET | 161 | VIEW | SUCCESS | `cod_proceso={$codProceso}` |
| 27 | `generarActaExamenPrivadoAction` | POST | 162 | CREATE | SUCCESS / FAILURE | `cod_proceso={$idProceso}` |
| 28 | `actasExamenGeneralAction` | GET | 166 | VIEW | SUCCESS | `null` |
| 29 | `actaExamenGeneralAction` | GET | 167 | VIEW | SUCCESS | `idProceso={$idProceso}` |
| 30 | `generarActaGeneralAction` | POST | 168 | CREATE | SUCCESS / FAILURE | `idProceso={$idProceso}` |
| 31 | `autorizacionImpresionAction` | GET | 118 | VIEW | SUCCESS | `null` |
| 32 | `guardarInstruccionesAutorizacionAction` | POST | 120 | UPDATE | SUCCESS / FAILURE | `sub_paso={$subPaso}` |
| 33 | `subirDocumentoSoporteAction` | POST | 121 | CREATE | SUCCESS / FAILURE | `sub_paso={$subPaso}` |
| 34 | `eliminarDocumentoSoporteAction` | POST | 122 | DELETE | SUCCESS / FAILURE | `cod_doc={$codDoc}` |
| 35 | `guardarProfesionalAction` | POST | 125 | CREATE | SUCCESS / FAILURE | `sub_paso={$subPaso}` |
| 36 | `eliminarProfesionalAction` | POST | 126 | DELETE | SUCCESS / FAILURE | `cod_prof={$codProfesional}` |
| 37 | `subirCartaDescargaAction` | POST | 127 | CREATE | SUCCESS / FAILURE | `sub_paso={$subPaso}` |
| 38 | `eliminarCartaDescargaAction` | POST | 128 | DELETE | SUCCESS / FAILURE | `cod_carta={$codCarta}` |
| 39 | `guardarMiembroJuntaAction` | POST | 130 | CREATE | SUCCESS / FAILURE | `sub_paso={$subPaso}` |
| 40 | `eliminarMiembroJuntaAction` | POST | 131 | DELETE | SUCCESS / FAILURE | `cod_miembro={$codMiembro}` |
| 41 | `aprobarRevisionPresencialAction` | POST AJAX | 132 | UPDATE | SUCCESS / FAILURE | `cod_proceso={$codProceso}` |

#### Tareas 3C

- [x] **F3C.1** `actaExamenPrivadoAction`: log antes del return final.
- [x] **F3C.2** `generarActaExamenPrivadoAction`: log antes del return final (éxito o redirect con error).
- [x] **F3C.3** `actasExamenGeneralAction`: log antes del return final.
- [x] **F3C.4** `actaExamenGeneralAction`: log antes del return final.
- [x] **F3C.5** `generarActaGeneralAction`: log antes del return final.
- [x] **F3C.6** `autorizacionImpresionAction`: log antes del return final.
- [x] **F3C.7** `guardarInstruccionesAutorizacionAction`: log antes del return final.
- [x] **F3C.8** `subirDocumentoSoporteAction`: log antes del return final (via `handleUploadGlobal`).
- [x] **F3C.9** `eliminarDocumentoSoporteAction`: log antes del return final.
- [x] **F3C.10** `guardarProfesionalAction`: log antes del return final.
- [x] **F3C.11** `eliminarProfesionalAction`: log antes del return final.
- [x] **F3C.12** `subirCartaDescargaAction`: log antes del return final (via `handleUploadGlobal`).
- [x] **F3C.13** `eliminarCartaDescargaAction`: log antes del return final.
- [x] **F3C.14** `guardarMiembroJuntaAction`: log antes del return final.
- [x] **F3C.15** `eliminarMiembroJuntaAction`: log antes del return final.
- [x] **F3C.16** `aprobarRevisionPresencialAction`: log antes del return final.
- [x] **F3C.17** `php -l` sobre `ExamenController.php` — sin errores.

---

## Fase 4 — Validación global y cierre

- [ ] **F4.1** Ejecutar `composer cs-check` sobre los 3 controllers modificados. Debe pasar limpio.
- [ ] **F4.2** Revisar que ninguna llamada a `$this->pg()->log()` quede **después** de un `return` (código muerto).
- [ ] **F4.3** Verificar que `access_filter.php` tiene definidos los códigos de acción para todas las actions loggeadas.
- [ ] **F4.4** Probar flujo completo en Docker:
  - Estudiante inicia proceso (paso 1).
  - Sube documento.
  - Director revisa y avanza paso.
  - Genera carta de examinadores.
  - Aprueba trabajo.
  - Genera acta.
- [ ] **F4.5** Consultar `bitacora` con rango de fecha reciente y confirmar que las acciones de graduación aparecen con:
  - `cod_accion` correcto.
  - `detalle` con contexto (ej. `cod_proceso=123`).
  - `cod_estado` 1 (SUCCESS) o 3 (FAILURE) según corresponda.
  - `cod_operacion` 1/2/3/4/5 correcto.
- [x] **F4.6** Actualizar este archivo marcando todas las fases como completadas.

---

## Registro de avances / notas

> Usar esta sección para anotar hallazgos, decisiones cambiadas, o problemas encontrados durante la ejecución.

### 2026-08-14 — Plan creado y contexto de trabajo inicializado
- Estado: **en progreso** (Fase 0).
- Archivo de contexto creado: `.opencode/CONTEXT_LOGGING_GRADUACION.md`.
- Próximo paso: validar registros en tabla `accion`.

### 2026-08-14 — Fases 1, 2, 3 completadas
- Fase 1 (`EvaluacionDocenteController`): 6 actions loggeadas. `use Eep\Service\LogManager as LM;` agregado.
- Fase 2 (`StudentGraduationController`): 13 actions críticas loggeadas.
- Fase 3 (`ExamenController`): ~40 actions críticas loggeadas en 3 sub-fases (3A, 3B, 3C).
- Hallazgo: faltaban códigos 87 y 170 en `accion`; se insertaron manualmente y se actualizó `ejecuciones_extra.sql`.
- Incidente: durante edición de `ExamenController` se eliminó accidentalmente `aprobarRevisionPresencialAction`; fue restaurada desde HEAD.
- Validación: `php -l` sin errores en los 3 controllers modificados.
- Próximo paso: Fase 4 — probar flujos en Docker y verificar tabla `bitacora`.

---

*Fin del plan.*
