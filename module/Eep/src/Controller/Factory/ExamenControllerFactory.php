<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\ExamenController;
use Eep\Service\ExamenManager;
use Eep\Service\CartaExaminadoresManager;
use Eep\Service\AutorizacionImpresionManager;
use Eep\Service\UserManager;
use Eep\Service\MailManager;
use Zend\Authentication\AuthenticationService;

class ExamenControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $examenManager       = $container->get(ExamenManager::class);
        $cartaManager        = $container->get(CartaExaminadoresManager::class);
        $autorizacionManager = $container->get(AutorizacionImpresionManager::class);
        $userManager         = $container->get(UserManager::class);
        $mailManager         = $container->get(MailManager::class);
        $authService         = $container->get(AuthenticationService::class);
        $config              = $container->get('Config');

        return new ExamenController(
            $examenManager,
            $cartaManager,
            $autorizacionManager,
            $userManager,
            $mailManager,
            $authService,
            $config
        );
    }
}
