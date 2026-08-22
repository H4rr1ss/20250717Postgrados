# Guía de Despliegue — Módulo Formulario de Admisión

> Documento de bitácora para instalación en producción.
> Fecha de implementación: 2026-08-22

## 1. Resumen de la funcionalidad

Se agregó un módulo completo de **Formularios de Admisión** que permite:

- Crear formularios de admisión con período de vigencia (fecha inicio / fecha fin).
- Definir campos dinámicos globales que se presentan automáticamente en cada formulario.
- Recibir respuestas públicas de aspirantes vía `/admisiones` (sin autenticación).
- Gestionar, visualizar, editar y archivar respuestas desde el panel administrativo.
- Registrar aspirantes aprobados como estudiantes del sistema.

Roles con acceso administrativo: **DIRECTOR** y **ASISTENTE**.
El formulario público es accesible para cualquier usuario (incluyendo no autenticados).

---

## 2. Archivos a desplegar

Asegúrate de que los siguientes archivos estén presentes en el servidor de producción:

### Nuevos archivos

```
module/Eep/src/Controller/FormularioAdmisionController.php
module/Eep/src/Controller/Factory/FormularioAdmisionControllerFactory.php
module/Eep/src/Service/FormularioAdmisionManager.php
module/Eep/src/Service/Factory/FormularioAdmisionManagerFactory.php
module/Eep/src/Form/FormularioAdmisionForm.php
module/Eep/src/Entity/FormularioAdmision.php
module/Eep/src/Entity/CampoFormulario.php
module/Eep/src/Entity/RespuestaAspirante.php
module/Eep/view/eep/formulario-admision/index.phtml
module/Eep/view/eep/formulario-admision/crear.phtml
module/Eep/view/eep/formulario-admision/respuestas.phtml
module/Eep/view/eep/formulario-admision/editar-respuesta.phtml
module/Eep/view/eep/formulario-admision/public.phtml
module/Eep/view/eep/formulario-admision/registrar-aspirante.phtml
```

### Archivos modificados (deben reemplazarse)

```
module/Eep/config/module.config.php        (rutas + factories)
module/Eep/config/access_filter.php        (permisos ACL códigos 68–76)
module/Eep/config/menus.php                (entrada de menú lateral)
module/Eep/src/ValueObject/View.php        (constante FORMULARIO_ADMISION = 25)
```

### Scripts de referencia (ejecutar en la BD, no copiar al web root)

```
database/modulo_aspirantes_final.sql
```

---

## 3. Pasos de base de datos (obligatorios)

### 3.1 Crear tablas del módulo

Conectarse a la base de datos `db_postgrados` y ejecutar:

```bash
mysql -u <usuario> -p db_postgrados < database/modulo_aspirantes_final.sql
```

**Tablas creadas:**

| Tabla | Propósito |
|-------|-----------|
| `formulario_admision` | Configuración de cada formulario (nombre, fechas, estado) |
| `campo_formulario` | Catálogo global de campos dinámicos con tipo, etiqueta, sección, orden |
| `respuesta_aspirante` | Registro maestro de cada envío por aspirante |
| `respuesta_campo` | Respuesta individual por campo y por envío |

**Seeds incluidos:** 24 campos globales predefinidos (datos personales, contacto, laboral, académico, adicionales).

### 3.2 Insertar acciones de control de acceso (ACL)

> **IMPORTANTE:** Las acciones del módulo de admisiones (códigos 68–76) deben existir en la tabla `accion` para que el sistema de permisos funcione. Si no existen, insértalas manualmente:

```sql
INSERT INTO accion (cod_accion, nombre) VALUES
  (68, 'Ver formulario de admisión'),
  (69, 'Ver respuestas de formulario'),
  (70, 'Editar respuesta de aspirante'),
  (71, 'Archivar formulario'),
  (72, 'Eliminar formulario'),
  (73, 'Ver formulario público de admisiones'),
  (74, 'Verificar CUI de aspirante'),
  (75, 'Descargar archivo de respuesta'),
  (76, 'Registrar aspirante como estudiante');
```

Verificar que no collisionen con códigos existentes:

```sql
SELECT cod_accion, nombre FROM accion WHERE cod_accion BETWEEN 68 AND 76;
```

---

## 4. Preparación del servidor de archivos

El módulo almacena archivos adjuntos (fotos de DPI, títulos, etc.) en disco:

```bash
# Crear carpeta de uploads si no existe
mkdir -p /var/www/data/admisiones

# Establecer permisos correctos
chown -R www-data:www-data /var/www/data/admisiones
chmod -R 755 /var/www/data/admisiones
```

**Ruta configurable en código:** `FormularioAdmisionManager::RUTA_ARCHIVOS = './data/admisiones'`.

**Validaciones de archivos:**
- Tamaño máximo: 5 MB por archivo.
- Tipos permitidos: `image/jpeg`, `image/png`, `image/gif`, `application/pdf`.

---

## 5. Verificación post-instalación

Después de desplegar los archivos y aplicar los cambios en la BD, verificar lo siguiente:

### 5.1 Menú visible para director/asistente
- Iniciar sesión con un usuario que tenga rol **DIRECTOR** o **ASISTENTE**.
- En el menú lateral izquierdo debe aparecer: **"Formulario de Admisión"** (icono de documento).

### 5.2 Acceso al panel administrativo
- Hacer clic en el menú.
- Debe cargar la pantalla de listado de formularios (activos y archivados).
- Presionar **"Crear Formulario"**, llenar nombre, fecha inicio y fecha fin.
- Guardar y verificar que aparece en el listado.

### 5.3 Formulario público de aspirantes
- Abrir en navegador incógnito (sin sesión):
  ```
  http://<dominio>/admisiones
  ```
- Debe cargar el formulario público con los campos predefinidos.
- Llenar datos de prueba y enviar.
- Verificar mensaje de éxito.

### 5.4 Recepción de respuestas en admin
- Volver al panel como DIRECTOR.
- Ir a **Formulario de Admisión → Ver Respuestas** del formulario creado.
- Debe aparecer la respuesta del aspirante de prueba.
- Probar **"Editar Respuesta"** y guardar cambios.
- Probar **"Archivar"** el formulario y verificar que desaparece de activos.

### 5.5 Seguridad — acceso denegado
- Iniciar sesión con un usuario que **NO** sea director ni asistente (ej. estudiante).
- Intentar acceder directamente a:
  ```
  /formulario-admision
  /formulario-admision/respuestas/1
  ```
- El sistema debe redirigir al **home** o mostrar acceso denegado.

### 5.6 Verificar integridad de archivos
- Subir una foto de DPI desde el formulario público.
- Verificar que el archivo se guarda en `data/admisiones/`.
- Verificar que desde el panel admin se puede descargar/visualizar el archivo adjunto.

---

## 6. Rollback (en caso de emergencia)

Si se necesita revertir este módulo:

1. Restaurar los archivos modificados (`module.config.php`, `access_filter.php`, `menus.php`, `View.php`) desde backup.
2. Opcional: eliminar las acciones agregadas:
   ```sql
   DELETE FROM accion WHERE cod_accion BETWEEN 68 AND 76;
   ```
3. Opcional: eliminar las tablas (⚠️ **pérdida de datos**):
   ```sql
   DROP TABLE IF EXISTS respuesta_campo;
   DROP TABLE IF EXISTS respuesta_aspirante;
   DROP TABLE IF EXISTS campo_formulario;
   DROP TABLE IF EXISTS formulario_admision;
   ```
4. Limpiar caché de sesiones si es necesario (`data/sessiones/*`).

---

## 7. Notas adicionales

- **No se agregaron nuevas dependencias de Composer** para este módulo. Solo requiere las dependencias base del proyecto (Zend Framework 3, Zend\Db, etc.).
- **Los campos del formulario son globales**, no se crean por formulario individual. Cualquier cambio al catálogo de campos afecta a todos los formularios existentes.
- **La sección del campo** (`seccion` en `campo_formulario`) determina en qué pestaña aparece en la vista pública: `personal`, `contacto`, `laboral`, `academico`, `adicional`.
- **Campos marcados como `seccion = 'admin'`** no aparecen en el formulario público; se usan para configuración interna.
- **El campo `photo_dpi` es obligatorio** en el formulario público; valida que sea imagen (JPG, PNG, GIF) o PDF.
- **El formulario público valida el período de admisión** (`fecha_inicio_admision` <= hoy <= `fecha_fin_admision`). Si el período está cerrado, muestra mensaje informativo y no permite envío.

---

## 8. Diagrama de rutas y acciones

| Ruta | Action | Acceso | `cod_accion` |
|------|--------|--------|--------------|
| `/formulario-admision` | `index` | DIRECTOR, ASISTENTE | 68 |
| `/formulario-admision/crear` | `crear` | DIRECTOR, ASISTENTE | 68 |
| `/formulario-admision/respuestas/:id` | `respuestas` | DIRECTOR, ASISTENTE | 69 |
| `/formulario-admision/editar-respuesta/:id` | `editarRespuesta` | DIRECTOR, ASISTENTE | 70 |
| `/formulario-admision/archivar/:id` | `archivar` | DIRECTOR, ASISTENTE | 71 |
| `/formulario-admision/eliminar/:id` | `eliminar` | DIRECTOR, ASISTENTE | 72 |
| `/formulario-admision/descargar/:id` | `descargar` | DIRECTOR, ASISTENTE | 75 |
| `/formulario-admision/registrar-aspirante/:id` | `registrarAspirante` | DIRECTOR, ASISTENTE | 76 |
| `/admisiones` | `public` | PÚBLICO (ALL) | 73 |
| `/admisiones/verificar-cui` | `verificarCui` | PÚBLICO (ALL) | 74 |

---

**Responsable de despliegue:** ___________________  
**Fecha de despliegue en producción:** ___________________  
**Verificado por:** ___________________
