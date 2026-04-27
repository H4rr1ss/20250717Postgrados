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
        
        return new ViewModel([
            'proceso' => $proceso
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

    public function paso2TernaAction() {
        $view = new ViewModel([]);
        $view->setTemplate('eep/student-graduation/partial/paso2-terna');
        return $view;
    }

    /**
     * Recibe el archivo subido por el estudiante vía AJAX
     * Por ahora solo valida que la información llegue correctamente
     */
    public function subirDocumentoAction() {
        $request = $this->getRequest();
        
        // Validar que sea una petición POST
        if (!$request->isPost()) {
            return new JsonModel([
                'success' => false,
                'message' => 'Método no permitido'
            ]);
        }
        
        // Obtener el código del usuario logueado
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ]);
        }
        
        // Obtener datos del formulario
        $files = $request->getFiles()->toArray();
        
        // Validar que se recibió el archivo
        if (!isset($files['archivo']) || $files['archivo']['error'] !== UPLOAD_ERR_OK) {
            return new JsonModel([
                'success' => false,
                'message' => 'No se recibió el archivo correctamente'
            ]);
        }
        
        $archivo = $files['archivo'];
        
        // Información del archivo recibido
        $infoArchivo = [
            'nombre' => $archivo['name'],
            'tipo' => $archivo['type'],
            'tamano_bytes' => $archivo['size'],
            'temporal' => $archivo['tmp_name']
        ];
        
        // Por ahora, solo retornar éxito con la información recibida
        return new JsonModel([
            'success' => true,
            'message' => 'Archivo recibido correctamente (modo prueba)',
            'data' => [
                'cod_usuario' => $codUsuario,
                'cod_requisito' => $codRequisito,
                'archivo' => $infoArchivo
            ]
        ]);
    }
}
