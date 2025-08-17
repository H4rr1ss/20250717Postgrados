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
class GeneralManagerFactory implements FactoryInterface {

    /**
     * This method creates the UserManager service and returns its instance. 
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $eepAdapter = $container->get(Adapter::class);
        return new GeneralManager($eepAdapter);
    }

}
