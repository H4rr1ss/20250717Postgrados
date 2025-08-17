<?php

namespace Eep\Service\Factory;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Eep\Service\MassiveLoadManager;
use Zend\Db\Adapter\Adapter;
use Eep\Service\AuthManager;
use Eep\Service\AcademyManager;
use Eep\Service\CohortManager;
use Eep\Service\UserManager;
use Eep\Service\OrderManager;
use Eep\Service\InscriptionManager;
use Eep\Service\TimetableManager;
use Eep\Service\AssignmentManager;
use Eep\Service\LogManager;

/**
 * This is the factory class for UserManager service. The purpose of the factory
 * is to instantiate the service and pass it dependencies (inject dependencies).
 */
class MassiveLoaderManagerFactory implements FactoryInterface {

    /**
     * This method creates the UserManager service and returns its instance. 
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $eepAdapter = $container->get(Adapter::class);
        $satuAdapter = $container->get('satu');
        $authManager = $container->get(AuthManager::class);
        $academyManager = $container->get(AcademyManager::class);
        $cohortManager = $container->get(CohortManager::class);
        $userManager = $container->get(UserManager::class);
        $orderManager = $container->get(OrderManager::class);
        $inscriptionManager = $container->get(InscriptionManager::class);
        $timetableManager = $container->get(TimetableManager::class);
        $asignmentManager = $container->get(AssignmentManager::class);
        $logManager = $container->get(LogManager::class);
        return new MassiveLoadManager($eepAdapter, $satuAdapter, $authManager, $asignmentManager, $userManager, $academyManager, $orderManager, $inscriptionManager, $cohortManager, $timetableManager, $logManager);
    }

}
