<?php

namespace Eep\Controller\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Eep\Controller\EvaluacionDocenteController;
use Eep\Service\EvaluacionDocenteManager;
use Eep\Service\EvaluacionDocenteGraficaService;

class EvaluacionDocenteControllerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        $evaluacionDocenteManager = $container->get(EvaluacionDocenteManager::class);
        $renderer = $container->get('ViewRenderer');
        $graficaService = new EvaluacionDocenteGraficaService();
        return new EvaluacionDocenteController($evaluacionDocenteManager, $renderer, $graficaService);
    }

}
