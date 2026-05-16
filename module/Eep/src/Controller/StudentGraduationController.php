<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Authentication\AuthenticationService;
// SERVICES
use Eep\Service\StudentGraduationManager;
use Eep\Service\CartaExaminadoresManager;
use Eep\Service\LogManager as LM;

class StudentGraduationController extends AbstractActionController {

    /**
     * @var StudentGraduationManager
     */
    private $processManager;

    /**
     * @var CartaExaminadoresManager
     */
    private $cartaManager;

    /**
     * @var AuthenticationService
     */
    private $authService;

    /**
     * Constructor: inyecta los managers del paso 1-4 (StudentGraduationManager)
     * y del paso 5 "Carta de Examinadores" (CartaExaminadoresManager).
     */
    public function __construct(
        StudentGraduationManager $processManager,
        CartaExaminadoresManager $cartaManager,
        AuthenticationService $authService
    )
    {
        $this->processManager = $processManager;
        $this->cartaManager   = $cartaManager;
        $this->authService    = $authService;
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

        $carta = null;
        if ($proceso) {
            $carta = $this->cartaManager->getCartaPorProceso((int) $proceso['cod_proceso']);
        }

        return new ViewModel([
            'proceso' => $proceso,
            'terna'   => $terna,
            'carta'   => $carta,
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

    // ================================================================
    // PASO 5 — Carta de Examinadores
    //
    // Flujo simplificado (correcciones por correo, plataforma = bitácora):
    //   - GET  paso5-carta-examinadores  -> pantalla del estudiante: bitácora + subida
    //   - POST subir-evidencia           -> estudiante: agrega entrada a la bitácora
    //   - POST eliminar-evidencia        -> estudiante: elimina una entrada
    //   - POST aprobar-trabajo           -> director: aprueba y genera la carta
    //   - GET  descargar-carta           -> sirve el .docx generado
    // ================================================================

    /**
     * Pantalla del paso 5 (Carta de Examinadores) — vista del estudiante.
     *
     * El intercambio de correcciones ocurre por correo externo. La plataforma
     * solo muestra la bitácora de evidencias subidas por el estudiante y, si
     * ya fue aprobado, el enlace de descarga de la carta.
     */
    public function paso5CartaExaminadoresAction()
    {
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return $this->redirect()->toRoute('auth', ['action' => 'login']);
        }

        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return $this->redirect()->toRoute('student-graduation', ['action' => 'index']);
        }

        // Inicializar el ciclo 1 si todavía no existe (entrada al paso 5).
        $this->cartaManager->iniciarPasoCarta($proceso['cod_proceso']);

        $cicloActual = $this->cartaManager->getCicloActual($proceso['cod_proceso']);
        $evidencias  = $this->cartaManager->getEvidenciasPlanas($proceso['cod_proceso']);
        $carta       = $this->cartaManager->getCartaPorProceso($proceso['cod_proceso']);

        $view = new ViewModel([
            'proceso'           => $proceso,
            'cicloActual'       => $cicloActual,
            'evidencias'        => $evidencias,
            'carta'             => $carta,
            'formatosEvidencia' => $this->cartaManager->getFormatosEvidencia(),
            'tamanoMaxMb'       => 5,
        ]);
        $view->setTemplate('eep/student-graduation/partial/paso5-carta-examinadores');
        return $view;
    }

    /**
     * Sube una evidencia (captura de correo) y la asocia al ciclo
     * indicado. Sólo formatos pequeños (jpg/png/pdf) y tamaño limitado.
     */
    public function subirEvidenciaAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel(['success' => false, 'message' => 'Usuario no autenticado']);
        }

        $codCiclo    = (int) $request->getPost('cod_ciclo', 0);
        $descripcion = (string) $request->getPost('descripcion', '');
        if (!$codCiclo) {
            return new JsonModel(['success' => false, 'message' => 'Ciclo no especificado']);
        }

        // El ciclo debe pertenecer al proceso activo del estudiante.
        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return new JsonModel(['success' => false, 'message' => 'No tiene un proceso activo']);
        }
        if (!$this->cartaManager->cicloPerteneceAProceso($codCiclo, (int) $proceso['cod_proceso'])) {
            return new JsonModel(['success' => false, 'message' => 'No autorizado']);
        }

        $files = $request->getFiles()->toArray();
        if (!isset($files['archivo']) || $files['archivo']['error'] !== UPLOAD_ERR_OK) {
            return new JsonModel(['success' => false, 'message' => 'No se recibió el archivo correctamente']);
        }
        $archivo   = $files['archivo'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $this->cartaManager->getFormatosEvidencia(), true)) {
            return new JsonModel([
                'success' => false,
                'message' => 'Formato no permitido. Aceptados: ' . implode(', ', $this->cartaManager->getFormatosEvidencia()),
            ]);
        }
        if ($archivo['size'] > $this->cartaManager->getTamanoMaxEvidenciaBytes()) {
            return new JsonModel([
                'success' => false,
                'message' => 'El archivo supera el tamaño máximo permitido (5 MB).',
            ]);
        }

        $nombreMd5 = md5(pathinfo($archivo['name'], PATHINFO_FILENAME) . date('YmdHis') . uniqid('', true));
        $directorio = $_SERVER['DOCUMENT_ROOT'] . '/archivos/';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
        $rutaDestino = $directorio . $nombreMd5 . '.' . $extension;
        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            return new JsonModel(['success' => false, 'message' => 'No se pudo guardar el archivo en el servidor']);
        }

        try {
            $codEvidencia = $this->cartaManager->adjuntarEvidencia([
                'cod_ciclo'       => $codCiclo,
                'archivo_md5'     => $nombreMd5,
                'extension'       => $extension,
                'nombre_original' => $archivo['name'],
                'tamano_bytes'    => $archivo['size'],
                'descripcion'     => $descripcion !== '' ? $descripcion : null,
                'subido_por'      => $codUsuario,
            ]);
        } catch (\Exception $e) {
            @unlink($rutaDestino);
            return new JsonModel(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }

        return new JsonModel([
            'success' => true,
            'message' => 'Evidencia adjuntada al ciclo.',
            'data'    => ['cod_evidencia' => $codEvidencia, 'hash' => $nombreMd5],
        ]);
    }

    /**
     * Elimina (soft-delete) una evidencia y borra el archivo físico.
     * Sólo el estudiante dueño del proceso puede eliminar sus evidencias.
     */
    public function eliminarEvidenciaAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel(['success' => false, 'message' => 'Usuario no autenticado']);
        }

        $codEvidencia = (int) $request->getPost('cod_evidencia', 0);
        if (!$codEvidencia) {
            return new JsonModel(['success' => false, 'message' => 'Evidencia no especificada']);
        }

        // Verificar propiedad: la evidencia debe pertenecer a un ciclo del
        // proceso activo del estudiante autenticado.
        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return new JsonModel(['success' => false, 'message' => 'No tiene un proceso activo']);
        }
        if (!$this->cartaManager->evidenciaPerteneceAProceso($codEvidencia, (int) $proceso['cod_proceso'])) {
            return new JsonModel(['success' => false, 'message' => 'No autorizado']);
        }

        $this->cartaManager->eliminarEvidencia($codEvidencia, rtrim($_SERVER['DOCUMENT_ROOT'] . '/archivos', '/'));
        return new JsonModel(['success' => true, 'message' => 'Evidencia eliminada.']);
    }

    /**
     * Director aprueba el trabajo de graduación. Cierra el ciclo abierto
     * como 'aprobado' y genera la carta de examinadores en .docx.
     */
    public function aprobarTrabajoAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel(['success' => false, 'message' => 'Usuario no autenticado']);
        }

        $codProceso = (int) $request->getPost('cod_proceso', 0);
        if (!$codProceso) {
            return new JsonModel(['success' => false, 'message' => 'Proceso no especificado']);
        }

        try {
            $resultado = $this->cartaManager->aprobarTrabajo($codProceso, $codUsuario);
        } catch (\Exception $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }

        return new JsonModel([
            'success' => true,
            'message' => 'Trabajo aprobado. Se generó la carta de examinadores.',
            'data'    => $resultado,
        ]);
    }

    /**
     * Sirve la carta de examinadores generada (.docx) para descarga.
     * Valida que el solicitante esté autenticado y que la carta pertenezca
     * a su proceso (o que sea staff con permiso al recurso).
     */
    public function descargarCartaAction()
    {
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            $this->getResponse()->setStatusCode(403);
            return $this->getResponse();
        }

        $codProceso = (int) $this->params()->fromQuery('p', 0);
        if (!$codProceso) {
            $this->getResponse()->setStatusCode(400);
            return $this->getResponse();
        }

        $carta = $this->cartaManager->getCartaPorProceso($codProceso);
        if (!$carta) {
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        // Resolver ruta absoluta. archivo_generado se guarda como ruta relativa
        // al proyecto: 'public/archivos/cartas-examinadores/proceso-N.docx'.
        $rutaProyecto = dirname($_SERVER['DOCUMENT_ROOT']);
        $rutaFisica   = $rutaProyecto . '/' . ltrim($carta['archivo_generado'], '/');
        if (!is_file($rutaFisica)) {
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        $nombreDescarga = 'carta-examinadores-proceso-' . $codProceso . '.docx';
        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->addHeaderLine('Content-Disposition', 'attachment; filename="' . $nombreDescarga . '"')
            ->addHeaderLine('Content-Length', (string) filesize($rutaFisica))
            ->addHeaderLine('X-Content-Type-Options', 'nosniff');
        $response->setContent(file_get_contents($rutaFisica));
        return $response;
    }
}
