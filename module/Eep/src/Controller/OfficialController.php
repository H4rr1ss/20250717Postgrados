<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\ValueObject\Message;
use Zend\View\Renderer\PhpRenderer;
use Spipu\Html2Pdf\Html2Pdf;
use Zend\View\Model\JsonModel;
use Eep\Entity\Order as O;
//SERVICES
use Eep\Service\TimetableManager;
use Eep\Service\GradesManager;
use Eep\Service\AcademyManager;
use Eep\Service\AssignmentManager;
use Eep\Service\UserManager;
use Eep\Service\LogManager as LM;
use Eep\Service\GeneralManager as GM;
//FORMS
use Eep\Form\CareerYearForm;
use Eep\Form\PostActStudentsForm;
use Eep\Form\ManualEntryForm;
use Zend\InputFilter\InputFilter;
use Zend\Validator\StringLength;
use Zend\Validator\Regex;
use Eep\Form\FieldError;
use Zend\Validator\NotEmpty;
use Zend\Validator\InArray;

class OfficialController extends AbstractActionController {

    private $timetableManager;
    private $gradesManager;
    private $academyManager;
    private $assignmentManager;
    private $renderer;
    private $userManager;

    public function __construct(TimetableManager $timetableManager, GradesManager $gradesManager, AcademyManager $academyManager, PhpRenderer $renderer, AssignmentManager $assignmentManager, UserManager $userManager) {
        $this->timetableManager = $timetableManager;
        $this->gradesManager = $gradesManager;
        $this->academyManager = $academyManager;
        $this->assignmentManager = $assignmentManager;
        $this->renderer = $renderer;
        $this->userManager = $userManager;
    }

    public function timetablesAction() {
        $careers = $this->academyManager->getCareers();
        $status = LM::FAILURE;
        $form = new CareerYearForm($careers);
        if ($this->getRequest()->isPost()) {
            $postData = $this->params()->fromPost();
            $form->setData($postData);
            if ($form->isValid()) {
                $data = $form->getData();
                $year = $data[CareerYearForm::YEAR];
                $careerCode = $data[CareerYearForm::CAREER];
                $result = $this->gradesManager->getTimetableOfficializationData($careerCode, $year);
                if (!$result->get()) {
                    $msg = new Message('Error de Consulta', $result);
                    $status = LM::ERROR;
                } else {
                    $coursesData = $result->getObj();
                    $status = LM::SUCCESS;
                }
            }
            $this->pg()->log($msg ?? null, $status, LM::READ);
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel([
            'msg' => $msg ?? null,
            'form' => $form,
            'data' => $coursesData ?? null
        ]);
    }

    public function actAction() {
        //GETTING ACT DATA
        $role = $this->layout()->role;
        $actCode = $this->params()->fromQuery('act', null);
        $status = LM::FAILURE;
        if ($actCode == null) {
            $msg = new Message('Error', 'No se obtuvo el código del acta a visualizar', Message::RED);
        } elseif ($role != null) {
            $result = $this->gradesManager->getTimetableActGralData($actCode);
            if ($result->get() == false) {
                $msg = new Message('Inconveniente', $result);
            } else {
                //CHECKING PERMISSION
                $info = $result->getObj();
                $professorCode = $info['cod_usuario_catedratico'];
                $coordinatorCode = $info['cod_usuario_coordinador'];
                if (!$role->hasAdminRole() && !$role->hasUdicaRole() && $professorCode != $this->identity() && $coordinatorCode != $this->identity()) {
                    $msg = new Message('Lectura No Permitida', "El acta solicitada ($actCode) no pertenece a un curso que impartes o coordinas");
                } else {
                    //READING ACT DATA
                    $result = $this->gradesManager->getAct($actCode);
                    if ($result->get() == false) {
                        $msg = new Message('Error en Lectura', $result);
                    } else {
                        $actData = $result->getObj();
                        ini_set('max_execution_time', 60 * 3); //THREE MINUTES WAITING IF THERE ARE MANY USERS
                        //CREATING PDF
                        $html = $this->renderer->render('eep/act', [
                            'data' => $actData
                        ]);
                        try {
                            //$path = 'Orden-de-Pago-' . $order->getCodOrden() . '.pdf';
                            $pdf = new Html2Pdf('L', 'Letter', 'es', array(mL, mT, mR, mB));
                            $pdf->setTestTdInOnePage(false);
                            $pdf->pdf->SetDisplayMode('fullpage');
                            $pdf->pdf->SetTitle("Acta Oficial $actCode");
                            $pdf->WriteHTML($html);
                            //$pdf->Output($path); //, 'D'); //->MOVED TO THE CONTROLLER FOR ITS LOGGIN (ENTER IT INTO THE LOG)
                            $pdf->Output("Acta Oficial $actCode.pdf");
                            $status = LM::SUCCESS;
                        } catch (Html2PdfException $ex) {
                            $msg = new Message('Error generando PDF', 'No se pudo generar el PDF: ' . $ex->getMessage() . '<br>' . $ex->getTraceAsString());
                            $status = LM::ERROR;
                        }
                    }
                }
            }
        } else {
            $msg = new Message('Error', 'Debes tener un rol en el sistema', Message::RED);
        }
        $this->pg()->log($result ?? ($msg ?? ''), $status, LM::READ);
        //IN THE OTHER WAY, THE MESSAGE WILL BE SHOWN        
        $viewModel = new ViewModel([
            'msg' => $msg ?? new Message('Error Desconocido', 'No se pudo descargar el acta solicitada ' . $actCode ?? '', Message::RED)
        ]);
        $viewModel->setTemplate('eep/msg');
        return $viewModel;
    }

    private function getTimetableDetailView($params, $log = false) {
        $status = LM::ERROR;
        $timetableCode = $this->params()->fromRoute('timetableCode', null);
        if ($timetableCode == null) {
            $msg = new Message('Horario No Obtenido', 'La ruta que se obtuvo, no identifica un horario asociado.', Message::RED);
        } else {
            //GETTING TIMETABLE OBJECT
            $result = $this->timetableManager->getTimetable($timetableCode);
            if (!$result->get()) {
                $msg = new Message('Error de Lectura', $result);
            } else {
                $timetable = $result->getObj();
                if ($timetable->getCodPensum() == O::CURSO_ACTUALIZACION) {
                    $msg = new Message('Curso de Actualizacion', 'No se brindan detalles de oficialización porque estos cursos no se oficializan.', Message::YELLOW);
                    $result = null;
                    $status = LM::FAILURE;
                } else {
                    //GETTING PONDER BLOCKS DATA
                    $result = $this->gradesManager->getPonderingBlocks($timetableCode);
                    if (!$result->get()) {
                        $msg = new Message('Error de Lectura de Bloques de Ponderación', $result);
                    } else {
                        $blocks = $result->getObj();
                        if (count($blocks) == 0) {
                            $msg = new Message('Bloques de Ponderación Pendientes', 'Aún no se han creado los bloques de ponderación. El catedrático debe crearlos.', Message::YELLOW);
                            $status = LM::SUCCESS;
                            $result = null;
                        } else {
                            //GET DATA IF NOT ALREADY OBTAINED
                            if (!isset($params['data'])) {
                                $result = $this->gradesManager->getOfficialGradesData($timetableCode);
                                if (!$result->get()) {
                                    $msg = new Message('Error Obteniendo Información', $result);
                                } else {
                                    $data = $result->getObj();
                                    $status = LM::SUCCESS;
                                }
                            } else {
                                $status = LM::SUCCESS;
                            }
                        }
                    }
                }
            }
        }
        //LOG
        if ($log) {
            $this->pg()->log($result ?? ($msg ?? null), $status, LM::READ);
        }
        $viewModel = new ViewModel(array_merge([
                    'timetable' => $timetable ?? null,
                    'blocks' => $blocks ?? null,
                    'data' => $data ?? null,
                    'msg' => $msg ?? null,
                    'minToApproval' => $this->gradesManager->getGlobal(GM::MINIMUM_GRADE_APPROVAL),
                    'form' => new PostActStudentsForm(null)
                        ], $params));
        $viewModel->setTemplate('eep/official/detail');
        return $viewModel;
    }

    public function detailAction() {
        return $this->getTimetableDetailView([], true);
    }

    public function receiveAction() {
        $params = [];
        $status = LM::ERROR;
        $timetableCode = $this->params()->fromRoute('timetableCode', null);
        if ($timetableCode == null) {
            $msg = new Message('Horario No Obtenido', 'La ruta que se obtuvo, no identifica un horario asociado.', Message::RED);
        } else {
            $result = $this->timetableManager->getTimetable($timetableCode);
            if (!$result->get()) {
                $msg = new Message('Error Obteniendo Horario', $result);
            } else {
                $timetable = $result->getObj();
                if ($timetable->getFechaActaAprobada() != null) {
                    $msg = new Message('Acta Ya Aprobada', 'El acta ' . $timetable->getActCode() . ' ya ha sido aprobada anteriormente.');
                    $result = null;
                    $status = LM::FAILURE;
                } else {
                    $result = $this->gradesManager->approveAct($timetable->getActCode());
                    if (!$result->get()) {
                        $msg = new Message('Error Aprobando el Acta', $result);
                    } else {
                        $msg = new Message('Aprobada', 'El acta "' . $timetable->getActCode() . '" ha sido aprobada satisfactoriamente', Message::GREEN);
                        $status = LM::SUCCESS;
                    }
                }
            }
        }
        if (isset($msg)) {
            $params['msg'] = $msg;
        }
        $this->pg()->log($result ?? ($msg ?? null), $status, LM::UPDATE);
        return $this->getTimetableDetailView($params);
    }

    public function addPostActAction() {
        $status = LM::ERROR;
        $timetableCode = $this->params()->fromRoute('timetableCode', null);
        if ($this->getRequest()->isPost() || $timetableCode == null) {
            $params = $this->params()->fromPost();
            if (empty($params)) {
                $status = LM::FAILURE;
                $msg = new Message('Sin Usuarios', 'No se obtuvieron usuarios para agregar al Acta de Postgrado');
            } else {
                $result = $this->gradesManager->getOfficialGradesData($timetableCode);
                if (!$result->get()) {
                    $msg = new Message('Error Obteniendo Detalle', $result);
                } else {
                    $data = $result->getObj();
                    $result = null;
                    $users = [];
                    $finalGrades = [];
                    $ballots = [];
                    foreach ($data as $userCode => $u) {
                        if ($u['postActAvailable']) {
                            $users[] = $userCode;
                            $finalGrades[$userCode] = $u['finalGrade'];
                            $ballots[$userCode] = $u['ballot'] ?? null;
                        }
                    }
                    $form = new PostActStudentsForm($users, $finalGrades, $ballots);
                    $form->setData($params);
                    if ($form->isValid()) {
                        $formData = $form->getData();
                        $result = $this->assignmentManager->createActIfNotExists($formData[PostActStudentsForm::ACT], AssignmentManager::EEP_POSTGRADUATE);
                        if (!$result->get()) {
                            $msg = new Message('Acta No Creada', $result);
                        } else {
                            $result = $this->gradesManager->makeGradesOfficial($formData, $timetableCode);
                            if (!$result->get()) {
                                $msg = new Message('Error de Oficialización', $result);
                            } else {
                                $msg = new Message('Notas Oficializadas', 'Las notas de los usuarios fueron oficializadas correctamente', Message::GREEN);
                                $status = LM::SUCCESS;
                                $form->setData([]);
                                unset($data);
                            }
                        }
                    } else {
                        $status = LM::FAILURE;
                        $formErrors = $form->getMessages();
                        $result = null;
                        $errors = [
                            'Observacion:' => $form->get(PostActStudentsForm::COMMENT)->getMessages(),
                            'Usuarios:' => $form->get(PostActStudentsForm::USERS)->getMessages()
                        ];
                        if (empty($errors['Observacion:'])) {
                            unset($errors['Observacion:']);
                        }
                        if (empty($errors['Usuarios:'])) {
                            unset($errors['Usuarios:']);
                        }
                        if (!empty($errors)) {
                            $msg = new Message('Campos Inválidos', Message::makeHtmlList($errors, true));
                        }
                    }
                }
            }
            $viewParams = [
                'form' => $form ?? new PostActStudentsForm(null),
                'postMsg' => $msg ?? null
            ];
            if (isset($data)) {
                $viewParams['data'] = $data;
            }
        } else {
            return $this->getTimetableDetailView([], true);
        }
        $this->pg()->log($formErrors ?? ($result ?? ($msg ?? null)), $status, LM::UPDATE);
        return $this->getTimetableDetailView($viewParams);
    }

    //AJAX
    public function getStudentsAction() {
        $response = ['status' => false];
        $logStatus = LM::FAILURE;
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $filter = new InputFilter();
            $filter->add([
                'name' => 'userId',
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => NotEmpty::class,
                        'options' => [
                            'messages' => FieldError::NOT_EMPTY
                        ],
                    ],
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 0,
                            'max' => 30,
                            'messages' => FieldError::STRING_LENGTH
                        ]
                    ],
                    [
                        'name' => Regex::class,
                        'options' => [
                            'pattern' => UserManager::PASSPORT_PATTERN,
                            'messages' => FieldError::REGEX
                        ]
                    ]
                ]
            ]);
            $filter->setData($params);
            if ($filter->isValid()) {
                $userId = $filter->getValue('userId');
                $result = $this->userManager->getMatchUsers($userId);
                if (!$result->get()) {
                    $response['error'] = implode(', ', $result->getMsg());
                    $logStatus = LM::ERROR;
                } else {
                    $response['status'] = true;
                    $response['users'] = $result->getObj();
                    $logStatus = LM::SUCCESS;
                }
            } else {
                $response['error'] = implode(', ', $filter->getMessages()['userId']);
            }
        } else {
            $this->getResponse()->setStatusCode(400);
            $response['error'] = 'Solicitud sin datos (No POST)';
        }
        if ($logStatus != LM::SUCCESS) {
            $this->pg()->log($response['error'] ?? null, $logStatus, LM::READ);
        }
        $view = new JsonModel($response);
        $view->setTerminal(true);
        return $view;
    }

    //AJAX
    public function getCoursesAction() {
        $response = ['status' => false];
        $logStatus = LM::FAILURE;
        if ($this->getRequest()->isPost()) {
            $result = $this->academyManager->getPensums();
            $pensums = [];
            foreach ($result as $pensumData) {
                $pensums[] = $pensumData['cod_pensum'];
            }
            $params = $this->params()->fromPost();
            $filter = new InputFilter();
            $filter->add([
                'name' => 'pensumCode',
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => $pensums,
                            'messages' => FieldError::IN_ARRAY,
                        ],
                    ]
                ]
            ]);
            $filter->setData($params);
            if ($filter->isValid()) {
                $pensumCode = $filter->getValue('pensumCode');
                $result = $this->academyManager->getCourses($pensumCode, null, false);
                if (!$result->get()) {
                    $response['error'] = implode(', ', $result->getMsg());
                    $logStatus = LM::ERROR;
                } else {
                    $response['status'] = true;
                    $logStatus = LM::SUCCESS;
                    $coursesData = $result->getObj();
                    $courses = [];
                    foreach ($coursesData as $courseData) {
                        $courseCode = $courseData['cod_curso'] . '';
                        $courses[$courseCode] = "$courseCode - $courseData[nombre]";
                    }
                    $response['courses'] = $courses;
                }
            } else {
                //$response['error'] = implode(', ', $filter->getMessages());
                $response['error'] = implode(', ', $filter->getMessages()['pensumCode']);
            }
        } else {
            $this->getResponse()->setStatusCode(400);
            $response['error'] = 'Solicitud sin datos (No POST)';
        }
        $view = new JsonModel($response);
        $view->setTerminal(true);
        if ($logStatus != LM::SUCCESS) {
            $this->pg()->log($response['error'] ?? null, $logStatus, LM::READ);
        }
        return $view;
    }

    public function manualEntryAction() {
        //GETTING FORM VALUES
        $result = $this->gradesManager->getFinalGradeTypes();
        if (!$result->get()) {
            $msg = new Message('Tipos de Nota Final No Obtenidos', $result);
            $this->pg()->log($result);
        } else {
            $finalGradeTypes = $result->getObj();
            if ($this->getRequest()->isPost()) {
                $params = $this->params()->fromPost();
                //GETTING USER PENSUMS
                $userCode = $params[ManualEntryForm::USER_CODE] ?? null;
                $userId = $params[ManualEntryForm::ACADEMIC_REGISTRY] ?? null;
                $pensums = [];
                if ($userCode != null && $userId != null) {
                    $result = $this->userManager->getMatchUsers($userId);
                    if ($result->get()) {
                        $resultData = $result->getObj();
                        if (isset($resultData[$userCode]['pensums'])) {
                            $pensums = $resultData[$userCode]['pensums'];
                        }
                    }
                }
                //GETTING PENSUM COURSES
                $pensumCode = $params[ManualEntryForm::PENSUM] ?? null;
                $courses = [];
                if ($pensumCode != null) {
                    $result = $this->academyManager->getCourses($pensumCode, null, false);
                    if ($result->get()) {
                        $coursesData = $result->getObj();
                        foreach ($coursesData as $courseData) {
                            $courses[$courseData['cod_curso']] = $courseData['cod_curso'] . ' - ' . $courseData['alias'];
                        }
                    }
                }
                //VALIDATING FORM
                $form = new ManualEntryForm($finalGradeTypes, $pensums, $courses);
                $form->setData($params);
                if ($form->isValid()) {
                    $data = $form->getData();
                    $postAct = $data[ManualEntryForm::ACT];
                    //ADDING ACT
                    $this->assignmentManager->beginTransaction();
                    $result = $this->assignmentManager->createActIfNotExists($postAct, AssignmentManager::EEP_POSTGRADUATE);
                    if (!$result->get()) {
                        $msg = new Message('Error en Creación de Acta', $result);
                        $this->pg()->log($result, LM::ERROR, LM::CREATE);
                        $this->assignmentManager->rollback();
                    } else {
                        //CLEARING FORM DATA
                        $userCode = $data[ManualEntryForm::USER_CODE];
                        $acadRegistry = $data[ManualEntryForm::ACADEMIC_REGISTRY];
                        $pensumCode = $data[ManualEntryForm::PENSUM];
                        $courseCode = $data[ManualEntryForm::COURSE];
                        $finalGradeType = $data[ManualEntryForm::FINAL_GRADE_TYPE];
                        $ponderType = $data[ManualEntryForm::PONDER_TYPE];
                        $grade = $data[ManualEntryForm::GRADE];
                        $approved = $data[ManualEntryForm::CHECK_GRADE];
                        $ballot = $data[ManualEntryForm::BALLOT];
                        $description = $data[ManualEntryForm::DESCRIPTION];
                        $date = $data[ManualEntryForm::DATE];
                        $section = $data[ManualEntryForm::SECTION];
                        $result = $this->gradesManager->addManualFinalGrade($userCode, $pensumCode, $courseCode, $section, $finalGradeType, $ponderType, $grade, $approved, $postAct, $ballot, $description, $date, false);
                        if (!$result->get()) {
                            $msg = new Message('Error en el Ingreso', $result);
                            $this->pg()->log($result, LM::ERROR, LM::CREATE);
                            $this->gradesManager->rollback();
                        } else {
                            $this->gradesManager->commit();
                            $text = [];
                            //ADDING USER MESSAGE TEXT
                            $text[] = "Estudiante: $acadRegistry";
                            $text[] = "Curso: $courseCode";
                            $text[] = "Pénsum: $pensumCode";
                            $text[] = "Tipo: " . ($ponderType ? 'Con Nota' : 'Sin Nota');
                            if ($ponderType) {
                                $text[] = "Nota: $grade";
                            } else {
                                $text[] = "Nota: " . ($approved==true ? 'APROBADO' : 'REPROBADO');
                            }
                            $text[] = "Acta de Postgrado: $postAct";
                            $text[] = "Recibo (Boleta): $ballot";
                            $text[] = 'Fecha Cursado: ' . date('d/m/Y', strtotime($date));
                            $msg = new Message('Nota Oficializada', $text, Message::GREEN);
                            //..AND LOG MESSAGE
                            $text[] = "Código de Usuario: $userCode";
                            $this->pg()->log($text, LM::SUCCESS, LM::CREATE);
                            //CLEANING FORM
                            $form = new ManualEntryForm($finalGradeTypes);
                        }
                    }
                } else {
                    $this->pg()->log($form, LM::FAILURE, LM::CREATE);
                }
            } else {
                $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
                $form = new ManualEntryForm($finalGradeTypes);
            }
        }
        return new ViewModel([
            'msg' => $msg ?? null,
            'form' => $form ?? null
        ]);
    }

}
