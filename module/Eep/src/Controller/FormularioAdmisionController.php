<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Session\Container;
use Eep\Service\FormularioAdmisionManager;
use Eep\Service\UserManager;
use Eep\Service\AcademyManager;
use Eep\Service\CohortManager;
use Eep\Service\AuthManager;
use Eep\Service\InscriptionManager;
use Eep\Service\SatuManager;
use Eep\ValueObject\Message;
use Eep\Service\LogManager as LM;
use Eep\Form\FormularioAdmisionForm;
use Eep\Form\CandidateForm;
use Eep\Entity\Role;
use Eep\Entity\Order;

class FormularioAdmisionController extends AbstractActionController {
    private $formularioAdmisionManager;
    private $userManager;
    private $academyManager;
    private $cohortManager;
    private $authManager;
    private $inscriptionManager;
    private $satuManager;

    public function __construct(
        FormularioAdmisionManager $formularioAdmisionManager,
        UserManager $userManager,
        AcademyManager $academyManager,
        CohortManager $cohortManager,
        AuthManager $authManager,
        InscriptionManager $inscriptionManager,
        SatuManager $satuManager
    ) {
        $this->formularioAdmisionManager = $formularioAdmisionManager;
        $this->userManager = $userManager;
        $this->academyManager = $academyManager;
        $this->cohortManager = $cohortManager;
        $this->authManager = $authManager;
        $this->inscriptionManager = $inscriptionManager;
        $this->satuManager = $satuManager;
    }

    /**
     * VISTA 5: Archivar formulario de admisión
     */
    public function archivarAction() {
        $idFormulario = (int) $this->params()->fromRoute('id', 0);
        if ($idFormulario <= 0) {
            $msg = new Message('Error', 'ID de formulario inválido', Message::RED);
            $this->pg()->log($msg, LM::FAILURE, LM::UPDATE);
        } else {
            $result = $this->formularioAdmisionManager->archivarFormulario($idFormulario);
            if ($result->get()) {
                $msg = new Message('Formulario archivado', 'El formulario fue archivado exitosamente.', Message::GREEN);
            } else {
                $msg = new Message('Error', $result->getMsg(), Message::RED);
            }
            $this->pg()->log($msg, $result->get() ? LM::SUCCESS : LM::FAILURE, LM::UPDATE);
        }
        // Obtener listas actualizadas
        $formulariosActivosResult = $this->formularioAdmisionManager->getFormulariosActivos();
        $formulariosActivos = $formulariosActivosResult->get() ? $formulariosActivosResult->getObj() : [];
        $formulariosArchivadosResult = $this->formularioAdmisionManager->getFormulariosArchivados();
        $formulariosArchivados = $formulariosArchivadosResult->get() ? $formulariosArchivadosResult->getObj() : [];
        $view = new ViewModel([
            'formulariosActivos' => $formulariosActivos,
            'formulariosArchivados' => $formulariosArchivados,
            'activosMsg' => $formulariosActivosResult->get() ? null : new Message('Error', $formulariosActivosResult->getMsg(), Message::RED),
            'archivadosMsg' => $formulariosArchivadosResult->get() ? null : new Message('Error', $formulariosArchivadosResult->getMsg(), Message::RED),
            'msg' => $msg
        ]);
        $view->setTemplate('eep/formulario-admision/index');
        return $view;
    }

    /**
     * VISTA 6: Eliminar formulario de admisión
     */
    public function eliminarAction() {
        $idFormulario = (int) $this->params()->fromRoute('id', 0);
        if ($idFormulario <= 0) {
            $msg = new Message('Error', 'ID de formulario inválido', Message::RED);
            $status = LM::FAILURE;
        } else {
            $result = $this->formularioAdmisionManager->eliminarFormulario($idFormulario);
            if ($result->get()) {
                $msg = new Message('Formulario Eliminado', 'El formulario fue eliminado exitosamente.', Message::GREEN);
                $status = LM::SUCCESS;
            } else {
                $msg = new Message('Error', $result->getMsg(), Message::RED);
                $status = LM::FAILURE;
            }
        }
        $this->pg()->log($msg, $status, LM::DELETE);
        // Refrescar listas
        $activos = $this->formularioAdmisionManager->getFormulariosActivos();
        $archivados = $this->formularioAdmisionManager->getFormulariosArchivados();
        $view = new ViewModel([
            'formulariosActivos' => $activos->get() ? $activos->getObj() : [],
            'formulariosArchivados' => $archivados->get() ? $archivados->getObj() : [],
            'activosMsg' => $activos->get() ? null : new Message('Error', $activos->getMsg(), Message::RED),
            'archivadosMsg' => $archivados->get() ? null : new Message('Error', $archivados->getMsg(), Message::RED),
            'msg' => $msg
        ]);
        $view->setTemplate('eep/formulario-admision/index');
        return $view;
    }

    /**
     * VISTA 1: Lista de formularios (activos y archivados)
     */
    public function indexAction() {
        // Obtener formularios activos
        $formulariosActivosResult = $this->formularioAdmisionManager->getFormulariosActivos();
        $formulariosActivos = $formulariosActivosResult->get() ? $formulariosActivosResult->getObj() : [];

        // Obtener formularios archivados
        $formulariosArchivadosResult = $this->formularioAdmisionManager->getFormulariosArchivados();
        $formulariosArchivados = $formulariosArchivadosResult->get() ? $formulariosArchivadosResult->getObj() : [];

        // Log de acceso
        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);

        return new ViewModel([
            'formulariosActivos' => $formulariosActivos,
            'formulariosArchivados' => $formulariosArchivados,
            'activosMsg' => $formulariosActivosResult->get() ? null : new Message('Error', $formulariosActivosResult->getMsg(), Message::RED),
            'archivadosMsg' => $formulariosArchivadosResult->get() ? null : new Message('Error', $formulariosArchivadosResult->getMsg(), Message::RED)
        ]);
    }

    /**
     * VISTA 2: Lista de respuestas de un formulario específico
     */
    public function respuestasAction() {
        $idFormulario = (int) $this->params()->fromRoute('id', 0);
        $page = (int) $this->params()->fromQuery('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        if ($idFormulario <= 0) {
            $this->pg()->log('ID de formulario inválido', LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }

        // Obtener información del formulario
        $formularioResult = $this->formularioAdmisionManager->getFormulario($idFormulario);
        if (!$formularioResult->get()) {
            $this->pg()->log($formularioResult->getMsg(), LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }
        $formulario = $formularioResult->getObj();

        // Contar total para paginación
        $countResult = $this->formularioAdmisionManager->countRespuestasFormulario($idFormulario);
        $totalRecords = ($countResult->get()) ? (int)$countResult->getObj() : 0;
        $perPage = 20;
        $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $perPage) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        // Obtener respuestas del formulario paginadas
        $respuestasResult = $this->formularioAdmisionManager->getRespuestasFormulario($idFormulario, $page, $perPage);
        $respuestas = $respuestasResult->get() ? $respuestasResult->getObj() : [];

        // Datos para asignación masiva y verificación
        $nationalities = $this->userManager->getCountries();
        $degrees = $this->academyManager->getAcademicDegrees();
        $careers = $this->academyManager->getCareers();
        $cohorts = $this->cohortManager->getCohorts(date('Y') . '-01-01');
        $careerTree = [];
        foreach ($careers as $career) {
            $careerTree[$career['cod_grado']][$career['cod_carrera']] = $career['alias_actual'];
        }

        // Verificar cuáles aspirantes ya están registrados como usuarios
        $yaRegistrados = [];
        foreach ($respuestas as $respuesta) {
            $cui = $respuesta->getAspiranteCui();
            $correo = $respuesta->getAspiranteCorreoElectronico();
            if (!empty($cui) || !empty($correo)) {
                $existeResult = $this->formularioAdmisionManager->verificarUsuarioRegistrado($cui, $correo);
                if ($existeResult->get()) {
                    $yaRegistrados[$respuesta->getIdRespuesta()] = true;
                }
            }
        }

        // Manejar acciones POST (eliminar respuesta o asignación masiva)
        $message = null;
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();

            if (isset($data['registrar_seleccionados']) && !empty($data['aspirantes'])) {
                // ASIGNACIÓN MASIVA
                $idsAspirantes = array_map('intval', $data['aspirantes']);
                $careerCode = $data['carrera_a_ingresar'] ?? '';
                $degreeCode = $data['grado_a_ingresar'] ?? '';
                $cohortDate = $data['cohorte'] ?? '';

                $exitosos = 0;
                $fallidos = 0;
                $erroresDetalle = [];

                $formUrl = $this->url()->fromRoute('formulario-admision', ['action' => 'respuestas', 'id' => $idFormulario]);

                foreach ($idsAspirantes as $idRespuesta) {
                    $respuestaResult = $this->formularioAdmisionManager->getRespuestaDetallada($idRespuesta);
                    if (!$respuestaResult->get()) {
                        $fallidos++;
                        $erroresDetalle[] = "Respuesta #$idRespuesta: no se pudo obtener";
                        continue;
                    }
                    $userData = $this->mapearRespuestaAUsuario($respuestaResult->getObj());

                    $formData = array_merge($userData, [
                        CandidateForm::ACADEMIC_DEGREE => $degreeCode,
                        CandidateForm::CAREER => $careerCode,
                        CandidateForm::COHORT => $cohortDate,
                    ]);

                    $candidateForm = new CandidateForm($formUrl, $nationalities, $cohorts, $degrees, $careers);
                    $candidateForm->setData($formData);

                    if (!$candidateForm->isValid()) {
                        $fallidos++;
                        $msgs = [];
                        foreach ($candidateForm->getElements() as $e) {
                            $m = $e->getMessages();
                            if (!empty($m)) $msgs[] = ($e->getLabel() ?: $e->getName()) . ': ' . implode(', ', $m);
                        }
                        $erroresDetalle[] = ($userData['nombres'] ?? "Aspirante #$idRespuesta") . ' — ' . implode('; ', $msgs);
                        continue;
                    }

                    $validData = $candidateForm->getData();
                    try {
                        $this->academyManager->beginTransaction();
                        $this->satuManager->beginTransaction();

                        $result = $this->userManager->addUser($validData);
                        if ($result->get() !== false) {
                            $addedUser = $result->get();
                            $result = $this->authManager->addUserRole($addedUser, Role::ESTUDIANTE);
                            if ($result->get() === true) {
                                if ($careerCode != Order::CURSO_ACTUALIZACION) {
                                    $result = $this->academyManager->assignCareer($addedUser, $careerCode, $cohortDate);
                                    $this->inscriptionManager->getInscriptionStatus($addedUser);
                                }
                            }
                        }

                        if ($result->get() == true) {
                            $this->academyManager->commit();
                            $this->satuManager->commit();
                            $exitosos++;
                        } else {
                            $this->academyManager->rollback();
                            $this->satuManager->rollback();
                            $fallidos++;
                            $erroresDetalle[] = ($userData['nombres'] ?? "Aspirante #$idRespuesta") . ' — No se pudo asignar carrera/rol';
                        }
                    } catch (\Exception $ex) {
                        $fallidos++;
                        $erroresDetalle[] = ($userData['nombres'] ?? "Aspirante #$idRespuesta") . ' — ' . $ex->getMessage();
                    }
                }

                if ($exitosos > 0 && $fallidos == 0) {
                    $message = new Message('Registro exitoso', "Se registraron $exitosos aspirante(s) correctamente.", Message::GREEN);
                    $this->pg()->log(null, LM::SUCCESS, LM::CREATE);
                } elseif ($exitosos > 0 && $fallidos > 0) {
                    $msgText = "Registrados: $exitosos. Fallidos: $fallidos.<br/>Detalles:<br/>" . implode('<br/>', $erroresDetalle);
                    $message = new Message('Registro parcial', $msgText, Message::YELLOW);
                    $this->pg()->log($msgText, LM::FAILURE, LM::CREATE);
                } else {
                    $msgText = "No se registró ningún aspirante.<br/>Detalles:<br/>" . implode('<br/>', $erroresDetalle);
                    $message = new Message('Registro fallido', $msgText, Message::RED);
                    $this->pg()->log($msgText, LM::FAILURE, LM::CREATE);
                }

                // Refrescar lista de respuestas
                $countResult = $this->formularioAdmisionManager->countRespuestasFormulario($idFormulario);
                $totalRecords = ($countResult->get()) ? (int)$countResult->getObj() : 0;
                $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $perPage) : 1;
                if ($page > $totalPages) $page = $totalPages;
                if ($page < 1) $page = 1;
                $respuestasResult = $this->formularioAdmisionManager->getRespuestasFormulario($idFormulario, $page, $perPage);
                $respuestas = $respuestasResult->get() ? $respuestasResult->getObj() : [];

                // Re-verificar registrados tras la asignación masiva
                $yaRegistrados = [];
                foreach ($respuestas as $respuesta) {
                    $cui = $respuesta->getAspiranteCui();
                    $correo = $respuesta->getAspiranteCorreoElectronico();
                    if (!empty($cui) || !empty($correo)) {
                        $existeResult = $this->formularioAdmisionManager->verificarUsuarioRegistrado($cui, $correo);
                        if ($existeResult->get()) {
                            $yaRegistrados[$respuesta->getIdRespuesta()] = true;
                        }
                    }
                }
            } elseif (isset($data['eliminar_respuesta'])) {
                $idRespuesta = (int) $data['eliminar_respuesta'];
                $eliminarResult = $this->formularioAdmisionManager->eliminarRespuesta($idRespuesta);

                $message = new Message(
                    $eliminarResult->get() ? 'Respuesta Eliminada' : 'Error al Eliminar',
                    $eliminarResult->getMsg(),
                    $eliminarResult->get() ? Message::GREEN : Message::RED
                );

                $this->pg()->log($eliminarResult->get() ? null : $eliminarResult->getMsg(),
                               $eliminarResult->get() ? LM::SUCCESS : LM::FAILURE, LM::DELETE);

                // Refrescar lista de respuestas
                if ($eliminarResult->get()) {
                    $countResult = $this->formularioAdmisionManager->countRespuestasFormulario($idFormulario);
                    $totalRecords = ($countResult->get()) ? (int)$countResult->getObj() : 0;
                    $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $perPage) : 1;
                    if ($page > $totalPages) {
                        $page = $totalPages;
                    }
                    if ($page < 1) {
                        $page = 1;
                    }
                    $respuestasResult = $this->formularioAdmisionManager->getRespuestasFormulario($idFormulario, $page, $perPage);
                    $respuestas = $respuestasResult->get() ? $respuestasResult->getObj() : [];
                }
            }
        }

        // Recuperar mensaje flash de sesión (ej: tras registrar aspirante)
        $session = new Container('admisiones');
        if (empty($message) && !empty($session->mensaje)) {
            $msgData = $session->mensaje;
            $message = new Message($msgData['titulo'], $msgData['texto'], $msgData['tipo']);
            unset($session->mensaje);
        }

        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);

        return new ViewModel([
            'formulario' => $formulario,
            'respuestas' => $respuestas,
            'message' => $message,
            'respuestasMsg' => $respuestasResult->get() ? null : new Message('Error', $respuestasResult->getMsg(), Message::RED),
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'perPage' => $perPage,
            'yaRegistrados' => $yaRegistrados,
            'nationalities' => $nationalities,
            'degrees' => $degrees,
            'careers' => $careers,
            'cohorts' => $cohorts,
            'careerTree' => $careerTree,
        ]);
    }

    /**
     * VISTA 3: Ver/Editar respuesta específica de un aspirante
     */
    public function editarRespuestaAction() {
        $idRespuesta = (int) $this->params()->fromRoute('id', 0);

        if ($idRespuesta <= 0) {
            $this->pg()->log('ID de respuesta inválido', LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }

        // Obtener respuesta detallada (ahora solo campos)
        $respuestaResult = $this->formularioAdmisionManager->getRespuestaDetallada($idRespuesta);
        if (!$respuestaResult->get()) {
            $this->pg()->log($respuestaResult->getMsg(), LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }
        $respuesta = $respuestaResult->getObj();

        // Obtener información del formulario
        $formulario = null;
        if (!empty($respuesta)) {
            $formularioResult = $this->formularioAdmisionManager->getFormularioPorRespuesta($idRespuesta);
            $formulario = $formularioResult->get() ? $formularioResult->getObj() : null;
        }

        // Obtener datos para selects dinámicos (necesarios en la vista de edición)
        $nationalities = $this->userManager->getCountries();
        $degrees = $this->academyManager->getAcademicDegrees();
        $careers = $this->academyManager->getCareers();
        $cohorts = $this->cohortManager->getCohorts(date('Y') . '-01-01');

        $message = null;

        // Manejar edición de respuesta - SOLO SI EL FORMULARIO ESTÁ ACTIVO
        if ($this->getRequest()->isPost()) {
            if ($formulario && !$formulario->getActivo()) {
                $message = new Message('Formulario Inactivo',
                    'Este formulario está inactivo. No se pueden realizar cambios en las respuestas.',
                    Message::YELLOW);
                $this->pg()->log($message, LM::FAILURE, LM::UPDATE);
            } else {
                $data = $this->params()->fromPost();

                if (isset($data['guardar_cambios'])) {
                    // Actualizar respuesta (solo textos; archivos se ignoran en el manager)
                    $result = $this->formularioAdmisionManager->actualizarRespuesta($idRespuesta, $data, []);

                    if ($result->get()) {
                        $message = new Message('Cambios Guardados', 'Los cambios se guardaron correctamente', Message::GREEN);
                        $this->pg()->log($message, LM::SUCCESS, LM::UPDATE);

                        // Recargar datos actualizados
                        $respuestaResult = $this->formularioAdmisionManager->getRespuestaDetallada($idRespuesta);
                        $respuesta = $respuestaResult->get() ? $respuestaResult->getObj() : $respuesta;
                    } else {
                        $message = new Message('Error', $result->getMsg(), Message::RED);
                        $this->pg()->log($message, LM::FAILURE, LM::UPDATE);
                    }
                }
            }
        }

        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);

        return new ViewModel([
            'respuesta' => $respuesta,
            'formulario' => $formulario,
            'message' => $message,
            'nationalities' => $nationalities,
            'degrees' => $degrees,
            'careers' => $careers,
            'cohorts' => $cohorts,
        ]);
    }

    /**
     * VISTA 4: Crear nuevo formulario de admisión
     */
    public function crearAction() {
        // Crear formulario usando la clase Zend Form
        $formUrl = $this->url()->fromRoute('formulario-admision', ['action' => 'crear']);
        $form = new FormularioAdmisionForm($formUrl);

        $message = null;

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            $status = LM::SUCCESS;

            if ($form->isValid()) {
                $validData = $form->getData();

                // Agregar usuario que crea
                $validData['creado_por'] = $this->identity();

                $result = $this->formularioAdmisionManager->crearFormulario($validData);

                if ($result->get()) {
                    $formularioId = $result->getObj();
                    $message = new Message('Formulario Creado',
                        "Formulario creado correctamente",
                        Message::GREEN);
                    $form->clearData();
                } else {
                    $message = new Message('Error', $result->getMsg(), Message::RED);
                    $status = LM::FAILURE;
                }
            } else {
                $message = new Message('Campos faltantes', 'Hay campos que requieren corrección', Message::YELLOW);
                $status = LM::FAILURE;
            }

            $this->pg()->log($message ?? null, $status, LM::CREATE);
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }

        return new ViewModel([
            'form' => $form,
            'message' => $message
        ]);
    }

    /**
     * VISTA PÚBLICA: Mostrar formulario activo para aspirantes
     */
    public function publicAction() {
        // Usar layout minimal (sin sidebar ni menús)
        $this->layout('layout/empty');
        // Obtener el primer formulario activo
        $activosResult = $this->formularioAdmisionManager->getFormulariosActivos();
        $formularios = $activosResult->get() ? $activosResult->getObj() : [];
        if (empty($formularios)) {
            $msg = new Message('Info', 'No hay formularios de admisión disponibles en este momento.', Message::YELLOW);
            $this->pg()->log($msg, LM::SUCCESS, LM::VIEW);
            return new ViewModel(['message' => $msg]);
        }
        $formulario = $formularios[0];
        // Obtener campos activos
        $camposResult = $this->formularioAdmisionManager->getCamposFormulario($formulario->getIdFormulario());
        $campos = $camposResult->get() ? $camposResult->getObj() : [];

        // Obtener datos para selects dinámicos ANTES del POST (los necesitamos para validación)
        $nationalities = $this->userManager->getCountries();
        $degrees = $this->academyManager->getAcademicDegrees();
        $careers = $this->academyManager->getCareers();
        $cohorts = $this->cohortManager->getCohorts(date('Y') . '-01-01');

        // Buscar código de Guatemala para validación condicional
        $codGuatemala = null;
        foreach ($nationalities as $pais) {
            if ($pais['nombre'] === 'Guatemala') {
                $codGuatemala = $pais['cod_pais'];
                break;
            }
        }

        // Árbol grado -> carrera para JS cascada
        $careerTree = [];
        foreach ($careers as $career) {
            $careerTree[$career['cod_grado']][$career['cod_carrera']] = $career['alias_actual'];
        }

        // Recuperar mensaje de sesion (POST-Redirect-GET)
        $session = new Container('admisiones');
        $message = null;
        if (!empty($session->mensaje)) {
            $msgData = $session->mensaje;
            $message = new Message($msgData['titulo'], $msgData['texto'], $msgData['tipo']);
            unset($session->mensaje);
        }

        // Manejar envío público
        if ($this->getRequest()->isPost()) {
            // Obtener datos y archivos enviados
            $data = $this->params()->fromPost();
            $files = $this->getRequest()->getFiles()->toArray();
            $errors = [];
            foreach ($campos as $campo) {
                if ($campo->getRequerido()) {
                    if ($campo->getTipoCampo() === 'archivo') {
                        // Validar carga de archivo
                        $fileInfo = $files[$campo->getNombreCampo()] ?? null;
                        if (!$fileInfo || $fileInfo['error'] !== UPLOAD_ERR_OK) {
                            $errors[] = $campo->getEtiqueta();
                        }
                    } else {
                        if (empty($data[$campo->getNombreCampo()])) {
                            $errors[] = $campo->getEtiqueta();
                        }
                    }
                }
            }

            // Validación condicional backend: CUI para Guatemala, Pasaporte para extranjeros
            if (empty($errors)) {
                $nacionalidad = $data['nacionalidad'] ?? '';
                if ($nacionalidad == $codGuatemala) {
                    if (empty($data['cui'])) {
                        $errors[] = 'DPI / CUI (obligatorio para guatemaltecos)';
                    }
                } else {
                    if (empty($data['pasaporte'])) {
                        $errors[] = 'Pasaporte (obligatorio para extranjeros)';
                    }
                }
            }

            // Validación condicional backend: información laboral obligatoria si trabaja_actualmente == yes
            if (empty($errors)) {
                $trabaja = $data['trabaja_actualmente'] ?? '';
                if ($trabaja === 'yes') {
                    if (empty($data['ubicacion_laboral'])) {
                        $errors[] = 'Ubicación laboral';
                    }
                    if (empty($data['hora_inicio'])) {
                        $errors[] = 'Hora inicio laboral';
                    }
                    if (empty($data['hora_fin'])) {
                        $errors[] = 'Hora fin laboral';
                    }
                    if (empty($data['dias_labora'])) {
                        $errors[] = 'Días que labora';
                    }
                }
            }

            if (!empty($errors)) {
                $session->mensaje = [
                    'titulo' => 'Errores en el formulario',
                    'texto'  => 'Faltan campos obligatorios: ' . implode(', ', $errors),
                    'tipo'   => Message::RED
                ];
                $this->pg()->log('Errores en formulario publico', LM::FAILURE, LM::CREATE);
            } else {
                // Guardar respuesta en la base de datos
                $result = $this->formularioAdmisionManager
                    ->registrarRespuestaPublica(
                        $formulario->getIdFormulario(),
                        $campos,
                        $data,
                        $files
                    );
                if ($result->get()) {
                    $session->mensaje = [
                        'titulo' => 'Enviado',
                        'texto'  => 'Formulario enviado correctamente',
                        'tipo'   => Message::GREEN
                    ];
                    $this->pg()->log('Formulario enviado correctamente', LM::SUCCESS, LM::CREATE);
                } else {
                    $session->mensaje = [
                        'titulo' => 'Error',
                        'texto'  => $result->getMsg(),
                        'tipo'   => Message::RED
                    ];
                    $this->pg()->log($result->getMsg(), LM::FAILURE, LM::CREATE);
                }
            }
            // Redirect para evitar reenvio al refrescar (POST-Redirect-GET)
            return $this->redirect()->toRoute('admisiones');
        }

        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        return new ViewModel([
            'formulario'   => $formulario,
            'campos'       => $campos,
            'message'      => $message,
            'nationalities'=> $nationalities,
            'degrees'      => $degrees,
            'careers'      => $careers,
            'cohorts'      => $cohorts,
            'careerTree'   => $careerTree,
            'codGuatemala' => $codGuatemala,
        ]);
    }

    /**
     * Registrar un aspirante (respuesta) como estudiante en el sistema.
     * GET  -> muestra pantalla de confirmación (admin elige cohorte y confirma carrera/grado).
     * POST -> ejecuta el registro con los datos confirmados.
     */
    public function registrarAspiranteAction() {
        $idRespuesta = (int) $this->params()->fromRoute('id', 0);
        if ($idRespuesta <= 0) {
            $this->pg()->log('ID de respuesta inválido', LM::FAILURE, LM::CREATE);
            return $this->redirect()->toRoute('formulario-admision');
        }

        // Obtener respuesta detallada
        $respuestaResult = $this->formularioAdmisionManager->getRespuestaDetallada($idRespuesta);
        if (!$respuestaResult->get()) {
            $this->pg()->log($respuestaResult->getMsg(), LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }
        $respuestasCampos = $respuestaResult->getObj();

        // Obtener id_formulario para redirección
        $formularioResult = $this->formularioAdmisionManager->getFormularioPorRespuesta($idRespuesta);
        $idFormulario = ($formularioResult->get() && $formularioResult->getObj())
            ? $formularioResult->getObj()->getIdFormulario()
            : 0;

        // Mapear datos del aspirante al formato que espera CandidateForm / addUser
        $userData = $this->mapearRespuestaAUsuario($respuestasCampos);

        // Datos para selects dinámicos (siempre los necesitamos)
        $nationalities = $this->userManager->getCountries();
        $degrees = $this->academyManager->getAcademicDegrees();
        $careers = $this->academyManager->getCareers();
        $cohorts = $this->cohortManager->getCohorts(date('Y') . '-01-01');
        $careerTree = [];
        foreach ($careers as $career) {
            $careerTree[$career['cod_grado']][$career['cod_carrera']] = $career['alias_actual'];
        }

        // Preparar CandidateForm para validar exactamente igual que candidatesAction
        $formUrl = $this->url()->fromRoute('formulario-admision', ['action' => 'registrar-aspirante', 'id' => $idRespuesta]);
        $candidateForm = new CandidateForm($formUrl, $nationalities, $cohorts, $degrees, $careers);

        $candidateMsg = null;

        // POST: ejecutar registro
        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();

            // Combinar datos de la respuesta con lo confirmado por el admin
            $formData = array_merge($userData, [
                CandidateForm::ACADEMIC_DEGREE => $post['grado_a_ingresar'] ?? ($userData['grado_a_ingresar'] ?? ''),
                CandidateForm::CAREER          => $post['carrera_a_ingresar'] ?? ($userData['carrera'] ?? ''),
                CandidateForm::COHORT          => $post['cohorte'] ?? '',
            ]);

            $candidateForm->setData($formData);
            $status = LM::SUCCESS;

            if ($candidateForm->isValid()) {
                $data = $candidateForm->getData();

                try {
                    // REPLICAR EXACTAMENTE la lógica de candidatesAction
                    $this->academyManager->beginTransaction();
                    $this->satuManager->beginTransaction();

                    $result = $this->userManager->addUser($data);
                    if ($result->get() !== false) {
                        $addedUser = $result->get();
                        $result = $this->authManager->addUserRole($addedUser, Role::ESTUDIANTE);
                        if ($result->get() === true) {
                            $careerCode = $data[CandidateForm::CAREER];
                            if ($careerCode != Order::CURSO_ACTUALIZACION) {
                                $cohort = $data[CandidateForm::COHORT];
                                $result = $this->academyManager->assignCareer($addedUser, $careerCode, $cohort);
                                $this->inscriptionManager->getInscriptionStatus($addedUser);
                            }
                        }
                    }

                    if ($result->get() == true) {
                        $this->academyManager->commit();
                        $this->satuManager->commit();
                        $title = 'Usuario asignado';
                        $candidateForm->clearData();
                    } else {
                        $this->academyManager->rollback();
                        $this->satuManager->rollback();
                        $title = 'Usuario no asignado';
                        $status = LM::FAILURE;
                    }
                    $candidateMsg = new Message($title, $result);
                } catch (\Exception $ex) {
                    $candidateMsg = new Message('Error Interno', $ex->getMessage(), Message::RED);
                    $status = LM::ERROR;
                }
            } else {
                $errors = [];
                foreach ($candidateForm->getElements() as $element) {
                    $msgs = $element->getMessages();
                    if (!empty($msgs)) {
                        $errors[] = '<li><strong>' . ($element->getLabel() ?: $element->getName()) . '</strong>: ' . implode(', ', $msgs) . '</li>';
                    }
                }
                $detail = empty($errors)
                    ? 'Hay campos que requieren cambios'
                    : '<ul>' . implode('', $errors) . '</ul>';
                $candidateMsg = new Message('Campos faltantes', $detail, Message::YELLOW);
                $status = LM::FAILURE;
            }

            $this->pg()->log($candidateMsg ?? null, $status, LM::CREATE);

            // Si fue exitoso, redirigir; si falló, renderizar la misma vista con errores
            if ($status == LM::SUCCESS) {
                $session = new Container('admisiones');
                $session->mensaje = [
                    'titulo' => $candidateMsg->getTitle(),
                    'texto'  => $candidateMsg->getMessage(),
                    'tipo'   => $candidateMsg->getType()
                ];
                return $this->redirect()->toRoute('formulario-admision', ['action' => 'respuestas', 'id' => $idFormulario]);
            }

            // En caso de error, preservar los valores POST para re-renderizar
            $userData['grado_a_ingresar'] = $post['grado_a_ingresar'] ?? ($userData['grado_a_ingresar'] ?? '');
            $userData['carrera'] = $post['carrera_a_ingresar'] ?? ($userData['carrera'] ?? '');
            $userData['cohorte'] = $post['cohorte'] ?? '';
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }

        // GET (o POST fallido): mostrar pantalla de confirmación
        return new ViewModel([
            'respuesta'     => $respuestasCampos,
            'userData'      => $userData,
            'nationalities'   => $nationalities,
            'degrees'       => $degrees,
            'careers'       => $careers,
            'cohorts'       => $cohorts,
            'careerTree'    => $careerTree,
            'idFormulario'  => $idFormulario,
            'candidateMsg'  => $candidateMsg,
        ]);
    }

    /**
     * Mapea los campos de respuesta_campo al array que espera UserManager::addUser()
     */
    private function mapearRespuestaAUsuario(array $respuestasCampos): array {
        $map = [];
        foreach ($respuestasCampos as $campo) {
            $map[$campo['nombre_campo']] = $campo['valor_respuesta'] ?? '';
        }

        // Sanitizar CUI: quitar espacios, guiones y cualquier carácter no numérico
        $cui = preg_replace('/[^0-9]/', '', $map['cui'] ?? '');

        $data = [
            'nombres'              => $map['nombres'] ?? '',
            'apellidos'            => $map['apellidos'] ?? '',
            'cui'                  => $cui,
            'correo'               => $map['correo_electronico'] ?? '',
            'telefono'             => $map['telefono'] ?? '',
            'fecha_nacimiento'     => $map['fecha_nacimiento'] ?? '',
            'sexo'                 => $map['sexo'] ?? '',
            'pasaporte'            => $map['pasaporte'] ?? '',
            'cod_pais'             => $map['nacionalidad'] ?? '',
            'grado_academico'      => $map['grado_academico_posee'] ?? '',
            'trabaja_actualmente'  => $map['trabaja_actualmente'] ?? 'no',
            'ubicacion'            => $map['ubicacion_laboral'] ?? '',
            'hora_inicio'          => $map['hora_inicio'] ?? '',
            'hora_fin'             => $map['hora_fin'] ?? '',
            'days'                 => $this->parsearDiasLaborales($map['dias_labora'] ?? ''),
            // Campos para la vista de confirmación / assignCareer
            'carrera'              => $map['carrera_a_ingresar'] ?? '',
            'grado_a_ingresar'     => $map['grado_a_ingresar'] ?? '',
        ];

        return $data;
    }

    /**
     * Parsea el valor guardado de días laborales (lista separada por comas desde
     * multicheckbox) a array reconocido por InfoLaboral.
     * Ej: "lunes,martes,miercoles" => ['lunes','martes','miercoles']
     */
    private function parsearDiasLaborales(string $texto): array {
        if (empty($texto)) {
            return [];
        }
        $partes = explode(',', $texto);
        $diasMap = [
            'lunes'     => 'lunes',
            'martes'    => 'martes',
            'miercoles' => 'miercoles',
            'miércoles' => 'miercoles',
            'jueves'    => 'jueves',
            'viernes'   => 'viernes',
            'sabado'    => 'sabado',
            'sábado'    => 'sabado',
            'domingo'   => 'domingo',
        ];
        $encontrados = [];
        foreach ($partes as $parte) {
            $parte = trim(mb_strtolower($parte, 'UTF-8'));
            if (isset($diasMap[$parte]) && !in_array($diasMap[$parte], $encontrados)) {
                $encontrados[] = $diasMap[$parte];
            }
        }
        return $encontrados;
    }

    /**
     * AJAX: Verificar si un CUI ya tiene respuesta en el formulario activo
     */
    public function verificarCuiAction() {
        $cui = $this->params()->fromPost('cui', '');
        $activosResult = $this->formularioAdmisionManager->getFormulariosActivos();
        $formularios = $activosResult->get() ? $activosResult->getObj() : [];

        if (empty($formularios)) {
            return new JsonModel(['disponible' => true]);
        }

        $idFormulario = $formularios[0]->getIdFormulario();
        $duplicado = $this->formularioAdmisionManager->verificarCuiDuplicado($cui, $idFormulario);

        if ($duplicado) {
            return new JsonModel([
                'disponible' => false,
                'mensaje' => 'Ya registró una respuesta. Si desea volver a enviar, comuníquese con el administrador.'
            ]);
        }

        return new JsonModel(['disponible' => true]);
    }

    /**
     * Ver archivo adjunto de forma segura desde data/admisiones (inline para imagenes, descarga para otros)
     */
    public function descargarAction() {
        $idRespuesta = (int) $this->params()->fromRoute('id', 0);
        $nombreCampo = $this->params()->fromQuery('campo', '');

        if ($idRespuesta <= 0 || empty($nombreCampo)) {
            return $this->getResponse()->setStatusCode(404);
        }

        $archivo = $this->formularioAdmisionManager->obtenerArchivoAdjunto($idRespuesta, $nombreCampo);

        if (!$archivo) {
            return $this->getResponse()->setStatusCode(404);
        }

        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type: ' . $archivo['mime_type']);

        // Para imagenes, no enviar Content-Disposition para que se vean inline
        // Para PDF u otros, usar inline para visualizar en el navegador
        if (strpos($archivo['mime_type'], 'image/') !== 0) {
            $headers->addHeaderLine('Content-Disposition: inline; filename="' . $archivo['nombre'] . '"');
        }

        $headers->addHeaderLine('Content-Length: ' . $archivo['tamano']);
        $headers->addHeaderLine('Cache-Control: private, max-age=3600');

        $response->setContent(file_get_contents($archivo['ruta_fisica']));
        return $response;
    }
}
