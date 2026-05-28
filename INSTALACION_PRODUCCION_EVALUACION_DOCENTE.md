# Guía de Despliegue — Reporte de Evaluación Docente

> Documento de bitácora para instalación en producción.
> Fecha de implementación: 2026-05-27

## 1. Resumen de la funcionalidad

Se agregó un reporte de evaluación docente accesible únicamente para el rol **DIRECTOR**.
El director puede:
- Ver una tabla resumen con los resultados agregados por docente y curso.
- Filtrar por **año** y **mes**.
- Descargar un archivo **CSV** con el detalle completo (promedios, porcentajes Sí/No y comentarios textuales anónimos).

---

## 2. Archivos a desplegar

Asegúrate de que los siguientes archivos estén presentes en el servidor de producción:

### Nuevos archivos
```
module/Eep/view/eep/evaluacion-docente/reporte-docente.phtml
```

### Archivos modificados (deben reemplazarse)
```
module/Eep/src/Service/EvaluacionDocenteManager.php
module/Eep/src/Controller/EvaluacionDocenteController.php
module/Eep/config/module.config.php
module/Eep/config/access_filter.php
module/Eep/config/menus.php
module/Eep/src/ValueObject/View.php
```

### Scripts de referencia (no es necesario copiarlos al servidor web, solo ejecutarlos en la BD)
```
database/evaluacion_docente.sql
```

---

## 3. Pasos de base de datos (obligatorios)

### 3.1 Insertar las nuevas acciones de control de acceso

Conectarse a la base de datos `db_postgrados` y ejecutar:

```sql
INSERT INTO accion (cod_accion, nombre) VALUES
  (145, 'Ver reporte de evaluación docente'),
  (146, 'Descargar reporte de evaluación docente');
```

### 3.2 Verificar que las tablas de evaluación docente existan

Si el módulo de evaluación docente **ya estaba instalado** previamente, solo se requiere el paso 3.1.

Si el módulo **nunca se ha instalado**, ejecutar el script completo:

```bash
mysql -u <usuario> -p db_postgrados < database/evaluacion_docente.sql
```

---

## 4. Verificación post-instalación

Después de desplegar los archivos y aplicar los cambios en la BD, verificar lo siguiente:

### 4.1 Menú visible para el director
- Iniciar sesión con un usuario que tenga rol **DIRECTOR**.
- En el menú lateral izquierdo debe aparecer: **"Reporte Evaluación Docente"** (icono de gráfica).

### 4.2 Acceso a la vista de reporte
- Hacer clic en el menú.
- Debe cargar la pantalla con filtros de **Año** y **Mes**.
- Si ya existen evaluaciones docentes registradas, debe mostrar la tabla con los docentes, cursos, secciones, período y promedio general.

### 4.3 Descarga del CSV
- Presionar el botón **"Descargar CSV"**.
- Debe descargarse un archivo `.csv` que se abre correctamente en Excel.
- Verificar que los acentos y caracteres especiales se vean bien (el archivo incluye BOM UTF-8).

### 4.4 Seguridad — acceso denegado a no directores
- Iniciar sesión con un usuario que **NO** sea director (ej. estudiante o catedrático).
- Intentar acceder directamente a la URL:
  ```
  /evaluacion-docente/reporte-docente
  ```
- El sistema debe redirigir al **home** o mostrar acceso denegado.

---

## 5. Rollback (en caso de emergencia)

Si se necesita revertir esta funcionalidad:

1. Restaurar los archivos modificados desde el backup previo.
2. En la base de datos, eliminar las acciones agregadas:
   ```sql
   DELETE FROM accion WHERE cod_accion IN (145, 146);
   ```
3. Limpiar caché de sesiones si es necesario (`data/sessiones/*`).

---

## 6. Notas adicionales

- No se agregaron nuevas dependencias de Composer para esta funcionalidad.
- El formato de descarga es **CSV** (no requiere librerías adicionales como PhpSpreadsheet).
- Las evaluaciones docentes deben existir previamente en las tablas `evaluacion_respuesta` y `evaluacion_respuesta_detalle` para que el reporte muestre datos.
- Si no hay evaluaciones registradas, la tabla aparecerá vacía con el mensaje: *"No hay evaluaciones registradas para el período seleccionado."*

---

**Responsable de despliegue:** ___________________  
**Fecha de despliegue en producción:** ___________________  
**Verificado por:** ___________________
