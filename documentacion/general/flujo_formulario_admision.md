# Flujo del Módulo Formulario de Admisión

Este documento describe el flujo de archivos y la arquitectura del módulo de formulario de admisión en el sistema.

## 1. Vistas (Frontend)
- **index.phtml**: Listado de formularios activos y archivados. Incluye botón para archivar.
- **crear.phtml**: Formulario para crear un nuevo formulario de admisión.
- **respuestas.phtml**: Listado de respuestas de aspirantes para un formulario específico.

## 2. Controlador
- **FormularioAdmisionController.php**: Orquesta la lógica entre vistas y backend.
  - `indexAction`: Listado de formularios.
  - `crearAction`: Procesa la creación de un formulario.
  - `respuestasAction`: Muestra respuestas de un formulario.
  - `editarRespuestaAction`: Edita una respuesta de aspirante.
  - `archivarAction`: Archiva un formulario (flujo de archivado, ver abajo).

## 3. Formulario Zend
- **FormularioAdmisionForm.php**: Define los campos y validaciones del formulario de admisión.

## 4. Entidad
- **FormularioAdmision.php**: Representa el objeto formulario de admisión en PHP.

## 5. Servicio/Manager
- **FormularioAdmisionManager.php**: Lógica de negocio y acceso a la base de datos para formularios de admisión.
  - Métodos para crear, obtener, archivar, eliminar formularios y respuestas.

## 6. Factory
- **FormularioAdmisionControllerFactory.php**: Inyección de dependencias al controlador.
- **FormularioAdmisionManagerFactory.php**: Inyección de dependencias al manager.

## 7. Base de Datos
- **formularios_admision_tablas.sql**: Script para crear/modificar las tablas relacionadas.

---

# Flujo de Archivado de Formulario

1. **Vista (index.phtml):**
   - El usuario hace clic en el botón "Deshabilitar" (archivar) de un formulario.
   - Se ejecuta la función JS `archivarFormulario(idFormulario)` que redirige a la ruta `/formulario-admision/archivar/{id}`.

2. **Controlador (FormularioAdmisionController.php):**
   - Se implementa el método `archivarAction()`.
   - Este método recibe el `id` del formulario, llama al manager para archivar y redirige al listado con un mensaje.

3. **Manager (FormularioAdmisionManager.php):**
   - Se implementa el método `archivarFormulario($idFormulario)`.
   - Actualiza el campo `activo` del formulario en la base de datos a `0` (archivado).
   - Devuelve resultado de éxito o error.

4. **Vista (index.phtml):**
   - Se muestra un mensaje de éxito o error tras archivar.

---

**¿Cómo ubicarte?**
- Si quieres cambiar la lógica de archivado, ve al controlador y manager.
- Si quieres cambiar el botón o mensaje, ve a la vista.
- Si quieres cambiar la estructura de la tabla, ve al SQL.
