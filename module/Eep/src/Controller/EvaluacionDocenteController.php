<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Service\EvaluacionDocenteManager;
use Eep\Entity\Result as R;
use Eep\ValueObject\Message;

class EvaluacionDocenteController extends AbstractActionController {

    private $evaluacionDocenteManager;

    public function __construct(EvaluacionDocenteManager $evaluacionDocenteManager) {
        $this->evaluacionDocenteManager = $evaluacionDocenteManager;
    }

    public function indexAction() {
        $userCode = (int) $this->identity();
        $result = $this->evaluacionDocenteManager->getCursosPendientes($userCode);

        $cursosPendientes = ($result->get() && is_array($result->getObj())) ? $result->getObj() : [];
        if (!$result->get()) {
            $msg = new Message('Error', $result);
        }

        return new ViewModel([
            'cursosPendientes' => $cursosPendientes,
            'msg' => $msg ?? null,
        ]);
    }

    public function evaluarAction() {
        $idCursoProgramado = (int) $this->params()->fromRoute('id', 0);
        $userCode = (int) $this->identity();

        if ($idCursoProgramado === 0) {
            $this->flashMessenger()->addErrorMessage('ID de curso inválido');
            return $this->redirect()->toRoute('evaluacion-docente');
        }

        // Verificar que el curso esté pendiente de evaluación
        $pendientes = $this->evaluacionDocenteManager->getCursosPendientes($userCode);
        if (!$pendientes->get()) {
            $this->flashMessenger()->addErrorMessage('No se pudieron verificar los cursos pendientes');
            return $this->redirect()->toRoute('evaluacion-docente');
        }

        $cursosPendientes = is_array($pendientes->getObj()) ? $pendientes->getObj() : [];
        $cursoEncontrado = null;
        foreach ($cursosPendientes as $curso) {
            if ((int) $curso['cod_horario'] === $idCursoProgramado) {
                $cursoEncontrado = $curso;
                break;
            }
        }

        if ($cursoEncontrado === null) {
            $this->flashMessenger()->addErrorMessage('El curso no está disponible para evaluación o ya fue evaluado');
            return $this->redirect()->toRoute('evaluacion-docente');
        }

        $resultPreguntas = $this->evaluacionDocenteManager->getPreguntas();
        $preguntas = ($resultPreguntas->get() && is_array($resultPreguntas->getObj())) ? $resultPreguntas->getObj() : [];

        return new ViewModel([
            'curso' => $cursoEncontrado,
            'preguntas' => $preguntas,
        ]);
    }

    public function guardarEvaluacionAction() {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('evaluacion-docente');
        }

        $userCode = (int) $this->identity();
        $post = $this->params()->fromPost();
        $codHorario = isset($post['cod_horario']) ? (int) $post['cod_horario'] : 0;

        if ($codHorario === 0) {
            $this->flashMessenger()->addErrorMessage('Datos de evaluación incompletos');
            return $this->redirect()->toRoute('evaluacion-docente');
        }

        // Extraer respuestas de las preguntas (inputs con name="pregunta_{id}")
        $respuestas = [];
        foreach ($post as $key => $value) {
            if (strpos($key, 'pregunta_') === 0) {
                $idPregunta = (int) substr($key, 9);
                if ($idPregunta > 0) {
                    $respuestas[$idPregunta] = trim($value);
                }
            }
        }

        if (empty($respuestas)) {
            $this->flashMessenger()->addErrorMessage('Debe responder al menos una pregunta');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'evaluar', 'id' => $codHorario]);
        }

        $result = $this->evaluacionDocenteManager->guardarEvaluacion($userCode, $codHorario, $respuestas);
        if ($result->get()) {
            $this->flashMessenger()->addSuccessMessage('Evaluación enviada exitosamente');
            return $this->redirect()->toRoute('assignment', ['action' => 'assignment']);
        } else {
            $this->flashMessenger()->addErrorMessage($result->getMsg());
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'evaluar', 'id' => $codHorario]);
        }
    }

}
