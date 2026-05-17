<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Authentication\AuthenticationService;
use Eep\Controller\StudentGraduationController;
use Eep\Service\StudentGraduationManager;
use Eep\Service\CartaExaminadoresManager;
use Eep\Service\AutorizacionImpresionManager;

class StudentGraduationControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $processManager       = $container->get(StudentGraduationManager::class);
        $cartaManager         = $container->get(CartaExaminadoresManager::class);
        $autorizacionManager  = $container->get(AutorizacionImpresionManager::class);
        $authService          = $container->get(AuthenticationService::class);
        return new StudentGraduationController(
            $processManager,
            $cartaManager,
            $autorizacionManager,
            $authService
        );
    }
}
