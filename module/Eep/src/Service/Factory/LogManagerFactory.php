<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Authentication\AuthenticationService;
use Eep\Service\LogManager;
use Zend\Db\Adapter\Adapter;

class LogManagerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $authenticationService = $container->get(AuthenticationService::class);
        $dbAdapter = $container->get(Adapter::class);

        $config = $container->get('Config');
        return new LogManager($dbAdapter, $config, $authenticationService);
    }

}
