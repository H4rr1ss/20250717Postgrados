<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Service\OrderManager;
use Zend\Db\Adapter\Adapter;

class OrderManagerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $dbAdapter = $container->get(Adapter::class);

        return new OrderManager($dbAdapter);
    }

}
