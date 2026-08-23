# Mejoras Realizadas — Rama `development`

**Comparación:** Primer commit (`ee5962b` — estado inicial de producción) vs. HEAD actual (`4595f38`).
**Fecha de generación:** 2026-08-22

---

## Resumen General

| Estado | Cantidad | Descripción |
|---|---|---|
| **A** (Agregado / Creado) | **119** | Archivos nuevos |
| **M** (Modificado) | **28** | Archivos existentes alterados |
| **D** (Eliminado) | **2** | Archivos eliminados |
| **R100** (Renombrado) | **0*** | Sin renombros en el diff neto |
| **Total (reales)** | **149** | Archivos afectados del proyecto |

> *Nota:* Se excluyen del conteo **~9,039 archivos temporales de sesión** (`data/sessiones/sess_*`) que fueron eliminados automáticamente por el `.gitignore` actualizado.

---

## Estructura de Carpetas Nuevas

```
20250717Postgrados/ (dev)
├── .opencode/                          ← Skills y contexto para agentes IA
│   ├── skills/
│   │   ├── database-core/
│   │   └── database-graduacion/
│   ├── CONTEXT_LOGGING_GRADUACION.md
│   └── database-context.md
├── database/                           ← Scripts SQL organizados
│   ├── Demos/
│   │   └── new-users/
│   └── modulo graduacion/
├── data/
│   ├── fonts/
│   └── graduacion/
│       ├── global/
│       │   ├── cartas-descarga/
│       │   ├── documentos-soporte/
│       │   └── requisitos-apoyo/
│       └── plantillas/
│           └── carta-examinadores/
├── documentacion/
│   ├── general/
│   └── modulo-graduacion/
├── docker/                             ← Dockerfile movido aquí
├── module/Eep/
│   ├── src/
│   │   ├── Controller/
│   │   │   └── Factory/
│   │   ├── Entity/
│   │   ├── Form/
│   │   ├── Service/
│   │   │   └── Factory/
│   │   └── ValueObject/
│   └── view/eep/
│       ├── evaluacion-docente/
│       ├── examen/
│       │   └── partial/
│       ├── formulario-admision/
│       ├── student-graduation/
│       │   └── partial/
│       └── user/
└── public/
    ├── img/
    └── js/
```

---

## Archivos Creados (A = 119)

### Configuración del Proyecto
| Archivo | Descripción |
|---|---|
| `AGENTS.md` | Documentación para agentes de IA |
| `opencode.json` | Configuración de OpenCode |

### Base de Datos (Scripts SQL)
| Archivo | Descripción |
|---|---|
| `database/20250718Postgrados.sql` | Dump SQL inicial renombrado desde raíz |
| `database/evaluacion_docente.sql` | Schema de evaluación docente |
| `database/modulo_aspirantes_final.sql` | Schema de formulario de admisión |
| `database/modulo graduacion/modulo_graduacion_schema.sql` | Schema principal de graduación |
| `database/modulo graduacion/ejecuciones_extra.sql` | Ejecuciones adicionales |
| `database/modulo graduacion/estructura_archivos.sql` | Estructura de carpetas |
| `database/modulo graduacion/matriz_evaluacion_completo.sql` | Matriz de evaluación |
| `database/Demos/new-users/estudiantes.sql` | Demo de estudiantes |
| `database/Demos/new-users/hash.php` | Utilidad de hashing |
| `database/Demos/new-users/users.sql` | Demo de usuarios |

### Documentación
| Archivo | Descripción |
|---|---|
| `documentacion/CAMBIOS.md` | Registro de cambios técnicos |
| `documentacion/INSTALACION_PRODUCCION_EVALUACION_DOCENTE.md` | Instalación de evaluación docente |
| `documentacion/INSTALACION_PRODUCCION_FORMULARIO_ADMISION.md` | Instalación de formulario de admisión |
| `documentacion/INSTALACION_PRODUCCION_GENERAL.md` | Guía de instalación general |
| `documentacion/general/DOCUMENTACION_PROYECTO.md` | Documentación general |
| `documentacion/general/ESTRUCTURA_ARCHIVOS_GRADUACION.md` | Estructura de archivos |
| `documentacion/general/flujo_formulario_admision.md` | Flujo del formulario |
| `documentacion/modulo-graduacion/CHECKLIST_MODULO_GRADUACION.md` | Checklist del módulo |
| `documentacion/modulo-graduacion/MODULO_GRADUACION_REQUISITOS_INICIALES.md` | Requisitos iniciales |
| `documentacion/modulo-graduacion/README.md` | README del módulo |
| `documentacion/modulo-graduacion/inicializar-modulo-graduacion.sh` | Script de inicialización |
| `documentacion/modulo-graduacion/verificar-modulo-graduacion.sh` | Script de verificación |

### Módulo Eep — Controladores (Nuevos)
| Archivo | Descripción |
|---|---|
| `module/Eep/src/Controller/EvaluacionDocenteController.php` | Evaluación docente |
| `module/Eep/src/Controller/ExamenController.php` | Gestión de exámenes |
| `module/Eep/src/Controller/FormularioAdmisionController.php` | Formulario de admisión |
| `module/Eep/src/Controller/StudentGraduationController.php` | Graduación de estudiantes |
| `module/Eep/src/Controller/Factory/AssignmentControllerFactory.php` | Factory |
| `module/Eep/src/Controller/Factory/EvaluacionDocenteControllerFactory.php` | Factory |
| `module/Eep/src/Controller/Factory/ExamenControllerFactory.php` | Factory |
| `module/Eep/src/Controller/Factory/FormularioAdmisionControllerFactory.php` | Factory |
| `module/Eep/src/Controller/Factory/StudentGraduationControllerFactory.php` | Factory |

### Módulo Eep — Entidades (Nuevas)
| Archivo | Descripción |
|---|---|
| `module/Eep/src/Entity/CampoFormulario.php` | Campo dinámico de formulario |
| `module/Eep/src/Entity/FormularioAdmision.php` | Formulario de admisión |
| `module/Eep/src/Entity/RespuestaAspirante.php` | Respuesta de aspirante |

### Módulo Eep — Formularios (Nuevos)
| Archivo | Descripción |
|---|---|
| `module/Eep/src/Form/FormularioAdmisionForm.php` | Formulario de admisión |
| `module/Eep/src/Form/RecoverPasswordForm.php` | Recuperación de contraseña |

### Módulo Eep — Servicios (Nuevos)
| Archivo | Descripción |
|---|---|
| `module/Eep/src/Service/EvaluacionDocenteManager.php` | Gestión de evaluación docente |
| `module/Eep/src/Service/EvaluacionDocenteGraficaService.php` | Gráficas de evaluación |
| `module/Eep/src/Service/ExamenManager.php` | Gestión de exámenes |
| `module/Eep/src/Service/FormularioAdmisionManager.php` | Gestión de formularios de admisión |
| `module/Eep/src/Service/StudentGraduationManager.php` | Gestión de graduación |
| `module/Eep/src/Service/MailManager.php` | Envío de correos SMTP |
| `module/Eep/src/Service/CartaGenerator.php` | Generador de cartas |
| `module/Eep/src/Service/CartaExaminadoresManager.php` | Cartas a examinadores |
| `module/Eep/src/Service/AutorizacionImpresionManager.php` | Autorización de impresión |
| `module/Eep/src/Service/Factory/EvaluacionDocenteManagerFactory.php` | Factory |
| `module/Eep/src/Service/Factory/ExamenManagerFactory.php` | Factory |
| `module/Eep/src/Service/Factory/FormularioAdmisionManagerFactory.php` | Factory |
| `module/Eep/src/Service/Factory/StudentGraduationManagerFactory.php` | Factory |
| `module/Eep/src/Service/Factory/MailManagerFactory.php` | Factory |
| `module/Eep/src/Service/Factory/CartaGeneratorFactory.php` | Factory |
| `module/Eep/src/Service/Factory/CartaExaminadoresManagerFactory.php` | Factory |
| `module/Eep/src/Service/Factory/AutorizacionImpresionManagerFactory.php` | Factory |

### Módulo Eep — Vistas / Templates (Nuevos)
| Archivo | Descripción |
|---|---|
| `module/Eep/view/eep/evaluacion-docente/index.phtml` | Listado de evaluación docente |
| `module/Eep/view/eep/evaluacion-docente/evaluar.phtml` | Formulario de evaluación |
| `module/Eep/view/eep/evaluacion-docente/reporte-docente.phtml` | Reporte de docente |
| `module/Eep/view/eep/evaluacion-docente/ver-graficas.phtml` | Ver gráficas |
| `module/Eep/view/eep/evaluacion-docente/descargar-pdf-graficas.phtml` | Descarga PDF |
| `module/Eep/view/eep/examen/index.phtml` | Listado de exámenes |
| `module/Eep/view/eep/examen/iniciar-proceso.phtml` | Iniciar proceso de examen |
| `module/Eep/view/eep/examen/papeleria.phtml` | Papelería del examen |
| `module/Eep/view/eep/examen/revisarpapeleria.phtml` | Revisar papelería |
| `module/Eep/view/eep/examen/solicitudes.phtml` | Solicitudes de examen |
| `module/Eep/view/eep/examen/acta-examen-general.phtml` | Acta general |
| `module/Eep/view/eep/examen/acta-examen-privado.phtml` | Acta privado |
| `module/Eep/view/eep/examen/actas-examen-general.phtml` | Listado actas generales |
| `module/Eep/view/eep/examen/carta-examinadores.phtml` | Carta a examinadores |
| `module/Eep/view/eep/examen/autorizacion-impresion.phtml` | Autorización de impresión |
| `module/Eep/view/eep/examen/configurar-autorizacion.phtml` | Configurar autorización |
| `module/Eep/view/eep/examen/evaluacion-examen-privado.phtml` | Evaluación privado |
| `module/Eep/view/eep/examen/evaluacion-privado.phtml` | Evaluación privado (alt) |
| `module/Eep/view/eep/examen/notificacion-grupal.phtml` | Notificación grupal |
| `module/Eep/view/eep/examen/previsualizar-acta-examen-privado.phtml` | Previsualizar acta |
| `module/Eep/view/eep/examen/ver-carta.phtml` | Ver carta |
| `module/Eep/view/eep/examen/ver-matriz.phtml` | Ver matriz de evaluación |
| `module/Eep/view/eep/examen/partial/paso1-papeleria.phtml` | Paso 1: papelería |
| `module/Eep/view/eep/examen/partial/paso2-documentacion.phtml` | Paso 2: documentación |
| `module/Eep/view/eep/examen/partial/paso3-terna.phtml` | Paso 3: terna |
| `module/Eep/view/eep/examen/partial/paso3-terna.phtml.bak` | Backup del paso 3 |
| `module/Eep/view/eep/examen/partial/paso4-notificacion.phtml` | Paso 4: notificación |
| `module/Eep/view/eep/examen/partial/paso5-carta-examinadores.phtml` | Paso 5: cartas |
| `module/Eep/view/eep/formulario-admision/index.phtml` | Listado de formularios |
| `module/Eep/view/eep/formulario-admision/crear.phtml` | Crear formulario |
| `module/Eep/view/eep/formulario-admision/public.phtml` | Vista pública del formulario |
| `module/Eep/view/eep/formulario-admision/registrar-aspirante.phtml` | Registrar aspirante |
| `module/Eep/view/eep/formulario-admision/respuestas.phtml` | Ver respuestas |
| `module/Eep/view/eep/formulario-admision/editar-respuesta.phtml` | Editar respuesta |
| `module/Eep/view/eep/student-graduation/index.phtml` | Listado de graduación |
| `module/Eep/view/eep/student-graduation/configurar-madrina-padrino.phtml` | Configurar madrina/padrino |
| `module/Eep/view/eep/student-graduation/partial/paso1-solicitud-examen.phtml` | Paso 1: solicitud |
| `module/Eep/view/eep/student-graduation/partial/paso2-terna.phtml` | Paso 2: terna |
| `module/Eep/view/eep/student-graduation/partial/paso5-carta-examinadores.phtml` | Paso 5: cartas |
| `module/Eep/view/eep/student-graduation/partial/paso6-autorizacion-impresion.phtml` | Paso 6: autorización |
| `module/Eep/view/eep/user/recover-password.phtml` | Recuperar contraseña |

### Assets y Archivos de Datos
| Archivo | Descripción |
|---|---|
| `data/fonts/DejaVuSans.ttf` | Fuente para PDFs |
| `data/graduacion/global/cartas-descarga/*.docx` (5 archivos) | Cartas generadas |
| `data/graduacion/global/documentos-soporte/*.jpg,*.png,*.pdf` (3 archivos) | Documentos de soporte |
| `data/graduacion/global/requisitos-apoyo/*.pdf` (1 archivo) | Requisitos de apoyo |
| `data/graduacion/plantillas/carta-examinadores/README.md` | README de plantillas |
| `data/graduacion/plantillas/carta-examinadores/general.docx` | Plantilla de carta general |
| `public/img/email-footer.jpg` | Imagen de pie de correo |
| `public/js/confirm-service.js` | Servicio de confirmación (JS) |
| `public/js/toast-service.js` | Servicio de notificaciones toast (JS) |
| `public/js/README-TOAST-SERVICE.md` | Documentación del servicio toast |

### Configuración OpenCode
| Archivo | Descripción |
|---|---|
| `.opencode/CONTEXT_LOGGING_GRADUACION.md` | Contexto de logging |
| `.opencode/database-context.md` | Contexto de base de datos |
| `.opencode/skills/database-core/SKILL.md` | Skill database-core |
| `.opencode/skills/database-graduacion/SKILL.md` | Skill database-graduacion |

### Archivo de referencia propio
| Archivo | Descripción |
|---|---|
| `CAMBIOS_DEVELOPMENT.md` | Este documento de seguimiento |

---

## Archivos Modificados (M = 28)

### Configuración del Proyecto
| Archivo | Descripción del cambio |
|---|---|
| `.gitignore` | Reglas actualizadas (excluir `data/`, `vendor/`, `.env`, sesiones) |
| `composer.json` | Dependencias actualizadas |
| `composer.lock` | Lock de dependencias actualizado |
| `config/autoload/global.php` | Configuración de DB y sesiones |
| `config/modules.config.php` | Módulos habilitados (incluye `Zend\Mail`) |
| `docker-compose.yml` | Puertos 8080/3307, instrucciones comentadas |

### Layouts
| Archivo | Descripción del cambio |
|---|---|
| `module/Application/view/layout/layout-eep.phtml` | Layout de la aplicación Eep |
| `module/Eep/view/layout/layout.phtml` | Layout principal del módulo Eep |

### Configuración del Módulo Eep
| Archivo | Descripción del cambio |
|---|---|
| `module/Eep/config/access_filter.php` | ACL — nuevas acciones/permisos para módulos nuevos |
| `module/Eep/config/menus.php` | Menús de navegación — nuevas entradas |
| `module/Eep/config/module.config.php` | Rutas, factories y servicios nuevos |
| `module/Eep/src/Module.php` | Registro de listeners/view helpers |

### Controladores Modificados
| Archivo | Descripción del cambio |
|---|---|
| `module/Eep/src/Controller/AssignmentController.php` | AssignmentController (modificaciones menores) |
| `module/Eep/src/Controller/Plugin/PluginHandler.php` | PluginHandler |
| `module/Eep/src/Controller/UserController.php` | UserController — añadida lógica de recuperación de contraseña |

### Entidades Modificadas
| Archivo | Descripción del cambio |
|---|---|
| `module/Eep/src/Entity/Role.php` | Entidad Role (posiblemente nuevos campos o métodos) |
| `module/Eep/src/Entity/User.php` | Entidad User (posiblemente nuevos campos o métodos) |

### Formularios Modificados
| Archivo | Descripción del cambio |
|---|---|
| `module/Eep/src/Form/EditUserForm.php` | Formulario de edición de usuario |

### Servicios Modificados
| Archivo | Descripción del cambio |
|---|---|
| `module/Eep/src/Service/AssignmentManager.php` | AssignmentManager |
| `module/Eep/src/Service/AuthManager.php` | AuthManager |
| `module/Eep/src/Service/SatuManager.php` | SatuManager |
| `module/Eep/src/Service/TimetableManager.php` | TimetableManager |
| `module/Eep/src/Service/UserManager.php` | UserManager — añadida lógica de recuperación de clave |

### Value Objects
| Archivo | Descripción del cambio |
|---|---|
| `module/Eep/src/ValueObject/View.php` | View modificado |

### Vistas Modificadas
| Archivo | Descripción del cambio |
|---|---|
| `module/Eep/view/eep/auth/login.phtml` | Login — posiblemente enlace a recuperar contraseña |
| `module/Eep/view/eep/user/edit-user.phtml` | Edición de usuario |
| `module/Eep/view/eep/user/log-view.phtml` | Vista de logs de auditoría |
| `module/Eep/view/eep/user/student-search.phtml` | Búsqueda de estudiantes |

---

## Archivos Eliminados (D = 2)

| Archivo | Motivo |
|---|---|
| `20250718Postgrados` | Dump SQL renombrado a `database/20250718Postgrados.sql` |
| `Dockerfile` | Dockerfile movido a `docker/Dockerfile` |

---

## Análisis por Módulos Funcionales

### Módulo 1: Evaluación Docente
- **Controlador:** `EvaluacionDocenteController.php`
- **Servicios:** `EvaluacionDocenteManager.php`, `EvaluacionDocenteGraficaService.php`
- **Vistas:** 5 templates en `view/eep/evaluacion-docente/`
- **Tablas SQL:** 4 tablas (`evaluacion_seccion`, `evaluacion_pregunta`, `evaluacion_respuesta`, `evaluacion_respuesta_detalle`)
- **Estado:** Completo y funcional

### Módulo 2: Formulario de Admisión
- **Controlador:** `FormularioAdmisionController.php`
- **Entidades:** `CampoFormulario.php`, `FormularioAdmision.php`, `RespuestaAspirante.php`
- **Formulario:** `FormularioAdmisionForm.php`
- **Servicio:** `FormularioAdmisionManager.php`
- **Vistas:** 6 templates en `view/eep/formulario-admision/`
- **Tablas SQL:** 4 tablas (`formulario_admision`, `campo_formulario`, `respuesta_aspirante`, `respuesta_campo`)
- **Estado:** Completo y funcional

### Módulo 3: Graduación de Estudiantes
- **Controlador:** `StudentGraduationController.php`
- **Servicio:** `StudentGraduationManager.php`
- **Vistas:** 6 templates en `view/eep/student-graduation/`
- **Tablas SQL:** ~20 tablas del módulo de graduación
- **Estado:** Completo y funcional

### Módulo 4: Gestión de Exámenes (Actas, Cartas, Autorización)
- **Controlador:** `ExamenController.php`
- **Servicios:** `ExamenManager.php`, `CartaGenerator.php`, `CartaExaminadoresManager.php`, `AutorizacionImpresionManager.php`
- **Vistas:** 24 templates en `view/eep/examen/` (incluyendo partials)
- **Assets:** Plantillas Word en `data/graduacion/plantillas/`, cartas generadas en `data/graduacion/global/cartas-descarga/`
- **Estado:** Completo y funcional

### Módulo 5: Recuperación de Contraseña
- **Formulario:** `RecoverPasswordForm.php`
- **Servicio:** `MailManager.php` (SMTP)
- **Vista:** `recover-password.phtml`
- **Modificaciones en:** `UserController.php`, `UserManager.php`, `login.phtml`
- **Estado:** Completo y funcional

---

## Notas sobre Archivos Ignorados por Git (que deben existir localmente)

Aunque no aparecen en el diff anterior porque Git los ignora, las siguientes carpetas/archivos son **necesarios** para el funcionamiento:

| Carpeta/Archivo | Propósito |
|---|---|
| `vendor/` | Dependencias de Composer |
| `config/autoload/local.php` | Credenciales locales (NO subir a Git) |
| `data/cache/` | Caché de la aplicación |
| `data/sessiones/` | Sesiones de PHP en disco (115 archivos actualmente) |
| `data/graduacion/procesos/` | Procesos individuales de graduación (13 archivos) |
| `data/admisiones/` | Archivos del módulo de admisiones (1 archivo) |

---

## Métricas de Crecimiento

| Métrica | Valor |
|---|---|
| Tablas en base de datos (dev) | **81** |
| Tablas en base de datos (prod inicial) | **45** |
| Templates `.phtml` nuevos | **~50** |
| Clases PHP nuevas (controllers + services + entities + forms) | **~35** |
| Scripts SQL adicionales | **8** |
| Archivos de documentación | **12** |
| Archivos de assets (fuentes, imágenes, JS, plantillas) | **~15** |

---

*Documento generado automáticamente a partir del diff `ee5962b..HEAD` (rama `development`).*
