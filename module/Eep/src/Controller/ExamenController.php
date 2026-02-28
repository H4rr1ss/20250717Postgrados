<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Eep\Service\ExamenManager;

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
        
        // El usuario logueado (Simulado por ahora hasta llegar a T-29)
        $userId = $this->pg()->userId(); 
        $procesosActivos = $this->examenManager->getProcesos($userId);

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
            $userId = $this->pg()->userId();
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
        // Obtenemos todos los requisitos sin filtros de paso/tipo para la gestión general
        $requisitos = $this->examenManager->getTodosRequisitos();
        
        return new ViewModel([
            'requisitos' => $requisitos
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
            'id' => $request->getPost('id'),
            'nombre' => $request->getPost('titulo'),
            'descripcion' => $request->getPost('descripcion'),
            'cod_paso' => 2, // Por defecto al paso de papelería
            'activo' => 1
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

        $userId      = $this->pg()->userId();
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
     * T-16: Acción para que el administrador guarde la revisión de un documento.
     * Retorna JSON para manejo vía AJAX.
     */
    public function guardarRevisionAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $userId = $this->pg()->userId();
        $data = [
            'cod_documento'   => (int) $request->getPost('cod_documento'),
            'cod_proceso'     => (int) $request->getPost('cod_proceso'),
            'cod_requisito'   => (int) $request->getPost('cod_requisito'),
            'estado'          => $request->getPost('estado'), // aprobado, rechazado
            'motivo_rechazo'  => $request->getPost('motivo_rechazo'),
            'revisado_por'    => $userId
        ];

        if ($data['cod_documento'] <= 0 || !$data['estado']) {
            return new JsonModel(['status' => 'error', 'message' => 'Datos insuficientes para la revisión']);
        }

        try {
            $success = $this->examenManager->guardarRevisionDocumento($data);
            
            if ($success) {
                // Registrar en historial
                $this->examenManager->registrarHistorial([
                    'cod_proceso' => $data['cod_proceso'],
                    'cod_usuario' => $userId,
                    'tipo_evento' => 'revision_documento',
                    'descripcion' => "Documento ID {$data['cod_documento']} revisado como: " . $data['estado'],
                    'datos_nuevos' => $data
                ]);

                return new JsonModel([
                    'status' => 'success', 
                    'message' => 'Revisión guardada correctamente',
                    'estado' => $data['estado']
                ]);
            }
            
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar la revisión en la base de datos']);

        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * T-17: Acción para registrar la recepción física de documentos.
     * Retorna JSON para manejo vía AJAX.
     */
    public function guardarDocFisicoAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $userId = $this->pg()->userId();
        $codProceso = (int) $request->getPost('cod_proceso');
        $documentos = $request->getPost('documentos'); // Array esperado: [['cod_requisito' => X, 'recibido' => 1|0, 'observaciones' => '...'], ...]

        if ($codProceso <= 0 || !is_array($documentos)) {
            return new JsonModel(['status' => 'error', 'message' => 'Datos inválidos o incompletos']);
        }

        try {
            $success = $this->examenManager->guardarDocumentionFisica($codProceso, $documentos, $userId);
            
            if ($success) {
                // Registrar historial
                $this->examenManager->registrarHistorial([
                    'cod_proceso' => $codProceso,
                    'cod_usuario' => $userId,
                    'tipo_evento' => 'recepcion_fisica',
                    'descripcion' => 'Se actualizó el checklist de documentos físicos',
                    'datos_nuevos' => $documentos
                ]);

                return new JsonModel(['status' => 'success', 'message' => 'Documentación física actualizada']);
            }
            
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar la información']);

        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
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

        $userId = $this->pg()->userId();
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
                    'tipo_evento' => 'registro_terna',
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

        $userId = $this->pg()->userId();
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
                    'tipo_evento' => 'cambio_paso',
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
     * T-15: Cargar datos reales del estudiante, paso actual y documentos
     */
    public function solicitudesProcessAction() {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            return $this->redirect()->toRoute('examen');
        }

        $userId = $this->pg()->userId();
        
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
            // 4 => [
            //     'titulo' => 'Programación de Fecha',
            //     'subtitulo' => 'Asignación de fecha de examen',
            //     'partial' => 'eep/examen/partial/paso4-programacion'
            // ],
            4 => [
                'titulo' => 'Notificación',
                'subtitulo' => 'Comunicación al estudiante',
                'partial' => 'eep/examen/partial/paso4-notificacion'
            ],
            // 5 => [
            //     'titulo' => 'Preparación de Examen',
            //     'subtitulo' => 'Configuración del tribunal',
            //     'partial' => 'eep/examen/partial/paso5-preparacion'
            // ],
            // 6 => [
            //     'titulo' => 'Realización del Examen',
            //     'subtitulo' => 'Ejecución del examen privado',
            //     'partial' => 'eep/examen/partial/paso6-realizacion'
            // ],
            // 7 => [
            //     'titulo' => 'Calificación',
            //     'subtitulo' => 'Registro de resultado',
            //     'partial' => 'eep/examen/partial/paso7-calificacion'
            // ],
            // 8 => [
            //     'titulo' => 'Cierre y Acta Final',
            //     'subtitulo' => 'Generación de acta oficial',
            //     'partial' => 'eep/examen/partial/paso8-cierre'
            // ],
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
        $idProceso = $this->params()->fromRoute('id', null);

        if ($idProceso) {
            $proceso = $this->examenManager->getProceso($idProceso);
            if (!$proceso) {
                $this->flashMessenger()->addErrorMessage('Proceso no encontrado');
                return $this->redirect()->toRoute('examen', ['action' => 'solicitudes']);
            }

            $estudiante = $this->examenManager->getEstudiantePorProceso($idProceso);
            $pasoActualObj = $this->examenManager->getPasoActual($idProceso);
            
            // Permitir navegar por pasos vía query string, o cargar el actual por defecto
            $paso = (int) $this->params()->fromQuery('paso', ($pasoActualObj ? $pasoActualObj['cod_paso_actual'] : 1));
            
            // Obtener fechas de hitos desde el historial
            $hitos = $this->examenManager->getFechasHitos($idProceso);

            $estados = [
                1 => ['titulo' => 'Revisión de Papelería', 'partial' => 'eep/examen/partial/paso1-papeleria'],
                2 => ['titulo' => 'Solicitud y Asesor',   'partial' => 'eep/examen/partial/paso2-asesor'],
                3 => ['titulo' => 'Verificación Admin',  'partial' => 'eep/examen/partial/paso3-verificacion'],
                4 => ['titulo' => 'Programación y Terna','partial' => 'eep/examen/partial/paso4-programacion'],
                5 => ['titulo' => 'Preparación de Acta', 'partial' => 'eep/examen/partial/paso5-preparacion'],
                6 => ['titulo' => 'Realización Examen',  'partial' => 'eep/examen/partial/paso6-realizacion'],
                7 => ['titulo' => 'Calificación Final',  'partial' => 'eep/examen/partial/paso7-calificacion'],
                8 => ['titulo' => 'Cierre y Archivo',    'partial' => 'eep/examen/partial/paso8-cierre'],
            ];

            foreach ($estados as $num => &$e) {
                $e['subtitulo'] = isset($hitos[$num]) ? date('d/m/Y', strtotime($hitos[$num])) : 'Sin fecha';
            }
            unset($e);

            // T-25: Cargar documentos y requisitos para el paso 1
            $documentos = $this->examenManager->getDocumentosYRequisitos($idProceso, 1);

            // T-17: Cargar checklist de documentos físicos para el paso 2
            $docsFisicos = $this->examenManager->getDocumentosFisicos($idProceso);

            // T-18: Cargar terna de examinadores para el paso 3
            $terna = $this->examenManager->getTerna($idProceso);

            $vm = new ViewModel([
                'proceso'     => $proceso,
                'estudiante'  => $estudiante,
                'paso'        => $paso,
                'estados'     => $estados,
                'documentos'  => $documentos,
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
            'estado' => $estado,
            'carne'  => $carne
        ]);

        return new ViewModel([
            'procesos' => $procesos,
            'filtros'  => [
                'pagina' => $pagina,
                'estado' => $estado,
                'carne'  => $carne
            ]
        ]);
    }

}
