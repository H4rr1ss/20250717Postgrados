<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\TimetableController;
use Eep\Service\TimetableManager;
use Eep\Service\AcademyManager;
use Eep\Service\CohortManager;
use Eep\Service\UserManager;
use Eep\Service\AuthManager;
use Eep\Form\CategorizeTimetableForm;

/**
 * This is the factory for RegistrationController. Its purpose is to instantiate the
 * controller and inject dependencies into it.
 */
class TimetableControllerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $sessionContainer = $container->get(CategorizeTimetableForm::SESSION_CONTAINER); //ADDED TO module.config.php OF THIS MODULE
        $timetableManager = $container->get(TimetableManager::class);
        $academyManager = $container->get(AcademyManager::class);
        $cohortManager = $container->get(CohortManager::class);
	$userManager = $container->get(UserManager::class);
	$authManager = $container->get(AuthManager::class);

        // Instantiate the controller and inject dependencies
        return new TimetableController($sessionContainer, $timetableManager, $academyManager, $cohortManager, $userManager, $authManager);
    }

}
