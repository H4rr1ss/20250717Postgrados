# Integración de Acta de Examen Privado y Configuración de Secretario/Decano

## Resumen de Requerimientos

1. **Correlativo de acta**: Autoincrementable global (todas las maestrías comparten el mismo contador)
2. **Decano**: Dato quemado en configuración (`local.php`)
3. **Secretario**: Nuevo rol "Secretario de Examen Privado" con acceso al módulo de evaluación
4. **Múltiples actas por proceso**: Un estudiante puede reprogramarse y generar acta nueva (pierde → acta de reprobado | gana → acta de aprobado)
5. **Formato de acta**: `{correlativo}-{año}` (ej: `001-2026`)
6. **Ciclo**: Se elimina del formulario (se implementará en paso 1)

---

## 1. Cambios en Base de Datos

### 1.1. Nueva tabla de contadores global

```sql
CREATE TABLE `examen_acta_correlativo` (
    `anio` SMALLINT NOT NULL PRIMARY KEY,
    `ultimo_correlativo` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**Razón**: Mantiene un contador global por año. Todas las maestrías comparten el mismo correlativo (`001-2026`, `002-2026`, etc.).

### 1.2. Agregar campos a `examen_proceso`

> ⚠️ **Archivo**: `database/ejecuciones_extra.sql`

```sql
ALTER TABLE `examen_proceso`
  ADD COLUMN IF NOT EXISTS `numero_acta` VARCHAR(20) DEFAULT NULL
    COMMENT 'Número de acta generado (ej: 001-2026)',
  ADD COLUMN IF NOT EXISTS `anio_acta` SMALLINT DEFAULT NULL
    COMMENT 'Año del correlativo del acta',
  ADD COLUMN IF NOT EXISTS `correlativo_acta` INT DEFAULT NULL
    COMMENT 'Correlativo numérico del acta',
  ADD COLUMN IF NOT EXISTS `fecha_generacion_acta` DATETIME DEFAULT NULL
    COMMENT 'Fecha en que se generó el acta',
  ADD COLUMN IF NOT EXISTS `estado_acta` ENUM('aprobado', 'reprobado') DEFAULT NULL
    COMMENT 'Estado del examen cuando se generó el acta',
  ADD KEY IF NOT EXISTS `idx_ep_numero_acta` (`numero_acta`);
```

**Razón**: Un proceso puede tener múltiples actas a lo largo del tiempo (reprogramaciones). Estos campos guardan el acta más reciente generada. Si el estudiante reprograma, se genera un nuevo acta con nuevo correlativo.

### 1.3. Nuevo rol en tabla `rol`

> ⚠️ **Archivo**: `database/ejecuciones_extra.sql`

```sql
INSERT INTO `rol` (`cod_rol`, `nombre`) VALUES (11, 'Secretario de Examen Privado')
ON DUPLICATE KEY UPDATE `nombre` = 'Secretario de Examen Privado';
```

---

## 2. Cambios en Código PHP

### 2.1. `module/Eep/src/Entity/Role.php`

Agregar constante del nuevo rol:

```php
const SECRETARIO_EXAMEN_PRIVADO = 11;
```

Y agregar al array `STR`:

```php
self::SECRETARIO_EXAMEN_PRIVADO => 'Secretario de Examen Privado'
```

### 2.2. `config/autoload/local.php` (o `local.php.dist` si no existe)

Agregar configuración del Decano:

```php
return [
    // ... configuración existente ...
    
    'decano' => [
        'nombre' => 'Ing. Arq. [Nombre del Decano]',
        'titulo' => 'Decano'
    ]
];
```

**Nota**: El usuario proporcionará los nombres reales. Por ahora se deja con placeholder.

**Sobre el Secretario**: El Secretario de Examen Privado NO se configura en `local.php` porque tiene un usuario y rol (11) específico en la base de datos. El nombre se obtiene consultando la tabla `usuario` con `cod_rol = 11`.

### 2.3. `module/Eep/config/access_filter.php`

Agregar el nuevo rol `Role::SECRETARIO_EXAMEN_PRIVADO` a las acciones del ExamenController que necesite manejar:

- `evaluacionPrivado` (vista de evaluación)
- `verMatriz` (ver matriz de evaluación)
- `abrirEvaluacion` (abrir evaluación)
- `cerrarEvaluacion` (cerrar evaluación)
- `actaExamenPrivado` (vista del acta)
- `generarActaExamenPrivado` (generar acta)

**Ejemplo**:
```php
'evaluacionPrivado' => [
    'code' => 150,
    'view' => View::EVALUACION_PRIVADO,
    'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::SECRETARIO_EXAMEN_PRIVADO]
],
```

### 2.4. `module/Eep/src/Service/ExamenManager.php`

**Cambios en `getProceso()`**: Se incluyen las columnas del acta en la consulta para que la vista tenga acceso a la información del acta anterior.

**Cambios en `getEstadoEvaluacion()`**: Se incluyen las columnas `numero_acta`, `estado_acta`, `fecha_generacion_acta` para que la lógica de reprogramación y la vista puedan acceder al estado del acta previo.

**Nuevo método para generar el número de acta:**

```php
/**
 * Genera el siguiente correlativo de acta de forma global por año.
 * Todas las maestrías comparten el mismo contador.
 *
 * @param int $anio Año del acta
 * @return array ['numero_acta' => '001-2026', 'correlativo' => 1, 'anio' => 2026]
 */
public function generarNumeroActa(int $anio): array
{
    $table = new TableGateway('examen_acta_correlativo', $this->adapter);

    // Buscar el último correlativo para este año
    $result = $table->select(['anio' => $anio]);
    $row = $result->current();

    if ($row) {
        $nuevoCorrelativo = (int)$row['ultimo_correlativo'] + 1;
        // Actualizar
        $table->update(
            ['ultimo_correlativo' => $nuevoCorrelativo],
            ['anio' => $anio]
        );
    } else {
        $nuevoCorrelativo = 1;
        // Insertar nuevo registro
        $table->insert([
            'anio' => $anio,
            'ultimo_correlativo' => $nuevoCorrelativo
        ]);
    }

    // Formato con ceros a la izquierda: 001-2026
    $numeroActa = sprintf('%03d-%d', $nuevoCorrelativo, $anio);

    return [
        'numero_acta' => $numeroActa,
        'correlativo' => $nuevoCorrelativo,
        'anio' => $anio
    ];
}
```

**Nuevo método para guardar el acta en el proceso:**

```php
/**
 * Registra el acta generada en el proceso de examen.
 * Al reprogramar, las columnas del acta anterior se mantienen
 * hasta que se genere un nuevo acta (se actualizan con el nuevo).
 *
 * @param int $codProceso
 * @param string $numeroActa
 * @param int $correlativo
 * @param int $anio
 * @param string $estado 'aprobado' o 'reprobado'
 * @return bool
 */
public function registrarActaProceso(
    int $codProceso,
    string $numeroActa,
    int $correlativo,
    int $anio,
    string $estado
): bool {
    $table = new TableGateway('examen_proceso', $this->adapter);

    try {
        $table->update([
            'numero_acta' => $numeroActa,
            'anio_acta' => $anio,
            'correlativo_acta' => $correlativo,
            'fecha_generacion_acta' => date('Y-m-d H:i:s'),
            'estado_acta' => $estado
        ], [
            'cod_proceso' => $codProceso
        ]);
        return true;
    } catch (\Exception $e) {
        return false;
    }
}
```

**Nuevo método para obtener el nombre del Secretario:**

```php
/**
 * Obtiene el nombre del Secretario de Examen Privado (rol 11).
 * Busca el primer usuario activo con ese rol.
 *
 * @return string Nombre completo del secretario o 'Secretario Académico' si no existe
 */
public function getNombreSecretarioExamenPrivado(): string
{
    $sql = 'SELECT CONCAT(u.nombres, " ", u.apellidos) AS nombre_completo
            FROM usuario u
            JOIN usuario_rol ur ON ur.cod_usuario = u.cod_usuario
            WHERE ur.cod_rol = :rol
            LIMIT 1';

    $result = $this->execute($sql, ['rol' => 11]);
    return $result[0]['nombre_completo'] ?? 'Secretario Académico';
}
```

### 2.5. `module/Eep/src/Controller/ExamenController.php`

Modificar `generarActaExamenPrivadoAction()` para:

1. **Generar el correlativo** llamando a `ExamenManager::generarNumeroActa()`
2. **Guardar el acta** llamando a `ExamenManager::registrarActaProceso()`
3. **Usar el número de acta en el documento** (en vez del ciclo)
4. **Obtener Decano** desde la configuración (`local.php`) y **Secretario** desde la base de datos (rol 11)
5. **Calcular el estado** (`aprobado` o `reprobado`) basado en la nota final

Cambios clave en el método:

```php
// Obtener configuración
$decano = $this->config['decano']['nombre'] ?? 'Decano';
$secretario = $this->examenManager->getNombreSecretarioExamenPrivado();

$anio = (int) date('Y');

// Generar número de acta
$datosActa = $this->examenManager->generarNumeroActa($anio);
$numeroActa = $datosActa['numero_acta']; // Ej: "001-2026"

// Calcular estado
$notaFinal = ... // (lógica existente)
$estado = $notaFinal > 61 ? 'aprobado' : 'reprobado';

// Registrar en BD
$this->examenManager->registrarActaProceso(
    $idProceso, 
    $numeroActa, 
    $datosActa['correlativo'], 
    $datosActa['anio'],
    $estado
);

// Usar en el documento
$subtitulo = 'Maestría ' . $numeroActa;
```

### 2.6. `module/Eep/view/eep/examen/acta-examen-privado.phtml`

Cambios en la vista:

1. **Eliminar inputs** de:
   - Ciclo
   - Decano
   - Secretario Académico

2. **Mostrar el número de acta** (si ya existe uno generado para el proceso)
   - Si ya tiene acta previa, mostrar mensaje: "Este proceso ya tiene un acta generada: XXX-2026. Al generar una nueva, se creará un nuevo correlativo."

3. **Mostrar Decano y Secretario** como texto de solo lectura (desde la BD/config)

4. **Mantener**: Recibo, Justificación de modalidad

Vista simplificada:
```php
<!-- Datos del acta (solo lectura) -->
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Número de Acta</label>
            <input type="text" class="form-control" value="<?= $this->escapeHtml($numeroActa ?? 'Por generar') ?>" readonly>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Decano</label>
            <input type="text" class="form-control" value="<?= $this->escapeHtml($decano) ?>" readonly>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Secretario Académico</label>
            <input type="text" class="form-control" value="<?= $this->escapeHtml($secretario) ?>" readonly>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Número de Recibo</label>
            <input type="text" class="form-control" name="recibo" required>
        </div>
    </div>
</div>
```

### 2.7. Creación de usuario Secretario

Script SQL para crear el usuario:

```sql
INSERT INTO `usuario` (
    `nombres`, `apellidos`, `registro_academico`, `correo`, `contrasenia`, 
    `cod_pais`, `sexo`, `fecha_creacion`, `nombre_completo`
) VALUES (
    'NombreSecretario', 'ApellidoSecretario', '202600001', 
    'secretario.examen@farusac.edu.gt', 
    '$2y$10$...hash...', 73, 'H', NOW(), 'Nombre Apellido'
);

-- Obtener el cod_usuario generado (ej: 9999)
-- Asignar rol
INSERT INTO `usuario_rol` (`cod_usuario`, `cod_rol`) VALUES (9999, 11);
```

**Nota**: El hash de contraseña debe generarse con `password_hash()` de PHP.

---

## 3. Flujo de Generación del Acta

```
1. Staff accede a vista acta-examen-privado
2. Controller carga datos del proceso y estudiante
3. Si ya tiene acta previa, mostrar advertencia
4. Staff completa: Recibo, Justificación (opcional)
5. Staff presiona "Generar Acta"
6. Controller:
   a. Llama generarNumeroActa(año_actual)
   b. Obtiene Decano y Secretario de Config
   c. Calcula nota final y estado (aprobado/reprobado)
   d. Llama registrarActaProceso(idProceso, numero, correlativo, anio, estado)
   e. Genera el DOCX con todos los datos
   f. Devuelve el archivo para descargar
7. Si estudiante reprograma y pierde/gana:
   a. Se repite el proceso con nuevo correlativo
   b. El proceso guarda la última acta generada
```

---

## 4. Consideraciones Importantes

### 4.1. Concurrencia
El método `generarNumeroActa()` debe ser atómico. Como se usa `TableGateway` con `update/insert` en una sola operación, MySQL garantiza atomicidad en la fila.

### 4.2. Múltiples actas por proceso
- `examen_proceso` solo guarda el **último acta** generada
- Si se necesita historial completo de actas por proceso, se debería crear una tabla `examen_acta_historial` en el futuro
- Por ahora, el requerimiento solo necesita generar el acta y el correlativo

### 4.3. Reprogramación y actas
Cuando un estudiante reprograma (por haber reprobado):
- El proceso mantiene `ex1_completado`, `ex2_completado`, `ex3_completado` en `examen_proceso`
- Se reinicia el estado de evaluación (poner en 0 los completados)
- **Las columnas del acta anterior (`numero_acta`, `estado_acta`, `fecha_generacion_acta`) se mantienen** (no se limpian)
- Al generar nueva acta, se obtiene un nuevo correlativo y las columnas se actualizan
- La vista muestra el acta anterior con su estado (aprobado/reprobado) y fecha de generación

### 4.4. Coordinación de lógica
La lógica de reprogramación (`reprogramarExamenPrivado`) se mantiene igual:
1. Elimina evaluaciones anteriores
2. Resetea estado (ex1_completado, ex2_completado, ex3_completado = 0)
3. Actualiza fecha/hora del examen
4. Incrementa numero_reprogramacion

Las columnas del acta se mantienen intactas durante la reprogramación, permitiendo ver el historial del acta anterior en la vista. Cuando se genera el nuevo acta, las columnas se actualizan con los nuevos datos.

### 4.5. Estilo de código
- Seguir PSR-2 + short arrays (`[]`)
- Los nombres de métodos en `ExamenManager` en camelCase
- Constantes del nuevo rol en `Role.php` en UPPER_CASE

---

## 5. Archivos a Modificar

| Archivo | Cambio | Tipo |
|---------|--------|------|
| `database/modulo_graduacion/modulo_graduacion_schema.sql` | Agregar `examen_acta_correlativo` (CREATE TABLE) | SQL |
| `database/ejecuciones_extra.sql` | ALTER `examen_proceso` + INSERT rol 11 | SQL |
| `module/Eep/src/Entity/Role.php` | Agregar constante `SECRETARIO_EXAMEN_PRIVADO` | PHP |
| `config/autoload/local.php` | Agregar config de Decano y Secretario | PHP |
| `module/Eep/config/access_filter.php` | Agregar rol a acciones de ExamenController | PHP |
| `module/Eep/src/Service/ExamenManager.php` | Agregar `generarNumeroActa()`, `registrarActaProceso()`, actualizar `getProceso()` y `getEstadoEvaluacion()` | PHP |
| `module/Eep/src/Controller/ExamenController.php` | Modificar `generarActaExamenPrivadoAction()` y `actaExamenPrivadoAction()` | PHP |
| `module/Eep/view/eep/examen/acta-examen-privado.phtml` | Quitar inputs, mostrar datos de config, mostrar acta anterior | PHP (Vista) |
| `module/Eep/config/menus.php` | Verificar que el menú incluya el nuevo rol si es necesario | PHP |
| `INTEGRACION_ACTA_Y_SECRETARIO_DECANO.md` | Este documento | MD |
| `database/ejecuciones_extra.sql` | Centralización de modificaciones y registros | SQL |

---

## 6. Pruebas Recomendadas

1. **Generar acta** para Maestría A → debe ser `001-2026`
2. **Generar acta** para Maestría B → debe ser `002-2026` (mismo año, siguiente correlativo global)
3. **Generar segunda acta** para Maestría A → debe ser `003-2026`
4. **Reprogramar** estudiante que reprobó → verificar que la vista muestra el acta anterior
5. **Generar acta** después de reprogramación → debe ser `004-2026`, y la vista muestra el acta anterior
6. **Verificar** que el DOCX muestra el número correcto
7. **Verificar** que Decano y Secretario aparecen en el documento
8. **Verificar** que el usuario con rol 11 puede acceder al módulo

---

## 7. Notas Pendientes

- [ ] El usuario debe proporcionar el nombre real del Decano en `config/autoload/local.php`
- [ ] El usuario debe crear el usuario Secretario en el sistema con rol 11
- [ ] El usuario debe ejecutar el SQL en la base de datos
- [ ] El usuario debe verificar que el formato del acta cumple con los requisitos de la facultad

---

*Documento generado para planificación de implementación.*
