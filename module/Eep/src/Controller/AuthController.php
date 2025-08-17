<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Form\LoginForm;
use Eep\Service\AuthManager;
use Eep\Service\UserManager;
use Eep\ValueObject\Message;
use Eep\Entity\Role;
use Zend\Authentication\Result;
use Eep\Form\RoleForm as RF;
use Eep\Service\LogManager as LM;
use Eep\Entity\User;
use Zend\View\Model\JsonModel;

class AuthController extends AbstractActionController {

    private $authManager;
    private $userManager;

    public function __construct(AuthManager $authManager, UserManager $userManager) {
        $this->authManager = $authManager;
        $this->userManager = $userManager;
    }

    public function noAuthAction() {
        $response = ['status' => false, 'error' => 'Solicitud no autorizada'];
        $view = new JsonModel($response);
        $view->setTerminal(true);
        return $view;
    }

    public function loginAction() {
        $form = new LoginForm();
        $urlData = $this->params()->fromQuery();
        if (isset($urlData[LoginForm::REDIRECT]) && strlen(($urlData[LoginForm::REDIRECT]) <= 2048)) {
            $form->setData($urlData);
        }
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData(); //VALIDATED DATA
                $userId = $data[LoginForm::USER];
                $userPass = $data[LoginForm::PASS];
                $rememberMe = $data[LoginForm::REMEMBER_ME] == true;
                try {
                    //AUTHENTICATING AND STARTING SESSION
                    $result = $this->authManager->login($userId, $userPass, $rememberMe);
                    if ($result->getCode() == Result::SUCCESS) {
                        //REDIRECT TO PROFILE
                        //return $this->redirect()->toRoute('user', ['action' => 'profile']);
                        $this->pg()->log(null, LM::SUCCESS, LM::READ);

                        $previewUrl = $data[LoginForm::REDIRECT] ?? null;
                        $uri = new \Zend\Uri\Uri($previewUrl);
                        if (strlen($previewUrl) > 2048 || empty($previewUrl) || !$uri->isValid() || $uri->getHost() != null) {
                            return $this->redirect()->toRoute('user', ['action' => 'profile']);
                        } else {
                            $this->redirect()->toUrl($previewUrl);
                        }
                    } else {
                        //GETTING UNSUCCESSFUL REASON MESSAGE
                        $text = "";
                        foreach ($result->getMessages() as $message) {
                            $text = $text . $message . "\n";
                        }
                        $message = new Message("Inicio de Sesión Fallida", $text, Message::RED);
                        $this->pg()->log($text, LM::FAILURE, LM::READ);
                    }
                } catch (\Exception $ex) {
                    $message = new Message("Error", $ex->getMessage(), Message::RED);
                    $this->pg()->log($ex->getMessage(), LM::ERROR, LM::READ);
                }
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        $this->layout()->setTemplate('eep/empty-layout'); //LOGIN DOESN'T HAVE LEFT PANEL
        return new ViewModel([
            'form' => $form,
            'message' => isset($message) ? $message : null
        ]);
    }

    public function logoutAction() {
        try {
            $this->authManager->logout();
            $this->pg()->log(null, LM::SUCCESS, LM::READ);
        } catch (\Exception $ex) {
            //DO NOTHING BECAUSE THE EXCEPTION THROWS ONLY WHEN THE USER IS ALREADY LOGGED OUT
            $this->pg()->log($ex->getMessage(), LM::FAILURE, LM::READ);
        }
        return $this->redirect()->toRoute('auth', ['action' => 'login']);
    }

    private function getRoleView($params = [], $userId = null, $logView = false) {
        if (!isset($params['queryForm'])) {
            $params['queryForm'] = $this->getRoleForm(RF::TYPE_QUERY);
        }
        if (!isset($params['roleForm'])) {
            $params['roleForm'] = $this->getRoleForm(RF::TYPE_NEW);
        }
        if (!isset($params['deleteForm'])) {
            $params['deleteForm'] = $this->getRoleForm(RF::TYPE_DELETE);
        }
        if (!isset($params['editForm'])) {
            $params['editForm'] = $this->getRoleForm(RF::TYPE_EDIT_REQUEST);
        }
        if ($userId != null) {
            $users = $this->userManager->getPossibleUsers($userId, true);
            $usersCount = count($users);
            if ($usersCount == 0) {
                $actionMsg = new Message("Usuario No Encontrado", "No se encontró un usuario con el código '$userId'", Message::RED);
            } elseif ($usersCount > 1) {
                $actionMsg = new Message("Múltiples usuarios','Existen $usersCount usuarios con el código de identificación '$userId'. Esto sucede porque se busca entre CUI, Registro Académico, Registro de Personal y Pasaporte.", Message::YELLOW);
            } else {
                $user = array_pop($users);
                $roles = $this->authManager->getUserRoles($user->getCode());
                $params['roles'] = $roles;
                $title = (!empty($user->getCui()) ? 'C-' . $user->getCui() : 'P-' . $user->getPasaporte());
                $msg[] = 'Nombre: <strong>' . $user->getApellidos() . ', ' . $user->getNombres() . '</strong>';
                if (!empty($user->getRegistroAcademico())) {
                    $msg[] = 'Registro Académico: <strong>' . $user->getRegistroAcademico() . '</strong>';
                }
                if (!empty($user->getRegistroPersonal())) {
                    $msg[] = 'Registro de Personal: <strong>' . $user->getRegistroPersonal() . '</strong>';
                }
                $actionMsg = new Message($title, $msg, Message::BLUE);
            }
            if ($logView) {
                if (isset($user)) {
                    $this->pg()->log(null, LM::SUCCESS, LM::READ);
                } else {
                    $this->pg()->log($actionMsg, LM::FAILURE, LM::READ);
                }
            }
            $params['actionMsg'] = $actionMsg;
        }
        $view = new ViewModel($params);
        $view->setTemplate('eep/auth/roles');
        return ($view);
    }

    private function getRoleForm($type): RF {
        if ($type == RF::TYPE_QUERY) {
            $formUrl = $this->url()->fromRoute('auth', ['action' => 'roles']);
            $form = new RF(RF::TYPE_QUERY, $formUrl);
        } elseif ($type == RF::TYPE_NEW) {
            $roles = $this->authManager->getRoles();
            $formUrl = $this->url()->fromRoute('auth', ['action' => 'newRole']);
            $form = new RF(RF::TYPE_NEW, $formUrl, $roles);
        } elseif ($type == RF::TYPE_EDIT) {
            $roles = $this->authManager->getRoles();
            $formUrl = $this->url()->fromRoute('auth', ['action' => 'saveRole']);
            $form = new RF(RF::TYPE_EDIT, $formUrl, $roles);
        } elseif ($type == RF::TYPE_DELETE) {
            $formUrl = $this->url()->fromRoute('auth', ['action' => 'deleteRole']);
            $form = new RF(RF::TYPE_DELETE, $formUrl);
        } elseif ($type == RF::TYPE_EDIT_REQUEST) {
            $formUrl = $this->url()->fromRoute('auth', ['action' => 'editRole']);
            $form = new RF(RF::TYPE_EDIT_REQUEST, $formUrl);
        }
        return $form;
    }

    public function rolesAction() {
        $queryForm = $this->getRoleForm(RF::TYPE_QUERY);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            //LOOKING FOR SUBMIT TYPE
            $queryForm->setData($params);
            if ($queryForm->isValid()) {
                $data = $queryForm->getData();
                $userId = $data[RF::USER];
                //SUCCESS LOG IS IN getRoleView FUNCTION
            } else {
                $this->pg()->log($queryForm->getMessages(), LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return $this->getRoleView([
                    'queryForm' => $queryForm,
                        ], $userId ?? null, true);
    }

    public function newRoleAction() {
        $roleForm = $this->getRoleForm(RF::TYPE_NEW);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $roleForm->setData($params);
            if ($roleForm->isValid()) {
                $data = $roleForm->getData();
                $userId = $data[RF::USER];
                $users = $this->userManager->getPossibleUsers($userId, true);
                $usersCount = count($users);
                $status = LM::FAILURE;
                if ($usersCount == 0) {
                    $newMsg = new Message("Usuario No Encontrado", "No se encontró un usuario con el código '$userId'", Message::RED);
                } elseif ($usersCount > 1) {
                    $newMsg = new Message("Múltiples usuarios','Existen $usersCount usuarios con el código de identificación '$userId'. Esto sucede porque se busca entre CUI, Registro Académico, Registro de Personal y Pasaporte.", Message::YELLOW);
                } else {
                    $user = array_pop($users);
                    $roleCode = $data[RF::ROLE];
                    $startDate = $data[RF::START_DATE];
                    $finishDate = empty($data[RF::FINISH_DATE]) ? null : $data[RF::FINISH_DATE];
                    $res = $this->authManager->addUserRole($user->getCode(), $roleCode, $finishDate, $startDate);
                    if ($res->get()) {
                        $newMsg = new Message('Rol Incluido', $res);
                        $roleForm->cleanData();
                        $userId = $user->getCui() ?? $user->getPasaporte();
                        $status = LM::SUCCESS;
                        //ADDING DATA TO LOG
                        $res->addMsg($this->getUserData2($user, $startDate, $finishDate, $roleCode));
                    } else {
                        $newMsg = new Message("Rol No Agregado", $res->getMsg(), $res->getType());
                        $status = LM::ERROR;
                    }
                }
                $this->pg()->log($res ?? $newMsg, $status, LM::CREATE);
            } else {
                $this->pg()->log($roleForm->getMessages(), LM::FAILURE, LM::CREATE);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return $this->getRoleView([
                    'roleForm' => $roleForm,
                    'newMsg' => empty($newMsg) ? null : $newMsg,
                        ], $userId ?? null);
    }

    private function getUserData($info) {
        $userData = [];
        $userData[] = 'Rol implicado: <strong>' . $info['rol'] . '</strong>';
        $userData[] = 'Fecha de Inicio: ' . date('d/m/Y', strtotime($info['fecha_inicio']));
        $userData[] = 'Fecha de Fin: ' . (empty($info['fecha_fin']) ? 'Indeterminada' : date('d/m/Y', strtotime($info['fecha_fin'])));
        $userData[] = 'Nombre de Usuario: ' . $info['nombres'] . ' ' . $info['apellidos'];
        if (isset($info['cui'])) {
            $userData[] = 'CUI: ' . $info['cui'];
        }
        if (isset($info['pasaporte'])) {
            $userData[] = 'Pasaporte: ' . $info['pasaporte'];
        }
        if (isset($info['registro_personal'])) {
            $userData[] = 'Registro de Personal: ' . $info['registro_personal'];
        }
        if (isset($info['registro_academico'])) {
            $userData[] = 'Registro Académico: ' . $info['registro_academico'];
        }
        return $userData;
    }

    private function getUserData2(User $user, $startDate, $finishDate, $roleCode) {
        $userData = [];
        $userData[] = 'Rol implicado: <strong>' . Role::getStr($roleCode) . '</strong>';
        $userData[] = 'Fecha de Inicio: ' . date('d/m/Y', strtotime($startDate));
        $userData[] = 'Fecha de Fin: ' . (empty($finishDate) ? 'Indeterminada' : date('d/m/Y', strtotime($finishDate)));
        $userData[] = 'Nombre de Usuario: ' . $user->getNombres() . ' ' . $user->getApellidos();
        if (null != ($user->getCui())) {
            $userData[] = 'CUI: ' . $user->getCui();
        }
        if (null != ($user->getPasaporte())) {
            $userData[] = 'Pasaporte: ' . $user->getPasaporte();
        }
        if (null != ($user->getRegistroPersonal())) {
            $userData[] = 'Registro de Personal: ' . $user->getRegistroPersonal();
        }
        if (null != ($user->getRegistroAcademico())) {
            $userData[] = 'Registro Académico: ' . $user->getRegistroAcademico();
        }
        return $userData;
    }

    public function deleteRoleAction() {
        $deleteForm = $this->getRoleForm(RF::TYPE_DELETE);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $deleteForm->setData($params);
            if ($deleteForm->isValid()) {
                $data = $deleteForm->getData();
                $userRoleCode = $data[RF::USER_ROLE_CODE];
                $result = $this->authManager->getUserRoleCodeInfo($userRoleCode);
                if ($result->get() == false) {
                    $deleteMsg = new Message('Error de Rol', $result);
                } else {
                    $info = $result->getObj();
                    $userId = $info['cui'] ?? $info['pasaporte'];
                    $result = $this->authManager->deleteRole($userRoleCode);
                    if ($result->get()) {
                        $deleteMsg = new Message('Rol Eliminado', 'El rol para el usuario ha sido eliminado satisfactoriamente.', Message::GREEN);
                        $result->addMsg($this->getUserData($info));
                    } else {
                        $deleteMsg = new Message('Rol No Eliminado', $result);
                    }
                }
                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::FAILURE, LM::DELETE);
            } else {
                $deleteMsg = new Message('Error De Solicitud', 'El formulario de eliminación de rol de usuario tiene errores. No es válido el rol a eliminar.', Message::RED);
                $this->pg()->log($deleteMsg, LM::FAILURE, LM::DELETE);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return $this->getRoleView([
                    'deleteForm' => $deleteForm,
                    'deleteMsg' => empty($deleteMsg) ? null : $deleteMsg
                        ], $userId ?? null);
    }

    public function editRoleAction() {
        $editRequestForm = $this->getRoleForm(RF::TYPE_EDIT_REQUEST);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $editRequestForm->setData($params);
            if ($editRequestForm->isValid()) {
                $data = $editRequestForm->getData();
                $userRoleCode = $data[RF::USER_ROLE_CODE];
                $result = $this->authManager->getRole($userRoleCode);
                if ($result->get() == false) {
                    $msg = new Message('Rol No Encontrado', $result);
                } else {
                    //UPDATING ROLE
                    $roleData = $result->getObj();
                    $userId = $roleData['cui'] ?? ($roleData['pasaporte'] ?? $roleData['registro_academico']);
                    $roleData[RF::USER] = $userId;
                    $editForm = $this->getRoleForm(RF::TYPE_EDIT);
                    $editForm->setData($roleData);
                }
                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::FAILURE, LM::VIEW);
            } else {
                $msg = new Message('Error De Solicitud', 'El formulario de edición de rol de usuario tiene errores.', Message::RED);
                $this->pg()->log($msg, LM::FAILURE, LM::UPDATE);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return $this->getRoleView([
                    'actionMsg' => $msg ?? null,
                    'roleForm' => $editForm ?? null,
                        ], $userId ?? null);
    }

    public function saveRoleAction() {
        $editForm = $this->getRoleForm(RF::TYPE_EDIT);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $editForm->setData($params);
            if ($editForm->isValid()) {
                $data = $editForm->getData();
                $role = new Role([$data]); //LIKE ANOTHER ARRAY FOR THE ROLE EXCHANGE BEHAVIOR
                $this->authManager->beginTransaction();
                $result = $this->authManager->updateRole($role);
                if ($result->get() == false) {
                    $msg = new Message('Error', $result);
                } else {
                    $userId = $data[RF::USER];
                    //GETTING USER INFO
                    $result = $this->authManager->getUserRoleCodeInfo($role->getUserRoleCode());
                    if ($result->get() == false) {
                        $msg = new Message('Error de Rol', $result);
                    } else {
                        $text = "Se actualizaron los datos del rol del usuario '$userId' correctamente.";
                        $msg = new Message('Rol Actualizado', $text, Message::GREEN);
                        $editForm = null; //CLEANING SO THE getRoleView FUNCTION GENERATES A CLEAN FORM
                        $result->addMsg($text);
                        $info = $result->getObj();
                        $result->addMsg($this->getUserData($info));
                    }
                }
                if ($result->get() == false) {
                    $this->authManager->rollback();
                    $this->pg()->log($result, LM::FAILURE, LM::UPDATE);
                } else {
                    $this->authManager->commit();
                    $this->pg()->log($result, LM::SUCCESS, LM::UPDATE);
                }
            } else {
                $this->pg()->log($editForm->getMessages(), LM::FAILURE, LM::UPDATE);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return $this->getRoleView([
                    'newMsg' => $msg ?? null,
                    'roleForm' => $editForm ?? null,
                        ], $userId ?? null);
    }

}
