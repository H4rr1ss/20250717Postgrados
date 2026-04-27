<?php

namespace Eep\Service\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Service\StudentGraduationManager;
use Zend\Db\Adapter\AdapterInterface;

class StudentGraduationManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter = $container->get(AdapterInterface::class);
        return new StudentGraduationManager($adapter);
    }
}
