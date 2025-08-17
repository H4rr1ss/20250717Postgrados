<?php

namespace Eep\Controller\Plugin\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Service\LogManager;
use Eep\Controller\Plugin\PluginHandler;

/**
 * This is the factory for RegistrationController. Its purpose is to instantiate the
 * controller and inject dependencies into it.
 */
class PluginHandlerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $logManager = $container->get(LogManager::class);
        return new PluginHandler($logManager);
    }

}
