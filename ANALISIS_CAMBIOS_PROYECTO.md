# Análisis de Cambios del Proyecto Postgrados

> Comparación entre el primer commit de producción (`ee5962b`) y el estado actual del working directory.
> Fecha de análisis: 2026-08-22

---

## Resumen

| Categoría | Cantidad |
|-----------|----------|
| Archivos en primer commit | 9,537 |
| Archivos actuales (tracked) | 621 |
| **Archivos nuevos** | 126 |
| **Archivos modificados** | 28 |
| **Archivos eliminados** | 2 |

---

## 1. Archivos Nuevos (creados después del primer commit)

Los archivos nuevos se organizan por área funcional. Cada sección muestra los archivos que componen esa funcionalidad.

### Documentación

*Cantidad: 13 archivo(s)*

- `documentacion/CAMBIOS.md` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/general/DOCUMENTACION_PROYECTO.md` — `04e61ac52f897c61ccaa6278112d6f2d06a132db` (2026-08-22)
- `documentacion/general/ESTRUCTURA_ARCHIVOS_GRADUACION.md` — `04e61ac52f897c61ccaa6278112d6f2d06a132db` (2026-08-22)
- `documentacion/general/flujo_formulario_admision.md` — `04e61ac52f897c61ccaa6278112d6f2d06a132db` (2026-08-22)
- `documentacion/INSTALACION_PRODUCCION_EVALUACION_DOCENTE.md` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/INSTALACION_PRODUCCION_FORMULARIO_ADMISION.md` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/INSTALACION_PRODUCCION_GENERAL.md` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/INSTALACION_PRODUCCION_PERMISOS.md` — `3b59194df15395d7b816b0accfb70fdb7aab3e73` (2026-08-22)
- `documentacion/modulo-graduacion/CHECKLIST_MODULO_GRADUACION.md` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/modulo-graduacion/inicializar-modulo-graduacion.sh` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/modulo-graduacion/MODULO_GRADUACION_REQUISITOS_INICIALES.md` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/modulo-graduacion/README.md` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)
- `documentacion/modulo-graduacion/verificar-modulo-graduacion.sh` — `496c25911116a0754d95260d165d65063d4fa308` (2026-08-22)

### Base de datos (scripts SQL)

*Cantidad: 11 archivo(s)*

- `database/20250718Postgrados.sql` — `baeea6cc36330913f49da448809fb2d20f7bc413` (2025-08-17)
- `database/Demos/new-users/estudiantes.sql` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `database/Demos/new-users/hash.php` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `database/Demos/new-users/users.sql` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `database/evaluacion_docente.sql` — `a44fa1d95307d138bb56d2ba3d7eb96cd2aaeb0f` (2026-05-27)
- `database/modulo graduacion/ejecuciones_extra.sql` — `a345ec22531c7ccef391c8223afe99dffbcdeefe` (2026-07-26)
- `database/modulo graduacion/estructura_archivos.sql` — `ebf4e565e107fedfdfcd3c7a26e15c25f38655c7` (2026-05-30)
- `database/modulo graduacion/matriz_evaluacion_completo.sql` — `a345ec22531c7ccef391c8223afe99dffbcdeefe` (2026-07-26)
- `database/modulo graduacion/modulo_graduacion_schema.sql` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `database/modulo_aspirantes_final.sql` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `database/recuperacion_contrasena.sql` — `5930ee774679167907edc17999fe06ff8e0bc9ed` (2026-08-22)

### Controllers (Capa de Control)

*Cantidad: 9 archivo(s)*

- `module/Eep/src/Controller/EvaluacionDocenteController.php` — `9e2956a67b4763327e3561acd14281208ee1e526` (2026-02-08)
- `module/Eep/src/Controller/ExamenController.php` — `a7b994d179688407c8cee277abd5387e50774006` (2026-02-20)
- `module/Eep/src/Controller/Factory/AssignmentControllerFactory.php` — `a44fa1d95307d138bb56d2ba3d7eb96cd2aaeb0f` (2026-05-27)
- `module/Eep/src/Controller/Factory/EvaluacionDocenteControllerFactory.php` — `a44fa1d95307d138bb56d2ba3d7eb96cd2aaeb0f` (2026-05-27)
- `module/Eep/src/Controller/Factory/ExamenControllerFactory.php` — `c608b512fc3169c35df92857a4fc9b011ff3b727` (2026-02-27)
- `module/Eep/src/Controller/Factory/FormularioAdmisionControllerFactory.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/src/Controller/Factory/StudentGraduationControllerFactory.php` — `86a0b388555465dd60d48137e7c9c106addd3012` (2026-03-23)
- `module/Eep/src/Controller/FormularioAdmisionController.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/src/Controller/StudentGraduationController.php` — `86a0b388555465dd60d48137e7c9c106addd3012` (2026-03-23)

### Servicios / Managers (Capa de Negocio)

*Cantidad: 17 archivo(s)*

- `module/Eep/src/Service/AutorizacionImpresionManager.php` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `module/Eep/src/Service/CartaExaminadoresManager.php` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/src/Service/CartaGenerator.php` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/src/Service/EvaluacionDocenteGraficaService.php` — `3d037d17e4cf30a5b6c59be0f42ca6f87a008f9f` (2026-08-20)
- `module/Eep/src/Service/EvaluacionDocenteManager.php` — `a44fa1d95307d138bb56d2ba3d7eb96cd2aaeb0f` (2026-05-27)
- `module/Eep/src/Service/ExamenManager.php` — `8f11afeb151924af2acfa500fa414a95c7fca7a1` (2026-02-27)
- `module/Eep/src/Service/Factory/AutorizacionImpresionManagerFactory.php` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `module/Eep/src/Service/Factory/CartaExaminadoresManagerFactory.php` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/src/Service/Factory/CartaGeneratorFactory.php` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/src/Service/Factory/EvaluacionDocenteManagerFactory.php` — `a44fa1d95307d138bb56d2ba3d7eb96cd2aaeb0f` (2026-05-27)
- `module/Eep/src/Service/Factory/ExamenManagerFactory.php` — `8f11afeb151924af2acfa500fa414a95c7fca7a1` (2026-02-27)
- `module/Eep/src/Service/Factory/FormularioAdmisionManagerFactory.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/src/Service/Factory/MailManagerFactory.php` — `dea790fe343e8716297b04e910d236e5de863b71` (2026-05-31)
- `module/Eep/src/Service/Factory/StudentGraduationManagerFactory.php` — `86a0b388555465dd60d48137e7c9c106addd3012` (2026-03-23)
- `module/Eep/src/Service/FormularioAdmisionManager.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/src/Service/MailManager.php` — `dea790fe343e8716297b04e910d236e5de863b71` (2026-05-31)
- `module/Eep/src/Service/StudentGraduationManager.php` — `86a0b388555465dd60d48137e7c9c106addd3012` (2026-03-23)

### Formularios (Validación de entrada)

*Cantidad: 3 archivo(s)*

- `module/Eep/src/Form/FormularioAdmisionForm.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/src/Form/RecoverPasswordForm.php` — `9985251eaf6ca9b570b96c56c5e6e3c0820f1fcd` (2025-08-23)
- `module/Eep/src/Form/ResetPasswordForm.php` — `5930ee774679167907edc17999fe06ff8e0bc9ed` (2026-08-22)

### Entidades (Modelo de datos)

*Cantidad: 3 archivo(s)*

- `module/Eep/src/Entity/CampoFormulario.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/src/Entity/FormularioAdmision.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/src/Entity/RespuestaAspirante.php` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)

### Value Objects (Constantes y VO)

*Cantidad: 1 archivo(s)*

- `module/Eep/src/ValueObject/MenuGroup.php` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)

### Vistas / Plantillas (Capa de Presentación)

*Cantidad: 44 archivo(s)*

- `module/Eep/view/eep/evaluacion-docente/descargar-pdf-graficas.phtml` — `3d037d17e4cf30a5b6c59be0f42ca6f87a008f9f` (2026-08-20)
- `module/Eep/view/eep/evaluacion-docente/evaluar.phtml` — `9e2956a67b4763327e3561acd14281208ee1e526` (2026-02-08)
- `module/Eep/view/eep/evaluacion-docente/index.phtml` — `9e2956a67b4763327e3561acd14281208ee1e526` (2026-02-08)
- `module/Eep/view/eep/evaluacion-docente/reporte-docente.phtml` — `ebac86def601a850e79e8722b59e74451dd4b2a6` (2026-05-27)
- `module/Eep/view/eep/evaluacion-docente/ver-graficas.phtml` — `5a4c6cd7622713d4b2045a0a3a4c43b3c5f33237` (2026-08-14)
- `module/Eep/view/eep/examen/acta-examen-general.phtml` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/eep/examen/acta-examen-privado.phtml` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/eep/examen/actas-examen-general.phtml` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/eep/examen/autorizacion-impresion.phtml` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `module/Eep/view/eep/examen/carta-examinadores.phtml` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/view/eep/examen/configurar-autorizacion.phtml` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `module/Eep/view/eep/examen/evaluacion-examen-privado.phtml` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/eep/examen/evaluacion-privado.phtml` — `f90ac1f6a451d889763b92197f8075a4fa4b6c9a` (2026-06-06)
- `module/Eep/view/eep/examen/index.phtml` — `a7b994d179688407c8cee277abd5387e50774006` (2026-02-20)
- `module/Eep/view/eep/examen/iniciar-proceso.phtml` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `module/Eep/view/eep/examen/notificacion-grupal.phtml` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `module/Eep/view/eep/examen/papeleria.phtml` — `a693905b715f1b2bfb702bcaf17d1ade43d7746a` (2026-02-21)
- `module/Eep/view/eep/examen/partial/paso1-papeleria.phtml` — `4a12b3dc7a242016bcdb2acdcd70b757017a8b48` (2026-02-21)
- `module/Eep/view/eep/examen/partial/paso2-documentacion.phtml` — `ee210ffe43117d83291379421d3bfa574d5b158b` (2026-02-21)
- `module/Eep/view/eep/examen/partial/paso3-terna.phtml` — `ee210ffe43117d83291379421d3bfa574d5b158b` (2026-02-21)
- `module/Eep/view/eep/examen/partial/paso3-terna.phtml.bak` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/eep/examen/partial/paso4-notificacion.phtml` — `27ea9263af36a5e8a0666f32adb29e9e297d566f` (2026-05-13)
- `module/Eep/view/eep/examen/partial/paso5-carta-examinadores.phtml` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/view/eep/examen/previsualizar-acta-examen-privado.phtml` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/eep/examen/revisarpapeleria.phtml` — `a693905b715f1b2bfb702bcaf17d1ade43d7746a` (2026-02-21)
- `module/Eep/view/eep/examen/solicitudes.phtml` — `a693905b715f1b2bfb702bcaf17d1ade43d7746a` (2026-02-21)
- `module/Eep/view/eep/examen/ver-carta.phtml` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/view/eep/examen/ver-matriz.phtml` — `f90ac1f6a451d889763b92197f8075a4fa4b6c9a` (2026-06-06)
- `module/Eep/view/eep/formulario-admision/crear.phtml` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/view/eep/formulario-admision/editar-respuesta.phtml` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/view/eep/formulario-admision/index.phtml` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/view/eep/formulario-admision/public.phtml` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/view/eep/formulario-admision/registrar-aspirante.phtml` — `441de646dab9cac42ad48f5d23b20f44ed11ad5a` (2026-08-09)
- `module/Eep/view/eep/formulario-admision/respuestas.phtml` — `50beb43738c144660acbca93668a6ad5e6092760` (2026-06-01)
- `module/Eep/view/eep/student-graduation/configurar-madrina-padrino.phtml` — `a345ec22531c7ccef391c8223afe99dffbcdeefe` (2026-07-26)
- `module/Eep/view/eep/student-graduation/index.phtml` — `86a0b388555465dd60d48137e7c9c106addd3012` (2026-03-23)
- `module/Eep/view/eep/student-graduation/partial/paso1-solicitud-examen.phtml` — `86a0b388555465dd60d48137e7c9c106addd3012` (2026-03-23)
- `module/Eep/view/eep/student-graduation/partial/paso2-terna.phtml` — `dc7d73f2dfc6e33c2e6ac475420756d5ddc3306f` (2026-03-28)
- `module/Eep/view/eep/student-graduation/partial/paso3-notificacion.phtml` — `dc7d73f2dfc6e33c2e6ac475420756d5ddc3306f` (2026-03-28)
- `module/Eep/view/eep/student-graduation/partial/paso5-carta-examinadores.phtml` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `module/Eep/view/eep/student-graduation/partial/paso6-autorizacion-impresion.phtml` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `module/Eep/view/eep/student-graduation/proceso.phtml` — `86a0b388555465dd60d48137e7c9c106addd3012` (2026-03-23)
- `module/Eep/view/eep/user/recover-password.phtml` — `9985251eaf6ca9b570b96c56c5e6e3c0820f1fcd` (2025-08-23)
- `module/Eep/view/eep/user/reset-password.phtml` — `5930ee774679167907edc17999fe06ff8e0bc9ed` (2026-08-22)

### Datos y Assets (plantillas, fuentes, archivos)

*Cantidad: 13 archivo(s)*

- `data/fonts/DejaVuSans.ttf` — `3d037d17e4cf30a5b6c59be0f42ca6f87a008f9f` (2026-08-20)
- `data/graduacion/global/cartas-descarga/01eeb2cd411d3e0a12ef13f8deb15e4a.docx` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `data/graduacion/global/cartas-descarga/15f9d48162d27b252ed283bfcc3710cb.docx` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `data/graduacion/global/cartas-descarga/1c83f329b1d7715bfabe3c73518e099b.docx` — `00d2be8a6e8457e200d9a6ed1941075a2a6f9153` (2026-08-06)
- `data/graduacion/global/cartas-descarga/8c094fa057b15aec7c4af7cbe38681c8.docx` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `data/graduacion/global/cartas-descarga/ca9a9bb89ec1b3c76323b670f079d2bd.docx` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `data/graduacion/global/documentos-soporte/09b8673336737ed1121273a55d830487.jpg` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `data/graduacion/global/documentos-soporte/5365a782bd94991094833c5418f3e567.png` — `4b509f32c0507373ab84e804102adf5240547c9b` (2026-05-31)
- `data/graduacion/global/documentos-soporte/cdfad457693de4bf66cee89a2a360e40.pdf` — `00d2be8a6e8457e200d9a6ed1941075a2a6f9153` (2026-08-06)
- `data/graduacion/global/requisitos-apoyo/req-0-d642f988648ab492d40aa4d1dd510b5a.pdf` — `a345ec22531c7ccef391c8223afe99dffbcdeefe` (2026-07-26)
- `data/graduacion/plantillas/carta-examinadores/general.docx` — `ebf4e565e107fedfdfcd3c7a26e15c25f38655c7` (2026-05-30)
- `data/graduacion/plantillas/carta-examinadores/README.md` — `ebf4e565e107fedfdfcd3c7a26e15c25f38655c7` (2026-05-30)
- `data/graduacion/procesos/.gitkeep` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)

### Infraestructura Docker

*Cantidad: 1 archivo(s)*

- `docker/Dockerfile` — `baeea6cc36330913f49da448809fb2d20f7bc413` (2025-08-17)

### Recursos públicos (img, css, js)

*Cantidad: 4 archivo(s)*

- `public/img/email-footer.jpg` — `dea790fe343e8716297b04e910d236e5de863b71` (2026-05-31)
- `public/js/confirm-service.js` — `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `public/js/README-TOAST-SERVICE.md` — `2ee5a57549cb391c82506e90bd2ed4396aeb6a09` (2026-04-04)
- `public/js/toast-service.js` — `2ee5a57549cb391c82506e90bd2ed4396aeb6a09` (2026-04-04)

### Raíz del proyecto

*Cantidad: 3 archivo(s)*

- `AGENTS.md` — `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)
- `mejoras-realizadas.md` — `3b59194df15395d7b816b0accfb70fdb7aab3e73` (2026-08-22)
- `opencode.json` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)

### Otras áreas

#### .opencode/
*Cantidad: 4 archivo(s)*

- `.opencode/CONTEXT_LOGGING_GRADUACION.md` — `43bbfd3ac6d3b8c432468cd25f4484ef79787109` (2026-08-15)
- `.opencode/database-context.md` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `.opencode/skills/database-core/SKILL.md` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `.opencode/skills/database-graduacion/SKILL.md` — `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)

---

## 2. Archivos Modificados (existentes en primer commit, con cambios)

Archivos que ya existían en el primer commit y han recibido modificaciones para soportar nuevas funcionalidades.

### Configuración global

*Cantidad: 2 archivo(s)*

- `config/autoload/global.php` — última modificación: `f63d8787de4754e40369e44a087ac9bc8b2ac310` (2025-08-17)
- `config/modules.config.php` — última modificación: `dea790fe343e8716297b04e910d236e5de863b71` (2026-05-31)

### Configuración del módulo Eep

*Cantidad: 3 archivo(s)*

- `module/Eep/config/access_filter.php` — última modificación: `5930ee774679167907edc17999fe06ff8e0bc9ed` (2026-08-22)
- `module/Eep/config/menus.php` — última modificación: `0af070589c1116cac965277ed9cbe57319aa43d4` (2026-08-03)
- `module/Eep/config/module.config.php` — última modificación: `5930ee774679167907edc17999fe06ff8e0bc9ed` (2026-08-22)

### Controllers

*Cantidad: 3 archivo(s)*

- `module/Eep/src/Controller/AssignmentController.php` — última modificación: `a44fa1d95307d138bb56d2ba3d7eb96cd2aaeb0f` (2026-05-27)
- `module/Eep/src/Controller/Plugin/PluginHandler.php` — última modificación: `43bbfd3ac6d3b8c432468cd25f4484ef79787109` (2026-08-15)
- `module/Eep/src/Controller/UserController.php` — última modificación: `5930ee774679167907edc17999fe06ff8e0bc9ed` (2026-08-22)

### Servicios / Managers

*Cantidad: 5 archivo(s)*

- `module/Eep/src/Service/AssignmentManager.php` — última modificación: `9e2956a67b4763327e3561acd14281208ee1e526` (2026-02-08)
- `module/Eep/src/Service/AuthManager.php` — última modificación: `a57937e05302fac2679affc8b6e7b22926591fc8` (2026-05-17)
- `module/Eep/src/Service/SatuManager.php` — última modificación: `a8e5f5d3dc2b16bbc6d80e061b9c9c78ac222571` (2025-08-24)
- `module/Eep/src/Service/TimetableManager.php` — última modificación: `f63d8787de4754e40369e44a087ac9bc8b2ac310` (2025-08-17)
- `module/Eep/src/Service/UserManager.php` — última modificación: `5930ee774679167907edc17999fe06ff8e0bc9ed` (2026-08-22)

### Formularios

*Cantidad: 1 archivo(s)*

- `module/Eep/src/Form/EditUserForm.php` — última modificación: `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)

### Entidades

*Cantidad: 2 archivo(s)*

- `module/Eep/src/Entity/Role.php` — última modificación: `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/src/Entity/User.php` — última modificación: `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)

### Value Objects

*Cantidad: 1 archivo(s)*

- `module/Eep/src/ValueObject/View.php` — última modificación: `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)

### Vistas / Plantillas

*Cantidad: 5 archivo(s)*

- `module/Eep/view/eep/auth/login.phtml` — última modificación: `9985251eaf6ca9b570b96c56c5e6e3c0820f1fcd` (2025-08-23)
- `module/Eep/view/eep/user/edit-user.phtml` — última modificación: `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/eep/user/log-view.phtml` — última modificación: `2ee5a57549cb391c82506e90bd2ed4396aeb6a09` (2026-04-04)
- `module/Eep/view/eep/user/student-search.phtml` — última modificación: `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)
- `module/Eep/view/layout/layout.phtml` — última modificación: `d83843f97bf7c7473da5e7e557fa16884382522f` (2026-07-11)

### Módulo Application

*Cantidad: 1 archivo(s)*

- `module/Application/view/layout/layout-eep.phtml` — última modificación: `311a8b6b9f32f37fc746c54f382169d3862f46f4` (2026-05-16)

### Raíz del proyecto

*Cantidad: 4 archivo(s)*

- `composer.json` — última modificación: `dea790fe343e8716297b04e910d236e5de863b71` (2026-05-31)
- `composer.lock` — última modificación: `dea790fe343e8716297b04e910d236e5de863b71` (2026-05-31)
- `docker-compose.yml` — última modificación: `04e61ac52f897c61ccaa6278112d6f2d06a132db` (2026-08-22)
- `.gitignore` — última modificación: `4595f38306cef2088836d4cb46cb78289ed7a191` (2026-08-22)

### Otras áreas

#### module/Eep/src/
*Cantidad: 1 archivo(s)*

- `module/Eep/src/Module.php` — última modificación: `5c318601cd5f12842927803c5af271843a73d613` (2026-06-01)

---

## 3. Archivos Eliminados

Total: **2** archivos eliminados del código fuente (excluyendo `data/sessiones/`).

- `20250718Postgrados` — eliminado en: `baeea6cc36330913f49da448809fb2d20f7bc413` (2025-08-17)
- `Dockerfile` — eliminado en: `baeea6cc36330913f49da448809fb2d20f7bc413` (2025-08-17)

---

## 4. Archivos Ignorados por Git pero de Configuración Vital (Comparación Dev vs Main/Prod)

Los siguientes archivos están en `.gitignore` por seguridad (credenciales, rutas locales, etc.) y **no aparecen en el diff de Git entre ramas**, pero son críticos para el funcionamiento de la plataforma. Se comparó el working tree de `development` (`/home/harris/Escritorio/unificacion/20250717Postgrados/`) contra el de `main`/`prod` (`/home/harris/Escritorio/unificacion/prod/20250717Postgrados/`).

### `config/autoload/local.php` — DIFERENCIAS CRÍTICAS

| Aspecto | `development` | `main` / `prod` |
|---------|---------------|-----------------|
| **db** | `username: user`, `password: password`, adaptador `satu` idéntico | `username: user`, `password: password`, adaptador `satu` idéntico |
| **session_config.save_path** | `/var/www/data/sessiones/` | `/var/www/data/sessiones/` |
| **mail** (SMTP) | ✅ Presente: host `smtp.gmail.com`, puerto `587`, TLS, credenciales `harry.usac20@gmail.com` / `aady eqbz ylke gepo` | ❌ **AUSENTE** |
| **decano** | ✅ Presente: `nombre: Arq. Francisco Bonini`, `titulo: Decano` | ❌ **AUSENTE** |

> **⚠️ Acción requerida:** Al hacer merge de `development` → `main`, asegurarse de que `config/autoload/local.php` en producción incluya las nuevas claves `mail` y `decano`, o los módulos de envío de correo y generación de documentos oficiales fallarán silenciosamente.

### `config/development.config.php` — Modo desarrollo de ZF3

| Aspecto | `development` | `main` / `prod` |
|---------|---------------|-----------------|
| **Activo** | ❌ No (solo existe `.dist`) | ❌ No (solo existe `.dist`) |

> **ℹ️ Nota:** Ambos entornos tienen únicamente `development.config.php.dist`. No hay riesgo de que el modo desarrollo quede activado accidentalmente en producción.

### Carpetas de datos en `.gitignore` — Referencia para revisión

Estas carpetas contienen archivos de runtime subidos por usuarios o generados por la plataforma. No deben versionarse, pero se listan para que al desplegar en producción se verifique que:

1. Los permisos de escritura estén correctos (`www-data` o equivalente).
2. El `.gitkeep` de `data/graduacion/procesos/` se respete para que la carpeta exista al clonar.

| Carpeta | Ignorada | Contenido tipo | Revisar al desplegar |
|---------|----------|----------------|----------------------|
| `data/graduacion/procesos/*` | ✅ Sí | Documentos de estudiantes (PDF, JPG, PNG) por proceso de graduación | Permisos de escritura y existencia del directorio raíz |
| `data/admisiones/*` | ✅ Sí | Archivos adjuntos de formularios de admisión | Permisos de escritura y existencia del directorio raíz |
| `data/sessiones/` | ✅ Sí | Cache de sesiones PHP | Ignorar (se regenera) |
| `data/cache/` | ✅ Sí | Cache de la aplicación | Ignorar (se regenera) |
| `data/logs/` | ✅ Sí | Logs de la aplicación | Ignorar (se regenera) |
| `data/tmp/` | ✅ Sí | Archivos temporales | Ignorar (se regenera) |

> **⚠️ Acción requerida:** `data/graduacion/procesos/.gitkeep` está en ambos entornos y debe seguir estando en `main` para que Git cree la carpeta vacía al clonar. Si se pierde, la subida de documentos de graduación fallará.

---

## Notas

- Los 9,040 archivos de sesión (`data/sessiones/sess_*`) del primer commit fueron excluidos del análisis porque son datos temporales de runtime y no aportan valor al análisis de cambios del código fuente.
- El proyecto limpió significativamente su estructura desde el primer commit (pasó de 9,537 archivos a 621 archivos tracked).
- Los archivos nuevos se concentran principalmente en:
  - **Módulo de Graduación** (`module/Eep/src/Controller/ExamenController.php`, `module/Eep/src/Service/ExamenManager.php`, vistas, formularios, etc.)
  - **Evaluación Docente** (`EvaluacionDocenteController`, `EvaluacionDocenteManager`, vistas y gráficas PDF)
  - **Formulario de Admisión** (`FormularioAdmisionController`, entidades, formularios)
  - **Recuperación de Contraseña** (`UserController`, `UserManager`, `ResetPasswordForm`, SQL de tokens)
  - **Documentación** (guías de instalación, checklist de producción, `AGENTS.md`)
  - **Base de datos** (scripts SQL de schema para cada nuevo módulo)
  - **Infraestructura** (`docker/Dockerfile`, `docker-compose.yml`)
- Los archivos modificados corresponden principalmente a:
  - **Configuración del módulo Eep** (`module.config.php`, `access_filter.php`, `menus.php`) donde se registran rutas, permisos y menús de cada nueva funcionalidad.
  - **Servicios centrales** (`UserManager`, `AuthManager`, `SatuManager`) adaptados para soportar nuevos flujos de negocio.
  - **Controllers** (`UserController`, `AssignmentController`) donde se agregan nuevas acciones.
  - **Configuración global** (`composer.json` con nuevas dependencias, `modules.config.php`).