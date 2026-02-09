<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class EvaluacionDocenteController extends AbstractActionController
{
    /**
     * Vista principal - Lista de cursos pendientes de evaluar
     */
    public function indexAction()
    {
        return new ViewModel([
            // Aquí irán los datos reales desde el Manager
            // Por ahora solo retorna la vista con datos de ejemplo
        ]);
    }
    
    /**
     * Formulario de evaluación para un curso específico
     */
    public function evaluarAction()
    {
        $idCursoProgramado = (int) $this->params()->fromRoute('id', 0);
        
        if ($idCursoProgramado === 0) {
            $this->flashMessenger()->addErrorMessage('ID de curso inválido');
            return $this->redirect()->toRoute('assignment', ['action' => 'assignment']);
        }
        
        return new ViewModel([
            'idCursoProgramado' => $idCursoProgramado,
            // Aquí irán los datos del curso y las preguntas
        ]);
    }
    
    /**
     * Guardar evaluación enviada por el estudiante
     */
    public function guardarEvaluacionAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('assignment', ['action' => 'assignment']);
        }
        
        // Aquí se procesará el formulario enviado
        // Por ahora solo redirige a confirmación
        
        return $this->redirect()->toRoute('assignment', ['action' => 'assignment'], ['query' => ['confirmacion' => 1]]);
    }
    
    /**
     * Pantalla de confirmación después de enviar evaluación
     */
    public function confirmacionAction()
    {
        return new ViewModel([
            // Aquí irán las estadísticas del estudiante
        ]);
    }
    
    /**
     * Historial de evaluaciones realizadas por el estudiante
     */
    public function historialAction()
    {
        return new ViewModel([
            // Aquí irán las evaluaciones completadas
        ]);
    }
}
