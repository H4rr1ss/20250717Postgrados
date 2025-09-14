<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\FormularioAdmisionController;
use Eep\Service\FormularioAdmisionManager;

class FormularioAdmisionControllerFactory implements FactoryInterface {
    
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $formularioAdmisionManager = $container->get(FormularioAdmisionManager::class);
        
        return new FormularioAdmisionController($formularioAdmisionManager);
    }
}
