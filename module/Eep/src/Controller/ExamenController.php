<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
// SERVICES
use Eep\Service\ExamenManager;
use Eep\Service\LogManager as LM;

class ExamenController extends AbstractActionController {

    /**
     * @var ExamenManager
     */
    private $examenManager;

    /**
     * Constructor para inyectar ExamenManager
     * T-11
     */
    public function __construct(ExamenManager $examenManager)
    {
        $this->examenManager = $examenManager;
    }

    public function indexAction() {
        $tiposExamen = $this->examenManager->getTiposExamen();
        $procesosActivos = $this->examenManager->getProcesos(['limite' => 5]);

        return new ViewModel([
            'tiposExamen' => $tiposExamen,
            'procesosActivos' => $procesosActivos
        ]);
    }

    /**
     * T-13: Iniciar un proceso de examen
     */
    public function inscripcionAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $userId = $this->layout()->role->getUserCode();
            $tipoExamenId = (int) $request->getPost('tipo_examen_id');

            if ($tipoExamenId > 0) {
                try {
                    $idProceso = $this->examenManager->iniciarProceso($userId, $tipoExamenId);
                    // Redirigir al primer paso técnico (Paso 2 según el flujo de la vista)
                    return $this->redirect()->toRoute('examen', [
                        'action' => 'paso',
                        'id' => $idProceso
                    ]);
                } catch (\Exception $e) {
                    $this->flashMessenger()->addErrorMessage('Error al iniciar el proceso: ' . $e->getMessage());
                }
            }
        }
        return $this->redirect()->toRoute('examen');
    }

    // 1. PAPELERIA ---------------------------------------
    /**
     * T-14: Gestión de requisitos de papelería (CRUD para el administrador)
     */
    public function papeleriaAction() {
        $codTipoExamen = (int) $this->params()->fromRoute('cod_tipo_examen', 0);

        if ($codTipoExamen <= 0) {
            return $this->redirect()->toRoute('examen');
        }

        $requisitos = $this->examenManager->getTodosRequisitos($codTipoExamen);
        $nombreExamen = $this->examenManager->getNombreTipoExamen($codTipoExamen);

        return new ViewModel([
            'requisitos'    => $requisitos,
            'codTipoExamen' => $codTipoExamen,
            'nombreExamen'  => $nombreExamen
        ]);
    }

    /**
     * T-22.1: AJAX para guardar/actualizar requisito
     */
    public function guardarRequisitoAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $data = [
            'id'             => $request->getPost('id'),
            'nombre'         => $request->getPost('titulo'),
            'descripcion'    => $request->getPost('descripcion'),
            'cod_tipo_examen'=> (int) $request->getPost('cod_tipo_examen'),
            'cod_paso'       => 2, // Por defecto al paso de papelería
            'activo'         => 1
        ];

        try {
            $id = $this->examenManager->upsertRequisito($data);
            return new JsonModel([
                'status' => 'success', 
                'message' => $data['id'] ? 'Requisito actualizado' : 'Requisito creado',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * T-22.2: AJAX para eliminar requisito
     */
    public function eliminarRequisitoAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $id = (int) $request->getPost('id');
        try {
            $this->examenManager->desactivarRequisito($id);
            return new JsonModel(['status' => 'success', 'message' => 'Requisito eliminado']);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * T-14.1: Gestión de subida de documentos vía AJAX.
     * Integración simulada con Google Drive (Service Account requerida para producción).
     */
    public function subirDocumentoAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $userId      = $this->layout()->role->getUserCode();
        $codProceso  = (int) $request->getPost('cod_proceso');
        $codRequisito = (int) $request->getPost('cod_requisito');
        $files       = $request->getFiles()->toArray();

        if (empty($files['archivo'])) {
            return new JsonModel(['status' => 'error', 'message' => 'No se recibió ningún archivo']);
        }

        $fileData = $files['archivo'];

        // 1. Validaciones de negocio
        $pasoActual = $this->examenManager->getPasoActual($codProceso);
        if (!$pasoActual || !$this->examenManager->puedeSubir($codProceso, $pasoActual['cod_paso_actual'])) {
            return new JsonModel(['status' => 'error', 'message' => 'El proceso o paso actual está cerrado para modificaciones.']);
        }

        try {
            // SIMULACIÓN LOGICA GOOGLE DRIVE (T-14.1)
            // En un entorno real, aquí se llamaría al Google_Service_Drive
            // Por ahora generamos IDs y links ficticios para completar el flujo técnico
            $driveFileId = '1_simulated_drive_id_' . uniqid();
            $viewLink    = 'https://drive.google.com/file/d/' . $driveFileId . '/view';
            $downloadLink = 'https://drive.google.com/uc?id=' . $driveFileId . '&export=download';

            // 2. Registrar en Base de Datos
            $idDoc = $this->examenManager->guardarDocumentoDb([
                'cod_proceso'        => $codProceso,
                'cod_requisito'      => $codRequisito,
                'drive_file_id'      => $driveFileId,
                'drive_view_link'    => $viewLink,
                'drive_download_link' => $downloadLink,
                'nombre_archivo'     => $fileData['name'],
                'mime_type'          => $fileData['type'],
                'tamano_bytes'       => $fileData['size'],
                'subido_por'         => $userId
            ]);

            // 3. Registrar Historial
            $this->examenManager->registrarHistorial([
                'cod_proceso' => $codProceso,
                'cod_usuario' => $userId,
                'tipo_evento' => 'subida_documento',
                'descripcion' => "Documento subido: " . $fileData['name'],
                'datos_nuevos' => ['cod_documento' => $idDoc, 'drive_id' => $driveFileId]
            ]);

            return new JsonModel([
                'status'  => 'success',
                'message' => 'Archivo subido correctamente',
                'data'    => [
                    'id'   => $idDoc,
                    'link' => $viewLink,
                    'name' => $fileData['name']
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => 'Error técnico al subir: ' . $e->getMessage()]);
        }
    }

    /**
     * T-16 / T-25: Guarda la revisión de todos los documentos del Paso 1 en bloque.
     * Recibe: cod_proceso, cod_paso_actual, requisitos[] (cod_requisito, cod_documento, estado_evaluacion, observacion).
     * Si todos quedan 'aprobado', avanza el proceso al siguiente paso.
     * Retorna JSON { status, avanzado?, message?, error? }.
     */
    public function guardarRevisionAction() {
        $response  = ['status' => false];
        $logStatus = LM::FAILURE;

        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $role = $this->layout()->role;
            $userRolId     = $role->getCode();
            
            $codProceso    = $params['cod_proceso'];
            $codPasoActual = $params['cod_paso_actual'];
            $requisitos    = $params['requisitos'];
            $cod_tipo_examen = $params['cod_tipo_examen'];

            error_log("DEBUG user rol: ".print_r($userRolId, true));
            error_log("DEBUG cod proceso: ".print_r($codProceso, true));
            error_log("DEBUG cod paso actual: ".print_r($codPasoActual, true));
            error_log("DEBUG cod tipo examen: ".print_r($cod_tipo_examen, true));

            
            if ($codProceso == null || $codPasoActual == null || !is_array($requisitos) || empty($requisitos)) {
                $response['error'] = 'Datos insuficientes para guardar la revisión';
            } else {
                try {
                    // 1. Guardar/actualizar revisiones en bloque (INSERT si no existe, UPDATE si existe)
                    $this->examenManager->guardarRevisionesBulk($codProceso, $requisitos, $userRolId);

                    // 3. Verificar si todos los requisitos fueron aprobados
                    $todosAprobados = $this->examenManager->todosRequisitosAceptados($codProceso, $codPasoActual, $cod_tipo_examen);

                    if ($todosAprobados) {
                        // 4. Obtener documentos del paso antes de avanzar
                        $documentosTipoExamen = $this->examenManager->getRequisitosDocumento($codPasoActual, $cod_tipo_examen);

                        // 5. Avanzar al siguiente paso
                        $this->examenManager->avanzarPaso($codProceso, $codPasoActual, $userRolId);

                        // 6. Agregar requisitos a recepcion física si es el paso 1 (T-17) getDocumentosFisicos
                        $this->examenManager->InitDocumentacionFisica($codProceso, $documentosTipoExamen);

                        $response['status']   = true;
                        $response['avanzado'] = true;
                        $response['message']  = '¡Papelería aprobada! El proceso ha avanzado al siguiente paso.';
                    } else {
                        $response['status']   = true;
                        $response['avanzado'] = false;
                        $response['message']  = "Documento revisado correctamente.";
                    }

                } catch (\Exception $e) {
                    $response['error'] = 'Error: ' . $e->getMessage();
                }
            }
        } else {
            $this->getResponse()->setStatusCode(400);
            $response['error'] = 'Solicitud sin datos (No POST)';
        }

        $view = new JsonModel($response);
        $view->setTerminal(true);
        return $view;
    }

    /**
     * T-17: Acción para registrar la recepción física de documentos.
     * Retorna JSON para manejo vía AJAX.
     */
    public function guardarDocFisicoAction() {
        $response  = ['status' => false];

        if ($this->getRequest()->isPost()) {
            $userId = $this->layout()->role->getCode();
            $params = $this->params()->fromPost();
            $role = $this->layout()->role;
            $userRolId     = $role->getCode();

            error_log("DEBUG user id: ".print_r($userId, true));
            error_log("DEBUG params: ".print_r($params, true));

            $codProceso    = $params['cod_proceso'];
            $codPasoActual = $params['cod_paso_actual'];
            $cod_tipo_examen = $params['cod_tipo_examen'];
            $documentos    = $params['documentos'];

            if ($codProceso <= 0 || !is_array($documentos)) {
                return new JsonModel(['status' => 'error', 'message' => 'Datos inválidos o incompletos']);
            }

            try {
                $success = $this->examenManager->guardarDocumentacionFisica($codProceso, $documentos, $userId);
                
                if ($success) {
                    $docsFisicosRes = $this->examenManager->getDocumentosFisicos($codProceso, $cod_tipo_examen);

                    // Verificar si todos los documentos tienen estado = 1 (recibidos)
                    $todosRecibidos = true;
                    if (!empty($docsFisicosRes)) {
                        foreach ($docsFisicosRes as $doc) {
                            if ((int)$doc['estado'] !== 1) {
                                $todosRecibidos = false;
                                break;
                            }
                        }
                    }

                    // Solo avanzar paso si todos los documentos fueron recibidos
                    if ($todosRecibidos && !empty($docsFisicosRes)) {
                        $this->examenManager->avanzarPaso($codProceso, $codPasoActual, $userRolId);
                        return new JsonModel([
                            'status' => 'success', 
                            'message' => 'Documentación física completada. El proceso ha avanzado al siguiente paso.',
                            'avanzado' => true
                        ]);
                    }

                    return new JsonModel([
                        'status' => 'success', 
                        'message' => 'Documentación física actualizada',
                        'avanzado' => false
                    ]);
                }
                
                return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar la información']);

            } catch (\Exception $e) {
                return new JsonModel(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            $this->getResponse()->setStatusCode(400);
            $response['error'] = 'Solicitud sin datos (No POST)';
        }

        $view = new JsonModel($response);
        $view->setTerminal(true);
        return $view;
    }

    /**
     * T-18: Acción para guardar la terna de examinadores y programación.
     * Retorna JSON para manejo vía AJAX.
     */
    public function guardarTernaAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $userId = $this->layout()->role->getUserCode();
        $codProceso = (int) $request->getPost('cod_proceso');
        $terna = $request->getPost('terna'); // Array esperado: ['presidente' => [...], 'secretario' => [...], 'vocal' => [...]]
        $programacion = $request->getPost('programacion'); // Array esperado: ['fecha' => 'YYYY-MM-DD', 'hora' => 'HH:MM']

        if ($codProceso <= 0 || !is_array($terna)) {
            return new JsonModel(['status' => 'error', 'message' => 'Datos de la terna inválidos']);
        }

        try {
            $success = $this->examenManager->guardarTerna($codProceso, $terna, $programacion, $userId);
            
            if ($success) {
                // Registrar historial
                $this->examenManager->registrarHistorial([
                    'cod_proceso' => $codProceso,
                    'cod_usuario' => $userId,
                    'tipo_evento' => 'asignacion_terna',
                    'descripcion' => 'Se registró/actualizó la terna examinadora y programación',
                    'datos_nuevos' => ['terna' => $terna, 'programacion' => $programacion]
                ]);

                return new JsonModel(['status' => 'success', 'message' => 'Terna y programación guardadas correctamente']);
            }
            
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar la terna']);

        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * T-19: Acción para avanzar el proceso al siguiente paso.
     * Retorna JSON para manejo vía AJAX.
     */
    public function avanzarPasoAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $userId = $this->layout()->role->getUserCode();
        $codProceso = (int) $request->getPost('cod_proceso');
        $codPasoActual = (int) $request->getPost('cod_paso_actual');

        if ($codProceso <= 0 || $codPasoActual <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Identificadores de proceso o paso inválidos']);
        }

        try {
            $success = $this->examenManager->avanzarPaso($codProceso, $codPasoActual, $userId);
            
            if ($success) {
                // Registrar en historial
                $this->examenManager->registrarHistorial([
                    'cod_proceso' => $codProceso,
                    'cod_usuario' => $userId,
                    'tipo_evento' => 'avance_paso',
                    'descripcion' => "El proceso avanzó desde el paso ID {$codPasoActual}",
                    'datos_anteriores' => ['cod_paso' => $codPasoActual]
                ]);

                return new JsonModel(['status' => 'success', 'message' => 'Proceso avanzado correctamente']);
            }
            
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo avanzar al siguiente paso']);

        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // 2. SOLICITUDES ---------------------------------------
    /**
     * CARGA INICIAL PARA SOLICITUDES (PASO 1)
     * T-15: Cargar datos reales del estudiante, paso actual y documentos
     */
    public function solicitudesProcessAction() {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            return $this->redirect()->toRoute('examen');
        }

        $userId = $this->layout()->role->getUserCode();
        
        // 1. Obtener información del paso actual
        $pasoActual = $this->examenManager->getPasoActual($idProceso);
        if (!$pasoActual) {
            $this->flashMessenger()->addErrorMessage('El proceso no tiene un paso activo o ya fue finalizado.');
            return $this->redirect()->toRoute('examen');
        }

        // 2. Obtener datos reales del estudiante
        $estudiante = $this->examenManager->getEstudiantePorProceso($idProceso);

        // 3. Obtener requisitos y documentos ya subidos para este proceso
        $requisitos = $this->examenManager->getRequisitosDocumento($pasoActual['cod_paso_actual']);
        $documentosSubidos = $this->examenManager->getDocumentosProceso($idProceso);

        // 4. Si el paso requiere terna (ej: paso 4), cargarla
        $terna = [];
        if ($pasoActual['numero_orden'] >= 4) {
            $terna = $this->examenManager->getTerna($idProceso);
        }

        return new ViewModel([
            'idProceso'   => $idProceso,
            'pasoActual'  => $pasoActual,
            'estudiante'  => $estudiante,
            'requisitos'  => $requisitos,
            'documentos'  => $documentosSubidos,
            'terna'       => $terna
        ]);
    }
    
    public function solicitudesAction(){
        $idProceso = $this->params()->fromRoute('id', null);

        if ($idProceso) {
            $proceso = $this->examenManager->getProceso((int) $idProceso);

            if (!$proceso) {
                $this->flashMessenger()->addErrorMessage('Proceso no encontrado');
                return $this->redirect()->toRoute('examen', ['action' => 'solicitudes']);
            }

            $estudiante = $this->examenManager->getEstudiantePorProceso($idProceso);
            $pasoActualObj = $this->examenManager->getPasoActual($idProceso);
            
            // Permitir navegar por pasos vía query string, o cargar el actual por defecto
            $paso = $this->params()->fromQuery('paso', ($pasoActualObj ? $pasoActualObj['cod_paso_actual'] : 1));
            
            // Obtener fechas de los estados
            $fechas = $this->examenManager->getFechasPasosCompletado($idProceso);

            $estados = [
                1 => [
                    'titulo' => 'Revisión de Papelería',
                    'subtitulo' => 'Sin fecha',
                    'partial' => 'eep/examen/partial/paso1-papeleria'
                ],
                2 => [
                    'titulo' => 'Entrega de Papelería',
                    'subtitulo' => 'Sin fecha',
                    'partial' => 'eep/examen/partial/paso2-documentacion'
                ],
                3 => [
                    'titulo' => 'Terna Examinadora',
                    'subtitulo' => 'Sin fecha',
                    'partial' => 'eep/examen/partial/paso3-terna'
                ],
                4 => [
                    'titulo' => 'Notificación',
                    'subtitulo' => 'Sin fecha',
                    'partial' => 'eep/examen/partial/paso4-notificacion'
                ],
            ];

            if (!empty($fechas)){
                foreach ($estados as $num => &$e) {
                    $e['subtitulo'] = isset($fechas[$num]) ? date('d/m/Y', strtotime($fechas[$num])) : 'Sin fecha';
                }
                unset($e);
            }

            // T-25: Cargar documentos y requisitos para el paso 1
            $codigo_tipo_examen = (int) $proceso['tipo_cod_examen'];
            $documentos = $this->examenManager->getDocumentosYRequisitos($idProceso, 1, $codigo_tipo_examen);

            // T-17: Cargar checklist de documentos físicos para el paso 2
            $docsFisicos = $this->examenManager->getDocumentosFisicos($idProceso, $codigo_tipo_examen);

            // T-18: Cargar terna de examinadores para el paso 3
            $terna = $this->examenManager->getTerna($idProceso);

            $vm = new ViewModel([
                'proceso'     => $proceso,
                'estudiante'  => $estudiante,
                'paso'        => $paso,
                'estados'     => $estados,
                'docsDigitales'  => $documentos,
                'docsFisicos' => $docsFisicos,
                'terna'       => $terna
            ]);
            $vm->setTemplate('eep/examen/revisarpapeleria');
            return $vm;
        }

        // Listado de solicitudes (Paginado)
        $pagina = (int) $this->params()->fromQuery('page', 1);
        $estado = $this->params()->fromQuery('estado', null);
        $carne  = $this->params()->fromQuery('carne', null);

        $procesos = $this->examenManager->getProcesos([
            'pagina' => $pagina,
            'limite' => 10,
            'estado' => $estado
        ]);

        return new ViewModel([
            'procesos'   => $procesos['procesos'],
            'paginacion' => [
                'total'         => $procesos['total'],
                'pagina'        => $procesos['pagina'],
                'limite'        => $procesos['limite'],
                'paginas_total' => $procesos['paginas_total'],
            ],
            'filtros'  => [
                'estado' => $estado,
                'carne'  => $carne
            ]
        ]);
    }

}
