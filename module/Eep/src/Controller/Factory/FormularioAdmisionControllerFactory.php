<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\FormularioAdmisionController;
use Eep\Service\FormularioAdmisionManager;
use Eep\Service\UserManager;
use Eep\Service\AcademyManager;
use Eep\Service\CohortManager;
use Eep\Service\AuthManager;
use Eep\Service\InscriptionManager;
use Eep\Service\SatuManager;

class FormularioAdmisionControllerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $formularioAdmisionManager = $container->get(FormularioAdmisionManager::class);
        $userManager = $container->get(UserManager::class);
        $academyManager = $container->get(AcademyManager::class);
        $cohortManager = $container->get(CohortManager::class);
        $authManager = $container->get(AuthManager::class);
        $inscriptionManager = $container->get(InscriptionManager::class);
        $satuManager = $container->get(SatuManager::class);

        return new FormularioAdmisionController(
            $formularioAdmisionManager,
            $userManager,
            $academyManager,
            $cohortManager,
            $authManager,
            $inscriptionManager,
            $satuManager
        );
    }
}
