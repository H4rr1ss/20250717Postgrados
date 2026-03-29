<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class StudentGraduationController extends AbstractActionController {

    public function indexAction() {
        return new ViewModel([]);
    }

    public function procesoAction() {
        return new ViewModel([]);
    }

    public function paso1SolicitudExamenAction() {
        $view = new ViewModel([]);
        $view->setTemplate('eep/student-graduation/partial/paso1-solicitud-examen');
        return $view;
    }

    public function paso2TernaAction() {
        $view = new ViewModel([]);
        $view->setTemplate('eep/student-graduation/partial/paso2-terna');
        return $view;
    }
}
