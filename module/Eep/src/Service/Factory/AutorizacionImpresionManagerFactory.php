<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Db\Adapter\AdapterInterface;
use Eep\Service\AutorizacionImpresionManager;
use Eep\Service\ExamenManager;

class AutorizacionImpresionManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter       = $container->get(AdapterInterface::class);
        $examenManager = $container->get(ExamenManager::class);
        return new AutorizacionImpresionManager($adapter, $examenManager);
    }
}
