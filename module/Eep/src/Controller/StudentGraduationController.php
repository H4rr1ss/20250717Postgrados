<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Authentication\AuthenticationService;
// SERVICES
use Eep\Service\StudentGraduationManager;
use Eep\Service\CartaExaminadoresManager;
use Eep\Service\AutorizacionImpresionManager;
use Eep\Service\ExamenManager;
use Eep\Service\MailManager;
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
     * @var AutorizacionImpresionManager
     */
    private $autorizacionManager;

    /**
     * @var MailManager
     */
    private $mailManager;

    /**
     * @var AuthenticationService
     */
    private $authService;

    /**
     * Constructor: inyecta los managers del paso 1-4 (StudentGraduationManager),
     * del paso 5 "Carta de Examinadores" (CartaExaminadoresManager) y del
     * paso 6 "Autorización de Impresión" (AutorizacionImpresionManager).
     */
    public function __construct(
        StudentGraduationManager $processManager,
        CartaExaminadoresManager $cartaManager,
        AutorizacionImpresionManager $autorizacionManager,
        MailManager $mailManager,
        AuthenticationService $authService
    )
    {
        $this->processManager      = $processManager;
        $this->cartaManager        = $cartaManager;
        $this->autorizacionManager = $autorizacionManager;
        $this->mailManager         = $mailManager;
        $this->authService         = $authService;
    }
    
    public function indexAction() {
        // Obtener el código del usuario logueado
        $codUsuario = $this->authService->getIdentity();

        // Obtener el proceso del estudiante
        $proceso = null;
        if ($codUsuario) {
            $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        }

        if ($proceso) {
            error_log('[StudentGraduation:index] cod_paso_actual=' . var_export($proceso['cod_paso_actual'], true));
            error_log('[StudentGraduation:index] fase_paso_actual=' . var_export($proceso['fase_paso_actual'] ?? null, true));
            foreach ($proceso['pasos'] as $idx => $p) {
                error_log('[StudentGraduation:index] paso[' . $idx . '] cod_paso=' . var_export($p['cod_paso'], true)
                    . ' orden=' . var_export($p['numero_orden'], true)
                    . ' template=' . var_export($p['template_parcial'] ?? null, true)
                    . ' estado=' . var_export($p['estado'], true));
            }
        }

        $terna = null;
        $faseActual = 'examen_privado';
        if ($proceso) {
            // Determinar la fase actual para obtener la fecha/hora correcta
            if (!empty($proceso['pasos']) && !empty($proceso['cod_paso_actual'])) {
                foreach ($proceso['pasos'] as $p) {
                    if ($p['cod_paso'] == $proceso['cod_paso_actual']) {
                        $faseActual = $p['fase'];
                        break;
                    }
                }
            }
            $terna = $this->processManager->getTerna($proceso['cod_proceso']);
        }

        $carta = null;
        if ($proceso) {
            $carta = $this->cartaManager->getCartaPorProceso((int) $proceso['cod_proceso']);

            // Si la fase actual es examen_general, mostrar "General" como tipo de examen
            if ($faseActual === 'examen_general') {
                $proceso['tipo_examen'] = 'General';
            }
        }

        $this->pg()->log('El estudiante consultó su panel principal de proceso de graduación.', LM::SUCCESS, LM::VIEW);

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
        $requisitosReferencia = []; // Documentos del paso anterior para referencia
        $esPasoFisico = false;
        $pasoCompletado = false;
        
        if ($codUsuario) {
            $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
            
            // Si existe un proceso activo, obtener los requisitos digitales
            if ($proceso) {
                // Determinar la fase actual para usar el tipo de examen correcto
                $faseActual = $proceso['fase_paso_actual'] ?? 'examen_privado';
                
                // Para examen_general, siempre usar tipo 99 (Público General)
                // Para examen_privado, usar el tipo del proceso (1 o 2)
                if ($faseActual === 'examen_general') {
                    $codTipoExamenFase = ExamenManager::TIPO_PUBLICO_GENERAL;  // 99
                    // Actualizar el nombre del tipo de examen para la vista
                    $proceso['tipo_examen'] = 'General';
                } else {
                    $codTipoExamenFase = $proceso['cod_tipo_examen'];
                }
                
                // Verificar si el paso actual es null (ya completado)
                $codPasoActual = $proceso['cod_paso_actual'];
                if ($codPasoActual === null || $codPasoActual === '') {
                    $pasoCompletado = true;
                } else {
                    $codPasoActual = (int) $codPasoActual;
                    
                    // Determinar si es un paso de entrega física (paso 2 o 6)
                    if (in_array($codPasoActual, [2, 6])) {
                        $esPasoFisico = true;
                        // Cargar requisitos del paso anterior para referencia
                        $pasoAnterior = ($codPasoActual === 2) ? 1 : 5;
                        $requisitosReferencia = $this->processManager->getRequisitosDigitales(
                            $proceso['cod_proceso'],
                            $pasoAnterior,
                            $codTipoExamenFase
                        );
                    }
                    
                    $requisitos = $this->processManager->getRequisitosDigitales(
                        $proceso['cod_proceso'],
                        $proceso['cod_paso_actual'], // Usar el paso actual del proceso
                        $codTipoExamenFase
                    );
                }
            }
        }
        
        $instruccionesEntrega = null;
        if ($proceso && $esPasoFisico) {
            $instruccionesEntrega = $this->processManager->getInstruccionesEntregaFisica($codTipoExamenFase);
        }

        $view = new ViewModel([
            'proceso' => $proceso,
            'requisitos' => $requisitos,
            'requisitosReferencia' => $requisitosReferencia,
            'esPasoFisico' => $esPasoFisico,
            'pasoCompletado' => $pasoCompletado,
            'madrina' => [
                'tipo'             => $proceso['madrina_tipo'] ?? '',
                'nombre'           => $proceso['madrina_nombre'] ?? '',
                'titulo_profesional' => $proceso['madrina_titulo'] ?? '',
                'tiene_madrina'    => (bool) ($proceso['tiene_madrina'] ?? 0),
            ],
            'instruccionesEntrega' => $instruccionesEntrega
        ]);
        $this->pg()->log('El estudiante ingresó a la pantalla del Paso 1 (Solicitud de Examen / Papelería).', LM::SUCCESS, LM::VIEW);

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

        // Determinar la fase actual para obtener la fecha/hora correcta
        $faseActual = 'examen_privado';
        if (!empty($proceso['pasos']) && !empty($proceso['cod_paso_actual'])) {
            foreach ($proceso['pasos'] as $p) {
                if ($p['cod_paso'] == $proceso['cod_paso_actual']) {
                    $faseActual = $p['fase'];
                    break;
                }
            }
        }
        $terna = $this->processManager->getTerna($proceso['cod_proceso'], $faseActual);

        $view = new ViewModel([
            'proceso' => $proceso,
            'terna'   => $terna,
        ]);
        $this->pg()->log('El estudiante consultó la pantalla del Paso 2 (Terna Examinadora).', LM::SUCCESS, LM::VIEW);

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
        // NUEVA RUTA: data/graduacion/procesos/{cod_proceso}/
        $directorio = dirname($_SERVER['DOCUMENT_ROOT']) . '/data/graduacion/procesos/' . $proceso['cod_proceso'] . '/';

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
            $this->pg()->log('Error al registrar el documento del requisito ' . $codRequisito . ' en el proceso del estudiante ' . $proceso['nombres'] . ' ' . $proceso['apellidos'] . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return new JsonModel(['success' => false, 'message' => 'Error al registrar el documento en la base de datos']);
        }

        $this->pg()->log('El estudiante subió el documento del requisito para su proceso de graduación.', LM::SUCCESS, LM::CREATE);

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
            $this->pg()->log('Intento de visualizar documento sin autenticación.', LM::FAILURE, LM::READ);
            $this->getResponse()->setStatusCode(403);
            return $this->getResponse();
        }

        // 2. Obtener el hash del query string
        $hash = $this->params()->fromQuery('h', '');
        error_log('[verDocumento] hash=' . var_export($hash, true));
        if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
            $this->pg()->log('Se intentó visualizar un documento con un identificador hash inválido (' . $hash . ').', LM::FAILURE, LM::READ);
            $this->getResponse()->setStatusCode(400);
            return $this->getResponse();
        }

        // 3. Buscar el archivo en BD
        $archivoInfo = $this->processManager->getArchivoByHash($hash);
        error_log('[verDocumento] archivoInfo=' . var_export($archivoInfo, true));
        if (!$archivoInfo) {
            $this->pg()->log('Se intentó visualizar un documento no encontrado en el sistema (hash ' . $hash . ').', LM::FAILURE, LM::READ);
            $this->getResponse()->setStatusCode(403);
            return $this->getResponse();
        }

        // 4. Construir ruta física y verificar que existe
        // NUEVA RUTA: data/graduacion/procesos/{cod_proceso}/
        $rutaFisica = dirname($_SERVER['DOCUMENT_ROOT']) . '/data/graduacion/procesos/'
                    . $archivoInfo['cod_proceso'] . '/'
                    . $archivoInfo['nombre_md5'] . '.' . $archivoInfo['extension'];
        error_log('[verDocumento] rutaFisica=' . $rutaFisica . ' exists=' . var_export(is_file($rutaFisica), true));

        if (!is_file($rutaFisica)) {
            $this->pg()->log('El archivo físico del documento no existe en el servidor (hash ' . $hash . ').', LM::ERROR, LM::READ);
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
        $this->pg()->log('El estudiante visualizó un documento subido.', LM::SUCCESS, LM::READ);

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
        $this->pg()->log('El estudiante consultó la pantalla del Paso 5 (Carta de Examinadores).', LM::SUCCESS, LM::VIEW);

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
        $descripcion = trim((string) $request->getPost('descripcion', ''));
        if (!$codCiclo) {
            return new JsonModel(['success' => false, 'message' => 'Ciclo no especificado']);
        }
        if ($descripcion === '') {
            return new JsonModel(['success' => false, 'message' => 'La descripción es obligatoria.']);
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
        // NUEVA RUTA: data/graduacion/procesos/{cod_proceso}/
        $directorio = dirname($_SERVER['DOCUMENT_ROOT']) . '/data/graduacion/procesos/' . $proceso['cod_proceso'] . '/';
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
                'descripcion'     => $descripcion,
                'subido_por'      => $codUsuario,
            ]);
        } catch (\Exception $e) {
            @unlink($rutaDestino);
            $this->pg()->log('Error al adjuntar evidencia al ciclo de correcciones: ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return new JsonModel(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }

        $this->pg()->log('El estudiante adjuntó una nueva evidencia al ciclo de correcciones.', LM::SUCCESS, LM::CREATE);

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

        // NUEVA RUTA: data/graduacion/procesos/{cod_proceso}/
        $rutaBase = dirname($_SERVER['DOCUMENT_ROOT']) . '/data/graduacion/procesos/' . $proceso['cod_proceso'];
        $this->cartaManager->eliminarEvidencia($codEvidencia, rtrim($rutaBase, '/'));

        $this->pg()->log('El estudiante eliminó una evidencia del ciclo de correcciones.', LM::SUCCESS, LM::DELETE);

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

        $proceso = $this->processManager->getProceso($codProceso);

        try {
            $resultado = $this->cartaManager->aprobarTrabajo($codProceso, $codUsuario);

            // Notificar al estudiante que su trabajo fue aprobado y se generó la carta
            if ($proceso && !empty($proceso['correo'])) {
                $nombreEstudiante = htmlspecialchars(
                    ($proceso['nombres'] ?? '') . ' ' . ($proceso['apellidos'] ?? '')
                );

                $html = '<p>Estimado(a) <strong>' . $nombreEstudiante . '</strong>,</p>'
                      . '<p>Se le informa que su <strong>trabajo de graduación</strong> ha sido <strong>aprobado</strong>.</p>'
                      . '<p>Se ha generado la <strong>carta de examinadores</strong> correspondiente a su proceso.</p>'
                      . '<p>Puede ingresar a la plataforma para descargarla y revisar los pasos a seguir.'
                      . ' <a href="http://localhost:8080/" style="color:#003366;text-decoration:underline;">Ir a la plataforma</a></p>';

                $faseLabel = str_replace(['_', 'examen'], [' ', 'Examen'], $proceso['fase_paso_actual'] ?? 'examen_privado');
                $faseLabel = ucwords(trim($faseLabel));

                $this->mailManager->sendHtmlMessage(
                    $proceso['correo'],
                    'Trabajo de Graduacion Aprobado - Carta de Examinadores Generada - ' . htmlspecialchars($faseLabel),
                    $html
                );
            }
        } catch (\Exception $e) {
            $this->pg()->log('Error al aprobar el trabajo de graduación del estudiante ' . ($proceso['nombres'] ?? '') . ' ' . ($proceso['apellidos'] ?? '') . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->pg()->log('Se aprobó el trabajo de graduación del estudiante ' . ($proceso['nombres'] ?? '') . ' ' . ($proceso['apellidos'] ?? '') . ' y se generó la carta de examinadores.', LM::SUCCESS, LM::UPDATE);

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
        $procesoCarta = null;
        if (!$codUsuario) {
            $this->pg()->log('Intento de descargar carta de examinadores sin autenticación.', LM::FAILURE, LM::READ);
            $this->getResponse()->setStatusCode(403);
            return $this->getResponse();
        }

        $codProceso = (int) $this->params()->fromQuery('p', 0);
        if (!$codProceso) {
            $this->pg()->log('Se intentó descargar carta de examinadores sin especificar el proceso.', LM::FAILURE, LM::READ);
            $this->getResponse()->setStatusCode(400);
            return $this->getResponse();
        }

        $carta = $this->cartaManager->getCartaPorProceso($codProceso);
        $procesoCarta = $this->processManager->getProceso($codProceso);
        if (!$carta) {
            $this->pg()->log('No se encontró carta de examinadores para el estudiante ' . ($procesoCarta['nombres'] ?? '') . ' ' . ($procesoCarta['apellidos'] ?? '') . '.', LM::FAILURE, LM::READ);
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        // Resolver ruta absoluta. archivo_generado se guarda como ruta relativa
        // al proyecto: 'data/graduacion/procesos/{cod}/carta-examinadores.docx'.
        $rutaProyecto = dirname($_SERVER['DOCUMENT_ROOT']);
        $rutaFisica   = $rutaProyecto . '/' . ltrim($carta['archivo_generado'], '/');
        if (!is_file($rutaFisica)) {
            $this->pg()->log('El archivo de la carta de examinadores del estudiante ' . ($procesoCarta['nombres'] ?? '') . ' ' . ($procesoCarta['apellidos'] ?? '') . ' no existe en el servidor.', LM::ERROR, LM::READ);
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        $this->pg()->log('Se descargó la carta de examinadores del estudiante ' . ($procesoCarta['nombres'] ?? '') . ' ' . ($procesoCarta['apellidos'] ?? '') . '.', LM::SUCCESS, LM::READ);

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

    // ================================================================
    // PASO 6 — Autorización de Impresión del Proyecto de Graduación
    //
    // Vista del estudiante:
    //   - Lee las instrucciones publicadas por el director
    //   - Descarga documentos de soporte (logos/escudos)
    //   - Selecciona un profesional calificado (licenciado en letras)
    //   - Descarga cartas tipo .docx
    //   - Visualiza la junta directiva (informativo)
    //   - Confirma haber descargado los materiales
    //
    // La aprobación final corresponde al director (revisión presencial)
    // y se ejecuta desde ExamenController::aprobarRevisionPresencial.
    // ================================================================

    /**
     * Vista del paso 6 para el estudiante. Carga todos los recursos
     * globales en sólo lectura más el estado individual del proceso.
     */
    public function paso6AutorizacionImpresionAction()
    {
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return $this->redirect()->toRoute('auth', ['action' => 'login']);
        }

        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return $this->redirect()->toRoute('student-graduation', ['action' => 'index']);
        }

        $estado = $this->autorizacionManager->getOrCreateEstadoProceso((int) $proceso['cod_proceso']);
        $subPaso = (int) ($estado['sub_paso'] ?? 1);

        $view = new ViewModel([
            'proceso'       => $proceso,
            'estado'        => $estado,
            'instrucciones' => $this->autorizacionManager->getInstrucciones($subPaso),
            'documentos'    => $this->autorizacionManager->getDocumentosSoporte(true),
            'profesionales' => $this->autorizacionManager->getProfesionales(true),
            'cartas'        => $this->autorizacionManager->getCartasDescarga(true),
            'miembros'      => $this->autorizacionManager->getMiembrosJunta(true),
        ]);
        $this->pg()->log('El estudiante consultó la pantalla del Paso 6 (Autorización de Impresión).', LM::SUCCESS, LM::VIEW);

        $view->setTemplate('eep/student-graduation/partial/paso6-autorizacion-impresion');
        return $view;
    }

    /**
     * AJAX: el estudiante selecciona (o cambia) su profesional calificado.
     */
    public function seleccionarProfesionalAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel(['success' => false, 'message' => 'Usuario no autenticado']);
        }

        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return new JsonModel(['success' => false, 'message' => 'No tiene un proceso activo']);
        }
        if (!$this->autorizacionManager->procesoEstaEnFase((int) $proceso['cod_proceso'])) {
            return new JsonModel(['success' => false, 'message' => 'Su proceso no está en la fase de Autorización de Impresión']);
        }

        $codProfesional = (int) $request->getPost('cod_profesional', 0);
        if ($codProfesional <= 0) {
            return new JsonModel(['success' => false, 'message' => 'Profesional inválido']);
        }

        try {
            $this->autorizacionManager->seleccionarProfesional(
                (int) $proceso['cod_proceso'],
                $codProfesional
            );
            $this->pg()->log('El estudiante seleccionó al profesional calificado para la revisión de su proyecto.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel(['success' => true, 'message' => 'Profesional seleccionado correctamente']);
        } catch (\Exception $e) {
            $this->pg()->log('Error al seleccionar al profesional calificado para el estudiante ' . $proceso['nombres'] . ' ' . $proceso['apellidos'] . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Guarda el tema de tesis del estudiante.
     * Retorna JSON para manejo vía AJAX.
     */
    public function guardarTemaTesisAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel(['success' => false, 'message' => 'Usuario no autenticado']);
        }

        $temaTesis = trim((string) $request->getPost('tema_tesis', ''));
        if (empty($temaTesis)) {
            $this->pg()->log('El estudiante intentó guardar un tema de tesis vacío.', LM::FAILURE, LM::CREATE);
            return new JsonModel(['success' => false, 'message' => 'Debe ingresar el título de su trabajo de graduación.']);
        }

        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return new JsonModel(['success' => false, 'message' => 'No tiene un proceso activo']);
        }

        // Verificar que está en fase 1 (examen_privado, paso 1)
        $pasoActualOrden = (int) ($proceso['paso_actual_orden'] ?? 0);
        $faseActual = $proceso['fase_paso_actual'] ?? '';
        if ($pasoActualOrden !== 1 || $faseActual !== 'examen_privado') {
            return new JsonModel(['success' => false, 'message' => 'Solo puede registrar el tema durante la fase de revisión de papelería.']);
        }

        try {
            $result = $this->processManager->guardarTemaTesis(
                (int) $proceso['cod_proceso'],
                $codUsuario,
                $temaTesis
            );
            $this->pg()->log('El estudiante registró el tema de tesis "' . substr($temaTesis, 0, 50) . '".', $result['success'] ? LM::SUCCESS : LM::FAILURE, LM::CREATE);
            return new JsonModel($result);
        } catch (\Exception $e) {
            $this->pg()->log('Error al registrar el tema de tesis del estudiante ' . $proceso['nombres'] . ' ' . $proceso['apellidos'] . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return new JsonModel(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: guarda el nombre de madrina/padrino para el proceso.
     * Solo aplicable para examen general público.
     */
    public function guardarMadrinaPadrinoAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return new JsonModel(['success' => false, 'message' => 'Usuario no autenticado']);
        }

        $tipo     = trim((string) $request->getPost('tipo', ''));
        $nombre   = trim((string) $request->getPost('nombre', ''));
        $titulo   = trim((string) $request->getPost('titulo_profesional', ''));

        if (!in_array($tipo, ['madrina', 'padrino'], true)) {
            return new JsonModel(['success' => false, 'message' => 'Debe seleccionar si es madrina o padrino.']);
        }
        if ($nombre === '') {
            return new JsonModel(['success' => false, 'message' => 'Debe ingresar el nombre.']);
        }

        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return new JsonModel(['success' => false, 'message' => 'No tiene un proceso activo.']);
        }

        $faseActual = $proceso['fase_paso_actual'] ?? '';
        if ($faseActual !== 'examen_general') {
            return new JsonModel(['success' => false, 'message' => 'Este dato solo es requerido para el examen general público.']);
        }

        try {
            $this->processManager->guardarMadrinaPadrino(
                (int) $proceso['cod_proceso'],
                $tipo,
                $nombre,
                $titulo !== '' ? $titulo : null
            );
            $this->pg()->log('El estudiante guardó los datos de su ' . $tipo . ' para el examen general público.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel([
                'success' => true,
                'message' => 'Datos guardados correctamente.',
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al guardar los datos de ' . $tipo . ' del estudiante ' . $proceso['nombres'] . ' ' . $proceso['apellidos'] . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
            return new JsonModel(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    /**
     * Vista dedicada para configurar el nombre de madrina/padrino.
     * Solo aplicable para examen general público.
     */
    public function configurarMadrinaPadrinoAction()
    {
        $codUsuario = $this->authService->getIdentity();
        if (!$codUsuario) {
            return $this->redirect()->toRoute('auth', ['action' => 'login']);
        }

        $proceso = $this->processManager->getProcesoEstudiante($codUsuario);
        if (!$proceso) {
            return $this->redirect()->toRoute('student-graduation', ['action' => 'index']);
        }

        $faseActual = $proceso['fase_paso_actual'] ?? '';
        if ($faseActual !== 'examen_general') {
            return $this->redirect()->toRoute('student-graduation', ['action' => 'index']);
        }

        return new ViewModel([
            'proceso' => $proceso,
            'madrina' => [
                'tipo'             => $proceso['madrina_tipo'] ?? '',
                'nombre'           => $proceso['madrina_nombre'] ?? '',
                'titulo_profesional' => $proceso['madrina_titulo'] ?? '',
                'tiene_madrina'    => (bool) ($proceso['tiene_madrina'] ?? 0),
            ],
        ]);
    }

}
