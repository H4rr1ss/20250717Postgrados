<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Eep\Service\EvaluacionDocenteManager;
use Eep\Service\EvaluacionDocenteGraficaService;
use Eep\Service\LogManager as LM;
use Eep\Entity\Result as R;
use Eep\ValueObject\Message;

class EvaluacionDocenteController extends AbstractActionController {

    private $evaluacionDocenteManager;
    private $renderer;
    private $graficaService;

    public function __construct(EvaluacionDocenteManager $evaluacionDocenteManager, PhpRenderer $renderer, EvaluacionDocenteGraficaService $graficaService) {
        $this->evaluacionDocenteManager = $evaluacionDocenteManager;
        $this->renderer = $renderer;
        $this->graficaService = $graficaService;
    }

    public function indexAction() {
        $userCode = (int) $this->identity();
        $result = $this->evaluacionDocenteManager->getCursosPendientes($userCode);

        $cursosPendientes = ($result->get() && is_array($result->getObj())) ? $result->getObj() : [];
        if (!$result->get()) {
            $msg = new Message('Error', $result);
        }

        $this->pg()->log('El estudiante consultó la lista de cursos pendientes de evaluación docente.', LM::SUCCESS, LM::VIEW);

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

        $this->pg()->log('El estudiante abrió la evaluación docente del curso código ' . $idCursoProgramado . '.', LM::SUCCESS, LM::VIEW);

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
            $this->pg()->log('El estudiante envió la evaluación docente del curso código ' . $codHorario . '.', LM::SUCCESS, LM::CREATE);
            $this->flashMessenger()->addSuccessMessage('Evaluación enviada exitosamente');
            return $this->redirect()->toRoute('assignment', ['action' => 'assignment']);
        } else {
            $this->pg()->log('Error al enviar la evaluación docente del curso código ' . $codHorario . ': ' . $result->getMsg(), LM::FAILURE, LM::CREATE);
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
        $curso = $this->params()->fromQuery('curso');
        $anio = ($anio !== null && $anio !== '') ? (int) $anio : null;
        $mes = ($mes !== null && $mes !== '') ? (int) $mes : null;
        $docente = ($docente !== null && $docente !== '') ? (int) $docente : null;
        $curso = ($curso !== null && $curso !== '') ? (int) $curso : null;

        $periodosResult = $this->evaluacionDocenteManager->getPeriodosEvaluacion();
        $periodos = ($periodosResult->get() && is_array($periodosResult->getObj())) ? $periodosResult->getObj() : [];

        $docentesResult = $this->evaluacionDocenteManager->getDocentesEvaluados();
        $docentes = ($docentesResult->get() && is_array($docentesResult->getObj())) ? $docentesResult->getObj() : [];

        $reporteResult = $this->evaluacionDocenteManager->getReportePorDocente($anio, $mes, $docente, $curso);
        $reporte = [];
        if ($reporteResult->get() && is_array($reporteResult->getObj())) {
            $reporte = $reporteResult->getObj();
        } else {
            $msg = new Message('Error', $reporteResult->getMsg());
        }

        $this->pg()->log('Se consultó el reporte de evaluación docente.', LM::SUCCESS, LM::VIEW);

        return new ViewModel([
            'periodos' => $periodos,
            'docentes' => $docentes,
            'reporte' => $reporte,
            'anio' => $anio,
            'mes' => $mes,
            'docente' => $docente,
            'curso' => $curso,
            'msg' => $msg ?? null,
        ]);
    }

    public function verGraficasAction() {
        $role = $this->layout()->role;
        if ($role == null || !$role->isDirector()) {
            return $this->redirect()->toRoute('home');
        }

        $codHorario = (int) $this->params()->fromRoute('id', 0);
        if ($codHorario === 0) {
            $this->flashMessenger()->addErrorMessage('ID de curso inválido');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        $reporteResult = $this->evaluacionDocenteManager->getReportePorDocente(null, null, null, $codHorario);
        $reporte = [];
        if ($reporteResult->get() && is_array($reporteResult->getObj())) {
            $reporte = $reporteResult->getObj();
        } else {
            $msg = new Message('Error', $reporteResult->getMsg());
        }

        if (empty($reporte)) {
            $this->flashMessenger()->addErrorMessage('No se encontraron evaluaciones para el curso seleccionado');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        $preguntasResult = $this->evaluacionDocenteManager->getPreguntas();
        $preguntas = ($preguntasResult->get() && is_array($preguntasResult->getObj())) ? $preguntasResult->getObj() : [];

        $this->pg()->log('Se consultaron las gráficas de evaluación del curso código ' . $codHorario . '.', LM::SUCCESS, LM::VIEW);

        return new ViewModel([
            'curso' => $reporte[0],
            'preguntas' => $preguntas,
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
        $curso = $this->params()->fromQuery('curso');
        $anio = ($anio !== null && $anio !== '') ? (int) $anio : null;
        $mes = ($mes !== null && $mes !== '') ? (int) $mes : null;
        $docente = ($docente !== null && $docente !== '') ? (int) $docente : null;
        $curso = ($curso !== null && $curso !== '') ? (int) $curso : null;

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

        $detalleResult = $this->evaluacionDocenteManager->getEvaluacionesDetalle($anio, $mes, $docente, $curso);
        if (!$detalleResult->get()) {
            $this->flashMessenger()->addErrorMessage('No se pudo generar el reporte');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        $evaluaciones = is_array($detalleResult->getObj()) ? $detalleResult->getObj() : [];

        $reporteResult = $this->evaluacionDocenteManager->getReportePorDocente($anio, $mes, $docente, $curso);
        $notasFinales = [];
        if ($reporteResult->get() && is_array($reporteResult->getObj())) {
            foreach ($reporteResult->getObj() as $r) {
                $notasFinales[(int) $r['cod_horario']] = $r['nota_final_100'] ?? null;
            }
        }
        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $filename = 'reporte_evaluacion_docente' . ($anio ? "_$anio" : '') . ($mes ? "_$mes" : '') . '.xls';

        $headers = ['Fecha Evaluacion', 'Docente', 'Curso', 'Seccion', 'Periodo'];
        foreach ($columnasPregunta as $col) {
            $headers[] = $col['texto'];
        }
        $headers[] = 'Nota Final / 100';

        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $headers, "\t");

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

            $codHorario = (int) ($ev['cod_horario'] ?? 0);
            $notaFinal = $notasFinales[$codHorario] ?? null;
            $row[] = $notaFinal !== null ? number_format($notaFinal, 2) : 'N/A';

            fputcsv($output, $row, "\t");
        }

        rewind($output);
        $xlsContent = stream_get_contents($output);
        fclose($output);

        $this->pg()->log('Se descargó el reporte XLS de evaluación docente.', LM::SUCCESS, LM::READ);

        $response = $this->getResponse();
        $response->setContent($xlsContent);
        $responseHeaders = $response->getHeaders();
        $responseHeaders->addHeaderLine('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $responseHeaders->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setHeaders($responseHeaders);

        return $response;
    }

    public function descargarPdfGraficasAction() {
        $role = $this->layout()->role;
        if ($role == null || !$role->isDirector()) {
            return $this->redirect()->toRoute('home');
        }

        $codHorario = (int) $this->params()->fromRoute('id', 0);
        if ($codHorario === 0) {
            $this->flashMessenger()->addErrorMessage('ID de curso inválido');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        $reporteResult = $this->evaluacionDocenteManager->getReportePorDocente(null, null, null, $codHorario);
        $reporte = [];
        if ($reporteResult->get() && is_array($reporteResult->getObj())) {
            $reporte = $reporteResult->getObj();
        } else {
            $this->flashMessenger()->addErrorMessage('No se encontraron evaluaciones para el curso seleccionado');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        if (empty($reporte)) {
            $this->flashMessenger()->addErrorMessage('No se encontraron evaluaciones para el curso seleccionado');
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'reporte-docente']);
        }

        $curso = $reporte[0];
        $filename = 'resultados_evaluacion_' . $codHorario . '.pdf';

        $graficasPaths = [];

        if (!empty($curso['preguntas'])) {
            foreach ($curso['preguntas'] as &$pregunta) {
                if ($pregunta['tipo'] === 'escala10') {
                    $path = $this->graficaService->generarGraficaEscala10(
                        $pregunta['distribucion'] ?? [],
                        $pregunta['promedio'] ?? 0
                    );
                    $pregunta['grafica_path'] = $path;
                    $graficasPaths[] = $path;
                } elseif ($pregunta['tipo'] === 'boolean') {
                    $si = (int) ($pregunta['count_si'] ?? 0);
                    $total = (int) ($curso['total_evaluaciones'] ?? 0);
                    $no = $total - $si;
                    $path = $this->graficaService->generarGraficaBoolean($si, $no, $total);
                    $pregunta['grafica_path'] = $path;
                    $graficasPaths[] = $path;
                }
            }
            unset($pregunta);
        }

        $html = $this->renderer->render('eep/evaluacion-docente/descargar-pdf-graficas', [
            'curso' => $curso,
        ]);

        $pdfContent = null;
        $status = LM::FAILURE;

        set_error_handler(function () {
            return true;
        });
        ob_start();

        try {
            $pdf = new Html2Pdf('P', 'Letter', 'es', true, 'UTF-8', [15, 15, 15, 15]);
            $pdf->pdf->SetDisplayMode('fullpage');
            $pdf->pdf->SetTitle('Resultados de Evaluación Docente');
            $pdf->WriteHTML($html);
            $pdfContent = $pdf->output($filename, 'S');
            $status = LM::SUCCESS;
        } catch (Html2PdfException $ex) {
            $this->flashMessenger()->addErrorMessage('No se pudo generar el PDF: ' . $ex->getMessage());
        } catch (\Exception $ex) {
            $this->flashMessenger()->addErrorMessage('No se pudo generar el PDF: ' . $ex->getMessage());
        } finally {
            if (!empty($graficasPaths)) {
                $this->graficaService->limpiarGraficas($graficasPaths);
            }
        }

        ob_end_clean();
        restore_error_handler();

        $this->pg()->log('Se descargó el PDF de resultados de evaluación del curso código ' . $codHorario . '.', $status, LM::READ);

        if ($status === LM::FAILURE) {
            return $this->redirect()->toRoute('evaluacion-docente', ['action' => 'ver-graficas', 'id' => $codHorario]);
        }

        $response = $this->getResponse();
        $response->setContent($pdfContent);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', 'application/pdf');
        $headers->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $headers->addHeaderLine('Content-Length', strlen($pdfContent));
        $response->setHeaders($headers);

        return $response;
    }

}
