<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Db\Adapter\AdapterInterface;
use Eep\Service\CartaExaminadoresManager;
use Eep\Service\CartaGenerator;

class CartaExaminadoresManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter        = $container->get(AdapterInterface::class);
        $cartaGenerator = $container->get(CartaGenerator::class);
        return new CartaExaminadoresManager($adapter, $cartaGenerator);
    }
}
