# Cambios en el Proyecto — Rama `development`

**Comparación:** Primer commit de `main` (`ee5962b`) vs. HEAD actual de `development`.

---

## Resumen de Cambios

| Estado | Cantidad | Descripción |
|---|---|---|
| **A** (Agregado) | 129 | Archivos nuevos |
| **M** (Modificado) | 28 | Archivos existentes modificados |
| **D** (Eliminado) | 2 | Archivos eliminados |
| **Total** | **159** | Archivos afectados (excluyendo sesiones temporales) |

---

## Cambios por Categoría

### 1. Configuración del Proyecto

| Estado | Archivo | Descripción |
|---|---|---|
| M | `.gitignore` | Reglas de exclusión actualizadas |
| M | `composer.json` | Dependencias actualizadas |
| M | `composer.lock` | Lock de dependencias actualizado |
| M | `config/autoload/global.php` | Configuración global de DB |
| M | `config/modules.config.php` | Módulos habilitados (incluye `Zend\Mail`) |
| M | `docker-compose.yml` | Configuración Docker (puertos 8080/3307, volumen comentado) |
| A | `docker/Dockerfile` | Dockerfile movido a subdirectorio |
| D | `Dockerfile` | Dockerfile original eliminado (movido a `docker/`) |
| A | `AGENTS.md` | Documentación para agentes de IA |
| A | `init-docker-env.sh` | Script de inicialización del entorno Docker |
| A | `opencode.json` | Configuración de OpenCode |

### 2. Base de Datos

| Estado | Archivo | Descripción |
|---|---|---|
| D | `20250718Postgrados` | Dump original eliminado |
| A | `database/20250718Postgrados.sql` | Dump SQL renombrado a carpeta `database/` |
| A | `database/evaluacion_docente.sql` | Script de evaluación docente |
| A | `database/modulo_aspirantes_final.sql` | Script del módulo de aspirantes |
| A | `database/modulo graduacion/ejecuciones_extra.sql` | Ejecuciones adicionales de graduación |
| A | `database/modulo graduacion/estructura_archivos.sql` | Estructura de archivos de graduación |
| A | `database/modulo graduacion/matriz_evaluacion_completo.sql` | Matriz de evaluación completa |
| A | `database/modulo graduacion/modulo_graduacion_schema.sql` | Schema del módulo de graduación |
| A | `database/Demos/new-users/estudiantes.sql` | Demo: estudiantes |
| A | `database/Demos/new-users/hash.php` | Demo: utilidad de hashing |
| A | `database/Demos/new-users/users.sql` | Demo: usuarios |

### 3. Documentación

| Estado | Archivo | Descripción |
|---|---|---|
| A | `documentacion/CAMBIOS.md` | Registro de cambios |
| A | `documentacion/INSTALACION_PRODUCCION_EVALUACION_DOCENTE.md` | Guía de instalación de evaluación docente |
| A | `documentacion/INSTALACION_PRODUCCION_FORMULARIO_ADMISION.md` | Guía de instalación de formulario de admisión |
| A | `documentacion/INSTALACION_PRODUCCION_GENERAL.md` | Guía de instalación general |
| A | `documentacion/general/DOCUMENTACION_MODULO_FORMULARIO_ADMISION.md` | Documentación del módulo de admisión |
| A | `documentacion/general/DOCUMENTACION_PROYECTO.md` | Documentación general del proyecto |
| A | `documentacion/general/ESTRUCTURA_ARCHIVOS_GRADUACION.md` | Estructura de archivos de graduación |
| A | `documentacion/general/EXPLICACION_RUTAS_ZF3.md` | Explicación de rutas ZF3 |
| A | `documentacion/general/INTEGRACION_ACTA_Y_SECRETARIO_DECANO.md` | Integración acta y secretario decano |
| A | `documentacion/general/PLAN_AMBIENTE_POSTGRADOS_NUEVO.md` | Plan de ambiente nuevo |
| A | `documentacion/general/PLAN_LOGGING_GRADUACION.md` | Plan de logging de graduación |
| A | `documentacion/general/flujo_formulario_admision.md` | Flujo del formulario de admisión |
| A | `documentacion/general/formulario_admision.md` | Formulario de admisión |
| A | `documentacion/general/usuarios.md` | Documentación de usuarios |
| A | `documentacion/modulo-graduacion/CHECKLIST_MODULO_GRADUACION.md` | Checklist del módulo de graduación |
| A | `documentacion/modulo-graduacion/MODULO_GRADUACION_REQUISITOS_INICIALES.md` | Requisitos iniciales de graduación |
| A | `documentacion/modulo-graduacion/README.md` | README del módulo de graduación |
| A | `documentacion/modulo-graduacion/inicializar-modulo-graduacion.sh` | Script de inicialización |
| A | `documentacion/modulo-graduacion/verificar-modulo-graduacion.sh` | Script de verificación |

### 4. Configuración de OpenCode / Skills

| Estado | Archivo | Descripción |
|---|---|---|
| A | `.opencode/CONTEXT_LOGGING_GRADUACION.md` | Contexto de logging de graduación |
| A | `.opencode/database-context.md` | Contexto de base de datos |
| A | `.opencode/skills/database-core/SKILL.md` | Skill: database-core |
| A | `.opencode/skills/database-graduacion/SKILL.md` | Skill: database-graduacion |

### 5. Módulo Eep — Controladores Nuevos

| Estado | Archivo | Descripción |
|---|---|---|
| A | `module/Eep/src/Controller/EvaluacionDocenteController.php` | Evaluación docente |
| A | `module/Eep/src/Controller/ExamenController.php` | Gestión de exámenes |
| A | `module/Eep/src/Controller/FormularioAdmisionController.php` | Formulario de admisión |
| A | `module/Eep/src/Controller/StudentGraduationController.php` | Graduación de estudiantes |
| A | `module/Eep/src/Controller/Factory/AssignmentControllerFactory.php` | Factory de AssignmentController |
| A | `module/Eep/src/Controller/Factory/EvaluacionDocenteControllerFactory.php` | Factory de EvaluacionDocenteController |
| A | `module/Eep/src/Controller/Factory/ExamenControllerFactory.php` | Factory de ExamenController |
| A | `module/Eep/src/Controller/Factory/FormularioAdmisionControllerFactory.php` | Factory de FormularioAdmisionController |
| A | `module/Eep/src/Controller/Factory/StudentGraduationControllerFactory.php` | Factory de StudentGraduationController |

### 6. Módulo Eep — Entidades Nuevas

| Estado | Archivo | Descripción |
|---|---|---|
| A | `module/Eep/src/Entity/CampoFormulario.php` | Campo de formulario |
| A | `module/Eep/src/Entity/FormularioAdmision.php` | Formulario de admisión |
| A | `module/Eep/src/Entity/RespuestaAspirante.php` | Respuesta de aspirante |

### 7. Módulo Eep — Formularios Nuevos

| Estado | Archivo | Descripción |
|---|---|---|
| A | `module/Eep/src/Form/FormularioAdmisionForm.php` | Formulario de admisión |
| A | `module/Eep/src/Form/RecoverPasswordForm.php` | Recuperación de contraseña |

### 8. Módulo Eep — Servicios Nuevos

| Estado | Archivo | Descripción |
|---|---|---|
| A | `module/Eep/src/Service/AutorizacionImpresionManager.php` | Autorización de impresión |
| A | `module/Eep/src/Service/CartaExaminadoresManager.php` | Cartas a examinadores |
| A | `module/Eep/src/Service/CartaGenerator.php` | Generador de cartas |
| A | `module/Eep/src/Service/EvaluacionDocenteGraficaService.php` | Gráficas de evaluación docente |
| A | `module/Eep/src/Service/EvaluacionDocenteManager.php` | Gestión de evaluación docente |
| A | `module/Eep/src/Service/ExamenManager.php` | Gestión de exámenes |
| A | `module/Eep/src/Service/FormularioAdmisionManager.php` | Gestión de formulario de admisión |
| A | `module/Eep/src/Service/MailManager.php` | Gestión de correos (SMTP) |
| A | `module/Eep/src/Service/StudentGraduationManager.php` | Gestión de graduación de estudiantes |
| A | `module/Eep/src/Service/Factory/AutorizacionImpresionManagerFactory.php` | Factory correspondiente |
| A | `module/Eep/src/Service/Factory/CartaExaminadoresManagerFactory.php` | Factory correspondiente |
| A | `module/Eep/src/Service/Factory/CartaGeneratorFactory.php` | Factory correspondiente |
| A | `module/Eep/src/Service/Factory/EvaluacionDocenteManagerFactory.php` | Factory correspondiente |
| A | `module/Eep/src/Service/Factory/ExamenManagerFactory.php` | Factory correspondiente |
| A | `module/Eep/src/Service/Factory/FormularioAdmisionManagerFactory.php` | Factory correspondiente |
| A | `module/Eep/src/Service/Factory/MailManagerFactory.php` | Factory correspondiente |
| A | `module/Eep/src/Service/Factory/StudentGraduationManagerFactory.php` | Factory correspondiente |

### 9. Módulo Eep — Vistas Nuevas

#### Evaluación Docente
- `module/Eep/view/eep/evaluacion-docente/descargar-pdf-graficas.phtml`
- `module/Eep/view/eep/evaluacion-docente/evaluar.phtml`
- `module/Eep/view/eep/evaluacion-docente/index.phtml`
- `module/Eep/view/eep/evaluacion-docente/reporte-docente.phtml`
- `module/Eep/view/eep/evaluacion-docente/ver-graficas.phtml`

#### Examen / Actas
- `module/Eep/view/eep/examen/acta-examen-general.phtml`
- `module/Eep/view/eep/examen/acta-examen-privado.phtml`
- `module/Eep/view/eep/examen/actas-examen-general.phtml`
- `module/Eep/view/eep/examen/autorizacion-impresion.phtml`
- `module/Eep/view/eep/examen/carta-examinadores.phtml`
- `module/Eep/view/eep/examen/configurar-autorizacion.phtml`
- `module/Eep/view/eep/examen/evaluacion-examen-privado.phtml`
- `module/Eep/view/eep/examen/evaluacion-privado.phtml`
- `module/Eep/view/eep/examen/index.phtml`
- `module/Eep/view/eep/examen/iniciar-proceso.phtml`
- `module/Eep/view/eep/examen/notificacion-grupal.phtml`
- `module/Eep/view/eep/examen/papeleria.phtml`
- `module/Eep/view/eep/examen/partial/paso1-papeleria.phtml`
- `module/Eep/view/eep/examen/partial/paso2-documentacion.phtml`
- `module/Eep/view/eep/examen/partial/paso3-terna.phtml`
- `module/Eep/view/eep/examen/partial/paso3-terna.phtml.bak`
- `module/Eep/view/eep/examen/partial/paso4-notificacion.phtml`
- `module/Eep/view/eep/examen/partial/paso5-carta-examinadores.phtml`
- `module/Eep/view/eep/examen/previsualizar-acta-examen-privado.phtml`
- `module/Eep/view/eep/examen/revisarpapeleria.phtml`
- `module/Eep/view/eep/examen/solicitudes.phtml`
- `module/Eep/view/eep/examen/ver-carta.phtml`
- `module/Eep/view/eep/examen/ver-matriz.phtml`

#### Formulario de Admisión
- `module/Eep/view/eep/formulario-admision/crear.phtml`
- `module/Eep/view/eep/formulario-admision/editar-respuesta.phtml`
- `module/Eep/view/eep/formulario-admision/index.phtml`
- `module/Eep/view/eep/formulario-admision/public.phtml`
- `module/Eep/view/eep/formulario-admision/registrar-aspirante.phtml`
- `module/Eep/view/eep/formulario-admision/respuestas.phtml`

#### Graduación de Estudiantes
- `module/Eep/view/eep/student-graduation/configurar-madrina-padrino.phtml`
- `module/Eep/view/eep/student-graduation/index.phtml`
- `module/Eep/view/eep/student-graduation/partial/paso1-solicitud-examen.phtml`
- `module/Eep/view/eep/student-graduation/partial/paso2-terna.phtml`
- `module/Eep/view/eep/student-graduation/partial/paso5-carta-examinadores.phtml`
- `module/Eep/view/eep/student-graduation/partial/paso6-autorizacion-impresion.phtml`

#### Usuario / Otros
- `module/Eep/view/eep/user/recover-password.phtml`

### 10. Módulo Eep — Archivos Modificados

| Estado | Archivo | Descripción |
|---|---|---|
| M | `module/Eep/config/access_filter.php` | Filtros de acceso (permisos nuevos) |
| M | `module/Eep/config/menus.php` | Menús de navegación |
| M | `module/Eep/config/module.config.php` | Configuración de rutas y servicios |
| M | `module/Eep/src/Controller/AssignmentController.php` | AssignmentController modificado |
| M | `module/Eep/src/Controller/Plugin/PluginHandler.php` | PluginHandler modificado |
| M | `module/Eep/src/Controller/UserController.php` | UserController modificado |
| M | `module/Eep/src/Entity/Role.php` | Entidad Role modificada |
| M | `module/Eep/src/Entity/User.php` | Entidad User modificada |
| M | `module/Eep/src/Form/EditUserForm.php` | Formulario de edición de usuario |
| M | `module/Eep/src/Module.php` | Módulo Eep modificado |
| M | `module/Eep/src/Service/AssignmentManager.php` | AssignmentManager modificado |
| M | `module/Eep/src/Service/AuthManager.php` | AuthManager modificado |
| M | `module/Eep/src/Service/SatuManager.php` | SatuManager modificado |
| M | `module/Eep/src/Service/TimetableManager.php` | TimetableManager modificado |
| M | `module/Eep/src/Service/UserManager.php` | UserManager modificado |
| M | `module/Eep/src/ValueObject/View.php` | View modificado |
| M | `module/Eep/view/eep/auth/login.phtml` | Vista de login |
| M | `module/Eep/view/eep/user/edit-user.phtml` | Vista de edición de usuario |
| M | `module/Eep/view/eep/user/log-view.phtml` | Vista de logs |
| M | `module/Eep/view/eep/user/student-search.phtml` | Vista de búsqueda de estudiantes |
| M | `module/Eep/view/layout/layout.phtml` | Layout principal |
| M | `module/Application/view/layout/layout-eep.phtml` | Layout de la aplicación |

### 11. Value Objects

| Estado | Archivo | Descripción |
|---|---|---|
| A | `module/Eep/src/ValueObject/MenuGroup.php` | Grupos de menú |

### 12. Archivos de Datos / Assets

| Estado | Archivo | Descripción |
|---|---|---|
| A | `data/fonts/DejaVuSans.ttf` | Fuente para generación de PDFs |
| A | `data/graduacion/global/cartas-descarga/*.docx` (5 archivos) | Cartas generadas |
| A | `data/graduacion/global/documentos-soporte/*.jpg,*.png,*.pdf` (3 archivos) | Documentos de soporte |
| A | `data/graduacion/global/requisitos-apoyo/*.pdf` (1 archivo) | Requisitos de apoyo |
| A | `data/graduacion/plantillas/carta-examinadores/README.md` | README de plantillas |
| A | `data/graduacion/plantillas/carta-examinadores/general.docx` | Plantilla de carta general |

### 13. Frontend / Público

| Estado | Archivo | Descripción |
|---|---|---|
| A | `public/img/email-footer.jpg` | Imagen de pie de correo |
| A | `public/js/README-TOAST-SERVICE.md` | README del servicio de toast |
| A | `public/js/confirm-service.js` | Servicio de confirmación |
| A | `public/js/toast-service.js` | Servicio de notificaciones toast |
| A | `public/toast-example.html` | Ejemplo de toast |

### 14. Tareas Programadas

| Estado | Archivo | Descripción |
|---|---|---|
| A | `scheduledTask/README.md` | Documentación de tareas programadas |
| A | `scheduledTask/test-carta.php` | Prueba de generación de cartas |

---

## Módulos Funcionales Añadidos en `development`

1. **Evaluación Docente** (`EvaluacionDocenteController` + servicios + vistas + tabla en DB)
2. **Formulario de Admisión** (`FormularioAdmisionController` + entidades + formularios + vistas + tabla en DB)
3. **Módulo de Graduación** (`StudentGraduationController` + servicios + vistas + múltiples scripts SQL)
4. **Gestión de Exámenes** (`ExamenController` + servicios + vistas + actas + cartas a examinadores)
5. **Recuperación de Contraseña** (formulario + servicio de mail + vista)
6. **Autorización de Impresión** (servicio + vistas + configuración)

---

## Notas

- Los archivos temporales de sesión (`data/sessiones/sess_*`) fueron excluidos del listado.
- `development` tiene **81 tablas** en la base de datos vs. **45 tablas** en `main`.
- La funcionalidad de `Zend\Mail` fue habilitada en `development` (permite recuperación de contraseña y notificaciones por correo).
- El contenedor Docker de `dev` usa **PHP 7.4** (`php:7.4-apache`) mientras que `prod` usa **PHP 7.0** (`php:7.0-apache`).
