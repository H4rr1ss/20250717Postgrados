<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
//OBJECTS
use Eep\ValueObject\Message;
use Eep\Entity\Role;
use Eep\Entity\Result;
use Eep\Entity\Timetable;
//FORMS
use Eep\Form\CategorizeTimetableForm;
use Eep\Form\TimetableForm;
//SERVICES
use Eep\Service\AcademyManager;
use Eep\Service\CohortManager;
use Eep\Service\TimetableManager;
use Eep\Service\UserManager;
use Eep\Service\AuthManager;
use Eep\Service\LogManager as LM;

class TimetableController extends AbstractActionController {

    private $timetableManager;
    private $academyManager;
    private $cohortManager;
    private $userManager;
    private $authManager;
    private $category;

    public function __construct($sessionContainer, TimetableManager $timetableManager, AcademyManager $academyManager, CohortManager $cohortManager, UserManager $userManager, AuthManager $authManager) {
        $this->timetableManager = $timetableManager;
        $this->category = $sessionContainer;
        $this->academyManager = $academyManager;
        $this->cohortManager = $cohortManager;
	$this->userManager = $userManager;
    	$this->authManager = $authManager;
    }

    public function taughtAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            if (empty($params['year']) || !preg_match('/^\d+$/', $params['year'])) {
                $yearError = 'El año no tiene el formato correcto';
                $this->pg()->log($yearError, LM::FAILURE, LM::READ);
            } else {
                $year = intval($params['year']);
            }
        } else {
            $year = intval(date('Y'));
        }
        if (isset($year)) {
            $role = $this->layout()->role;
            if ($role != null & $role->hasAdminRole()) {
                $userCode = null;
            } else {
                $userCode = $this->identity();
            }
            $result = $this->timetableManager->getTaughtTimetables($year, $userCode);
            if ($result->get()) {
                $timetables = $result->getObj();
                $this->pg()->log($result, LM::SUCCESS, LM::READ);
            } else {
                $msg = new Message('Error de búsqueda', $result);
                $this->pg()->log($result, LM::ERROR, LM::READ);
            }
        }
        return new ViewModel([
            'timetables' => $timetables,
            'selectedYear' => $year ?? intval(date('Y')),
            'yearError' => $yearError ?? null,
            'msg' => $msg ?? null
        ]);
    }

    public function availableCoursesAction() {
        $role = $this->layout()->role;
        if ($role != null) {
            if ($role->hasAdminRole() || $role->hasUdicaRole()) {
                $userCode = null;
            } else {
                $userCode = $this->identity();
            }
            $result = $this->timetableManager->getUserTimetable($userCode, false, false, true, false, true);
            if ($result->get() == false) {
                $msg = new Message('Error, Cursos No Disponibles', $result->getMsg(), Message::YELLOW);
                $this->pg()->log($result, LM::ERROR, LM::READ);
            } else {
                if (!empty($result->getMsg())) {
                    $msg = new Message('Sin Carreras Asignadas', $result->getMsg(), Message::YELLOW);
                }
                $careers = $result->getObj();
                $this->pg()->log(null, LM::SUCCESS, LM::READ);
            }
        } else {
            $msg = new Message('Error', 'No se obtuvo el rol del usuario a consultar', Message::RED);
            $this->pg()->log($msg, LM::ERROR, LM::READ);
        }
        return new ViewModel([
            'msg' => $msg ?? null,
            'careers' => $careers ?? []
        ]);
    }

//ACTIONS RELATED TO THE SAME VIEW
    private function getSchedulingView($params = null, $logView = false) {
        if ($params == null) {
            $params = [];
        }
        //CHECK IF CATEGORIZATION HAS BEEN SELECTED PREVIOUSLY
        $logResult = new Result();
        $logResult->success();
        if (isset($this->category->degree)) {
            $pensum = $this->category->pensum;
            $params['pensumCode'] = $pensum;
            $cohort = $this->category->cohort;
            if ($cohort == CategorizeTimetableForm::UPG_COURSE_COHORT) {//CHANGING TEMPORAL DATE FOR VALIDATIONS TO NULL
                $cohort = null;
            }
            //GETTING TIMETABLES
            $result = $this->timetableManager->getTimetables($pensum, $cohort);
            if ($result->get() == true) {
                $timetables = $result->getObj();
                $params['timetables'] = $timetables;

                //CREATING NEW TIMETABLE FORM - THE EDIT TIMETABLE FORM IS CREATED IN ITS ACTION
                if (!isset($params['timetableForm'])) {
                    $result = $this->getTimetableForm(TimetableForm::TYPE_NEW);
                    $params['timetableForm'] = $result->getObj();
                    if ($result->get() == false) {
                        $params['timetableMsg'] = new Message('Error', $result);
                        $logResult->failure($result->getMsg());
                    }
                }
                //CREATING DELETE TIMETABLE FORM
                if (!isset($params['deleteForm'])) {
                    $result = $this->getTimetableForm(TimetableForm::TYPE_DELETE);
                    $params['deleteForm'] = $result->getObj();
                    if ($result->get() == false) {
                        $params['actionMsg'] = new Message('Error', $result);
                        $logResult->failure($result->getMsg());
                    }
                }
                //CREATING EDIT TIMETABLE FORM
                if (!isset($params['editForm'])) {
                    $result = $this->getTimetableForm(TimetableForm::TYPE_EDIT_REQUEST);
                    $params['editForm'] = $result->getObj();
                    if ($result->get() == false) {
                        $params['actionMsg'] = new Message('Error', $result);
                        $logResult->failure($result->getMsg());
                    }
                }
            } else {
                $logResult->failure($result->getMsg());
                $categoryMsg = new Message("Error", $result);
                $params['categoryMsg'] = $categoryMsg;
            }
        }
        //ADDING CATEGORIZATION FORM IF NOT EXISTS
        if (!isset($params['categoryForm'])) {
            $result = $this->getCategoryForm();
            if ($result->get() == false) {
                $logResult->failure($result->getMsg());
                $params['categoryMsg'] = new Message('Error', $result);
            }
            $params['categoryForm'] = $result->getObj(); //GET IT EVEN IF IT IS HAS EMPTY ATTRIBUTES
        }
        if ($logView) {
            $this->pg()->log($logResult, $logResult->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
        }

        $view = new ViewModel($params);
        $view->setTemplate('eep/timetable/scheduling');
        return $view;
    }

    public function categorizeAction() {
	$result = $this->authManager->getUserRole($this->identity());
	if ($result->get() == true) {
		$role = $result->getObj();
		$isDirector = $role->isDirector();
   	}
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $result = $this->getCategoryForm();
            if ($result->get() == false) {
                $msg = new Message('Error', $result);
            }
            $categoryForm = $result->getObj(); //GET IT EVEN IF IT IS HAS EMPTY ATTRIBUTES
            $categoryForm->setData($params);
            if ($categoryForm->isValid()) {
                $data = $categoryForm->getData();
                $careerCode = $data[CategorizeTimetableForm::CAREER];
                $cohortDate = $data[CategorizeTimetableForm::COHORT];
                $degreeCode = $data[CategorizeTimetableForm::ACADEMIC_DEGREE];
                //SEARCHING PENSUM
                if ($careerCode == CategorizeTimetableForm::UPG_COURSE_CODE) {
                    $pensums[] = ['cod_pensum' => CategorizeTimetableForm::UPG_COURSE_CODE];
                } else {
                    $pensums = $this->academyManager->getPensumCohorts($careerCode, $cohortDate);
                }
                if (count($pensums) == 0) {
                    $date = date('d/m/Y', strtotime($cohortDate));
                    $msg = new Message('Pensum No Encontrado', "No se encontró un pensum asociado a la carrera con código $careerCode y cohorte $date", Message::RED);
                    $this->pg()->log($msg, LM::FAILURE, LM::READ);
                    if (isset($this->category->degree)) {
                        unset($this->category->degree);
                        unset($this->category->career);
                        unset($this->category->pensum);
                        unset($this->category->cohort);
                    }
                } else {
                    $pensum = array_pop($pensums);
                    $pensumCode = $pensum['cod_pensum'];
                    $this->category->degree = $degreeCode;
                    $this->category->career = $careerCode;
                    $this->category->pensum = $pensumCode;
                    $this->category->cohort = $cohortDate;
                    $logView = true;
                }
            } else {
                $this->pg()->log($categoryForm->getMessages(), LM::FAILURE, LM::READ);
                if (isset($this->category->degree)) {
                    unset($this->category->degree);
                    unset($this->category->career);
                    unset($this->category->pensum);
                    unset($this->category->cohort);
                }
            }
        } else {
            $logView = true;
        }
	return $this->getSchedulingView([
		    'isDirectorLogged' => $isDirector,
                    'categoryForm' => isset($categoryForm) ? $categoryForm : null,
                    'categoryMsg' => $msg ?? null
                        ], $logView ?? false);
    }

    public function createAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $result = $this->getTimetableForm(TimetableForm::TYPE_NEW);
            $timetableForm = $result->getObj();
            if ($result->get() == false) {
                $msg = new Message('Error', $result);
                $this->pg()->log($result, LM::ERROR, LM::DELETE);
            } else {
                $timetableForm->setData($params);
                if ($timetableForm->isValid()) {
                    if (isset($this->category->pensum) == false) {
                        $txt = 'Previamente debe seleccionar la categoría de cursos.';
                        $msg = new Message('Error', $txt, Message::RED);
                        $result->failure($txt);
                    } else {
                        //GETTING TIMETABLE DATA
                        $data = $timetableForm->getData();
                        $pensum = $this->category->pensum;
                        $cohortDate = ($this->category->cohort == CategorizeTimetableForm::UPG_COURSE_COHORT) ? null : $this->category->cohort;
                        $timetable = new Timetable($data);
                        $timetable->setFechaCohorte($cohortDate);
                        $timetable->setCodPensum($pensum);
                        //CREATING TIMETABLE IN DATABASE
                        $result = $this->timetableManager->createTimetable($timetable);
                        if ($result->get() == false) {
                            $msg = new Message('Error', $result);
                        } else {
                            $msg = new Message('Horario Creado', 'El horario ha sido creado satisfactoriamente.', Message::GREEN);
                            $timetableCode = $timetable->getCode();
                            $courseCode = $timetable->getCodCurso();
                            $section = $timetable->getSeccion();
                            $cohortDate = (strtotime($cohortDate) == false) ? '(Curso de Actualización)' : date('d/m/Y', strtotime($cohortDate));
                            $startDate = date('d/m/Y', strtotime($timetable->getFechaInicio()));
                            $finishDate = date('d/m/Y', strtotime($timetable->getFechaFin()));
                            $gradingLimitDate = date('d/m/Y', strtotime($timetable->getFechaLimiteCalificacion()));
                            $result->addMsg("Horario: $timetableCode. "
                                    . "Curso: $courseCode. "
                                    . "Pensum: $pensum. "
                                    . "Cohorte: $cohortDate. "
                                    . "Sección: $section. "
                                    . "Fecha Inicio: $startDate. "
                                    . "Fecha Fin: $finishDate. "
                                    . "Fecha Límite Calificacion: $gradingLimitDate.");
                            $timetableForm = null; //CLEANING IT SO THE getSchedulingView CREATES IT AGAIN
                        }
                    }
                    $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::CREATE);
                } else {
                    $this->pg()->log($timetableForm->getMessages(), LM::FAILURE, LM::CREATE);
                }
            }
        } else {
            $logView = true;
        }
        return $this->getSchedulingView([
                    'timetableForm' => $timetableForm,
                    'timetableMsg' => $msg ?? null
                        ], $logView ?? false);
    }

    public function deleteAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $result = $this->getTimetableForm(TimetableForm::TYPE_DELETE);
            if ($result->get() == false) {
                $msg = new Message('Error de Formulario', 'No se pudo obtener el formulario para validar la eliminación del horario', Message::RED);
                $this->pg()->log($result, LM::ERROR, LM::DELETE);
            } else {
                $deleteForm = $result->getObj();
                $deleteForm->setData($params);
                if ($deleteForm->isValid()) {
                    $timetableCode = $deleteForm->getData()[TimetableForm::TIMETABLE_CODE];
                    $result = $this->timetableManager->deleteTimetable($timetableCode);
                    if ($result->get() == false) {
                        $msg = new Message('Horario No Eliminado', $result);
                    } else {
                        $msg = new Message('Horario Eliminado', 'El horario ha sido eliminado satisfactoriamente.', Message::GREEN);
                        $result->addMsg("Horario: $timetableCode.");
                    }
                    $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::DELETE);
                } else {
                    $msg = new Message('Datos de Eliminación Inválidos', 'Los datos obtenidos para eliminar el horario no son válidos.', Message::RED);
                    $this->pg()->log($deleteForm->getMessages(), LM::FAILURE, LM::DELETE);
                }
            }
        } else {
            $logView = true;
        }
        return $this->getSchedulingView([
                    'actionMsg' => $msg ?? null
                        ], $logView ?? false);
    }

    public function editAction() {
	$result = $this->authManager->getUserRole($this->identity());
	if ($result->get() == true) {
            $role = $result->getObj();
            $isDirector = $role->isDirector();
        }
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            //GETTING EDIT REQUEST FORM
            $result = $this->getTimetableForm(TimetableForm::TYPE_EDIT_REQUEST);
            if ($result->get() == false) {
                $msg = new Message('Error de Formulario', 'No se pudo obtener el formulario para validar la edición del horario', Message::RED);
                $this->pg()->log($result, LM::ERROR, LM::READ);
            } else {
                $editRequestForm = $result->getObj();
                //VALIDATING DATA
                $editRequestForm->setData($params);
                if ($editRequestForm->isValid()) {
                    //GETTING THE TIMETABLE
                    $timetableCode = $editRequestForm->getData()[TimetableForm::TIMETABLE_CODE];
                    $result = $this->timetableManager->getTimetable($timetableCode);
                    if ($result->get() == false) {
                        $msg = new Message('Error', $result);
                    } else {
                        $timetable = $result->getObj();
                        //CHECKING IF GRADING COMPLETION IS DONE. IF IT IS, THE TIMETABLE CANNOT BE UPDATED
                        if ($timetable->getFechaNotasCompletadas() != null && !$isDirector) {
                            $result->failure('El horario que seleccionó para edición tiene el ingreso de notas completadas el ' . date('d/m/Y', strtotime($timetable->getFechaNotasCompletadas()))
                                    . ', por lo tanto ya no se puede modificar.');
                            $msg = new Message('Horario No Editable', $result);
                        } else {
                            //CREATING THE EDIT FORM FOR THE USER TO USE
                            $result = $this->getTimetableForm(TimetableForm::TYPE_EDIT);
                            //$msg = GP::Msg($timetable);
                            if ($result->get() == false) {
                                $msg = new Message('Error', $result);
                            } else {
                                $editForm = $result->getObj();
                                $editForm->setTimetable($timetable);
                            }
                        }
                    }
                    $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
                } else {
                    $msg = new Message('Datos de Eliminación Inválidos', 'Los datos obtenidos para eliminar el horario no son válidos.', Message::RED);
                    $this->pg()->log($editRequestForm->getMessages(), LM::FAILURE, LM::READ);
                }
            }
        } else {
            $logView = true;
        }
	return $this->getSchedulingView([
		    'isDirectorLogged' => $isDirector,
                    'actionMsg' => $msg ?? null,
                    'timetableForm' => $editForm
                        ], $logView);
    }

    public function saveAction() {
        $result = $this->authManager->getUserRole($this->identity());
        if ($result->get() == true) {
            $role = $result->getObj();
            $isDirector = $role->isDirector();
        }

        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $result = $this->getTimetableForm(TimetableForm::TYPE_EDIT);
            $timetableForm = $result->getObj();
            if ($result->get() == false) {
                $msg = new Message('Error', $result);
                $this->pg()->log($result, LM::ERROR, LM::UPDATE);
            } else {
                //VALIDATING ONLY TIMETABLE CODE FORMAT
                $timetableForm->setData($params);
                $valid = $timetableForm->isValid();
                if (!$valid) {
                    if (empty($timetableForm->get(TimetableForm::TIMETABLE_CODE)->getMessages())) {
                        $valid = true;
                    }
                }
                if ($valid) {
                    //CORRECT TIMETABLE CODE FORMAT
                    $data = $timetableForm->getData();
                    $result = $this->timetableManager->getTimetable((new Timetable($data))->getCode());
                    if ($result->get() == false) {
                        $msg = new Message('Error Consultando Horario', $result);
                        $this->pg()->log($result, LM::ERROR, LM::UPDATE);
                    } else {
                        $timetable = $result->getObj();
                        //CHECKING IF GRADING COMPLETION IS DONE. IF IT IS, THE TIMETABLE CANNOT BE UPDATED
                        if ($timetable->getFechaNotasCompletadas() != null && !$isDirector) {
                            $result->failure('El horario que seleccionó para edición tiene el ingreso de notas completadas el ' . date('d/m/Y', strtotime($timetable->getFechaNotasCompletadas()))
                                    . ', por lo tanto ya no se puede modificar.');
                            $msg = new Message('Horario No Editable', $result);
                            $timetableForm = null;
                            $this->pg()->log($result, LM::FAILURE, LM::UPDATE);
                        } else {
                            //THIS DATA IS SETTED TO SHOW IT THE NEXT TIME IF IT HAS ERRORS AND TO VALIDATE DATE COHERENCE
                            $data[TimetableForm::START_DATE] = date('Y-m-d', strtotime($timetable->getFechaInicio()));
                            $data[TimetableForm::LABORATORY] = $timetable->getLaboratorio() ? 'yes' : 'no';
                            $timetableForm->setData($data);
                            //VALIDATING DATE COHERENCE
                            if ($timetableForm->isValid()) {
                                //GETTING TIMETABLE FORM DATA
                                $data = $timetableForm->getData();
                                $data[TimetableForm::SECTION] = $timetable->getSeccion();
                                $pensum = $timetable->getCodPensum();
                                $cohortDate = strtotime($timetable->getFechaCohorte()) == false ? '(Curso de Actualización)' : date('d/m/Y', strtotime($timetable->getFechaCohorte()));
                                $timetable = new Timetable($data);
                                //UPDATING TIMETABLE IN DATABASE
                                $result = $this->timetableManager->updateTimetable($timetable);
                                if ($result->get() == false) {
                                    $msg = new Message('Error Editando', $result);
                                } else {
                                    $msg = new Message('Horario Editado', 'El horario ha sido editado satisfactoriamente.', Message::GREEN);
                                    $timetableCode = $timetable->getCode();
                                    $courseCode = $timetable->getCodCurso();
                                    $section = $timetable->getSeccion();
                                    $finishDate = date('d/m/Y', strtotime($timetable->getFechaFin()));
                                    $gradingLimitDate = date('d/m/Y', strtotime($timetable->getFechaLimiteCalificacion()));
                                    $result->addMsg("Horario: $timetableCode. "
                                            . "Curso: $courseCode. "
                                            . "Pensum: $pensum. "
                                            . "Cohorte: $cohortDate. "
                                            . "Sección: $section. "
                                            . "Fecha Fin: $finishDate. "
                                            . "Fecha Límite Calificacion: $gradingLimitDate.");
                                    //TIMETABLE FORM IS CLEANED SO THE getSchedulingView GENERATES A NEW AN CLEAN ONE FOR A NEW TIMETABLE
                                    $timetableForm = null;
                                }
                                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::UPDATE);
                            } else {
                                $this->pg()->log($timetableForm, LM::FAILURE, LM::UPDATE);
                            }
                        }
                    }
                } else {
                    $this->pg()->log($timetableForm, LM::FAILURE, LM::UPDATE);
                }
            }
        } else {
            $logView = true;
        }
	return $this->getSchedulingView([
		    'isDirectorLogged' => $isDirector,
                    'timetableForm' => $timetableForm,
                    'timetableMsg' => $msg ?? null
                        ], $logView ?? false);
    }

    public function downloadSeasonAction() {
        //CREATING FILE
        $result = $this->timetableManager->getSeasonsFile();
        if ($result->get() == false) {
            $msg = new Message('Error', $result);
            $this->pg()->log($result, LM::ERROR, LM::READ);
        } else {
            $path = $result->getObj();
            //SENDING THE ZIP FILE WITH THE SEASON FILES
            if (is_readable($path)) {
                //FILE SIZE
                $fileSize = filesize($path);
                // WRITING HTTP HEADERS
                $response = $this->getResponse();
                $headers = $response->getHeaders();
                $headers->addHeaderLine("Content-type: application/octet-stream"); // application/zip
                $headers->addHeaderLine("Content-Disposition: attachment; filename=\"Temporadas.zip\"");
                $headers->addHeaderLine("Content-length: $fileSize");
                $headers->addHeaderLine("Cache-control: private"); //OPEN FILE DIRECTLY
                // WRITE FILE CONTENT IN HTTP CONTENT   
                $fileContent = file_get_contents($path);
                if ($fileContent != false) {
                    $response->setContent($fileContent);
                    $this->pg()->log(null, LM::SUCCESS, LM::READ);
                    return $this->getResponse(); //RETURN RESPONSE TO AVOID VIEW RENDERING
                } else {
                    $msg = new Message("Lectura Incorrecta", "No se pudo obtener el archivo de las temporadas", Message::RED);
                }
            } else {
                $msg = new Message("Lectura Incorrecta", "No se pudo leer el archivo de las temporadas", Message::RED);
            }
            $this->pg()->log($msg, LM::ERROR, LM::READ);
        }
        $view = new ViewModel([
            'downloadMsg' => $msg ?? null
        ]);
        $view->setTemplate('eep/timetable/season-view');
        return $view;
    }

    private function getCategoryForm(): Result {
        $res = new Result();
        $cohorts = $this->cohortManager->getCohorts(); //date('Y') . "-01-01");
        $degrees = $this->academyManager->getAcademicDegrees();
        $careers = $this->academyManager->getCareers(); //CurrentPensums();
        $url = $this->url()->fromRoute('timetable', ['action' => 'categorize']);
        $categoryForm = new CategorizeTimetableForm($cohorts, $degrees, $careers, $url);
        if (isset($this->category->degree)) {
            $categoryForm->setData([
                CategorizeTimetableForm::ACADEMIC_DEGREE => $this->category->degree,
                CategorizeTimetableForm::CAREER => $this->category->career,
                CategorizeTimetableForm::COHORT => $this->category->cohort,
            ]);
        }
        $res->success();
        $res->setObj($categoryForm);
        return $res;
    }

    private function getTimetableForm($type): Result {
        $res = new Result();
        //CREATING FORM PARAMETERS
        $url = $this->url()->fromRoute('timetable', ['action' => ($type == TimetableForm::TYPE_NEW ? 'create' : ($type == TimetableForm::TYPE_EDIT ? 'save' : ($type == TimetableForm::TYPE_EDIT_REQUEST ? 'edit' : 'delete')))]);
        switch ($type) {
            case TimetableForm::TYPE_NEW:
            case TimetableForm::TYPE_EDIT:
                //GETTING FORM DATA
                //GETTING COURSES
                $cohortDate = ($this->category->cohort == CategorizeTimetableForm::UPG_COURSE_COHORT) ? null : $this->category->cohort;
                $result = $this->academyManager->getCourses($this->category->pensum ?? null, $cohortDate);
                if ($result->get() == true) {
                    $courses = $result->getObj();
                    //GETTING COORDINATORS
                    $result = $this->userManager->getUsersByRole(Role::COORDINADOR);
                    if ($result->get() == true) {
                        $coordinators = $result->getObj();
                        //GETTING PROFESSORS
                        $result = $this->userManager->getUsersByRole(Role::CATEDRATICO);
                        if ($result->get() == true) {
                            $professors = $result->getObj();
                            //GETTING COURSES' TYPES
                            $result = $this->timetableManager->getCoursesTypes();
                            if ($result->get() == true) {
                                $coursesTypes = $result->getObj();
                                //GETTING LOCATIONS
                                $result = $this->timetableManager->getLocations();
                                if ($result->get() == true) {
                                    $locations = $result->getObj();
                                    //GETTING ROOMS
                                    $result = $this->timetableManager->getRooms();
                                    if ($result->get() == true) {
                                        $rooms = $result->getObj();
                                    }
                                }
                            }
                        }
                    }
                }
                if ($result->get() == false) {
                    $res = $result;
                } else {
                    $data = [
                        TimetableForm::COURSE => $courses,
                        TimetableForm::COORDINATOR => $coordinators,
                        TimetableForm::PROFESSOR => $professors,
                        TimetableForm::COURSE_TYPE => $coursesTypes,
                        TimetableForm::LOCATION => $locations,
                        TimetableForm::ROOM => $rooms,
                    ];
                    $res->success();
                }
                break;
            case TimetableForm::TYPE_EDIT_REQUEST:
            case TimetableForm::TYPE_DELETE:
                $data = [];
                $res->success();
                break;
            default:
                $res->addMsg("El tipo de horario solicitado no es uno válido. Consultar a Control Académico.");
                break;
        }
        try {
            $res->setObj(new TimetableForm($url, $type, $data));
        } catch (\Exception $ex) {
            $res->failure($ex->getMessage());
        }
        return $res;
    }

    public function seasonViewAction() {
        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        return new ViewModel();
    }

}
