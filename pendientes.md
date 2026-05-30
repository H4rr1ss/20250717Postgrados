# PENDIENTES - Módulo de Graduación (Examen de Graduación)

> Última actualización: 2026-05-29
> Estado: En pruebas finales antes de producción

---

## CRÍTICOS - Bloqueantes para Producción

### 1. Flujo del Examen General (Tipo 3 - Público General)
- [ ] **Verificar que estudiantes Tipo 3 NO pasen por fases intermedias**
  - El tipo 3 (Público General) debe ir directo a `examen_general`
  - NO debe pasar por: examen_privado, carta_examinadores, autorizacion_impresion
  - Actual: El filtro de pasos en `StudentGraduationManager::getProcesoEstudiante()` muestra fases anteriores como "completadas" aunque no aplican
  - **Acción:** Ocultar pasos de fases que no corresponden al tipo 3 en la vista del estudiante

- [ ] **Carta de Examinadores solo para Tipos 1 y 2**
  - El paso 9 (carta_examinadores) solo debe existir para Privado General (1) y Privado Gerencia (2)
  - El tipo 3 (Público General) NO genera carta de examinadores
  - **Acción:** Validar en `ExamenManager::avanzarPaso()` que tipo 3 salte de paso 4 (privado) directo a paso 5 (general)

### 2. Base de Datos - Seeds Incompletos
- [ ] **Requisitos para paso 2 (Entrega Física)**
  - Paso 2 (Privado) y paso 6 (General): no hay requisitos digitales configurados en BD
  - Actualmente se muestran como "Entrega Física" sin documentos
  - **Acción:** Insertar requisitos `tipo_entrega = 'fisico'` o ajustar vista para no mostrar "no hay requisitos"

- [ ] **Requisitos para paso 2 del Examen General (paso 6)**
  - Falta insertar en BD los requisitos de entrega física para tipo 3
  - **Acción:** Insertar requisitos para `cod_paso = 6, cod_tipo_examen = 3`

### 3. Finalización del Proceso
- [ ] **Vista cuando proceso finaliza (`cod_paso_actual = NULL`)**
  - Actual: Muestra tabla verde aburrida sin confirmación clara
  - **Acción:** Agregar mensaje de felicitación: "¡Proceso de graduación completado exitosamente!"

- [ ] **Datos del estudiante al finalizar**
  - ¿Se necesita guardar nota del examen? ¿Fecha de aprobación formal?
  - **Acción:** Definir si se agrega campo `fecha_aprobacion` o `estado_final` a `examen_proceso`

---

## IMPORTANTES - Mejoras antes de Producción

### 4. Admin - Gestión de Procesos
- [ ] **Filtro de tipos de examen en vista admin**
  - Actual: `ExamenManager::getProcesos()` filtra por fase actual en lugar de tipo del proceso
  - Verificar que tipo 3 (General) aparezca cuando filtran "Examen Público General"
  - **Acción:** Probar filtro con estudiantes en cada fase

- [ ] **Carta de Examinadores - Plantillas .docx**
  - Actual: Todas las plantillas apuntan a `general.docx`
  - **Acción:** Crear plantillas específicas si se necesitan (o dejar genérica si es suficiente)
  - **Acción:** Verificar que `data/plantillas/carta-examinadores/general.docx` existe en producción

### 5. Upload de Archivos
- [ ] **Recarga de página al subir documento**
  - ✅ Corregido: ahora hace `location.reload()`
  - **Acción:** Verificar que funciona correctamente en paso 1 y paso 5 (ambos digitales)

- [ ] **Tamaño máximo de archivos**
  - Default: 10MB para todos los requisitos
  - **Acción:** Revisar si el trabajo de graduación (PDF) necesita más de 10MB (ej: 30MB)

### 6. Acciones ACL (Permisos)
- [ ] **Verificar acciones del módulo graduación**
  - Códigos existentes: 107-111, 132-133
  - **Acción:** Confirmar que roles correctos tienen acceso a cada acción en `estado_accion`

---

## MENORES - Polish y UX

### 7. Vista Estudiante
- [ ] **Icono de paso actual en tabla resumen**
  - Actual: Muestra `<?= $paso['fase'] ?>` que ya fue corregido a nombres formales
  - **Acción:** Verificar que se ve bien: "Examen Privado", "Examen General", etc.

- [ ] **Botón de terna del examen privado bloqueado en fase general**
  - ✅ Corregido: ahora verifica `$faseActual === 'examen_privado'`
  - **Acción:** Probar que estudiante en paso 5/6/7/8 no puede ver resumen de terna privada

### 8. Datos de Prueba
- [ ] **Eliminar datos de prueba**
  - `examen_correccion_evidencia`: tiene registros de prueba (CV.pdf, foto.jpg)
  - `examen_proceso`: procesos de prueba con estudiantes de prueba
  - **Acción:** Limpiar antes de producción o marcar como datos de prueba

### 9. Configuración Inicial
- [ ] **Profesionales Calificados**
  - Actual: 5 registros de ejemplo en `examen_profesional_calificado`
  - **Acción:** Reemplazar con datos reales o configurar desde admin

- [ ] **Junta Directiva**
  - Actual: 4 miembros de ejemplo en `examen_junta_directiva`
  - **Acción:** Reemplazar con datos reales o configurar desde admin

---

## FLUJO COMPLETO A VALIDAR

### Tipo 1 (Privado General) - Flujo Esperado:
1. Director inicia proceso → `cod_paso_actual = 1` (Revisión Papelería)
2. Estudiante sube documentos → Admin aprueba → Avanza a paso 2
3. Estudiante entrega físicamente → Admin confirma → Avanza a paso 3
4. Admin asigna terna → Avanza a paso 4
5. Admin programa fecha/hora → Avanza a paso 5 (Carta Examinadores)
6. Estudiante sube evidencias → Director aprueba → Genera carta → Avanza a paso 6
7. Estudiante selecciona profesional → Director aprueba → Avanza a paso 7
8. Estudiante sube documentos generales → Admin aprueba → Avanza a paso 8
9. Estudiante entrega físicamente → Admin confirma → Avanza a paso 9
10. Admin asigna terna general → Avanza a paso 10
11. Admin programa fecha/hora → Finaliza proceso (`cod_paso_actual = NULL`)

### Tipo 3 (Público General) - Flujo Esperado:
1. Director inicia proceso → `cod_paso_actual = 5` (Revisión Papelería General)
2. Estudiante sube empastados/CD/carta → Admin aprueba → Avanza a paso 6
3. Estudiante entrega físicamente → Admin confirma → Avanza a paso 7
4. Admin asigna terna general → Avanza a paso 8
5. Admin programa fecha/hora → Finaliza proceso (`cod_paso_actual = NULL`)

---

## NOTAS TÉCNICAS

### Seeds que DEBEN ejecutarse en producción (en orden):
```sql
-- 1. Tablas principales
source "database/modulo graduacion/modulo_graduacion.sql";

-- 2. Seeds iniciales (tipos, pasos, requisitos)
source "database/modulo graduacion/seeds_iniciales.sql";

-- 3. Fase 5: Carta de Examinadores (si aplica)
source "database/modulo graduacion/seeds_fase5_carta_examinadores.sql";

-- 4. Acciones ACL del módulo
-- Verificar que existan: cod_accion 107-111, 132-133
```

### Archivos que deben existir en producción:
- `data/plantillas/carta-examinadores/general.docx`
- `public/archivos/` (con permisos de escritura para www-data)
- `data/sessiones/` (sesiones del framework)

---

## ESCENARIOS DE PRUEBA RECOMENDADOS

1. **Crear proceso Tipo 1**, completar todos los pasos hasta finalizar
2. **Crear proceso Tipo 2**, verificar que flujo es igual al Tipo 1 pero con requisitos diferentes
3. **Crear proceso Tipo 3**, verificar que NO aparecen pasos de privado/carta/autorizacion
4. **Avanzar paso con documentos rechazados**, verificar que estudiante puede re-subir
5. **Finalizar proceso**, verificar que vista estudiante muestra confirmación
6. **Filtro admin por tipo de examen**, verificar que tipo 3 aparece en "Público General"

---

*Generado automáticamente basado en el desarrollo del módulo de graduación.*
