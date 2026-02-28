<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Eep\Service\ExamenManager;
use Laminas\Db\Adapter\AdapterInterface;

class ExamenManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter = $container->get(AdapterInterface::class);
        return new ExamenManager($adapter);
    }
}
