<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ExamenController extends AbstractActionController {

    public function indexAction() {
        return new ViewModel();
    }

    // 1. PAPELERIA ---------------------------------------
    //  Ir a gestionar papelería
    public function papeleriaAction() {
        return new ViewModel();
    }

    // 2. SOLICITUDES ---------------------------------------
    // Ir a gestionar solicitudes
    // public function solicitudesAction() {
    //     return new ViewModel();
    // }

    // Revisar solicitud de examen
    public function revisarpapeleriaAction() {
        $carne = $this->params()->fromRoute('carne', null)
               ?: $this->params()->fromQuery('carne', null);
        return new ViewModel([
            'carne' => $carne
        ]);
    }

    public function solicitudesAction(){
        $carne = $this->params()->fromRoute('id', null);

        if ($carne) {
            $vm = new ViewModel(['carne' => $carne]);
            $vm->setTemplate('eep/examen/revisarpapeleria');
            return $vm;
        }

        return new ViewModel();
    }

}
