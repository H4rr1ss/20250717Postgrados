<?php

namespace Eep\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Eep\Service\LogManager;

class PluginHandler extends AbstractPlugin {

    private $logManager;

    public function __construct(LogManager $logManager) {
        $this->logManager = $logManager;
    }

    /**
     * 
     * @param type $detail
     * @param type $resultStatus
     *      1: Satisfactorio
     *      2: Advertencia
     *      3: Fallido
     *      4: Erróneo
     *      5: Indefinido
     * @param type $actionType VCRUD
     *     const VIEW = 1;
     *     const CREATE = 2;
     *     const READ = 3;
     *     const UPDATE = 4;
     *     const DELETE = 5;
     */
    public function log($detail, $resultStatus = LogManager::UNDEFINED_STATE, $actionType = LogManager::VIEW, $internalSystem = false) {
        //CLEANING STATUS
        if ($resultStatus == null) {
            $resultStatus = LogManager::UNDEFINED_STATE;
        }
        //GETTING USER MAIN ROLE
        if (isset($this->getController()->layout()->role)) {
            $role = $this->getController()->layout()->role;
            $roleCode = $role->getCode();
        } else {
            $roleCode = null;
        }
        $ip = $this->getController()->getRequest()->getServer('REMOTE_ADDR');
        //GETTING ACTION/CONTROLLER DETAIL
        $controllerName = $this->getController()->params('controller');
        $actionName = $this->getController()->params('action');
        $this->logManager->addLog($controllerName, $actionName, $actionType, $detail, $ip, $resultStatus, $roleCode, $internalSystem);
    }

}
