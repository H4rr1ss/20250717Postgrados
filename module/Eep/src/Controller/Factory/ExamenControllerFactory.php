<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\ExamenController;
use Eep\Service\ExamenManager;
use Eep\Service\CartaExaminadoresManager;
use Eep\Service\AutorizacionImpresionManager;

class ExamenControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $examenManager       = $container->get(ExamenManager::class);
        $cartaManager        = $container->get(CartaExaminadoresManager::class);
        $autorizacionManager = $container->get(AutorizacionImpresionManager::class);
        return new ExamenController($examenManager, $cartaManager, $autorizacionManager);
    }
}
