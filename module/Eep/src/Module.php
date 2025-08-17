<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep;

use Zend\Mvc\MvcEvent;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Authentication\AuthenticationService;
use Zend\Session\SessionManager;
use Eep\Service\AuthManager;
use Eep\Form\LoginForm;
use Eep\Controller\Plugin\PluginHandler;
use Eep\Service\LogManager as LM;

class Module {// implements ConfigProviderInterface{

    public function getConfig() {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event) {
        //MAKE THE SESSION MANAGER THE DEFAULT ONE
        $serviceManager = $event->getApplication()->getServiceManager();
        $sessionManager = $serviceManager->get(SessionManager::class);

        //GETTING THE EVENT MANAGER
        $eventManager = $event->getApplication()->getEventManager();
        $sharedEventManager = $eventManager->getSharedManager();
        //REGISTRY OF THE EVENT LISTENER METHOD
        $sharedEventManager->attach(AbstractActionController::class, MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    public function onDispatch(MvcEvent $event) {
        //CHECK IF THE CONTROLLER IS IN THIS MODULE
        $matches = $event->getRouteMatch();
        $controller = $matches->getParam('controller');
        if (false === strpos($controller, __NAMESPACE__)) {
            return;
        }

        //GETTING SERVICES
        $serviceManager = $event->getApplication()->getServiceManager();
        $authManager = $serviceManager->get(AuthManager::class);
        $result = $authManager->testDBConnection();
        if ($result->get() == false) {
            $this->setLayout($event, TRUE);
            throw new \Exception(implode('; ', $result->getMsg()));
        } else {
            $controller = $event->getTarget();
            $authServ = $serviceManager->get(AuthenticationService::class);
            //GET CONTROLLER AND ACTION NAME
            $controllerName = $event->getRouteMatch()->getParam('controller', null);
            $actionName = $event->getRouteMatch()->getParam('action', null);
            $actionName = str_replace('-', '', lcfirst(ucwords($actionName, '-'))); //CAMELCASING
            //USERS WITHOUT AUTHORIZATION WILL HAVE NO ROLE -> LOG THEM OUT
            $role = null;
            if ($authServ->hasIdentity()) {
                $user_id = $authServ->getIdentity();
                $result = $authManager->getUserRole($user_id);
                if ($result->get() == true) {
                    $role = $result->getObj();
                } else {
                    $role = null;
                }
                if ($role != null && !$role->hasRole()) {
                    $authServ->clearIdentity();
                    $role = null;
                }
                //ADDING ROLE TO LAYOUT TO USE EVERYWHERE
                $viewModel = $event->getApplication()->getMvcEvent()->getViewModel();
                $viewModel->role = $role;
            }
            $this->setLayout($event);
            //CHECKING ACCESS AUTH
            //ROLE MIGHT BE NULL IF THE USER HASN'T LOGGED IN
            //return $actionName == 'login' ?: $controller->redirect()->toRoute('auth', ['action' => 'login'], ['query' => ['access' => $authManager->hasAccess($role, $controllerName, $actionName)]]);
            //return $actionName == 'changePassword' ?: $controller->redirect()->toRoute('user', ['action' => 'changePassword'], ['query' => ['access' => $authManager->hasAccess($role, $controllerName, $actionName)]]);
            if (!$authManager->hasAccess($role, $controllerName, $actionName)) {
                //REDIRECT BECAUSE THE USER HAS NO ACCESS
                if ($event->getTarget()->getRequest()->isXmlHttpRequest()) {
                    $event->getApplication()->getResponse()->setStatusCode(403); //FORBIDDEN
                    $logManager = $serviceManager->get(LM::class);
                    $logManager->addLog($controllerName, $actionName, LM::READ, "No autorizado.", LM::FAILURE, $role != null ? $role->getCode() : null);
                    return $controller->redirect()->toRoute('auth', ['action' => 'noAuth']);
                } else {
                    $controllerName = strtolower(str_replace('Controller', '', substr($controllerName, strrpos($controllerName, '\\', -1) + 1)));
                    if ($role == null) {
                        $uri = $event->getApplication()->getRequest()->getUri();
                        $uri->setScheme(null)
                                ->setHost(null)
                                ->setPort(null)
                                ->setUserInfo(null);
                        $redirectUrl = $uri->toString();
                        return $controller->redirect()->toRoute('auth', ['action' => 'login'], [
                                    'query' => [LoginForm::REDIRECT => $redirectUrl]]);
                    } else {

                        return $controller->redirect()->toRoute('user', ['action' => 'profile']);
                    }
                }
            }
            //USER DOES HAVE ACCESS
        }
    }

    public function setLayout($event, $empty = false) {
        //ONLY IF THE USER HAS LOGGED IN, THE LAYOUT HAS MENUS
        $serviceManager = $event->getApplication()->getServiceManager();
        $authServ = $serviceManager->get(AuthenticationService::class);
        $layout = $event->getViewModel();
        if ($empty || !$authServ->hasIdentity()) {
            //LAYOUT WITHOUT MENUS
            $layout->setTemplate('eep/empty-layout');
        } else {
            //GET CONTROLLER AND ACTION NAME
            $controllerName = $event->getRouteMatch()->getParam('controller', null);
            $actionName = $event->getRouteMatch()->getParam('action', null);
            $actionName = str_replace('-', '', lcfirst(ucwords($actionName, '-'))); //CAMELCASING
            $authManager = $serviceManager->get(AuthManager::class);
            $menus = $authManager->getAuthMenus($layout->role, $controllerName, $actionName);
            if (!$event->getTarget()->getRequest()->isXmlHttpRequest()) {
                $layout->setTemplate('eep/layout');
                $layout->setVariable("menus", $menus);
                $layout->setVariable("role", $layout->role);
            }
        }
    }

}
