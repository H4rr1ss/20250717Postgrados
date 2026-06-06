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

    public function reporteDocenteAction() {
        $role = $this->layout()->role;
        if ($role == null || !$role->isDirector()) {
            return $this->redirect()->toRoute('home');
        }

        $anio = $this->params()->fromQuery('anio');
        $mes = $this->params()->fromQuery('mes');
        $docente = $this->params()->fromQuery('docente');
        $anio = ($anio !== null && $anio !== '') ? (int) $anio : null;
        $mes = ($mes !== null && $mes !== '') ? (int) $mes : null;
        $docente = ($docente !== null && $docente !== '') ? (int) $docente : null;

        $periodosResult = $this->evaluacionDocenteManager->getPeriodosEvaluacion();
        $periodos = ($periodosResult->get() && is_array($periodosResult->getObj())) ? $periodosResult->getObj() : [];

        $docentesResult = $this->evaluacionDocenteManager->getDocentesEvaluados();
        $docentes = ($docentesResult->get() && is_array($docentesResult->getObj())) ? $docentesResult->getObj() : [];

        $reporteResult = $this->evaluacionDocenteManager->getReportePorDocente($anio, $mes, $docente);
        $reporte = [];
        if ($reporteResult->get() && is_array($reporteResult->getObj())) {
            $reporte = $reporteResult->getObj();
        } else {
            $msg = new Message('Error', $reporteResult->getMsg());
        }

        return new ViewModel([
            'periodos' => $periodos,
            'docentes' => $docentes,
            'reporte' => $reporte,
            'anio' => $anio,
            'mes' => $mes,
            'docente' => $docente,
            'msg' => $msg ?? null,
        ]);
    }

    public function descargarReporteDocenteAction() {
        $role = $this->layout()->role;
        if ($role == null || !$role->isDirector()) {
            return $this->redirect()->toRoute('home');
        }

        $anio = $this->params()->fromQuery('anio');
        $mes = $this->params()->fromQuery('mes');
        $docente = $this->params()->fromQuery('docente');
        $anio = ($anio !== null && $anio !== '') ? (int) $anio : null;
        $mes = ($mes !== null && $mes !== '') ? (int) $mes : null;
        $docente = ($docente !== null && $docente !== '') ? (int) $docente : null;

        $preguntasResult = $this->evaluacionDocenteManager->getPreguntas();
        if (!$preguntasResult->get()) {
            $this->flashMessenger()->addErrorMessage('No se pudieron obtener las preguntas del reporte');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        $preguntasList = ($preguntasResult->get() && is_array($preguntasResult->getObj())) ? $preguntasResult->getObj() : [];
        $columnasPregunta = [];
        foreach ($preguntasList as $seccion) {
            foreach ($seccion['preguntas'] as $pregunta) {
                $columnasPregunta[] = [
                    'id' => $pregunta['id'],
                    'texto' => $pregunta['texto'],
                    'tipo' => $pregunta['tipo'],
                ];
            }
        }

        $detalleResult = $this->evaluacionDocenteManager->getEvaluacionesDetalle($anio, $mes, $docente);
        if (!$detalleResult->get()) {
            $this->flashMessenger()->addErrorMessage('No se pudo generar el reporte');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        $evaluaciones = is_array($detalleResult->getObj()) ? $detalleResult->getObj() : [];
        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $filename = 'reporte_evaluacion_docente' . ($anio ? "_$anio" : '') . ($mes ? "_$mes" : '') . '.csv';

        $headers = ['Fecha Evaluacion', 'Docente', 'Curso', 'Seccion', 'Periodo'];
        foreach ($columnasPregunta as $col) {
            $headers[] = $col['texto'];
        }

        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $headers);

        foreach ($evaluaciones as $ev) {
            $row = [
                $ev['fecha_evaluacion'],
                $ev['nombre_docente'],
                $ev['nombre_curso'],
                $ev['seccion'],
                $ev['anio'] . ' - ' . ($nombresMeses[(int) $ev['mes']] ?? 'Mes ' . $ev['mes']),
            ];

            foreach ($columnasPregunta as $col) {
                $idPregunta = $col['id'];
                if (isset($ev['respuestas'][$idPregunta])) {
                    $resp = $ev['respuestas'][$idPregunta];
                    $tipo = $resp['tipo'];
                    $valor = $resp['valor'];
                    if ($tipo === 'boolean') {
                        $row[] = strtolower($valor) === 'si' ? 'Si' : 'No';
                    } else {
                        $row[] = $valor;
                    }
                } else {
                    $row[] = '';
                }
            }

            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $response = $this->getResponse();
        $response->setContent($csvContent);
        $responseHeaders = $response->getHeaders();
        $responseHeaders->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
        $responseHeaders->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setHeaders($responseHeaders);

        return $response;
    }

}
