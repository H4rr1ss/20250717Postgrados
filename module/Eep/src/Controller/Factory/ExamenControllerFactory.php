<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\ExamenController;
use Eep\Service\ExamenManager;
use Eep\Service\CartaExaminadoresManager;

class ExamenControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $examenManager = $container->get(ExamenManager::class);
        $cartaManager  = $container->get(CartaExaminadoresManager::class);
        return new ExamenController($examenManager, $cartaManager);
    }
}
