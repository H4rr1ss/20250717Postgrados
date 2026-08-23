<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Service\MailManager;

/**
 * Factory para instanciar MailManager con la configuración SMTP.
 */
class MailManagerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $config = $container->get('Config');
        $mailConfig = $config['mail'] ?? [];
        $footerPath = dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/public/img/email-footer.jpg';
        return new MailManager($mailConfig, $footerPath);
    }
}
