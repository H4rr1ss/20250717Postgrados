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
use Eep\Service\AcademyManager;
use Eep\Service\AuthManager;
use Eep\Service\OrderManager;
use Eep\Service\InscriptionManager;
use Eep\Service\CohortManager;
use Eep\Service\LogManager as LM;
use Eep\ValueObject\Message;
use Eep\Entity\Role;
use Eep\Entity\InfoLaboral;
use Eep\Entity\Result as R;
use Eep\Entity\Order;
//FORMS
use Eep\Form\ChangePassForm;
use Eep\Form\FileCandidatesForm;
use Eep\Form\CandidateForm;
use Eep\Form\ProfileForm;
use Eep\Form\StudentSearchForm;
use Eep\Service\TimetableManager;
use Eep\Form\EditUserForm;
use Eep\Controller\GP;
use Eep\Form\LogForm;
use Eep\Service\SatuManager;
use Eep\Form\UserIdForm;

class UserController extends AbstractActionController {

    private $userManager;
    private $academyManager;
    private $orderManager;
    private $inscriptionManager;
    private $cohortManager;
    private $timetableManager;
    private $authManager;
    private $logManager;
    private $satuManager;

    public function __construct(AuthManager $authManager, UserManager $userManager, AcademyManager $academyManager, OrderManager $orderManager, InscriptionManager $inscriptionManager, CohortManager $cohortManager, TimetableManager $timetableManager, LM $logManager, SatuManager $satuManager) {
        $this->userManager = $userManager;
        $this->academyManager = $academyManager;
        $this->orderManager = $orderManager;
        $this->inscriptionManager = $inscriptionManager;
        $this->cohortManager = $cohortManager;
        $this->authManager = $authManager;
        $this->timetableManager = $timetableManager;
        $this->logManager = $logManager;
        $this->satuManager = $satuManager;
    }

    public function recoverPasswordAction() {
        $form = new \Eep\Form\RecoverPasswordForm();
        $msg = null;
        $status = LM::FAILURE;
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                // Aquí se procesará el envío del correo de recuperación
                $status = LM::SUCCESS;
                $msg = new Message('Si el correo está registrado, se enviará un enlace de recuperación.', '', Message::GREEN);
            } else {
                $msg = new Message('Correo inválido.', 'Por favor ingrese un correo válido.', Message::YELLOW);
            }
            $this->pg()->log($msg ?? null, $status, LM::CREATE);
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel([
            'form' => $form,
            'msg' => $msg
        ]);
    }

    public function candidatesAction() {
        $params = $this->getCandParams();
        $candidateForm = $params['candidateForm'];
        $candidateMsg = null;
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $candidateForm->setData($data);
            $status = LM::SUCCESS;
            if ($candidateForm->isValid()) {
                $data = $candidateForm->getData();
                try {
                    //ADD USER HAS NO FALSE RETURN, JUST EXCEPTIONS
                    $this->academyManager->beginTransaction();
                    $this->satuManager->beginTransaction();
                    $result = $this->userManager->addUser($data);
                    if ($result->get() !== false) {
                        $addedUser = $result->get(); //USER ID
                        $result = $this->authManager->addUserRole($addedUser, Role::ESTUDIANTE);
                        if ($result->get() === true) {
                            $careerCode = $data[CandidateForm::CAREER];
                            if ($careerCode != Order::CURSO_ACTUALIZACION) {
                                $cohort = $data[CandidateForm::COHORT];
                                $result = $this->academyManager->assignCareer($addedUser, $careerCode, $cohort);
                                $this->inscriptionManager->getInscriptionStatus($addedUser); //TO UPDATE USER RYE'S STATUS
                            }
                        }
                    }
                    if ($result->get() == true) {
                        $this->academyManager->commit();
                        $this->satuManager->commit();
                        $title = "Usuario asignado";
                        $candidateForm->clearData();
                    } else {
                        $this->academyManager->rollback();
                        $this->satuManager->rollback();
                        $title = "Usuario no asignado";
                        $status = LM::FAILURE;
                    }
                    $candidateMsg = new Message($title, $result);
                } catch (\Exception $ex) {
                    $candidateMsg = new Message("Error Interno", $ex->getMessage(), Message::RED);
                    $status = LM::ERROR;
                }
            } else {
                $candidateMsg = new Message("Campos faltantes", "Hay campos que requieren cambios", Message::YELLOW);
                $status = LM::FAILURE;
            }
            $this->pg()->log($candidateMsg ?? null, $status, LM::CREATE);
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel(array_merge($params, ['candidateMsg' => $candidateMsg]));
    }

    public function massiveCandidatesAction() {
        $params = $this->getCandParams();
        $fileForm = $params['fileForm'];
        $fileMsg = null;
        if ($this->getRequest()->isPost()) {

            //MERGE FILE INFORMATION
            $request = $this->getRequest();
            $data = array_merge_recursive(
                    $request->getPost()->toArray(), $request->getFiles()->toArray()
            );

            $fileForm->setData($data);
            //GENERATING VALIDATORS
            if ($fileForm->isValid()) {
                //GET DATA AND EXECUTE FILTERS (SAME FUNCTION)
                $data = $fileForm->getData();
                $filename = $data['file']['tmp_name'];
                $status = LM::FAILURE;
                $readingSuccessful = true;
                try {
                    //GETTING FILE DATA
                    $content = file($filename);
                    if (count($content) <= 1) {
                        $fileMsg = new Message("Archivo Vacío", "El archivo está vacío", Message::YELLOW);
                        $readingSuccessful = false;
                    }
                } catch (\Exception $ex) {
                    $fileMsg = new Message("Error", "No se pudo leer el archivo", Message::RED);
                    $readingSuccessful = false;
                }
                if ($readingSuccessful) {
                    //VALIDATING FILE INFO
                    $filteredData = $fileForm->filterData($content);
                    $clean = empty($filteredData['clean']) ? false : $filteredData['clean'];
                    unset($filteredData['clean']);
                    if ($clean === true) {
                        unset($filteredData['error']);
                        $result = new R();
                        $result->success(); //POSITIVE LOGIC
                        $this->userManager->beginTransaction();
                        foreach ($filteredData as $index => $candidate) {
                            //TREATING EVERY ROW AS A VALID CANDIDATE INFO
                            try {
                                $servResult = $this->userManager->addUser($candidate);
                                if ($servResult->get() !== false) {
                                    $addedUser = $servResult->get(); //USER ID
                                    $servResult = $this->authManager->addUserRole($addedUser, Role::ESTUDIANTE);
                                    if ($servResult->get() === true) {
                                        $careerCode = $candidate[CandidateForm::CAREER];
                                        if ($careerCode != Order::CURSO_ACTUALIZACION) {
                                            $cohort = $candidate[CandidateForm::COHORT];
                                            $servResult = $this->academyManager->assignCareer($addedUser, $careerCode, $cohort);
                                        }
                                    }
                                }
                                if ($servResult->get() === false) {
                                    $result->failure("Línea $index: Asignación no realizada: " . Message::makeHtmlList($servResult->getMsg()));
                                }
                            } catch (\Exception $ex) {
                                $result->failure("Línea $index: Error interno - " . $ex->getMessage()); //THERE ARE CUSTOM MESSAGES INSIDE THE EXCEPTION
                            }
                        }
                        if ($result->get()) {
                            $this->userManager->commit();
                            $status = LM::SUCCESS;
                            $fileMsg = new Message("Carga Exitosa", "Se agregaron los aspirantes y se asignaron a las carreras correspondientes.", Message::GREEN);
                        } else {
                            $this->userManager->rollback();
                            $fileMsg = new Message("Carga sin éxito", $result);
                        }
                    } else {
                        $fileMsg = new Message("Carga sin éxito", $filteredData['error'], Message::RED);
                    }
                }
                $this->pg()->log(($status == LM::SUCCESS) ? null : ($fileMsg ?? null), $status, LM::CREATE);
            } else {
                $this->pg()->log('Error en el formulario de archivo', LM::FAILURE, LM::CREATE);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        $view = new ViewModel(array_merge($params, ['fileMsg' => $fileMsg]));
        $view->setTemplate('eep/user/candidates');
        return $view;
    }

    public function downloadTemplateAction() {
        $path = './data/plantilla.csv';
        if (is_readable($path)) {
            //FILE SIZE
            $fileSize = filesize($path);
            // WRITING HTTP HEADERS
            $response = $this->getResponse();
            $headers = $response->getHeaders();
            $headers->addHeaderLine("Content-type: application/octet-stream");
            $headers->addHeaderLine("Content-Disposition: attachment; filename=\"Plantilla.csv\"");
            $headers->addHeaderLine("Content-length: $fileSize");
            $headers->addHeaderLine("Cache-control: private"); //OPEN FILE DIRECTLY
            // WRITE FILE CONTENT IN HTTP CONTENT   
            $fileContent = file_get_contents($path);
            if ($fileContent != false) {
                $response->setContent($fileContent);
                $this->pg()->log(null, LM::READ, LM::SUCCESS);
                return $this->getResponse(); //RETURN RESPONSE TO AVOID VIEW RENDERING
            } else {
                $text = "No se pudo obtener el contenido de la plantilla y descargar";
            }
        } else {
            $text = "La plantilla no se pudo leer y descargar";
        }
        //IN THE OTHER WAY, THE MESSAGE WILL BE SHOWN
        $view = new ViewModel(getCandParams([
                    'fileMsg' => new Message("Descarga fallida", $text, Message::RED)
        ]));
        $this->pg()->log($text, LM::READ, LM::ERROR);
        $view->setTemplate('eep/user/candidates');
        return $view;
    }

    private function getCandParams($params = []) {
        /* PARAMETERS:
         * - candidateForm
         * - candidateMsg
         * - fileForm
         * - fileMsg
         */
        $missing = [];
        $nationalities = $this->userManager->getCountries();
        $cohorts = $this->cohortManager->getCohorts(date('Y') . '-01-01');
        $degrees = $this->academyManager->getAcademicDegrees();
        $careers = $this->academyManager->getCareers();
        if (!isset($params['candidateForm'])) {
            $formUrl = $this->url()->fromRoute('user', ['action' => 'candidates']);
            $missing = array_merge($missing, ['candidateForm' => new CandidateForm($formUrl, $nationalities, $cohorts, $degrees, $careers)]);
            //HANDLED BY candidatesAction FUNCTION
        }
        if (!isset($params['fileForm'])) {
            $formUrl = $this->url()->fromRoute('user', ['action' => 'massiveCandidates']);
            $missing = array_merge($missing, ['fileForm' => new FileCandidatesForm($formUrl, $nationalities, $cohorts, $degrees, $careers)]); //HANDLED BY massiveCandidatesAction FUNCTION
        }
        return (array_merge_recursive($missing, $params));
    }

    private function getNotifications(): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //GETTING NOTIFICATIONS
        $notifications = [];
        $userCode = $this->identity();
        $role = $this->layout()->role;
        if ($role != null && $role->isEstudiante()) {
            //INSCRIPTION
            $inscription = $this->inscriptionManager->isInscriptionValid($userCode);
            $notifications[] = new Message("Inscripción", $inscription);
            $hasInscription = $inscription->get();

            //ASSIGNMENTS AVAILABLE
            $result = $this->timetableManager->getUserTimetable($userCode, true, true);
            $timetables = [];
            if ($result->get() == true) {
                $careers = $result->getObj();
                foreach ($careers as $ttValue) {
                    $timetables = array_merge($timetables, $ttValue);
                }
                if (count($timetables) > 0) {
                    $title = '<a href="' . (string) $this->url()->fromRoute('timetable', ['action' => 'availableCourses']) . '">Cursos</a>&nbsp;Disponibles para Asignación - (sin orden de pago activa)';
                    $notifications[] = new Message($title, $timetables, Message::BLUE);
                }
            } else {
                $res->failure($result);
                $notifications[] = new Message('Error de consulta de horarios disponibles para asignación', $result);
            }

            //PENDING TO PAY PAYMENT ORDERS
            $result = $this->orderManager->getUserOrder($userCode, true, false);
            if ($result->get() == true) {
                $orders = $result->getObj();
                $pending = [];
                foreach ($orders as $order) {
                    $orderName = '<a href="' . (string) $this->url()->fromRoute('order', ['id' => $order->getCodOrden()]) . '" target=\"_blank\" rel=\"noopener noreferrer\" >No. ' . $order->getCodOrden() . '</a>';
                    $pending[$orderName] = $order->getTimetables();
                }
                if (count($pending) > 0) {
                    $title = '<a style="color:#ff9600;" href="' . (string) $this->url()->fromRoute('treasury', ['action' => 'orderListing']) . '">Órdenes de Pendientes de Pago</a>';
                    $notifications[] = new Message($title, Message::makeHtmlList($pending, true), Message::YELLOW);
                }
            } else {
                $res->failure($result);
                $notifications[] = new Message('Error de consulta de órdenes de pago pendientes de pagar', $result);
            }

            //PAYED TIMETABLES
            $result = $this->timetableManager->getUserTimetable($userCode, false, true, true, false, true, null, false, true);
            $timetables = [];
            if ($result->get() == true) {
                $careers = $result->getObj();
                foreach ($careers as $ttValue) {
                    $timetables = array_merge($timetables, $ttValue);
                }
                if (count($timetables) > 0) {
                    $note = '<button type="button" data-container="body" data-toggle="popover" data-placement="top" data-content=\'Estos horarios ya han sido pagados pero no significa estrictamente que estén '
                            //.'<a href="' . (string) $this->url()->fromRoute('assignment', ['action' => 'assignedCourses']) . '">asignados</a>.'
                            . 'asignados .'
                            . '\' class="btn-green" data-original-title="" title=""><i class="fa fa-info"></i></button>';
                    $notifications[] = new Message("Cursos Pagados $note", $timetables, Message::GREEN);
                }
            } else {
                $res->failure($result);
                $notifications[] = new Message('Error de consulta de horarios pagados', $result);
            }

            //PAYED OUT OF TIME
            $result = $this->timetableManager->getUserTimetable($userCode, false, //excludeActivePaymentOrderAsoc
                    true, //excludePayedPaymentOrderAsoc
                    true, //includeUpgradingCourses
                    false, //includeWholeCareer
                    false, //getActiveTimetables
                    date('Y'), //YEAR
                    true, //excludeAssigned
                    true); //Toggle Exclude to Include
            $timetables = [];
            if ($result->get() == true) {
                $careers = $result->getObj();
                foreach ($careers as $ttValue) {
                    $timetables = array_merge($timetables, $ttValue);
                }
                if (count($timetables) > 0) {
                    $notifications[] = new Message("Cursos Pagados Fuera de Tiempo (No Asignados)", $timetables, Message::RED);
                }
            } else {
                $res->failure($result);
                $notifications[] = new Message('Error de consulta de horarios pagados fuera de tiempo', $result);
            }

            //UNSIGNED COURSES
            $result = $this->timetableManager->getUnsignedCourses($userCode);
            if ($result->get() == true) {
                $unsignedTimetables = $result->getObj();
                if (count($unsignedTimetables) > 0) {
                    $notifications[] = new Message("Asignaturas Invalidadas por Falta de Inscripción", $unsignedTimetables, Message::RED);
                }
            } else {
                $notifications[] = new Message("Error - Cursos Desasignados", $result);
                $res->failure($result);
            }
        }
        if ($role != null && $role->isDirector() && (date("m") >= 8) && !$this->inscriptionManager->isInscriptionTimeOver()) {
            $notifications[] = new Message("Finalización de Inscripción", "La finalización de inscripción de estudiantes para el segundo periodo del año suele ser por estas fechas. ¿Aún no es tiempo de indicar la finalización del segundo periodo de inscripciones?", Message::YELLOW);
        }
        $res->setObj($notifications);
        return $res;
    }

    private function getProfileView($data = []): ViewModel {
        if (!isset($data['userForm'])) {
            $user = $this->userManager->getUser($this->identity());
            $user->setContrasenia(""); //CLEARING DATA BEFORE SENDING IT
            $url = $this->url()->fromRoute('user', ['action' => 'updateData']);
            $data['userForm'] = new ProfileForm($user, $url);
        }

        $result = $this->getNotifications();
        $notifications = $result->getObj();
        $data['notifications'] = $notifications;

        if (count($data) == 2) {//JUST PROFILE VIEW WAS REQUESTED. 1: Notifications, 2: userForm
            $this->pg()->log($result, null, LM::VIEW);
        }

        $view = new ViewModel($data);
        $view->setTemplate('eep/user/profile');
        return $view;
    }

    public function updateDataAction() {
        $userCode = $this->identity();
        $user = $this->userManager->getUser($userCode, true);
        $url = $this->url()->fromRoute('user', ['action' => 'updateData']);
        $userForm = new ProfileForm($user, $url);
        if ($this->getRequest()->isPost()) {
            //UPDATING USER DATA
            $data = $this->params()->fromPost();
            $userForm->setData($data);
            if ($userForm->isValid()) {
                //UserForm CHANGES $USER OBJECT WITH NEW DATA WHEN VALIDATING
                $updateUser = $userForm->getUser();
                $updateUser->setCodUsuario($userCode);
                $updateResult = $this->userManager->updateUser($updateUser);
                if ($updateResult === true) {
                    $msg = new Message("Cambios realizados", "Tus datos se actualizaron correctamente", Message::GREEN);
                    $this->pg()->log(null, LM::SUCCESS, LM::UPDATE);
                } else {
                    $msg = new Message("Error", $updateResult, Message::RED);
                    $this->pg()->log($updateResult, LM::FAILURE, LM::UPDATE);
                }
            } else {
                $this->pg()->log('Formulario Inválido', LM::FAILURE, LM::UPDATE);
            }
        }
        return $this->getProfileView([
                    'msg' => $msg ?? null,
                    'userForm' => $userForm
        ]);
    }

    public function inscriptionTimeOverAction() {
        $userCode = $this->identity();
        $role = $this->layout()->role; //$this->userManager->getUserRole($userId);
        if ($this->getRequest()->isPost()) {
            //UPDATING USER DATA
            if ($role != null && $role->isDirector()) {
                $this->academyManager->beginTransaction();
                $result = $this->inscriptionManager->setInscriptionTimeOver($userCode);
                if ($result->get() == false) {
                    $msg = new Message("Finalización No Realizada", $result);
                } else {
                    $periodOvered = $result->getObj();
                    $status = LM::FAILURE;
                    if ($periodOvered == false) {
                        $failureMsg = 'Ya se ha finalizado el periodo de inscripciones con anterioridad';
                        $result->addMsg($failureMsg);
                        $msg = new Message('Finalización Realizada Previamente', $failureMsg, Message::RED);
                    } else {
                        $result = $this->academyManager->unsigneNotInscribedUsers();
                        if ($result->get() == false) {
                            $msg = new Message("Desasignación No Efectuada", $result);
                        } else {
                            $result->addMsg("El segundo periodo de inscripciones fue finalizado.");
                            $status = LM::SUCCESS;
                            $msg = new Message("Periodo Finalizado", $result);
                        }
                    }
                }
                if ($result->get() == true) {
                    $this->academyManager->commit();
                    $this->pg()->log($status == LM::SUCCESS ? null : $result, $status, LM::UPDATE);
                } else {
                    $this->academyManager->rollback();
                    $this->pg()->log($result, LM::ERROR, LM::UPDATE);
                }
            } else {
                $msg = new Message('No Autorizado', 'No estás autorizado para realizar esta solicitud', Message::RED);
            }
        }
        return $this->getProfileView([
                    'timeOverMsg' => $msg ?? null
        ]);
    }

    public function profileAction() {
        return $this->getProfileView();
    }

    public function changePasswordAction() {
        $form = new ChangePassForm();
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            //VALIDATING FORM FIELDS
            if ($form->isValid()) {
                $data = $form->getData(); //VALIDATED DATA
                $userPass = $data[$form::ACTUAL];
                $newPass = $data[$form::NEW_PASS];
                $confirmPass = $data[$form::CONFIRM_PASS];
                $status = LM::FAILURE;
                if ($newPass != $confirmPass) {
                    $message = new Message('Inválido', 'La contraseña de confirmación es diferente a la nueva contraseña', Message::YELLOW);
                } else {
                    $id = $this->identity();
                    $user = $this->userManager->getUser($id);
                    if (!$this->userManager->validatePassword($user, $userPass)) {
                        //WRONG PASSWORD
                        $message = new Message('Inválido', 'Contraseña incorrecta', Message::RED);
                    } else {
                        //CORRECT PASSWORD
                        $changed = $this->userManager->changePassword($id, $newPass);
                        if (!$changed) {
                            $status = LM::ERROR;
                            $message = new Message("No se actualizó la contraseña", 'Ponerse en contacto con Control Académico indicando que la contraseña no se pudo actualizar automáticamente', Message::YELLOW);
                        } else {
                            //PASSWORD CHANGE SUCCESSFUL
                            $status = LM::SUCCESS;
                            $message = new Message("Se ha actualizado tu contraseña", "", Message::GREEN);
                        }
                    }
                }
                $this->pg()->log($message, $status, LM::UPDATE);
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::UPDATE);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel([
            'form' => $form,
            'message' => isset($message) ? $message : null
        ]);
    }

    public function studentSearchAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            if (!isset($params[StudentSearchForm::SEARCH_TYPE])) {
                $form = new StudentSearchForm();
                $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
            } else {
                $type = $params[StudentSearchForm::SEARCH_TYPE];
                $form = new StudentSearchForm($type);
                $form->setData($params);
                if ($form->isValid()) {
                    $data = $form->getData();
                    $user = $data[StudentSearchForm::USER];
                    if ($type == StudentSearchForm::TYPE_CODE || $type == StudentSearchForm::TYPE_NAME) {
                        if ($type == StudentSearchForm::TYPE_CODE) {
                            $users = $this->userManager->getPossibleUsers($user, true);
                        } else {
                            $users = $this->userManager->getUsersByName($user);
                        }
                        foreach ($users as $user) {
                            if (!empty($user->getCodInfoLaboral())) {
                                $infoId = $user->getCodInfoLaboral();
                                $info = $this->userManager->getInfoLaboral($infoId);
                                $user->setInfoLaboral($info);
                            }
                            //CHECKING USER INSCRIPTION STATUS
                            if ($type == StudentSearchForm::TYPE_CODE) {
                                $includeInscription = true;
                                $result = $this->inscriptionManager->getInscriptionStatus($user->getCode());
                                $status = $result->get();
                                $user->setInscriptionStatus($status);
                            }
                        }
                        $this->pg()->log(null, LM::SUCCESS, LM::READ);
                    } else {
                        $msg = new Message("Tipo Incorrecto", "El tipo de consulta '$type' es inválido", Message::RED);
                    }
                } else {
                    $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
                }
            }
        } else {
            $form = new StudentSearchForm();
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel([
            'form' => $form,
            'users' => (isset($users)) ? $users : null,
            'msg' => (isset($msg)) ? $msg : null,
            'includeInscription' => $includeInscription ?? false
        ]);
    }

    public function editUserAction() {
        //THIS SECTION IS ONLY FOR POST REQUESTS
        if (!$this->getRequest()->isPost()) {
            $this->pg()->log('Sólo vista no permitida', LM::WARNING, LM::VIEW);
            //REDIRECT TO SEARCH IF NOT POST
            return $this->redirect()->toRoute('user', [
                        'action' => 'studentSearch'
            ]);
        } else {
            $params = $this->params()->fromPost();
            if (!isset($params[EditUserForm::USER_CODE_SUBMIT])) {
                //THE REQUEST HAS TO HAVE THE USER CODE
                $form = new EditUserForm(null);
                $msg = new Message("Error de Código de Usuario", "El código de usuario de la persona a editar no fue encontrado", Message::RED);
                $this->pg()->log($msg, LM::ERROR, LM::UPDATE);
            } else {
                //GETTING USER DATA
                if (count($params) == 1) {
                    //GETTING USER DATA FOR FIRST REQUEST
                    $userId = $params[EditUserForm::USER_CODE_SUBMIT];
                    $user = $this->userManager->getUser($userId, true);
                    //GETTING LABORAL INFO
                    if ($user->getCodInfoLaboral() != null) {
                        $user->setInfoLaboral($this->userManager->getInfoLaboral($user->getCodInfoLaboral()));
                    }
                    $form = new EditUserForm($user);
                    $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
                } elseif (count($params) > 1) {
                    //GETTING DATA FROM UPDATING REQUEST
                    $userId = $params[EditUserForm::USER_CODE_SUBMIT];
                    $user = $this->userManager->getUser($userId, true);
                    $form = new EditUserForm($user);
                    $form->setData($params);
                    if ($form->isValid()) {
                        $newData = $form->getData();
                        //$oldData = $user->getExchangeArray();
                        $user->exchangeArray($newData, true);
                        $works = $newData[EditUserForm::CURRENTLY_WORKS];
                        if ($works == 'yes') {
                            $infoLaboral = new InfoLaboral($newData);
                        } else {
                            $infoLaboral = null;
                        }
                        $result = $this->userManager->updateUser($user, $infoLaboral, true);
                        if ($result) {
                            $msg = new Message("Usuario actualizado", "Se han actualizado los datos correctamente.", Message::GREEN);
                        } else {
                            $msg = new Message("Usuario no actualizado", "No se pudieron actualizar los datos del usuario.", Message::RED);
                        }
                        $this->pg()->log($result ? null : $msg, $result ? LM::SUCCESS : LM::ERROR, LM::UPDATE);
                    } else {
                        $this->pg()->log($form->getMessages(), LM::FAILURE, LM::UPDATE);
                    }
                }
            }
        }//

        return new ViewModel([
            'form' => $form,
            'msg' => (isset($msg)) ? $msg : null
        ]);
    }

    public function logViewAction() {
        $form = new LogForm();
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $form->setData($params);
            if ($form->isValid()) {
                $data = $form->getData();
                $userId = $data[LogForm::USER];
                $startDate = $data[LogForm::START_DATE];
                $finishDate = $data[LogForm::FINISH_DATE];
                $users = $this->userManager->getPossibleUsers($userId);
                $status = LM::FAILURE;
                if (count($users) != 1) {
                    $cantidad = count($users) == 0 ? 'Ninguna' : count($users) . '';
                    $msg = new Message('Error', "$cantidad persona(s) se encontraron con el código de identificación '$userId'", Message::RED);
                } else {
                    $user = array_pop($users);
                    $res = $this->logManager->getLog($user->getCode(), $startDate, $finishDate);
                    if ($res->get() == true) {
                        $logs = $res->getObj();

                        $title = (!empty($user->getCui()) ? 'C-' . $user->getCui() : 'P-' . $user->getPasaporte());
                        $txt[] = 'Nombre: <strong>' . $user->getApellidos() . ', ' . $user->getNombres() . '</strong>';
                        if (!empty($user->getRegistroAcademico())) {
                            $txt[] = 'Registro Académico: <strong>' . $user->getRegistroAcademico() . '</strong>';
                        }
                        if (!empty($user->getRegistroPersonal())) {
                            $txt[] = 'Registro de Personal: <strong>' . $user->getRegistroPersonal() . '</strong>';
                        }
                        $userDetail = new Message($title, $txt, Message::BLUE);
                        $status = LM::SUCCESS;
                    } else {
                        $msg = new Message('Error', $res->getMsg(), $res->getType());
                    }
                }
                $this->pg()->log($msg ?? null, $status, LM::READ);
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel([
            'form' => $form,
            'logs' => $logs ?? null,
            'msg' => $msg ?? null,
            'userDetail' => $userDetail ?? null
        ]);
    }

    public function officialCoursesAction() {
        $role = $this->layout()->role;
        $action = LM::READ;
        $status = LM::FAILURE;
        if ($role != null && ($role->hasAdminRole() || $role->hasUdicaRole())) {
            $searchAvailable = true;
            $form = new UserIdForm();
            if ($this->getRequest()->isPost()) {
                $params = $this->params()->fromPost();
                $form->setData($params);
                if ($form->isValid()) {
                    $userId = $form->getData()[UserIdForm::USER];
                    $users = $this->userManager->getPossibleUsers($userId);
                    if (count($users) > 0) {
                        $user = current($users);
                        $userCode = $user->getCode();
                        $userMsg = $this->academyManager->getUserCareerMsg($user); //GETTING USER CAREER DATA TO SHOW
                    } else {
                        $userMsg = new Message('No Encontrado', "No se encontró al usuario con identificación '$userId'");
                    }
                }
            } else {
                if ($role->isEstudiante()) {
                    $userCode = $this->identity();
                } else {
                    $action = LM::VIEW;
                    $status = LM::SUCCESS;
                }
            }
        } else {
            $userCode = $this->identity();
        }
        if (!empty($userCode)) {
            $result = $this->userManager->getOfficialCourses($userCode);
            if (!$result->get()) {
                $msg = new Message('Error', $result);
            } else {
                $data = $result->getObj();
                $status = LM::SUCCESS;
            }
        }
        $this->pg()->log(isset($data) ? null : ($result ?? ($userMsg ?? ($form ?? null))), $status, $action);
        return new ViewModel([
            'msg' => $msg ?? null,
            'userMsg' => $userMsg ?? null,
            'data' => $data ?? null,
            'form' => $form ?? null,
            'searchAvailable' => $searchAvailable ?? false,
        ]);
    }

}
