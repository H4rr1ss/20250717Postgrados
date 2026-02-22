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
        
        // Paso actual (1-10)
        $paso = (int) $this->params()->fromQuery('paso', 1);
        if ($paso < 1 || $paso > 10) {
            $paso = 1;
        }

        // Definición de los 10 estados del proceso
        $estados = [
            1 => [
                'titulo' => 'Revisión de Papelería',
                'subtitulo' => 'Revisión de documentos entregados',
                'partial' => 'eep/examen/partial/paso1-papeleria'
            ],
            // 2 => [
            //     'titulo' => 'Aprobación de Asesor',
            //     'subtitulo' => 'Validación del asesor asignado',
            //     'partial' => 'eep/examen/partial/paso2-asesor'
            // ],
            2 => [
                'titulo' => 'Entrega de Documentación',
                'subtitulo' => 'Recepción física de documentos',
                'partial' => 'eep/examen/partial/paso2-documentacion'
            ],
            3 => [
                'titulo' => 'Terna Examinadora',
                'subtitulo' => 'Revisión de requisitos académicos',
                'partial' => 'eep/examen/partial/paso3-terna'
            ],
            4 => [
                'titulo' => 'Programación de Fecha',
                'subtitulo' => 'Asignación de fecha de examen',
                'partial' => 'eep/examen/partial/paso4-programacion'
            ],
            5 => [
                'titulo' => 'Notificación',
                'subtitulo' => 'Comunicación al estudiante',
                'partial' => 'eep/examen/partial/paso5-notificacion'
            ],
            6 => [
                'titulo' => 'Preparación de Examen',
                'subtitulo' => 'Configuración del tribunal',
                'partial' => 'eep/examen/partial/paso6-preparacion'
            ],
            7 => [
                'titulo' => 'Realización del Examen',
                'subtitulo' => 'Ejecución del examen privado',
                'partial' => 'eep/examen/partial/paso7-realizacion'
            ],
            8 => [
                'titulo' => 'Calificación',
                'subtitulo' => 'Registro de resultado',
                'partial' => 'eep/examen/partial/paso8-calificacion'
            ],
            9 => [
                'titulo' => 'Cierre y Acta Final',
                'subtitulo' => 'Generación de acta oficial',
                'partial' => 'eep/examen/partial/paso9-cierre'
            ],
        ];

        // Asignar subtitulos de fecha dinámicamente
        foreach ($estados as $numPaso => &$estado) {
            if ($numPaso < $paso) {
                // TODO: Reemplazar con la fecha real de la base de datos
                $estado['subtitulo'] = '21/02/2026'; 
            } else {
                $estado['subtitulo'] = 'Sin fecha';
            }
        }
        unset($estado); // Romper la referencia del último elemento

        return new ViewModel([
            'carne' => $carne,
            'paso' => $paso,
            'estados' => $estados
        ]);
    }

    public function solicitudesAction(){
        $carne = $this->params()->fromRoute('id', null);

        if ($carne) {
            // Paso actual (1-10)
            $paso = (int) $this->params()->fromQuery('paso', 1);
            if ($paso < 1 || $paso > 10) {
                $paso = 1;
            }

            // Definición de los 10 estados del proceso
            $estados = [
                1 => [
                    'titulo' => 'Revisión de Papelería',
                    'subtitulo' => 'Revisión de documentos entregados',
                    'partial' => 'eep/examen/partial/paso1-papeleria'
                ],
                // 2 => [
                //     'titulo' => 'Aprobación de Asesor',
                //     'subtitulo' => 'Validación del asesor asignado',
                //     'partial' => 'eep/examen/partial/paso2-asesor'
                // ],
                2 => [
                    'titulo' => 'Entrega de Documentación',
                    'subtitulo' => 'Recepción física de documentos',
                    'partial' => 'eep/examen/partial/paso2-documentacion'
                ],
                3 => [
                    'titulo' => 'Terna Examinadora',
                    'subtitulo' => 'Revisión de requisitos académicos',
                    'partial' => 'eep/examen/partial/paso3-terna'
                ],
                4 => [
                    'titulo' => 'Programación de Fecha',
                    'subtitulo' => 'Asignación de fecha de examen',
                    'partial' => 'eep/examen/partial/paso4-programacion'
                ],
                5 => [
                    'titulo' => 'Notificación',
                    'subtitulo' => 'Comunicación al estudiante',
                    'partial' => 'eep/examen/partial/paso5-notificacion'
                ],
                6 => [
                    'titulo' => 'Preparación de Examen',
                    'subtitulo' => 'Configuración del tribunal',
                    'partial' => 'eep/examen/partial/paso6-preparacion'
                ],
                7 => [
                    'titulo' => 'Realización del Examen',
                    'subtitulo' => 'Ejecución del examen privado',
                    'partial' => 'eep/examen/partial/paso7-realizacion'
                ],
                8 => [
                    'titulo' => 'Calificación',
                    'subtitulo' => 'Registro de resultado',
                    'partial' => 'eep/examen/partial/paso8-calificacion'
                ],
                9 => [
                    'titulo' => 'Cierre y Acta Final',
                    'subtitulo' => 'Generación de acta oficial',
                    'partial' => 'eep/examen/partial/paso9-cierre'
                ],
            ];

            // Asignar subtitulos de fecha dinámicamente
            foreach ($estados as $numPaso => &$estado) {
                if ($numPaso < $paso) {
                    // TODO: Reemplazar con la fecha real de la base de datos
                    $estado['subtitulo'] = '21/02/2026'; 
                } else {
                    $estado['subtitulo'] = 'Sin fecha';
                }
            }
            unset($estado); // Romper la referencia del último elemento

            $vm = new ViewModel([
                'carne' => $carne,
                'paso' => $paso,
                'estados' => $estados
            ]);
            $vm->setTemplate('eep/examen/revisarpapeleria');
            return $vm;
        }

        return new ViewModel();
    }

}
