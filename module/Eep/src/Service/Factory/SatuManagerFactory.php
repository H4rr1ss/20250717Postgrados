<?php

namespace Eep\Service\Factory;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Eep\Service\SatuManager;
use Zend\Db\Adapter\Adapter;
use Eep\Service\UserManager;
use Eep\Service\InscriptionManager;

/**
 * This is the factory class for UserManager service. The purpose of the factory
 * is to instantiate the service and pass it dependencies (inject dependencies).
 */
class SatuManagerFactory implements FactoryInterface {

    /**
     * This method creates the UserManager service and returns its instance. 
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $eepAdapter = $container->get(Adapter::class);
        $satuAdapter = $container->get('satu');
        $userManager = $container->get(UserManager::class);
        $inscriptionManager = $container->get(InscriptionManager::class);
        return new SatuManager($eepAdapter, $satuAdapter, $userManager, $inscriptionManager);
    }

}
