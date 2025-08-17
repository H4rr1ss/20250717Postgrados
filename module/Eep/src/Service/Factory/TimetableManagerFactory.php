<?php

namespace Eep\Service\Factory;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Eep\Service\TimetableManager;
use Eep\Service\AcademyManager;
use Eep\Service\InscriptionManager;
use Zend\Db\Adapter\Adapter;

/**
 * This is the factory class for UserManager service. The purpose of the factory
 * is to instantiate the service and pass it dependencies (inject dependencies).
 */
class TimetableManagerFactory implements FactoryInterface {

    /**
     * This method creates the UserManager service and returns its instance. 
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $dbAdapter = $container->get(Adapter::class);
        $academyManager = $container->get(AcademyManager::class);
        $inscriptionManager = $container->get(InscriptionManager::class);
        return new TimetableManager($dbAdapter, $academyManager, $inscriptionManager);
    }

}
