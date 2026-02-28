<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\ExamenController;
use Eep\Service\ExamenManager;

class ExamenControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $examenManager = $container->get(ExamenManager::class);
        return new ExamenController($examenManager);
    }
}
