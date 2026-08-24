<?php

namespace Eep\Service\Factory;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Eep\Service\EvaluacionDocenteManager;
use Zend\Db\Adapter\Adapter;

class EvaluacionDocenteManagerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $dbAdapter = $container->get(Adapter::class);
        return new EvaluacionDocenteManager($dbAdapter);
    }

}
