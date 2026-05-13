<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Authentication\AuthenticationService;
// SERVICES
use Eep\Service\StudentGraduationManager;
use Eep\Service\LogManager as LM;

class StudentGraduationController extends AbstractActionController {

    /**
     * @var StudentGraduationManager
     */
    private $processManager;

    /**
     * @var AuthenticationService
     */
    private $authService;

    /**
     * Constructor para inyectar StudentGraduationManager y AuthenticationService
     * T-11
     */
    public function __construct(
        StudentGraduationManager $processManager,
        AuthenticationService $authService
    )
    {
        $this->processManager = $processManager;
        $this->authService = $authService;
    }
    
    public function indexAction() {
        // Obtener el código del usuario logueado
        $codUsuario = $this->authService->getIdentity();
        
        // Obtener el proceso del estudiante
        $proceso = null;
        if ($codUsuario) {
            $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        }
        
        $terna = null;
        if ($proceso) {
            $terna = $this->processManager->getTerna($proceso['cod_proceso']);
        }

        return new ViewModel([
            'proceso' => $proceso,
            'terna'   => $terna,
        ]);
    }

    public function procesoAction() {
        return new ViewModel([]);
    }

    public function paso1SolicitudExamenAction() {
        // Obtener el código del usuario logueado
        $codUsuario = $this->authService->getIdentity();
        
        // Obtener el proceso del estudiante
        $proceso = null;
        $requisitos = [];
        
        if ($codUsuario) {
            $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
            
            // Si existe un proceso activo, obtener los requisitos digitales del paso 1
            if ($proceso) {
                $requisitos = $this->processManager->getRequisitosDigitales(
                    $proceso['cod_proceso'],
                    1, // Paso 1: Solicitud de Examen
                    $proceso['cod_tipo_examen']
                );
            }
        }
        
        $view = new ViewModel([
            'proceso' => $proceso,
            'requisitos' => $requisitos
        ]);
        $view->setTemplate('eep/student-graduation/partial/paso1-solicitud-examen');
        return $view;
    }

    public function paso2TernaAction()
    {
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return $this->redirect()->toRoute('auth', ['action' => 'login']);
        }

        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return $this->redirect()->toRoute('student-graduation', ['action' => 'index']);
        }

        $terna = $this->processManager->getTerna($proceso['cod_proceso']);

        $view = new ViewModel([
            'proceso' => $proceso,
            'terna'   => $terna,
        ]);
        $view->setTemplate('eep/student-graduation/partial/paso2-terna');
        return $view;
    }

    /**
     * Recibe el archivo subido por el estudiante vía AJAX, lo guarda en disco
     * y registra el documento en la base de datos.
     * T-09
     */
    public function subirDocumentoAction()
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        // 1. Verificar autenticación
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel(['success' => false, 'message' => 'Usuario no autenticado']);
        }

        // 2. Obtener proceso activo del estudiante autenticado (nunca del POST)
        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return new JsonModel(['success' => false, 'message' => 'No tiene un proceso de graduación activo']);
        }

        // 3. Validar que el paso actual permite subidas
        if (!$this->processManager->puedeSubir($proceso['cod_proceso'], $proceso['cod_paso_actual'])) {
            return new JsonModel(['success' => false, 'message' => 'Este paso ya fue cerrado y no acepta nuevos documentos']);
        }

        // 4. Validar cod_requisito contra el proceso real (seguridad: evitar manipulación)
        $codRequisito = (int) $request->getPost('cod_requisito', 0);
        if (!$codRequisito) {
            return new JsonModel(['success' => false, 'message' => 'Requisito no especificado']);
        }

        $requisitoInfo = $this->processManager->getRequisitoInfo($proceso['cod_proceso'], $codRequisito);
        if (!$requisitoInfo) {
            return new JsonModel(['success' => false, 'message' => 'El requisito no corresponde al paso actual de su proceso']);
        }

        // 5. Validar que se recibió el archivo
        $files = $request->getFiles()->toArray();
        if (!isset($files['archivo']) || $files['archivo']['error'] !== UPLOAD_ERR_OK) {
            return new JsonModel(['success' => false, 'message' => 'No se recibió el archivo correctamente']);
        }

        $archivo = $files['archivo'];

        // 6. Validar extensión en backend
        $extension     = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $formatosArray = array_map('trim', explode(',', strtolower($requisitoInfo['formatos_permitidos'])));
        if (!in_array($extension, $formatosArray, true)) {
            return new JsonModel([
                'success' => false,
                'message' => 'Formato no permitido. Formatos aceptados: ' . $requisitoInfo['formatos_permitidos'],
            ]);
        }

        // 7. Validar tamaño en backend
        error_log("DEBUG TAMANIO MAQUINA MB: ".print_r($requisitoInfo['tamano_max_mb'], true));
        $tamanoMaxBytes = $requisitoInfo['tamano_max_mb'] * 1024 * 1024;
        if ($archivo['size'] > $tamanoMaxBytes) {
            return new JsonModel([
                'success' => false,
                'message' => 'El archivo supera el tamaño máximo permitido de ' . $requisitoInfo['tamano_max_mb'] . ' MB',
            ]);
        }

        // 8. Generar nombre MD5 único y mover archivo a disco
        $nombreBase = pathinfo($archivo['name'], PATHINFO_FILENAME);
        $nombreMd5  = md5($nombreBase . date('YmdHis') . uniqid('', true));
        $directorio = $_SERVER['DOCUMENT_ROOT'] . '/archivos/';

        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $rutaDestino = $directorio . $nombreMd5 . '.' . $extension;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            return new JsonModel(['success' => false, 'message' => 'No se pudo guardar el archivo en el servidor']);
        }

        // 9. Registrar en base de datos (con versionado automático)
        try {
            $codDocumento = $this->processManager->guardarDocumentoDb([
                'cod_proceso'    => $proceso['cod_proceso'],
                'cod_requisito'  => $codRequisito,
                'archivo_nombre' => $nombreMd5,
                'nombre_original'=> $archivo['name'],
                'mime_type'      => $archivo['type'],
                'tamano_bytes'   => $archivo['size'],
                'checksum_sha256'=> hash_file('sha256', $rutaDestino),
                'extension'      => $extension,
                'subido_por'     => $codUsuario,
            ]);
        } catch (\Exception $e) {
            // Si falla el registro en BD, eliminar el archivo ya guardado
            @unlink($rutaDestino);
            return new JsonModel(['success' => false, 'message' => 'Error al registrar el documento en la base de datos']);
        }

        return new JsonModel([
            'success' => true,
            'message' => 'Documento subido correctamente',
            'data'    => ['cod_documento' => $codDocumento],
        ]);
    }

    /**
     * Sirve un archivo subido de forma segura verificando que pertenece
     * al estudiante autenticado. Nunca expone la ruta física del servidor.
     * T-09
     */
    public function verDocumentoAction()
    {
        // 1. Verificar autenticación
        $codUsuario = $this->authService->getIdentity();
        error_log('[verDocumento] codUsuario=' . var_export($codUsuario, true));
        if (!$codUsuario) {
            $this->getResponse()->setStatusCode(403);
            return $this->getResponse();
        }

        // 2. Obtener el hash del query string
        $hash = $this->params()->fromQuery('h', '');
        error_log('[verDocumento] hash=' . var_export($hash, true));
        if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
            $this->getResponse()->setStatusCode(400);
            return $this->getResponse();
        }

        // 3. Buscar el archivo en BD
        $archivoInfo = $this->processManager->getArchivoByHash($hash);
        error_log('[verDocumento] archivoInfo=' . var_export($archivoInfo, true));
        if (!$archivoInfo) {
            $this->getResponse()->setStatusCode(403);
            return $this->getResponse();
        }

        // 4. Construir ruta física y verificar que existe
        $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . '/archivos/'
                    . $archivoInfo['nombre_md5'] . '.' . $archivoInfo['extension'];
        error_log('[verDocumento] rutaFisica=' . $rutaFisica . ' exists=' . var_export(is_file($rutaFisica), true));

        if (!is_file($rutaFisica)) {
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        // 5. Determinar Content-Type
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];
        $contentType = $mimeTypes[$archivoInfo['extension']] ?? 'application/octet-stream';

        // 6. Enviar archivo con headers seguros
        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', $contentType)
            ->addHeaderLine('Content-Disposition', 'inline; filename="' . $archivoInfo['nombre_original'] . '"')
            ->addHeaderLine('Content-Length', (string) filesize($rutaFisica))
            ->addHeaderLine('X-Content-Type-Options', 'nosniff');

        $response->setContent(file_get_contents($rutaFisica));
        return $response;
    }
}
