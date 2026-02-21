<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ExamenController extends AbstractActionController {

    public function indexAction() {
        return new ViewModel();
    }

    public function papeleriaAction() {
        return new ViewModel();
    }

    public function solicitudesAction() {
        return new ViewModel();
    }

}
