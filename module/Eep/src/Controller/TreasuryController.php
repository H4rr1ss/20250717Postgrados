<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Service\OrderManager;
use Eep\ValueObject\Message;
use Zend\View\Renderer\PhpRenderer;
use Eep\Form\UserIdForm;
use Eep\Form\DeleteIdForm;
use Eep\Form\GralReportForm;
use Eep\Service\AcademyManager;
use Eep\Service\AssignmentManager;
use Eep\Service\CohortManager;
use Eep\Service\TimetableManager;
use Eep\Service\InscriptionManager;
use Eep\Service\UserManager;
use Eep\Service\ReportManager;
use Zend\View\Model\JsonModel;
use Eep\Entity\Order;
use Eep\Entity\Result as R;
use Eep\Service\LogManager as LM;
use Eep\Service\GeneralManager as GM;

class TreasuryController extends AbstractActionController {

    private $orderManager;
    private $academyManager;
    private $timetableManager;
    private $userManager;
    private $cohortManager;
    private $reportManager;
    private $assignmentManager;
    private $inscriptionManager;
    private $renderer;

    public function __construct(InscriptionManager $inscriptionManager, AssignmentManager $assignmentManager, ReportManager $reportManager, OrderManager $orderManager, AcademyManager $academyManager, UserManager $userManager, CohortManager $cohortManager, TimetableManager $timetableManager, PhpRenderer $renderer) {//IF FACTORY NEEDED, INJECT RENDERER WITH: $container->get('ViewRenderer')
        $this->inscriptionManager = $inscriptionManager;
        $this->orderManager = $orderManager;
        $this->academyManager = $academyManager;
        $this->userManager = $userManager;
        $this->cohortManager = $cohortManager;
        $this->timetableManager = $timetableManager;
        $this->reportManager = $reportManager;
        $this->assignmentManager = $assignmentManager;
        $this->renderer = $renderer;
    }

    private function getGralReportForm($validateDate = true) {
        $cohorts = $this->cohortManager->getCohorts();
        $degrees = $this->academyManager->getAcademicDegrees();
        $careers = $this->academyManager->getPensums();
        $pensumCohorts = $this->academyManager->getPensumCohorts();
        return new GralReportForm($degrees, $careers, $cohorts, $pensumCohorts, $validateDate);
    }

    public function gralReportAction() {
        //GETTING DATA
        $form = $this->getGralReportForm();
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $course = $params[GralReportForm::COURSE];
            $timetable = $params[GralReportForm::SECTION];
            unset($params[GralReportForm::COURSE]);
            unset($params[GralReportForm::SECTION]);
            $form->setData($params);
            if ($form->isValid()) {
                $data = $form->getData();
                $degree = $data[GralReportForm::ACADEMIC_DEGREE];
                $pensum = $data[GralReportForm::PENSUM];
                $cohort = $data[GralReportForm::COHORT];
                $startDate = $data[GralReportForm::START_DATE];
                $finishDate = $data[GralReportForm::FINISH_DATE];
                //CHECK IF COURSE OR SECTION HAS ANY VALUE
                if (empty($cohort) || $cohort == GralReportForm::UPG_COURSE_COHORT) {
                    $cohort = null;
                }
                if (empty($course) && empty($timetable)) {
                    //GENERATE REPORT WITH COHORT, DEGREE AND CAREER
                    //CREATING REPORT
                    $result = $this->reportManager->getGralReport($degree, $pensum, $cohort, $startDate, $finishDate);
                    if ($result->get() == false) {
                        $msg = new Message('Error de Creación de Reporte', $result);
                    } else {
                        $report = $result->getObj();
                    }
                    $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
                } else {
                    //FILTER COURSE AND TIMETABLE TOO
                    if ($form->hasPensum()) {//IF IT'S FALSE, THE FORM ADDS THE MESSAGE TO THE ELEMENT
                        $result = $this->academyManager->getCourses($pensum, $cohort);
                        if ($result->get() == false) {
                            $msg = new Message('Error obteniendo los cursos del pensum seleccionado para validación de información', $result);
                        } else {
                            $courses = $result->getObj();
                            $result = $this->timetableManager->getTimetables($pensum, $cohort, false, $finishDate, $startDate, false);
                            if ($result->get() == false) {
                                $msg = new Message('Error obteniendo las secciones (horarios) del pensum seleccionado para validación de información', $result);
                                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
                            } else {
                                $timetables = $result->getObj();
                                //ADD FILTERING DATA TO FORM
                                $form->addCourseSectionFilter($courses, $timetables);
                                $form->setData(array_merge($data, [
                                    GralReportForm::COURSE => $course,
                                    GralReportForm::SECTION => $timetable
                                ]));
                                if ($form->isValid()) {
                                    //GETTIN REPORT
                                    if (!empty($timetable)) { //SEARCH BY TIMETABLE
                                        $result = $this->reportManager->getTimetableReport($timetable, $startDate, $finishDate);
                                    } else {
                                        $result = $this->reportManager->getCourseReport($course, $pensum, $cohort, $startDate, $finishDate);
                                    }
                                    //SUPPORTING REPORT ERROR
                                    if ($result->get() == false) {
                                        $msg = new Message('Error de Creación de Reporte', $result);
                                    } else {
                                        $report = $result->getObj();
                                    }
                                    $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
                                } else {
                                    $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
                                }
                            }
                        }
                    } else {
                        $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
                    }
                }
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS);
        }
        return new ViewModel([
            'form' => $form,
            'msg' => $msg ?? null,
            'report' => $report ?? null
        ]);
    }

    public function ajaxGetCoursesTimetablesAction() {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $response = ['status' => false];
            $params = $this->params()->fromPost();
            $form = $this->getGralReportForm(true); //VALIDATING DATE
            $form->setData($params);
            if ($form->isValid() && $form->hasPensum()) {
                $data = $form->getData();
                //GETTING NEEDED DATA
                $pensum = $data[GralReportForm::PENSUM];
                $cohort = $data[GralReportForm::COHORT];
                $startDate = $data[GralReportForm::START_DATE];
                $finishDate = $data[GralReportForm::FINISH_DATE];
                if (empty($cohort) || $cohort == GralReportForm::UPG_COURSE_COHORT) {
                    $cohort = null;
                }
                $result = $this->timetableManager->getCoursesTimetableTrees($pensum, $cohort, GralReportForm::EMPTY_OPTION_LABEL, $startDate, $finishDate);
                if ($result->get() == false) {
                    $response['message'] = 'Error: ' . Message::makeHtmlList($result->getMsg());
                } else {
                    $resultData = $result->getObj();
                    $response['data'] = $resultData;
                    $response['status'] = true;
                }
                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
                $es = $form->getElements();
                $messages = [];
                foreach ($es as $e) {
                    if (!empty($e->getMessages())) {
                        $messages[] = $e->getName() . ': ' . implode(', ', $e->getMessages());
                    }
                }
                $response['message'] = Message::makeHtmlList($messages);
            }
            $view = new JsonModel($response);
            $view->setTerminal(true);
            return $view;
        }
        $this->pg()->log('Solicitud errónea. No se obtuvo una solicitud AJAX.', LM::FAILURE, LM::READ);
        $viewModel = new ViewModel([
            'msg' => new Message('Solicitud Errónea', 'No se realizó la solicitud AJAX', Message::RED)
        ]);
        $viewModel->setTemplate('eep/msg');
        return $viewModel;
    }

    private function getOrderListingView($userCode = null, $logView = false): ViewModel {
        //CHECKING ADMIN AUTHORIZATION
        $role = $this->layout()->role;
        $fullAdminView = false;
        if ($role->isDirector() || $role->isAsistente() || $role->isTesorero() || $role->isUdicaJefe() || $role->isUdicaProgramador() || $role->isProgramador()) {
            $fullAdminView = true;
            //GETTING ADMIN USER FORM
            $form = new UserIdForm($this->url()->fromRoute('treasury', ['action' => 'orderListing']));
            //GETTING POST DATA IF NOT PROVIDED FROM DeleteOrderAction
            if ($userCode == null && $this->getRequest()->isPost()) {
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
                        $userCode = $user->getCode();
                    }
                }
            }
        } else {
            $userCode = $this->identity();
        }
        if (isset($userCode)) {
            //GETTING USER IF NOT PRIVIDED BY ADMIN POST VALIDATION DATA SEARCH
            if (!isset($user)) {
                $user = $this->userManager->getUser($userCode);
            }
            //WHEN ADMIN ORDER DELETION IS REQUESTED, USER ID IS SETTED INTO THE FORM FOR USER BETTER EXPERIENCE
            //THAT WILL BE THE CASE IF $userCode WAS PROVIDED ($params NOT SETTED) AND IS AN ADMIN VIEW REQUEST
            if (!isset($params) && $fullAdminView) {
                $userId = $user->getRegistroAcademico() ?? ($user->getCui() ?? $user->getPasaporte());
                $form->setData([UserIdForm::USER => $userId]);
            }
            if ($fullAdminView) {
                $userMsg = $this->academyManager->getUserCareerMsg($user); //GETTING USER CAREER DATA TO SHOW
                $result = $this->orderManager->getUserOrdersbyCareer($userCode);
            } else {
                $result = $this->orderManager->getUserOrdersbyCareer($userCode); //, true);
            }
            if ($result->get() == false) {
                $msg = new Message('Ordenes No Obtenidas', $result);
            } else {
                $ordersByCareer = $result->getObj();
            }
            if ($logView) {
                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
            }
        } elseif ($logView) {
            if ($fullAdminView) {
                //JUST VIEW
                $this->pg()->log(null, LM::SUCCESS);
            } else {
                $this->pg()->log('El usuario no tiene identidad y se le permitió ingresar a esta acción', LM::ERROR, LM::VIEW);
                return $this->redirect()->toRoute('auth', ['action' => 'login']);
            }
        }

        return new ViewModel([
            'form' => $form ?? null, //SEARCH FORM
            'msg' => $msg ?? null,
            'userMsg' => $userMsg ?? null,
            'ordersByCareer' => $ordersByCareer ?? null,
            'deleteForm' => isset($ordersByCareer) ? new DeleteIdForm($this->url()->fromRoute('treasury', ['action' => 'deleteOrder'])) : null
        ]);
    }

    public function orderListingAction() {
        return $this->getOrderListingView(null, true);
    }

    public function deleteOrderAction() {
        if ($this->getRequest()->isPost()) {
            //VALIDATING DATA
            $params = $this->params()->fromPost();
            $form = new DeleteIdForm();
            $form->setData($params);
            if ($form->isValid()) {
                //GETTING ORDER
                $data = $form->getData();
                $orderCode = $data[DeleteIdForm::DELETE_ID];
                $result = $this->orderManager->getOrder($orderCode);
                if ($result->get() == false) {
                    $msg = new Message('Error Obteniendo la Orden de Pago', $result);
                    $this->pg()->log($result, LM::ERROR, LM::READ);
                } else {
                    //INSCRIPTION ORDERS CANNOT BE DELTED
                    $order = $result->getObj();
                    if ($order->getCodTipoOrden() == Order::ASSIGNMENT) {
                        //VALIDATING AUTHORIZATION TO DELETE THAT SPECIFIC ORDER
                        $role = $this->layout()->role;
                        $hasPermission = false;
                        $userCode = $order->getCodUsuario();
                        if ($role->isAsistente() || $role->isTesorero() || $role->isDirector() || $role->isUdicaJefe()) {
                            $hasPermission = true;
                        } else {
                            $userIdentity = $this->identity();
                            if ($userCode != $userIdentity) {
                                $msg = new Message('No Autorizado', 'La orden de pago que quieres eliminar no es de tu dominio.', Message::RED);
                            } else {
                                if ($order->getActiva() == true) {
                                    $hasPermission = true;
                                } else {
                                    $payed = $order->getPagada() == true ? 'Sí' : 'No';
                                    $msg = new Message('Orden Vencida', "La orden de pago que solicitas ya no está activa. Por otro lado, $payed está pagada. ", Message::RED);
                                }
                            }
                        }
                        //DELETING IF AUTH
                        if ($hasPermission) {
                            $result = $this->orderManager->setOrderInactive($orderCode);
                            if ($result->get() == false) {
                                $msg = new Message('Orden No Eliminada', $result);
                            } else {
                                $result->addMsg("Orden No. $orderCode marcada como inactiva");
                                $msg = new Message('Orden Eliminada', $result);
                            }
                        }
                        $this->pg()->log($msg, (isset($result) ? ($result->get() ? LM::SUCCESS : LM::ERROR) : LM::FAILURE), LM::UPDATE);
                    } else {
                        $msg = new Message('Orden No Eliminada', 'No puedes eliminar una orden de pago de inscripción', Message::RED);
                        $this->pg()->log($msg, LM::FAILURE, LM::READ);
                    }
                }
            } else {
                $msg = new Message('Orden Errónea', $form->getMessages(), Message::RED);
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::DELETE);
            }
        } else {
            $msg = new Message('Solicitud Errónea', 'No se obtuvo la órden de pago que se desea eliminar', Message::RED);
            $this->pg()->log($msg, LM::FAILURE, LM::DELETE);
        }
        //GETTING ORDER LISTING VIEW
        $view = $this->getOrderListingView($userCode ?? null);
        //ADDING MESSAGE
        $view->setVariable('orderMsg', $msg ?? null);
        //SETTING ORDER-LISTING TEMPLATE VIEW
        $view->setTemplate('eep/treasury/order-listing');
        return $view;
    }

    public function downloadOrderAction() {
        //GETTING ORDER DATA
        $role = $this->layout()->role;
        $orderCode = $this->params()->fromRoute('id', false);
        if ($orderCode === false) {
            $msg = new Message('Error', 'No se obtuvo el código de la orden de pago', Message::RED);
            $this->pg()->log($msg, LM::FAILURE, LM::READ);
        } elseif ($role != null) {
            $result = $this->orderManager->getOrder($orderCode);
            if ($result->get() == false) {
                $msg = new Message('Orden de Pago No Obtenida', $result);
            } else {
                //VERIFYING USER PERMISSION
                $order = $result->getObj();
                $hasPermission = false;
                if ($role->isAsistente() || $role->isTesorero() || $role->isDirector() || $role->isUdicaJefe()) {
                    $hasPermission = true;
                } else {
                    $userCode = $order->getCodUsuario();
                    $userIdentity = $this->identity();
                    if ($userCode != $userIdentity) {
                        $msg = new Message('No Autorizado', 'La orden de pago que solicitas no está asignada a tu usuario.', Message::RED);
                        $this->pg()->log($msg, LM::FAILURE, LM::READ);
                    } else {
                        if ($order->getActiva() == true) {
                            $hasPermission = true;
                        } else {
                            $payed = $order->getPagada() == true ? 'Sí' : 'No';
                            $msg = new Message('Orden Vencida', "La orden de pago que solicitas ya no está activa. Por otro lado, $payed está pagada. ", Message::RED);
                            $this->pg()->log($msg, LM::FAILURE, LM::READ);
                        }
                    }
                }
                if ($hasPermission) {
                    //GET ORDER TYPE
                    $result = $this->orderManager->getAssignmentOrderType($orderCode);
                    if ($result->get() == false) {
                        $msg = new Message('Descarga Fallida', $result);
                    } else {
                        $type = $result->getObj();
                        //GENERATING PAYMENT ORDER
                        //INSIDE, THE COMMAND "$pdf->Output($path, 'D');" FORCES THE DOWNLOAD INSIDE THE MANAGER                    
                        $result = $this->orderManager->createOrderPDF($order, $this->renderer, $type);
                        if ($result->get() == true) {
                            try {
                                $path = 'Orden-de-Pago-' . $order->getCodOrden() . '.pdf';
                                $pdf = $result->getObj();
                                $pdf->Output($path);
                                $this->pg()->log("Orden No. $orderCode Descargada", LM::SUCCESS, LM::READ);
                            } catch (\Exception $ex) {
                                $result->failure('No se pudo generar el PDF (Controlador): ' . $ex->getMessage() . '<br>' . $ex->getTraceAsString());
                            }
                        }
                        $msg = new Message('Descarga Fallida', $result);

                        /* else {
                          $path = $result->getObj();
                          if (is_readable($path)) {
                          //FILE SIZE
                          $fileSize = filesize($path);
                          // WRITING HTTP HEADERS
                          $response = $this->getResponse();
                          $headers = $response->getHeaders();
                          $headers->addHeaderLine("Content-type: application/pdf");
                          $headers->addHeaderLine("Content-Disposition: inline; filename=\"Orden-$orderCode.pdf\"");
                          $headers->addHeaderLine("Content-length: $fileSize");
                          $headers->addHeaderLine("Cache-control: private"); //OPEN FILE DIRECTLY
                          // WRITE FILE CONTENT IN HTTP CONTENT
                          $fileContent = file_get_contents($path);
                          if ($fileContent != false) {
                          $response->setContent($fileContent);
                          return $this->getResponse(); //RETURN RESPONSE TO AVOID VIEW RENDERING
                          } else {
                          $text = "No se pudo obtener el contenido de la plantilla y descargar";
                          }
                          } else {
                          $text = "La plantilla no se pudo leer y descargar";
                          }
                          } */
                    }
                    if ($result->get() == false) {
                        $this->pg()->log($result, LM::ERROR, LM::READ);
                    }
                }
            }
        } else {
            $msg = new Message('Error', 'Debes tener un rol en el sistema', Message::RED);
            $this->pg()->log($msg, LM::FAILURE, LM::READ);
        }
        //IN THE OTHER WAY, THE MESSAGE WILL BE SHOWN        
        $viewModel = new ViewModel([
            'msg' => $msg ?? new Message('Error Desconocido', 'No se pudo descargar la orden de pago por razones desconocidas', Message::RED)
        ]);
        $viewModel->setTemplate('eep/msg');
        return $viewModel;
    }

    public function confirmOrderAction() {
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $user = $data['user'] ?? null;
            $password = $data['password'] ?? null;
            $orderCode = $data['ID_ORDEN_PAGO'] ?? null;
            $response['description'] = '';
            $status = LM::ERROR;
            $serverPassword = $this->orderManager->getGlobal(GM::SERVER_REQUEST_PASSWORD, false);
            if ($user == GM::USER && $password == $serverPassword) {
                //GETTING ORDER PREVIOUS STATUS
                if (empty($orderCode)) {
                    $response['status'] = false;
                    $response['description'] = 'El código de orden de pago está vacío';
                    $status = LM::FAILURE;
                } elseif (intval($orderCode) == false) {
                    $response['status'] = false;
                    $response['description'] = "El código de la orden de pago no es un número: '$orderCode'";
                    $status = LM::FAILURE;
                } else {
                    $response['status'] = false;
                    $result = $this->orderManager->getOrder($orderCode);
                    if ($result->get() == false) {
                        $response['description'] = "Error de Orden de Pago No. $orderCode: " . GM::resultToText($result);
                        $status = LM::FAILURE;
                    } else {
                        $order = $result->getObj();
                        $order->setFechaPago($data['FECHA_CERTIF_BCO']);
                        $wasPayed = $order->getPagada();
                        $result = $this->orderManager->confirmOrder($data);
                        $response['status'] = $result->get();
                        if ($result->get() == false) {
                            $response['description'] = GM::resultToText($result);
                        } else {
                            //VALIDATING ASSIGNMENT
                            if ($order->getCodTipoOrden() == Order::ASSIGNMENT) {
                                if ($wasPayed) {
                                    $status = LM::WARNING;
                                    $response['description'] = "La orden $orderCode ya estaba marcada como pagada.";
                                } else {
                                    $result = $this->assignmentManager->assignIfValid($order);
                                    if ($result->get() == false) {//RESPONSE STATUS REMAINS TRUE BECAUSE THE ORDER HAS BEEN UPDATED TO PAYED
                                        $response['description'] = 'Se actualizó la orden, pero no se realizaron las asignaciones que debieron hacerse. <br/>' . GM::resultToText($result);
                                    } else {
                                        $status = LM::SUCCESS;
                                        $notAssigned = count($result->getMsg()) == 0 ? '' : ($this->resultToText($result));
                                        $response['description'] = "Orden $orderCode marcada como pagada y horarios válidos para asignación asignados. $notAssigned";

                                        $outOfTimeTt = $result->getObj();
                                        if (count($outOfTimeTt) > 0) {
                                            $status = LM::WARNING;
                                            $text = "La orden $orderCode fue pagada fuera de tiempo (" . date('d/m/Y', strtotime($order->getFechaPago())) . ").<br><ul>";
                                            foreach ($outOfTimeTt as $d) {
                                                $courseName = $d['course'];
                                                $section = $d['section'];
                                                $limitDay = $d['limitDay'];
                                                $text .= "<li>"
                                                        . "$courseName - $section - límite: <b>$limitDay</b>"
                                                        . "</li>";
                                            }
                                            $text .= "</ul>";
                                            $this->orderManager->setDescription($orderCode, $text);
                                        }
                                    }
                                }
                            } else {
                                $status = LM::SUCCESS;
                                $response['description'] = "Orden de Inscripción $orderCode marcada como pagada";
                            }
                        }
                    }
                }
            } else {
                $response['status'] = false;
                $response['description'] = 'Autenticación fallida';
                $status = LM::FAILURE;
            }
            $this->pg()->log($response['description'] ?? null, $status, LM::UPDATE, true);
            $view = new JsonModel($response);
            $view->setTerminal(true);
            return $view;
        } else {
            $msg = new Message('Solicitud Errónea', 'No se realizó la solicitud POST', Message::RED);
            $this->pg()->log($msg, LM::FAILURE, LM::UPDATE, true);
        }
        $viewModel = new ViewModel([
            'msg' => $msg ?? null
        ]);
        $viewModel->setTemplate('eep/msg');
        return $viewModel;
    }

    public function updateOrdersStatusAction() {
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $user = $data['user'];
            $password = $data['password'];
            $response['status'] = false;
            $serverPassword = $this->orderManager->getGlobal(GM::SERVER_REQUEST_PASSWORD, false);
            if ($user == GM::USER && $password == $serverPassword) {
                $result = $this->orderManager->updateOrdersStatus();
                if ($result->get() == false) {
                    $response['description'] = GM::resultToText($result);
                } else {
                    $response['status'] = true;
                    $result->addMsg($result->getObj());
                    $response['description'] = GM::resultToText($result);
                }
            } else {
                $response['description'] = 'Autenticación fallida';
                $status = LM::FAILURE;
            }
            $this->pg()->log($response['description'] ?? null, $status ?? ($response['status'] ? LM::SUCCESS : LM::ERROR), LM::UPDATE, true);
            $view = new JsonModel($response);
            $view->setTerminal(true);
            return $view;
        }
        $this->pg()->log('Solicitud errónea, no se realizó la petición POST.', LM::ERROR, LM::UPDATE, true);
        $msg = new Message('Solicitud Errónea', 'No se realizó la solicitud POST', Message::RED);
        $viewModel = new ViewModel([
            'msg' => $msg ?? null
        ]);
        $viewModel->setTemplate('eep/msg');
        return $viewModel;
    }

//    public function orderUpdateAction() {
//        return new ViewModel();
//    }
}
