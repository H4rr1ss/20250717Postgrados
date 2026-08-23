<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Service\UserManager;
use Eep\ValueObject\Message;
use Eep\Entity\Result as R;
use Eep\Form\UserIdForm;
//SERVICES
use Eep\Service\TimetableManager;
use Eep\Service\AssignmentManager;
use Eep\Service\OrderManager;
use Eep\Service\AcademyManager;
use Eep\Service\EvaluacionDocenteManager;
//FORMS
use Eep\Form\AssignmentForm;
use Eep\Form\AssignmentTypeForm;
use Eep\Entity\Order;
use Eep\Service\LogManager as LM;
use Eep\Service\GeneralManager as GM;

class AssignmentController extends AbstractActionController {

    private $timetableManager;
    private $assignmentManager;
    private $orderManager;
    private $userManager;
    private $academyManager;
    private $evaluacionDocenteManager;

    public function __construct(TimetableManager $timetableManager, AssignmentManager $assignmentManager, OrderManager $orderManager, UserManager $userManager, AcademyManager $academyManager, EvaluacionDocenteManager $evaluacionDocenteManager) {
        $this->timetableManager = $timetableManager;
        $this->assignmentManager = $assignmentManager;
        $this->orderManager = $orderManager;
        $this->userManager = $userManager;
        $this->academyManager = $academyManager;
        $this->evaluacionDocenteManager = $evaluacionDocenteManager;
    }

    public function assignedCoursesAction() {
        $role = $this->layout()->role;
        if ($role->hasAdminRole() || $role->hasUdicaRole()) {
            //ADMIN VIEW
            $form = new UserIdForm();
            if ($this->getRequest()->isPost()) {
                $params = $this->params()->fromPost();
                $form->setData($params);
                if ($form->isValid()) {
                    $data = $form->getData();
                    $userId = $data[UserIdForm::USER];
                    $users = $this->userManager->getPossibleUsers($userId, true);
                    if (count($users) == 0) {
                        $msg = new Message('No Encontrado', "No se encontró a un usuario con el identificador '$userId'.", Message::RED);
                    } elseif (count($users) > 1) {
                        $msg = new Message('Múltiples Usuarios', "Intenta ingresar otro identificador porque varios usuarios se identifican con el código '$userId'.", Message::RED);
                    } else {
                        $user = array_pop($users); //GETTING USER
                        $userMsg = $this->academyManager->getUserCareerMsg($user); //GETTING USER CAREER DATA TO SHOW
                        $userCode = $user->getCode();
                    }
                }
            } else {
                $status = LM::SUCCESS;
            }
        } else {
            //USER VIEW
            $userCode = $this->identity();
        }
        //GETTING COURSES
        if (isset($userCode)) {
            $result = $this->assignmentManager->getUserCourses($userCode);
            if ($result->get() == false) {
                $msg = new Message('Cursos No Obtenidos', $result);
            } else {
                $coursesByCareer = $result->getObj();
            }
            $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::FAILURE, LM::READ);
        } else {
            $this->pg()->log($msg ?? (isset($form) ? $form->getMessages() : null), $status ?? LM::FAILURE, isset($status) ? LM::VIEW : LM::READ);
        }

        return new ViewModel([
            'form' => $form ?? null,
            'userMsg' => $userMsg ?? null,
            'msg' => $msg ?? null,
            'coursesByCareer' => $coursesByCareer ?? null
        ]);
    }

    /*
     * FUNCTION ALWAYS RETURN RESULT "R" WITH A FORM OBJECT, WITH ERROR OR SUCCESS RESULT
     */

    private function getAssignmentForm($userCode, $type, $year = null): R {
        $action = $type == AssignmentTypeForm::TYPE_STUDENT_REGULAR ? 'assignment' : 'adminAssignment';
        $url = $this->url()->fromRoute('assignment', ['action' => $action]);
        $timetables = [];
        //GETTING FORM
        switch ($type) {
            //getUserTimetable($userCode = null, $excludeActivePaymentOrderAsoc = false, $excludePayedPaymentOrderAsoc = false, $includeUpgradingCourses = true, $includeWholeCareer = false, $getActiveTimetables = false, $year = null, $excludeAssigned = false): R {
            case AssignmentTypeForm::TYPE_REGULAR:
            case AssignmentTypeForm::TYPE_STUDENT_REGULAR:
                $res = $this->timetableManager->getUserTimetable($userCode, //USER
                        true, //excludeActivePaymentOrderAsoc
                        true); //excludePayedPaymentOrderAsoc
                break;
            case AssignmentTypeForm::TYPE_EXTEMP:
                $res = $this->timetableManager->getUserTimetable($userCode, //USER
                        true, //excludeActivePaymentOrderAsoc
                        false, //excludePayedPaymentOrderAsoc
                        true, //includeUpgradingCourses
                        false, //includeWholeCareer
                        $year == null, //getActiveTimetables
                        $year, //YEAR
                        true); //excludeAssigned
                break;
            case AssignmentTypeForm::TYPE_EXTRA:
                $res = $this->timetableManager->getUserTimetable($userCode, //USER
                        true, //excludeActivePaymentOrderAsoc
                        true, //excludePayedPaymentOrderAsoc
                        false, //includeUpgradingCourses
                        true, //includeWholeCareer
                        true, //getActiveTimetables
                        $year);  //YEAR
                break;
            default:
                $res = $res;
                $res->addMsg("El tipo de asignación administrativo ($type) no es el correcto.");
                break;
        }
        if ($res->get() == true) {
            $timetables = $res->getObj();
        }

        $regularDays = $this->assignmentManager->getGlobal(GM::ASSIGNMENT_DAYS, 5);
        $extDays = $this->assignmentManager->getGlobal(GM::EXT_ASSIGNMENT_DAYS, 5);
        $form = new AssignmentForm($url, $timetables, $type, $regularDays, $extDays);
        $res->setObj($form);
        return $res;
    }

    private function getPayedNotAssignedTimetables($userCode, $year): R {
        $result = $this->timetableManager->getUserTimetable($userCode, //USER
                false, //excludeActivePaymentOrderAsoc
                true, //excludePayedPaymentOrderAsoc
                true, //includeUpgradingCourses
                false, //includeWholeCareer
                false, //getActiveTimetables
                $year, //YEAR
                true, //excludeAssigned
                true); //Toggle Exclude to Include
        if ($result->get() == true) {
            $careerByTimetable = $result->getObj();
            $timetables = [];
            foreach ($careerByTimetable as $careerTimetables) {
                $timetables = array_merge($timetables, $careerTimetables);
            }
            $result->setObj($timetables);
        }
        return $result;
    }

    public function assignmentTypeAction() {
        $url = $this->url()->fromRoute('assignment', ['action' => 'assignmentType']);
        $form = new AssignmentTypeForm($url);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $form->setData($params);
            if ($form->isValid()) {
                $data = $form->getData();
                $userId = $data[AssignmentTypeForm::USER];
                $type = $data[AssignmentTypeForm::ASSIGNMENT_TYPE];
                if ($type == AssignmentTypeForm::TYPE_EXTEMP) {
                    $year = $data[AssignmentTypeForm::YEAR];
                } else {
                    $year = null;
                }
                //SEARCHING USER
                $users = $this->userManager->getPossibleUsers($userId, true);
                $status = LM::FAILURE;
                if (count($users) == 0) {
                    $msg = new Message('Usuario No Encontrado', "No se encontró el usuario con el código de identificación \"$userId\".", Message::RED);
                } elseif (count($users) > 1) {
                    $msg = new Message('Múltiples Usuarios Encontrados', "Se encontraró más de un usuario con el código de identificación \"$userId\". Utilice otro código de identificación para ese estudiante. (CUI/Registro Académico/Pasaporte)", Message::RED);
                } else {
                    $user = array_pop($users);
                    $userMsg = $this->academyManager->getUserCareerMsg($user, $year);
                    //GETTING FORM
                    $result = $this->getAssignmentForm($user->getCode(), $type, $year);
                    if ($result->get() == false) {
                        $msg = new Message('Asignación no disponible', $result);
                        $status = LM::ERROR;
                    } else {
                        if (!empty($result->getMsg())) {
                            $formMsg = new Message('Observación de Asignación', $result);
                        }
                        $asgForm = $result->getObj();
                        $asgForm->setData($data);
                        $asgForm->setData([
                            AssignmentForm::USER_CODE => $user->getCode(),
                        ]);
                        if ($type == AssignmentTypeForm::TYPE_EXTEMP) {
                            $result = $this->getPayedNotAssignedTimetables($user->getCode(), $year);
                            if ($result->get() == false) {
                                $msg = new Message('Error al Obtener Horarios Pagados Sin Asignar', $result);
                                $status = LM::ERROR;
                            } else {
                                $payedTimetables = $result->getObj();
                                $status = LM::SUCCESS;
                            }
                        } else {
                            $status = LM::SUCCESS;
                        }
                    }
                }
                $this->pg()->log($result ?? $msg, $status, LM::READ);
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel([
            'form' => $form,
            'msg' => $msg ?? null,
            'asgForm' => $asgForm ?? null,
            'userMsg' => $userMsg ?? null,
            'formMsg' => $formMsg ?? null,
            'payedTimetables' => $payedTimetables ?? null
        ]);
    }

    public function assignmentAction() {
        $userCode = $this->identity();

        // Verificar si tiene evaluaciones docentes pendientes
        $evaluacionResult = $this->evaluacionDocenteManager->getCursosPendientes($userCode);
        if ($evaluacionResult->get() && is_array($evaluacionResult->getObj()) && count($evaluacionResult->getObj()) > 0) {
            $this->flashMessenger()->addWarningMessage(
                'Tiene evaluaciones docentes pendientes. Debe completarlas antes de asignar nuevos cursos.'
            );
            return $this->redirect()->toRoute('evaluacion-docente');
        }

        $result = $this->getAssignmentForm($userCode, AssignmentTypeForm::TYPE_STUDENT_REGULAR);
        if (!empty($result->getMsg())) {
            $formMsg = new Message('Observación de Asignación', $result);
        }
        $form = $result->getObj();

        //VALIDATING POST DATA
        if ($result->get() == true && $this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $form->setData($params);
            if ($form->isValid()) {
                //GETTING USER - NO VALIDATION REQUIRED BECAUSE IT IS OBTAIND BY SESSION IDENTITY
                $user = $this->userManager->getUser($userCode);
                //ADD PAYMENT ORDER
                $data = $form->getData();
                $tt = $data[AssignmentForm::TIMETABLES];
                $result = $this->orderManager->createOrder($user, $tt, AssignmentForm::TYPE_STUDENT_REGULAR);
                $status = LM::ERROR;
                if ($result->get() == false) {
                    $msg = new Message('Ordenes de Pago no Generadas', $result);
                } else {
                    //GETTING ORDER DETAILS
                    $orders = $result->getObj();
                    $texts = [];
                    $logMsgs = [];
                    foreach ($orders as $order) {
                        $orderCode = $order->getCodOrden();
                        $texts[] = '<a href="' . (string) $this->url()->fromRoute('order', ['id' => $orderCode]) . "\" target=\"_blank\" rel=\"noopener noreferrer\" >Descargar orden de pago No. $orderCode</a>";
                        $logMsgs[] = "Orden No. $orderCode";
                    }
                    //REMAKE THE FORM WITHOUT THE SELECTED COURSES
                    $result = $this->getAssignmentForm($userCode, AssignmentTypeForm::TYPE_STUDENT_REGULAR);
                    $result->addMsg($logMsgs);
                    $form = $result->getObj();
                    if ($result->get() == false) {
                        $msg = new Message('Orden(es) de Pago Creada(s)', $result->getMsg(), Message::YELLOW);
                        $status = LM::WARNING;
                    } else {
                        $msg = new Message('Orden(es) de Pago Creada(s):', $texts, Message::GREEN);
                        $status = LM::SUCCESS;
                    }
                }
                $this->pg()->log($result, $status, LM::CREATE);
            } else {
                $timetableErrors = $form->get(AssignmentForm::TIMETABLES)->getMessages();
                if (!empty($timetableErrors)) {
                    $msg = new Message('Error', $timetableErrors, Message::RED);
                }
                $this->pg()->log($form, LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }

        return new ViewModel([
            'form' => $form ?? null,
            'formMsg' => $formMsg ?? null,
            'msg' => $msg ?? null
        ]);
    }

    public function adminAssignmentAction() {
        $typeUrl = $this->url()->fromRoute('assignment', ['action' => 'assignmentType']);
        $asgTypeForm = new AssignmentTypeForm($typeUrl);
        if ($this->getRequest()->isPost()) {
            //VALIDATING ASSIGNMENT TYPE DATA
            $params = $this->params()->fromPost();
            $asgTypeForm->setData($params);
            if ($asgTypeForm->isValid()) {
                //GETTING RAW USER CODE
                $userCode = $params[AssignmentForm::USER_CODE] ?? false;
                if ($userCode === false || intval($userCode) == 0) {
                    $asgTypeMsg = new Message('Error de Código de Usuario', 'El código de usuario no fue obtenido correctamente', Message::RED);
                    $this->pg()->log($asgTypeMsg, LM::FAILURE, LM::CREATE);
                } else {
                    //CREATING THE ASSIGNMENT FORM
                    $status = LM::FAILURE;
                    $user = $this->userManager->getUser($userCode, true);
                    $typeData = $asgTypeForm->getData();
                    $type = $typeData[AssignmentTypeForm::ASSIGNMENT_TYPE];
                    $year = $typeData[AssignmentTypeForm::YEAR] ?? null; //THIS ONE MIGHT NOT BE SETTED
                    $userMsg = $this->academyManager->getUserCareerMsg($user, $year);
                    $result = $this->getAssignmentForm($userCode, $type, $year);
                    if (!empty($result->getMsg())) {
                        $formMsg = new Message('Observación de Asignación', $result);
                    }
                    $form = $result->getObj();
                    if ($result->get() == true) {
                        //VALIDATING DATA
                        $form->setData($params);
                        $isValid = $form->isValid();
                        if ($isValid) {
                            $data = $form->getData();
                            $tt = $data[AssignmentForm::TIMETABLES];

                            //SEARCH FOR ALREADY PAYED TIMETABLES
                            $asgPayedTtError = false;
                            if ($type == AssignmentForm::TYPE_EXTEMP) {
                                $result = $this->assignmentManager->assignPayedTimetables($userCode, $tt);
                                if ($result->get() == false) {
                                    $asgPayedTtError = true;
                                    $msg = new Message('Verificación de Horarios Pagados Fallida', $result);
                                } else {
                                    $notPayedTt = $result->getObj(); //TIMETABLES WITHOUT A PAYED ORDER
                                    $payedTt = array_diff($tt, $notPayedTt);
                                    if (count($payedTt) > 0) {
                                        $payedAndAssignedTt = [];
                                        foreach ($payedTt as $ttCode) {
                                            $ttObj = $this->timetableManager->getTimetable($ttCode)->getObj(); //SHOULDN'T HAVE ERRORS BECAUSE THE FORM VALIDATED THE CODES
                                            $month = $ttObj->getMes();
                                            $payedAndAssignedTt[] = "$ttObj [Mes: " . AssignmentForm::MONTHS[$month] . "]";
                                        }
                                        $assignedMsg = new Message('Horarios de Cursos Pagados Fuera de Tiempo y Asignados', $payedAndAssignedTt, Message::YELLOW);
                                    }
                                }
                            } else {
                                $notPayedTt = $tt;
                            }

                            //CREATING ORDERS
                            if (!$asgPayedTtError) {
                                if (count($notPayedTt) == 0) {
                                    //PAYED TIMETABLES ASSIGNED AND THERE ARE NO TIMETABLES LEFT
                                    $status = LM::SUCCESS;
                                } else {
                                    $this->assignmentManager->beginTransaction(); //ALL SERVICES SHARE THE SAME ADAPTER
                                    $result = $this->orderManager->createOrder($user, $notPayedTt, $type);
                                    if ($result->get() == false) {
                                        $this->assignmentManager->rollback();
                                        $msg = new Message('Órdenes de Pago No Generadas', $result);
                                    } else {
                                        $orders = $result->getObj();
                                        //ADD ACT DATA IF NEEDED
                                        $hasAct = $data[AssignmentForm::HAS_ACT] == 'yes';
                                        if (($type == AssignmentForm::TYPE_EXTEMP || $type == AssignmentForm::TYPE_EXTRA)) {
                                            $actCode = $hasAct ? $data[AssignmentForm::ACT_RECORD] : Order::NO_ACT_CODE;
                                            $actSubsection = $hasAct ? $data[AssignmentForm::ACT_SUBSECTION] : null;
                                            if ($type == AssignmentForm::TYPE_EXTEMP) {
                                                $actType = AssignmentManager::CA_EXTEMPORARY;
                                            } else {
                                                $actType = AssignmentManager::CA_EXTRAORDINARY;
                                            }
                                            foreach ($orders as $order) {
                                                $orderCode = $order->getCodOrden();
                                                $detail = $order->getDetail();
                                                $orderTt = [];
                                                foreach ($detail as $d) {
                                                    $orderTt[] = $d->getCodHorario();
                                                }
                                                $result = $this->assignmentManager->addActData($actCode, $actSubsection, $userCode, $orderCode, $orderTt, $actType, false);
                                                if ($result->get() == false) {
                                                    break;
                                                }
                                            }
                                            if ($result->get() == false) {
                                                $msg = new Message('Generación No Efectuada', $result);
                                                $this->assignmentManager->rollback();
                                            }
                                        }
                                        if ($result->get() == true) {
                                            $this->assignmentManager->commit(); //SAVE ALL CHANGES
                                            $status = LM::SUCCESS;
                                            //CREATING ORDER DOWNLOAD MESSAGE
                                            $ordersLog = [];
                                            $texts = [];
                                            foreach ($orders as $order) {
                                                $orderCode = $order->getCodOrden();
                                                $texts[] = '<a href="' . (string) $this->url()->fromRoute('order', ['id' => $orderCode]) . "\" target=\"_blank\" rel=\"noopener noreferrer\" >Descargar orden de pago No. $orderCode</a>";
                                                $ordersLog[] = "Orden No. $orderCode";
                                            }
                                            $asgTypeMsg = new Message('Orden(es) de Pago Creada(s):', $texts, Message::GREEN);
                                            //DELETING ASSIGNMENT FORM
                                            $form = null;
                                        }
                                    }
                                }
                            }
                        }
                        if ($type == AssignmentTypeForm::TYPE_EXTEMP) {
                            $result = $this->getPayedNotAssignedTimetables($user->getCode(), $year);
                            if ($result->get() == false) {
                                $msg = new Message('Error al Obtener Horarios Pagados Sin Asignar', $result);
                            } else {
                                $payedTimetables = $result->getObj();
                            }
                        }
                        $logMsgs = [];
                        if (!$isValid) {
                            $logMsgs = $form->getMessages();
                        }
                        if ($result->get() == false) {
                            $logMsgs = array_merge($logMsgs, $result->getMsg());
                            $status = LM::ERROR;
                        }
                        if (isset($payedAndAssignedTt)) {
                            $logMsgs['Horarios previamente pagados y asignados'] = $payedAndAssignedTt;
                        }
                        if (isset($ordersLog)) {
                            $logMsgs['Ordenes de pago creadas'] = $ordersLog;
                        }
                        if (isset($user)) {
                            $logMsgs['Estudiante'][] = 'Nombre:' . $user->getNombres() . ' ' . $user->getApellidos();
                            if (null != ($user->getCui())) {
                                $logMsgs['Estudiante'][] = 'CUI: ' . $user->getCui();
                            }
                            if (null != ($user->getPasaporte())) {
                                $logMsgs['Estudiante'][] = 'Pasaporte: ' . $user->getPasaporte();
                            }
                            if (null != ($user->getRegistroPersonal())) {
                                $logMsgs['Estudiante'][] = 'Registro de Personal: ' . $user->getRegistroPersonal();
                            }
                            if (null != ($user->getRegistroAcademico())) {
                                $logMsgs['Estudiante'][] = 'Registro Académico: ' . $user->getRegistroAcademico();
                            }
                        }
                        $this->pg()->log($logMsgs, $status, LM::CREATE);
                    } else {
                        $this->pg()->log($result, LM::FAILURE, LM::CREATE);
                    }
                }
            } else {
                $asgTypeMsg = new Message('Parámetros Ocultos Erróneos', 'Los parámetros del tipo de asignación fueron alterados. Mensajes personalizados mostrados en el formulario de tipo de asignación.', Message::RED);
                $this->pg()->log($asgTypeForm->getMessages(), LM::FAILURE, LM::CREATE);
            }
        } else {
            $asgTypeMsg = new Message('Solicitud Errónea', 'No se encontraron datos de petición', Message::RED);
            $this->pg()->log($asgTypeMsg, LM::FAILURE, LM::CREATE);
        }

        $view = new ViewModel([
            //ASSIGNMENT TYPE FORM
            'form' => $asgTypeForm,
            'msg' => $asgTypeMsg ?? null,
            'assignedMsg' => $assignedMsg ?? null,
            //ASSIGNMENT FORM
            'asgForm' => $form ?? null,
            'asgMsg' => $msg ?? null,
            'userMsg' => $userMsg ?? null,
            'formMsg' => $formMsg ?? null,
            'payedTimetables' => $payedTimetables
        ]);
        $view->setTemplate('eep/assignment/assignment-type');
        return $view;
    }

}
