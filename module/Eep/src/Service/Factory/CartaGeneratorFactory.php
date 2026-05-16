<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Db\Adapter\AdapterInterface;
use Eep\Service\CartaGenerator;

class CartaGeneratorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter = $container->get(AdapterInterface::class);

        // Raíz del proyecto = parent de /public (donde vive index.php).
        // En el contenedor Docker, DOCUMENT_ROOT = /var/www/public.
        $rutaProyecto = isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''
            ? dirname($_SERVER['DOCUMENT_ROOT'])
            : dirname(__DIR__, 5);

        return new CartaGenerator($adapter, $rutaProyecto);
    }
}
