<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\AssignmentController;
use Eep\Service\TimetableManager;
use Eep\Service\AssignmentManager;
use Eep\Service\OrderManager;
use Eep\Service\UserManager;
use Eep\Service\AcademyManager;
use Eep\Service\EvaluacionDocenteManager;

class AssignmentControllerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $timetableManager = $container->get(TimetableManager::class);
        $assignmentManager = $container->get(AssignmentManager::class);
        $orderManager = $container->get(OrderManager::class);
        $userManager = $container->get(UserManager::class);
        $academyManager = $container->get(AcademyManager::class);
        $evaluacionDocenteManager = $container->get(EvaluacionDocenteManager::class);

        return new AssignmentController(
            $timetableManager,
            $assignmentManager,
            $orderManager,
            $userManager,
            $academyManager,
            $evaluacionDocenteManager
        );
    }

}
