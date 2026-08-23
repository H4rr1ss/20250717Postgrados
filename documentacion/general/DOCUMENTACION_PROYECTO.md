# Documentación Técnica - Proyecto Postgrados

## Estructura General del Proyecto

Este sistema está basado en Zend Framework 3 y utiliza Docker para el entorno de desarrollo. La lógica principal y las funcionalidades se distribuyen en módulos y servicios dentro de la carpeta `module/`.

---

## Carpetas Clave y Su Propósito

### 1. **module/**
Contiene toda la lógica de negocio y los módulos funcionales del sistema.

- **Application/**: Módulo principal, lógica base y vistas generales.
- **Eep/**: Módulo especializado, aquí se encuentra la mayor parte de la lógica de usuarios, roles, inscripciones, horarios, etc.
  - **config/**: Configuración específica del módulo (rutas, menús, filtros de acceso).
  - **src/**: Código fuente principal del módulo.
    - **Controller/**: Controladores que gestionan las peticiones HTTP y coordinan la lógica entre servicios y vistas. Ejemplo: `UserController`, `TimetableController`, `MassiveLoadController`.
    - **Service/**: Servicios que encapsulan la lógica de negocio y acceso a datos. Ejemplo: `UserManager`, `TimetableManager`, `MassiveLoadManager`.
    - **Entity/**: Entidades que representan los modelos de datos (usuarios, roles, horarios, etc.).
    - **Form/**: Formularios y validaciones para la entrada de datos.
    - **Controller/Factory/**: Fábricas para la inyección de dependencias en los controladores.
  - **view/**: Vistas (templates) en formato `.phtml` para renderizar la interfaz de usuario.

- **RyE/**, **SIIF/**: Otros módulos funcionales, con estructura similar.

### 2. **config/**
Configuración global del sistema:
- `application.config.php`: Carga de módulos y rutas de configuración.
- `autoload/`: Configuraciones por entorno (`global.php`, `local.php`).
- `modules.config.php`: Lista de módulos habilitados.

### 3. **database/**
Scripts SQL para inicializar y poblar la base de datos.

### 4. **public/**
Archivos públicos y punto de entrada (`index.php`).
- `css/`, `js/`, `images/`: Recursos estáticos.

### 5. **vendor/**
Dependencias instaladas por Composer (Zend Framework y librerías externas).

---

## Ecosistema y Flujo de Lógica

1. **Rutas y Peticiones**
   - Las rutas se definen en `module/Eep/config/module.config.php`.
   - Cada ruta apunta a un controlador y una acción específica.
   - Ejemplo: `/user/create` → `UserController::createAction()`

2. **Controladores**
   - Reciben la petición, validan datos y llaman a los servicios.
   - Ejemplo: `UserController` gestiona usuarios, roles y perfiles.

3. **Servicios**
   - Encapsulan la lógica de negocio y el acceso a la base de datos.
   - Ejemplo: `UserManager` crea, edita y consulta usuarios; `TimetableManager` gestiona horarios.

4. **Entidades**
   - Representan los datos y sus relaciones (usuario, rol, horario, etc.).

5. **Formularios**
   - Validan y procesan la entrada de datos desde la interfaz.

6. **Vistas**
   - Renderizan la respuesta HTML para el usuario final.

7. **Fábricas**
   - Permiten la inyección de dependencias en los controladores y servicios.

---

## Desarrollo de Nuevas Funcionalidades

- **Agrega tu lógica en el módulo correspondiente (usualmente `Eep/src/`).**
- **Crea o edita controladores en `Controller/` para nuevas rutas o acciones.**
- **Implementa la lógica de negocio en `Service/`.**
- **Define entidades en `Entity/` si necesitas nuevos modelos de datos.**
- **Agrega formularios en `Form/` para validación de datos.**
- **Configura nuevas rutas en `config/module.config.php`.**
- **Agrega vistas en `view/` para la interfaz de usuario.**

---

## Recomendaciones

- Mantén la lógica de negocio en los servicios, no en los controladores.
- Usa las fábricas para inyectar dependencias y facilitar pruebas.
- Revisa los archivos de configuración antes de agregar nuevas rutas o servicios.
- Utiliza los scripts SQL de `database/` para migraciones y pruebas.

---

## Ejemplo de Flujo para Crear una Nueva Funcionalidad

1. **Define la ruta en `module.config.php`.**
2. **Crea el método en el controlador correspondiente.**
3. **Implementa la lógica en un servicio.**
4. **Agrega el formulario si se requiere entrada de datos.**
5. **Crea la vista para mostrar resultados o formularios.**

---

## Contacto y Soporte

- Revisa la documentación interna y los comentarios en el código.
- Consulta los archivos `README.md` y `CONDUCT.md` para buenas prácticas.
- Usa el sistema de control de versiones para colaborar y revisar cambios.

---

¿Listo para desarrollar nuevas funcionalidades? Sigue esta estructura y tendrás un desarrollo ordenado y escalable.
