<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
// SERVICES
use Eep\Service\ExamenManager;
use Eep\Service\CartaExaminadoresManager;
use Eep\Service\AutorizacionImpresionManager;
use Eep\Service\UserManager;
use Eep\Service\MailManager;
use Eep\Service\LogManager as LM;

class ExamenController extends AbstractActionController {

    /**
     * @var ExamenManager
     */
    private $examenManager;

    /**
     * @var CartaExaminadoresManager
     */
    private $cartaManager;

    /**
     * @var AutorizacionImpresionManager
     */
    private $autorizacionManager;

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var MailManager
     */
    private $mailManager;

    /**
     * Constructor: inyecta los managers de los distintos pasos del módulo
     * de graduación (papelería 1-4, carta examinadores 5, autorización 6).
     */
    public function __construct(
        ExamenManager $examenManager,
        CartaExaminadoresManager $cartaManager,
        AutorizacionImpresionManager $autorizacionManager,
        UserManager $userManager,
        MailManager $mailManager
    ) {
        $this->examenManager       = $examenManager;
        $this->cartaManager        = $cartaManager;
        $this->autorizacionManager = $autorizacionManager;
        $this->userManager         = $userManager;
        $this->mailManager         = $mailManager;
    }

    // ================================================================
    // PASO 6 — Autorización de Impresión del Proyecto de Graduación
    //
    // Estructura:
    //   - autorizacionImpresion       (GET)  Listado de procesos en fase 6
    //                                        + paneles globales (instrucciones,
    //                                        docs soporte, profesionales,
    //                                        cartas, junta directiva)
    //   - configurarAutorizacion      (GET)  Detalle de un proceso, botón Aprobar
    //
    //   CRUD globales (AJAX POST):
    //     - guardarInstruccionesAutorizacion
    //     - subirDocumentoSoporte / eliminarDocumentoSoporte
    //     - guardarProfesional      / eliminarProfesional
    //     - subirCartaDescarga      / eliminarCartaDescarga
    //     - guardarMiembroJunta     / eliminarMiembroJunta
    //
    //   Descargas (GET):
    //     - descargarDocumentoSoporte?h=<md5>
    //     - descargarCartaDescarga?h=<md5>
    //
    //   Aprobación por proceso (AJAX POST):
    //     - aprobarRevisionPresencial
    // ================================================================

    /**
     * Pantalla principal del paso 6 para el staff. Muestra el listado de
     * procesos que están actualmente en fase 'autorizacion_impresion' y
     * deja a la mano la administración de los recursos globales.
     */
    public function autorizacionImpresionAction()
    {
        $instrucciones = $this->autorizacionManager->getInstruccionesAmbas();
        return new ViewModel([
            'procesos'            => $this->autorizacionManager->getProcesosEnFase(),
            'instruccionesParte1' => $instrucciones['parte1'],
            'instruccionesParte2' => $instrucciones['parte2'],
            'documentos'          => $this->autorizacionManager->getDocumentosSoporte(false),
            'profesionales'       => $this->autorizacionManager->getProfesionales(false),
            'cartas'              => $this->autorizacionManager->getCartasDescarga(false),
            'miembros'            => $this->autorizacionManager->getMiembrosJunta(false),
            'formatosDoc'         => $this->autorizacionManager->getFormatosDocumentoSoporte(),
            'formatosCarta'       => $this->autorizacionManager->getFormatosCartaDescarga(),
            'tamanoMaxMb'         => $this->autorizacionManager->getTamanoMaxMb(),
        ]);
    }

    /**
     * Vista de detalle de un proceso en fase 6. Permite al director:
     *   - Ver al estudiante, su profesional asignado y descargas
     *   - Aprobar la revisión presencial (cierra la fase y avanza a examen_general)
     */
    public function configurarAutorizacionAction()
    {
        $codProceso = (int) $this->params()->fromRoute('id', 0);
        if ($codProceso <= 0) {
            return $this->redirect()->toRoute('examen', ['action' => 'autorizacionImpresion']);
        }

        $proceso = $this->examenManager->getProceso($codProceso);
        if (!$proceso) {
            $this->flashMessenger()->addErrorMessage('Proceso no encontrado.');
            return $this->redirect()->toRoute('examen', ['action' => 'autorizacionImpresion']);
        }

        $estudiante = $this->examenManager->getEstudiantePorProceso($codProceso);
        $estado     = $this->autorizacionManager->getOrCreateEstadoProceso($codProceso);
        $profesional = null;
        if (!empty($estado['cod_profesional'])) {
            $profesional = $this->autorizacionManager->getProfesional((int) $estado['cod_profesional']);
        }

        return new ViewModel([
            'proceso'     => $proceso,
            'estudiante'  => $estudiante,
            'estado'      => $estado,
            'profesional' => $profesional,
            'enFase'      => $this->autorizacionManager->procesoEstaEnFase($codProceso),
        ]);
    }

    /**
     * AJAX: actualiza el bloque de instrucciones GLOBAL.
     * Acepta parámetro 'parte' (1 o 2) para indicar qué instrucciones guardar.
     */
    public function guardarInstruccionesAutorizacionAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $userId = $this->layout()->role->getCode();
        $instrucciones = (string) $request->getPost('instrucciones', '');
        $parte = (int) $request->getPost('parte', 1);
        if ($parte < 1 || $parte > 2) {
            $parte = 1;
        }
        try {
            $this->autorizacionManager->guardarInstrucciones($instrucciones, $userId, $parte);
            $nombreParte = $parte === 1 ? 'Parte 1 (Autorización de Imprímase)' : 'Parte 2 (Entrega de Proyecto)';
            return new JsonModel(['status' => 'success', 'message' => "Instrucciones de {$nombreParte} guardadas correctamente"]);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: sube un documento de soporte GLOBAL (logo, escudo, guía).
     */
    public function subirDocumentoSoporteAction()
    {
        return $this->handleUploadGlobal('documento_soporte');
    }

    /**
     * AJAX: elimina un documento de soporte GLOBAL.
     */
    public function eliminarDocumentoSoporteAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $cod = (int) $request->getPost('cod_documento', 0);
        if ($cod <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Identificador inválido']);
        }
        try {
            $this->autorizacionManager->eliminarDocumentoSoporte(
                $cod,
                $this->getRutaDocumentosSoporte()
            );
            return new JsonModel(['status' => 'success', 'message' => 'Documento eliminado']);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET: descarga un documento de soporte por su hash MD5.
     * Disponible para cualquier usuario autenticado.
     */
    public function descargarDocumentoSoporteAction()
    {
        return $this->handleDescargaGlobal('documento_soporte');
    }

    /**
     * GET: descarga un requisito de apoyo por su nombre de archivo.
     * Los archivos se almacenan en data/graduacion/global/requisitos-apoyo/
     */
    public function descargarRequisitoApoyoAction()
    {
        $nombreArchivo = basename((string) $this->params()->fromQuery('file', ''));
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $nombreArchivo)) {
            $this->getResponse()->setStatusCode(400);
            return $this->getResponse();
        }

        $directorio = getcwd() . '/data/graduacion/global/requisitos-apoyo/';
        $rutaFisica = $directorio . $nombreArchivo;

        if (!is_file($rutaFisica)) {
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', $contentType)
            ->addHeaderLine('Content-Disposition', 'inline; filename="' . $nombreArchivo . '"')
            ->addHeaderLine('Content-Length', (string) filesize($rutaFisica))
            ->addHeaderLine('X-Content-Type-Options', 'nosniff');
        $response->setContent(file_get_contents($rutaFisica));
        return $response;
    }

    /**
     * AJAX: alta o edición de un profesional calificado.
     */
    public function guardarProfesionalAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $userId = $this->layout()->role->getCode();
        $data = [
            'cod_profesional' => (int) $request->getPost('cod_profesional', 0),
            'nombre_completo' => trim((string) $request->getPost('nombre_completo', '')),
            'correo'          => trim((string) $request->getPost('correo', '')) ?: null,
            'telefono'        => trim((string) $request->getPost('telefono', '')) ?: null,
            'activo'          => (int) $request->getPost('activo', 1),
            'creado_por'      => $userId,
        ];
        if ($data['nombre_completo'] === '') {
            return new JsonModel(['status' => 'error', 'message' => 'El nombre es obligatorio']);
        }
        try {
            $id = $this->autorizacionManager->guardarProfesional($data);
            return new JsonModel([
                'status'  => 'success',
                'message' => $data['cod_profesional'] ? 'Profesional actualizado' : 'Profesional creado',
                'id'      => $id,
            ]);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: desactivación (soft delete) de un profesional.
     */
    public function eliminarProfesionalAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $cod = (int) $request->getPost('cod_profesional', 0);
        if ($cod <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Identificador inválido']);
        }
        try {
            $this->autorizacionManager->eliminarProfesional($cod);
            return new JsonModel(['status' => 'success', 'message' => 'Profesional desactivado']);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: sube una carta genérica (.docx) para descarga del estudiante.
     */
    public function subirCartaDescargaAction()
    {
        return $this->handleUploadGlobal('carta_descarga');
    }

    /**
     * AJAX: elimina una carta genérica.
     */
    public function eliminarCartaDescargaAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $cod = (int) $request->getPost('cod_carta', 0);
        if ($cod <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Identificador inválido']);
        }
        try {
            $this->autorizacionManager->eliminarCartaDescarga(
                $cod,
                $this->getRutaCartasDescarga()
            );
            return new JsonModel(['status' => 'success', 'message' => 'Carta eliminada']);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET: descarga una carta tipo por su hash MD5.
     */
    public function descargarCartaDescargaAction()
    {
        return $this->handleDescargaGlobal('carta_descarga');
    }

    /**
     * AJAX: alta o edición de un miembro de junta directiva.
     */
    public function guardarMiembroJuntaAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $userId = $this->layout()->role->getCode();
        $data = [
            'cod_miembro'     => (int) $request->getPost('cod_miembro', 0),
            'nombre_completo' => trim((string) $request->getPost('nombre_completo', '')),
            'puesto'          => trim((string) $request->getPost('puesto', '')),
            'activo'          => (int) $request->getPost('activo', 1),
            'creado_por'      => $userId,
        ];
        if ($data['nombre_completo'] === '' || $data['puesto'] === '') {
            return new JsonModel(['status' => 'error', 'message' => 'Nombre y puesto son obligatorios']);
        }
        try {
            $id = $this->autorizacionManager->guardarMiembroJunta($data);
            return new JsonModel([
                'status'  => 'success',
                'message' => $data['cod_miembro'] ? 'Miembro actualizado' : 'Miembro creado',
                'id'      => $id,
            ]);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: elimina (hard delete) un miembro de junta directiva.
     */
    public function eliminarMiembroJuntaAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $cod = (int) $request->getPost('cod_miembro', 0);
        if ($cod <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Identificador inválido']);
        }
        try {
            $this->autorizacionManager->eliminarMiembroJunta($cod);
            return new JsonModel(['status' => 'success', 'message' => 'Miembro eliminado']);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: el director aprueba la revisión presencial. Valida que el
     * estudiante haya seleccionado un profesional y avanza el proceso
     * a la fase 'examen_general'.
     */
    public function aprobarRevisionPresencialAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $userId = $this->layout()->role->getCode();
        $codProceso = (int) $request->getPost('cod_proceso', 0);
        $observaciones = trim((string) $request->getPost('observaciones', '')) ?: null;

        if ($codProceso <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Proceso inválido']);
        }
        try {
            $res = $this->autorizacionManager->aprobarRevisionPresencial(
                $codProceso,
                $userId,
                $observaciones
            );
            return new JsonModel([
                'status'  => 'success',
                'message' => 'Revisión presencial aprobada. El proceso avanzó a la fase de Examen General.',
                'data'    => $res,
            ]);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ----------------------------------------------------------------
    // Helpers internos para el paso 6 (uploads + descargas globales)
    // ----------------------------------------------------------------

    private function getRutaDocumentosSoporte(): string
    {
        return rtrim(dirname($_SERVER['DOCUMENT_ROOT']), '/')
            . '/' . AutorizacionImpresionManager::SUBDIR_DOCUMENTOS;
    }

    private function getRutaCartasDescarga(): string
    {
        return rtrim(dirname($_SERVER['DOCUMENT_ROOT']), '/')
            . '/' . AutorizacionImpresionManager::SUBDIR_CARTAS;
    }

    /**
     * Manejador unificado para subida de archivos globales del paso 6.
     * $tipo: 'documento_soporte' | 'carta_descarga'
     */
    private function handleUploadGlobal(string $tipo): JsonModel
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }
        $userId = $this->layout()->role->getCode();

        $titulo      = trim((string) $request->getPost('titulo', ''));
        $descripcion = trim((string) $request->getPost('descripcion', '')) ?: null;

        if ($titulo === '') {
            return new JsonModel(['status' => 'error', 'message' => 'El título es obligatorio']);
        }

        $files = $request->getFiles()->toArray();
        if (empty($files['archivo']) || $files['archivo']['error'] !== UPLOAD_ERR_OK) {
            return new JsonModel(['status' => 'error', 'message' => 'No se recibió el archivo']);
        }
        $archivo = $files['archivo'];

        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $formatos = ($tipo === 'documento_soporte')
            ? $this->autorizacionManager->getFormatosDocumentoSoporte()
            : $this->autorizacionManager->getFormatosCartaDescarga();
        if (!in_array($extension, $formatos, true)) {
            return new JsonModel([
                'status'  => 'error',
                'message' => 'Formato no permitido. Aceptados: ' . implode(', ', $formatos),
            ]);
        }
        if ($archivo['size'] > $this->autorizacionManager->getTamanoMaxBytes()) {
            return new JsonModel([
                'status'  => 'error',
                'message' => 'El archivo supera el tamaño máximo de '
                              . $this->autorizacionManager->getTamanoMaxMb() . ' MB',
            ]);
        }

        $directorio = ($tipo === 'documento_soporte')
            ? $this->getRutaDocumentosSoporte()
            : $this->getRutaCartasDescarga();
        if (!is_dir($directorio)) {
            @mkdir($directorio, 0775, true);
        }

        $nombreMd5 = md5(pathinfo($archivo['name'], PATHINFO_FILENAME) . date('YmdHis') . uniqid('', true));
        $rutaDestino = $directorio . '/' . $nombreMd5 . '.' . $extension;
        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar el archivo en el servidor']);
        }

        try {
            $data = [
                'titulo'          => $titulo,
                'descripcion'     => $descripcion,
                'archivo_md5'     => $nombreMd5,
                'extension'       => $extension,
                'nombre_original' => $archivo['name'],
                'tamano_bytes'    => $archivo['size'],
                'subido_por'      => $userId,
            ];
            if ($tipo === 'documento_soporte') {
                $id = $this->autorizacionManager->guardarDocumentoSoporte($data);
            } else {
                $id = $this->autorizacionManager->guardarCartaDescarga($data);
            }
        } catch (\Exception $e) {
            @unlink($rutaDestino);
            return new JsonModel(['status' => 'error', 'message' => 'Error al registrar: ' . $e->getMessage()]);
        }

        return new JsonModel([
            'status'  => 'success',
            'message' => 'Archivo subido correctamente',
            'data'    => ['id' => $id, 'hash' => $nombreMd5],
        ]);
    }

    /**
     * Manejador unificado para descarga de archivos globales del paso 6.
     */
    private function handleDescargaGlobal(string $tipo)
    {
        $hash = (string) $this->params()->fromQuery('h', '');
        if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
            $this->getResponse()->setStatusCode(400);
            return $this->getResponse();
        }

        if ($tipo === 'documento_soporte') {
            $info = $this->autorizacionManager->getDocumentoSoportePorMd5($hash);
            $directorio = $this->getRutaDocumentosSoporte();
        } else {
            $info = $this->autorizacionManager->getCartaDescargaPorMd5($hash);
            $directorio = $this->getRutaCartasDescarga();
        }
        if (!$info) {
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        $rutaFisica = $directorio . '/' . $info['archivo_md5'] . '.' . $info['extension'];
        if (!is_file($rutaFisica)) {
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'  => 'application/msword',
        ];
        $contentType = $mimeTypes[$info['extension']] ?? 'application/octet-stream';
        $nombre = $info['nombre_original'] ?? ($info['archivo_md5'] . '.' . $info['extension']);

        $disposition = ($tipo === 'documento_soporte' && in_array($info['extension'], ['jpg','jpeg','png','pdf'], true))
            ? 'inline'
            : 'attachment';

        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', $contentType)
            ->addHeaderLine('Content-Disposition', $disposition . '; filename="' . $nombre . '"')
            ->addHeaderLine('Content-Length', (string) filesize($rutaFisica))
            ->addHeaderLine('X-Content-Type-Options', 'nosniff');
        $response->setContent(file_get_contents($rutaFisica));
        return $response;
    }

    public function indexAction() {
        $tiposExamen = $this->examenManager->getTiposExamen();
        $procesosActivos = $this->examenManager->getProcesos(['limite' => 5]);

        return new ViewModel([
            'tiposExamen' => $tiposExamen,
            'procesosActivos' => $procesosActivos
        ]);
    }

    // 0. INICIAR PROCESO DE GRADUACIÓN ---------------------

    /**
     * Vista para que el director/asistente inicie un proceso de graduación
     * para un estudiante. Muestra un buscador de estudiantes y selector
     * de tipo de examen.
     *
     * GET:  Muestra el formulario de búsqueda
     * POST: Inicia el proceso para el estudiante seleccionado
     */
    public function iniciarProcesoAction()
    {
        $tiposExamen = $this->examenManager->getTiposExamen();
        $userAdminId = $this->layout()->role->getCode();

        $request = $this->getRequest();

        if ($request->isPost()) {
            $codUsuario = (int) $request->getPost('cod_usuario', 0);
            $codTipoExamen = (int) $request->getPost('cod_tipo_examen', 0);

            error_log("DEBUG user id: ".print_r($userAdminId, true));
            error_log("DEBUG codUsuario: ".print_r($codUsuario, true));

            if ($codUsuario <= 0 || $codTipoExamen <= 0) {
                $this->flashMessenger()->addErrorMessage('Debe seleccionar un estudiante y un tipo de examen.');
                return $this->redirect()->toRoute('examen', ['action' => 'iniciar-proceso']);
            }

            // Verificar si el estudiante ya tiene un proceso activo
            $procesoActivo = $this->examenManager->getProcesoActivoEstudiante($codUsuario);
            if ($procesoActivo) {
                $this->flashMessenger()->addErrorMessage(
                    'El estudiante ya tiene un proceso activo: '
                    . $procesoActivo['tipo_examen']
                    . ' (en paso: ' . $procesoActivo['paso_actual'] . ').'
                    . ' Debe finalizar o cancelar el proceso existente antes de iniciar uno nuevo.'
                );
                return $this->redirect()->toRoute('examen', ['action' => 'iniciar-proceso']);
            }

            try {
                $idProceso = $this->examenManager->iniciarProceso($codUsuario, $codTipoExamen, $userAdminId);

                // Notificar al estudiante por correo electronico
                $estudiante = $this->userManager->getUser($codUsuario);
                $correo = $estudiante->getCorreo();

                if (!empty($correo)) {
                    $nombreTipoExamenTexto = 'Privado';
                    foreach ($tiposExamen as $tipo) {
                        if ((int) $tipo['cod_tipo_examen'] === $codTipoExamen) {
                            $nombreTipoExamenTexto = $tipo['nombre'];
                            break;
                        }
                    }

                    $procesoRecienCreado = $this->examenManager->getProceso((int) $idProceso);
                    $faseProceso = $procesoRecienCreado['fase_paso_actual'] ?? 'examen_privado';
                    $faseLabel = str_replace(['_', 'examen'], [' ', 'Examen'], $faseProceso);
                    $faseLabel = ucwords(trim($faseLabel));

                    $html = '<p>Estimado(a) <strong>' . htmlspecialchars($estudiante->getNombreCompleto()) . '</strong>,</p>'
                        . '<p>Se le notifica que su <strong>proceso de graduacion</strong> ha sido iniciado en la plataforma.</p>'
                        . '<p><strong>Tipo de examen:</strong> ' . htmlspecialchars($nombreTipoExamenTexto) . '</p>'
                        . '<p><strong>Fase:</strong> ' . htmlspecialchars($faseLabel) . '</p>'
                        . '<p><strong>Fecha de inicio:</strong> ' . date('d/m/Y H:i') . '</p>'
                        . '<p>Puede ingresar a la plataforma para revisar los pasos a seguir.'
                        . ' <a href="http://localhost:8080/" style="color:#003366;text-decoration:underline;">Ir a la plataforma</a></p>';

                    $enviado = $this->mailManager->sendHtmlMessage(
                        $correo,
                        'Proceso de Graduacion Iniciado - ' . htmlspecialchars($faseLabel),
                        $html
                    );

                    if (!$enviado) {
                        error_log('[ExamenController::iniciarProcesoAction] ' .
                            'Fallo envio de correo a ' . $correo .
                            ' para estudiante cod=' . $codUsuario .
                            ' proceso=' . $idProceso);
                    }
                }

                $this->flashMessenger()->addSuccessMessage('Proceso de graduacion iniciado correctamente.');
                return $this->redirect()->toRoute('examen', [
                    'action' => 'solicitudes',
                    'id'     => $idProceso
                ], ['query' => ['paso' => 1]]);
            } catch (\Exception $e) {
                $this->flashMessenger()->addErrorMessage('Error al iniciar el proceso: ' . $e->getMessage());
                return $this->redirect()->toRoute('examen', ['action' => 'iniciar-proceso']);
            }
        }

        return new ViewModel([
            'tiposExamen' => $tiposExamen,
        ]);
    }

    /**
     * AJAX: Busca estudiantes por registro académico o nombre.
     * Retorna JSON con la lista de coincidencias.
     */
    public function buscarEstudianteAction()
    {
        $request = $this->getRequest();
        if (!$request->isXmlHttpRequest() && !$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $termino = trim((string) $this->params()->fromQuery('q', ''));

        if (strlen($termino) < 2) {
            return new JsonModel(['status' => 'success', 'data' => []]);
        }

        try {
            $estudiantes = $this->examenManager->buscarEstudiantesParaGraduacion($termino);
            return new JsonModel(['status' => 'success', 'data' => $estudiantes]);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
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
        $instrucciones = $this->examenManager->getInstruccionesEntregaFisica($codTipoExamen);

        return new ViewModel([
            'requisitos'    => $requisitos,
            'codTipoExamen' => $codTipoExamen,
            'nombreExamen'  => $nombreExamen,
            'instrucciones' => $instrucciones
        ]);
    }

    /**
     * T-22.1: AJAX para guardar/actualizar requisito
     */
    public function guardarRequisitoAction() {
        try {
            $request = $this->getRequest();
            if (!$request->isPost()) {
                return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
            }

            $codTipoExamen = (int) $request->getPost('cod_tipo_examen');

            // Determinar la fase y cod_paso correcto según el tipo de examen:
            // Tipo 3 (Público General) → fase examen_general (cod_paso del paso 1 = 6)
            // Tipo 1, 2              → fase examen_privado  (cod_paso del paso 1 = 1)
            $fase = ($codTipoExamen === ExamenManager::TIPO_PUBLICO_GENERAL) ? 'examen_general' : 'examen_privado';
            $codPaso = $this->examenManager->getCodPasoPorFaseYOrden($fase, 1) ?? 1;

            $data = [
                'id'                 => $request->getPost('id'),
                'nombre'             => $request->getPost('titulo'),
                'descripcion'        => $request->getPost('descripcion'),
                'cod_tipo_examen'    => $codTipoExamen,
                'cod_paso'           => $codPaso,
                'formatos_permitidos'=> 'pdf,jpg,png',
                'tamano_max_mb'      => 10,
                'tipo_entrega'       => 'digital',
                'obligatorio'        => 1,
                'activo'             => 1
            ];

            // Procesar archivo de apoyo si se envió
            $files = $request->getFiles()->toArray();
            if (!empty($files['archivo_apoyo']) && (int)$files['archivo_apoyo']['error'] === UPLOAD_ERR_OK) {
                $archivo = $files['archivo_apoyo'];
                $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                $permitidos = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                if (!in_array($ext, $permitidos, true)) {
                    return new JsonModel(['status' => 'error', 'message' => 'Formato no permitido. Aceptados: ' . implode(', ', $permitidos)]);
                }
                if ($archivo['size'] > 10 * 1024 * 1024) {
                    return new JsonModel(['status' => 'error', 'message' => 'El archivo supera los 10 MB permitidos.']);
                }

                $directorio = getcwd() . '/data/graduacion/global/requisitos-apoyo/';
                if (!is_dir($directorio)) {
                    if (!@mkdir($directorio, 0755, true)) {
                        error_log('[guardarRequisito] No se pudo crear directorio: ' . $directorio);
                        return new JsonModel(['status' => 'error', 'message' => 'Error del servidor al preparar el directorio de archivos.']);
                    }
                }

                // Verificar permisos de escritura
                if (!is_writable($directorio)) {
                    error_log('[guardarRequisito] Directorio sin permisos de escritura: ' . $directorio);
                    return new JsonModel(['status' => 'error', 'message' => 'Error del servidor: no hay permisos para guardar archivos.']);
                }

                $nombreSeguro = 'req-' . (int)$request->getPost('id', 0) . '-' . md5(uniqid('', true)) . '.' . $ext;
                $rutaDestino = $directorio . $nombreSeguro;

                if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                    error_log('[guardarRequisito] move_uploaded_file falló. tmp_name=' . $archivo['tmp_name'] . ' destino=' . $rutaDestino);
                    return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar el archivo de apoyo en el servidor.']);
                }

                $data['archivo_apoyo'] = $nombreSeguro;
            }

            $id = $this->examenManager->upsertRequisito($data);
            return new JsonModel([
                'status' => 'success',
                'message' => $data['id'] ? 'Requisito actualizado' : 'Requisito creado',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            error_log('[guardarRequisito] Excepción: ' . $e->getMessage() . ' | Traza: ' . $e->getTraceAsString());
            return new JsonModel(['status' => 'error', 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
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
     * T-22.3: AJAX para guardar instrucciones generales de entrega física
     */
    public function guardarInstruccionesAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $codTipoExamen = (int) $request->getPost('cod_tipo_examen');
        $instrucciones = $request->getPost('instrucciones');

        if ($codTipoExamen <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Tipo de examen no válido']);
        }

        try {
            $this->examenManager->guardarInstruccionesEntregaFisica($codTipoExamen, $instrucciones ?: null);
            return new JsonModel(['status' => 'success', 'message' => 'Instrucciones guardadas']);
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
            // Generar nombre único MD5 para el archivo
            $extension     = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
            $nombreBase    = pathinfo($fileData['name'], PATHINFO_FILENAME);
            $nombreMd5     = md5($nombreBase . date('YmdHis') . uniqid());
            $directorioUpload = getcwd() . '/archivos/';

            if (!is_dir($directorioUpload)) {
                mkdir($directorioUpload, 0755, true);
            }

            $rutaDestino = $directorioUpload . $nombreMd5 . '.' . $extension;

            if (!move_uploaded_file($fileData['tmp_name'], $rutaDestino)) {
                return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar el archivo en el servidor.']);
            }

            // 2. Registrar en Base de Datos
            $idDoc = $this->examenManager->guardarDocumentoDb([
                'cod_proceso'    => $codProceso,
                'cod_requisito'  => $codRequisito,
                'archivo_nombre' => $nombreMd5,
                'nombre_original'=> $fileData['name'],
                'mime_type'      => $fileData['type'],
                'tamano_bytes'   => $fileData['size'],
                'checksum_sha256'=> hash_file('sha256', $rutaDestino),
                'extension'      => $extension,
                'subido_por'     => $userId,
            ]);

            // 3. Registrar Historial
            $this->examenManager->registrarHistorial([
                'cod_proceso'  => $codProceso,
                'cod_usuario'  => $userId,
                'tipo_evento'  => 'subida_documento',
                'descripcion'  => 'Documento subido: ' . $fileData['name'],
                'datos_nuevos' => ['cod_documento' => $idDoc, 'archivo_nombre' => $nombreMd5],
            ]);

            return new JsonModel([
                'status'  => 'success',
                'message' => 'Archivo subido correctamente',
                'data'    => [
                    'id'   => $idDoc,
                    'link' => '/ver-documento/' . $nombreMd5,
                    'name' => $fileData['name'],
                ],
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
            $userRolId     = $this->layout()->role->getCode();
            $codProceso    = $params['cod_proceso'];
            $codPasoActual = $params['cod_paso_actual'];
            $requisitos    = $params['requisitos'];

            // Determinar el tipo de examen correcto según la fase actual del proceso.
            // No se confía en el POST porque el tipo puede variar entre fases:
            // - examen_privado → tipo del proceso (1 o 2)
            // - examen_general → siempre tipo 3 (Público General)
            $procesoInfo = $this->examenManager->getProceso((int) $codProceso);
            $faseActual = $procesoInfo['fase_paso_actual'] ?? 'examen_privado';
            $cod_tipo_examen = $this->examenManager->getTipoExamenParaFase(
                (int) ($procesoInfo['tipo_cod_examen'] ?? 0),
                $faseActual
            );

            
            if ($codProceso == null || $codPasoActual == null || !is_array($requisitos) || empty($requisitos)) {
                $response['error'] = 'Datos insuficientes para guardar la revisión';
            } else {
                try {
                    // 1. Guardar/actualizar revisiones en bloque (INSERT si no existe, UPDATE si existe)
                    $this->examenManager->guardarRevisionesBulk($codProceso, $requisitos, $userRolId);

                    // 2. Notificar al estudiante por correo con resumen de revisiones
                    $estudiante = $this->examenManager->getEstudiantePorProceso((int) $codProceso);
                    if ($estudiante && !empty($estudiante['correo'])) {
                        $catalogoRequisitos = $this->examenManager->getRequisitosDocumento(
                            (int) $codPasoActual,
                            (int) $cod_tipo_examen
                        );
                        $mapaNombres = [];
                        foreach ($catalogoRequisitos as $reqCat) {
                            $mapaNombres[(int) $reqCat['cod_requisito']] = $reqCat['nombre'];
                        }

                        $filasHtml = '';
                        foreach ($requisitos as $req) {
                            $estado = $req['estado_evaluacion'] ?? 'pendiente';
                            if ($estado === 'pendiente') {
                                continue;
                            }
                            $codReq = (int) $req['cod_requisito'];
                            $nombreReq = $mapaNombres[$codReq] ?? 'Requisito #' . $codReq;
                            $color = ($estado === 'aprobado') ? '#28a745' : '#dc3545';
                            $estadoTexto = ($estado === 'aprobado') ? 'Aprobado' : 'Rechazado';
                            $filasHtml .= '<tr>'
                                . '<td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($nombreReq) . '</td>'
                                . '<td style="padding:8px;border:1px solid #ddd;text-align:center;font-weight:bold;color:' . $color . ';">' . $estadoTexto . '</td>'
                                . '</tr>';
                        }

                        if ($filasHtml !== '') {
                            $html = '<p>Estimado(a) <strong>' . htmlspecialchars($estudiante['nombre_completo']) . '</strong>,</p>'
                                . '<p>Se ha realizado una revisión de sus documentos en el <strong>Paso 1: Revisión de Papelería</strong>.</p>'
                                . '<p>Resultado:</p>'
                                . '<table style="border-collapse:collapse;width:100%;max-width:600px;">'
                                . '<thead>'
                                . '<tr style="background:#003366;color:#fff;">'
                                . '<th style="padding:10px;border:1px solid #ddd;text-align:left;">Documento</th>'
                                . '<th style="padding:10px;border:1px solid #ddd;text-align:center;">Estado</th>'
                                . '</tr>'
                                . '</thead>'
                                . '<tbody>' . $filasHtml . '</tbody>'
                                . '</tbody>'
                                . '</table>'
                                . '<p><strong>Para más detalles ingrese a la plataforma:</strong> '
                                . '<a href="http://localhost:8080/" style="color:#003366;text-decoration:underline;">Ir a la plataforma</a></p>';

                            $faseLabel = str_replace(['_', 'examen'], [' ', 'Examen'], $faseActual);
                            $faseLabel = ucwords(trim($faseLabel));

                            $enviado = $this->mailManager->sendHtmlMessage(
                                $estudiante['correo'],
                                'Revision de Papeleria - Resultado de documentos revisados - ' . htmlspecialchars($faseLabel),
                                $html
                            );

                            if (!$enviado) {
                                error_log('[ExamenController::guardarRevisionAction] ' .
                                    'Fallo envio de correo a ' . $estudiante['correo'] .
                                    ' para estudiante cod=' . $estudiante['cod_usuario'] .
                                    ' proceso=' . $codProceso);
                            }
                        }
                    }

                    // 3. Verificar si todos los requisitos fueron aprobados
                    $todosAprobados = $this->examenManager->todosRequisitosAceptados($codProceso, $codPasoActual, $cod_tipo_examen);

                    if ($todosAprobados) {
                        // 4. Obtener documentos del paso antes de avanzar
                        $documentosTipoExamen = $this->examenManager->getRequisitosDocumento($codPasoActual, $cod_tipo_examen);

                        // 5. Avanzar al siguiente paso
                        $this->examenManager->avanzarPaso($codProceso, $userRolId);

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

            error_log("DEBUG user id: ".print_r($userId, true));
            error_log("DEBUG params: ".print_r($params, true));

            $codProceso = $params['cod_proceso'];
            $documentos = $params['documentos'];

            if ($codProceso <= 0 || !is_array($documentos)) {
                return new JsonModel(['status' => 'error', 'message' => 'Datos inválidos o incompletos']);
            }

            // Determinar el tipo de examen correcto según la fase actual
            $procesoInfo = $this->examenManager->getProceso((int) $codProceso);
            $faseActual = $procesoInfo['fase_paso_actual'] ?? 'examen_privado';
            $cod_tipo_examen = $this->examenManager->getTipoExamenParaFase(
                (int) ($procesoInfo['tipo_cod_examen'] ?? 0),
                $faseActual
            );

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
                        $this->examenManager->avanzarPaso($codProceso, $userId);

                        // Notificar al estudiante que avanzo de paso
                        $estudiante = $this->examenManager->getEstudiantePorProceso((int) $codProceso);
                        if ($estudiante && !empty($estudiante['correo'])) {
                            $html = '<p>Estimado(a) <strong>' . htmlspecialchars($estudiante['nombre_completo']) . '</strong>,</p>'
                                . '<p>Se le informa que ha completado exitosamente la <strong>entrega de documentación física</strong></p>'
                                . '<p>Su proceso de graduación ha <strong>avanzado al siguiente paso</strong>.</p>'
                                . '<p>Puede ingresar a la plataforma para revisar los pasos a seguir.'
                                . ' <a href="http://localhost:8080/" style="color:#003366;text-decoration:underline;">Ir a la plataforma</a></p>';

                            $faseLabel = str_replace(['_', 'examen'], [' ', 'Examen'], $faseActual);
                            $faseLabel = ucwords(trim($faseLabel));

                            $this->mailManager->sendHtmlMessage(
                                $estudiante['correo'],
                                'Documentacion fisica completada - Proceso avanzado - ' . htmlspecialchars($faseLabel),
                                $html
                            );
                        }

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
        $params = $this->params()->fromPost();
        $userAdminId = $this->layout()->role->getCode();

        // Parametros
        $codProceso = (int) $params['cod_proceso'];
        $terna = $params['terna'] ?? [];
        $programacion = $params['programacion'] ?? null;
        $pasoUrl = (int) $params['pasoUrl'];
        $fase = $params['fase'] ?? 'examen_privado';

        if ($codProceso <= 0 || !is_array($terna)) {
            return new JsonModel(['status' => 'error', 'message' => 'Datos de la terna inválidos']);
        }

        // Validar que los números de colegiado sean solo numéricos
        foreach ($terna as $datos) {
            $colegiado = $datos['colegiado'] ?? '';
            if ($colegiado !== '' && !ctype_digit((string)$colegiado)) {
                return new JsonModel(['status' => 'error', 'message' => 'El número de colegiado solo puede contener dígitos numéricos.']);
            }
        }

        try {
            $statusRes = false;

            if (!empty($terna)){
                // Guardar terna con la fase correspondiente (examen_privado o examen_general)
                $success = $this->examenManager->guardarTerna($codProceso, $terna, $userAdminId, $fase);
                if ($success) {
                    $statusRes = true;
                }
            }

            if (!empty($programacion)) {
                // Validar que la fecha no sea anterior al día actual
                if (!empty($programacion['fecha'])) {
                    $fechaSeleccionada = new \DateTime($programacion['fecha']);
                    $hoy = new \DateTime('today');
                    if ($fechaSeleccionada < $hoy) {
                        return new JsonModel(['status' => 'error', 'message' => 'La fecha del examen no puede ser anterior al día de hoy.']);
                    }
                }

                $successProg = $this->examenManager->guardarProgramacionTerna($codProceso, $programacion, $userAdminId, $fase);
                if ($successProg) {
                    $statusRes = true;
                }
            }            
            
            if ($statusRes) {
                // Obtener la terna guardada y verificar si está completa
                $ternaGuardada = $this->examenManager->getTerna($codProceso, $fase);
                $ternaCompleta = true;
                
                $examinadores = $ternaGuardada['examinadores'] ?? [];

                // Debe haber exactamente 3 examinadores
                if (count($examinadores) !== 3) {
                    $ternaCompleta = false;
                } else {
                    foreach ($examinadores as $examinador) {
                        if (
                            empty($examinador['colegiado']) ||
                            empty($examinador['tipo'])     ||
                            empty($examinador['nombre'])
                        ) {
                            $ternaCompleta = false;
                            break;
                        }
                    }
                }

                if (
                    empty($ternaGuardada['programacion']) ||
                    empty($ternaGuardada['programacion']['fecha']) ||
                    empty($ternaGuardada['programacion']['hora'])
                ) {
                    $ternaCompleta = false;
                }

                // Obtener información del paso actual para obtener su número de orden
                $infoPasoActual = $this->examenManager->getPasoActual($codProceso);
                $numeroOrdenPasoActual = $infoPasoActual["numero_orden"] ?? null;

                if ($ternaCompleta && ($numeroOrdenPasoActual == $pasoUrl)) {
                    $this->examenManager->avanzarPaso($codProceso, $userAdminId);
                    return new JsonModel([
                        'status' => 'success', 
                        'message' => 'Terna completa. El proceso ha avanzado al siguiente paso.',
                        'avanzado' => true
                    ]);
                }

                return new JsonModel([
                    'status' => 'success', 
                    'message' => 'Terna y programación guardadas correctamente',
                    'avanzado' => false
                ]);
            }
            
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar la terna']);

        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Formatea una fecha Y-m-d a español: "Jueves 28 de Mayo del 2026".
     */
    private function formatearFechaEspanol(string $fecha): string
    {
        $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $ts   = strtotime($fecha);
        $diaS = $dias[(int)date('w', $ts)];
        $dia  = (int)date('j', $ts);
        $mes  = $meses[(int)date('n', $ts) - 1];
        $anio = (int)date('Y', $ts);

        return "{$diaS} {$dia} de {$mes} del {$anio}";
    }

    /**
     * Construye el HTML del correo de notificación de examen.
     */
    private function construirCuerpoNotificacion(array $estudiante, array $terna, string $infoExtra, string $fase, string $ubicacion): array
    {
        $correosCc = [];
        $listaExaminadoresHtml = '<ul style="list-style:none; padding-left:0; margin-top:5px;">';
        foreach ($terna['examinadores'] ?? [] as $ex) {
            $listaExaminadoresHtml .= '<li style="margin-bottom:4px;"><strong>' . htmlspecialchars($ex['nombre'] ?? '') . '</strong></li>';
            if (!empty($ex['correo'])) {
                $correosCc[] = $ex['correo'];
            }
        }
        $listaExaminadoresHtml .= '</ul>';

        $fechaExamen = !empty($terna['programacion']['fecha'])
            ? $this->formatearFechaEspanol($terna['programacion']['fecha'])
            : 'Por definir';
        $horaExamen = !empty($terna['programacion']['hora'])
            ? date('g:i A', strtotime($terna['programacion']['hora']))
            : 'Por definir';

        $faseLabel = str_replace(['_', 'examen'], [' ', 'Examen'], $fase);
        $faseLabel = ucwords(trim($faseLabel));

        $html = '<p>Estimado(a) graduando:</p>'
              . '<p>Reciba un cordial saludo de parte de la administración de Escuela de Estudios de Posgrado, le extendemos desde ya una felicitación por haber llegado a esta etapa.</p>';

        $html .= '<p>Adjunto encontrará información de interés relacionada con la sustentación de su ' . htmlspecialchars($faseLabel) . '. '
               . 'Asimismo, le confirmo que su examen se realizará el <strong>' . $fechaExamen . '</strong>, '
               . 'a las <strong>' . $horaExamen . '</strong>';
        if (!empty($ubicacion)) {
            $html .= ', en <strong>' . htmlspecialchars($ubicacion) . '</strong>';
        }
        $html .= '.</p>';

        if (!empty($infoExtra)) {
            $html .= '<p>' . nl2br(htmlspecialchars($infoExtra)) . '</p>';
        }

        $html .= '<p>Para la evaluación de su examen han sido designados los siguientes profesionales:</p>' . $listaExaminadoresHtml
               . '<p>Se le solicita proporcionar una copia de su proyecto a cada uno de los examinadores a la mayor brevedad posible, '
               . 'con el fin de que puedan revisarlo previamente y preparar las consultas correspondientes. '
               . 'Preferentemente, deberá entregarse una copia impresa; sin embargo, también puede compartir una versión digital '
               . 'con anticipación y entregar la copia física el día del examen.</p>'
               . '<p>Finalmente, para su comodidad durante la jornada, se recomienda llevar agua para consumo personal. '
               . 'Si desea proporcionar algún refrigerio adicional, queda a su consideración.</p>'
               . '<p>Saludos cordiales y éxito</p>';

        return [
            'html'      => $html,
            'asunto'    => 'Notificacion Examen de Graduacion - ' . htmlspecialchars($faseLabel),
            'correosCc' => $correosCc,
        ];
    }

    /**
     * Notifica al estudiante cerrando el paso 4 (último) del proceso.
     * Retorna JSON para manejo vía AJAX.
     */
    public function notificarEstudianteAction() {
        // Obtener el ID del usuario de forma segura
        $userId = null;
        if ($this->layout()->role !== null) {
            $userId = $this->layout()->role->getUserCode();
        } else {
            $userId = $this->identity();
        }
        
        if ($userId === null) {
            return new JsonModel(['success' => false, 'message' => 'Error: No se pudo identificar al usuario']);
        }
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $codProceso = (int) $request->getPost('cod_proceso');
        $infoExtra  = trim((string) $request->getPost('info_extra', ''));
        $ubicacion  = trim((string) $request->getPost('ubicacion', ''));

        if ($codProceso <= 0) {
            return new JsonModel(['success' => false, 'message' => 'Identificador de proceso inválido']);
        }

        try {
            // Obtener datos ANTES de avanzar el paso, ya que avanzarPaso cambia la fase
            $procesoInfo = $this->examenManager->getProceso($codProceso);
            $faseActual  = $procesoInfo['fase_paso_actual'] ?? 'examen_privado';
            $estudiante  = $this->examenManager->getEstudiantePorProceso($codProceso);
            $terna       = $this->examenManager->getTerna($codProceso, $faseActual);

            $advanced = $this->examenManager->avanzarPaso($codProceso, $userId);

            if ($advanced) {

                if ($estudiante && !empty($estudiante['correo'])) {
                    $cuerpo = $this->construirCuerpoNotificacion($estudiante, $terna, $infoExtra, $faseActual, $ubicacion);

                    error_log('[Notificar] CC examinadores: ' . implode(', ', $cuerpo['correosCc']));
                    $this->mailManager->sendHtmlMessage(
                        $estudiante['correo'],
                        $cuerpo['asunto'],
                        $cuerpo['html'],
                        [],
                        $cuerpo['correosCc']
                    );
                }

                return new JsonModel(['success' => true, 'message' => 'Estudiante notificado y proceso cerrado correctamente']);
            }

            return new JsonModel(['success' => false, 'message' => 'No se pudo cerrar el paso']);

        } catch (\Exception $e) {
            return new JsonModel(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Vista previa del correo de notificación. No envía el correo ni avanza el paso.
     */
    public function previewNotificacionAction() {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $codProceso = (int) $request->getPost('cod_proceso');
        $infoExtra  = trim((string) $request->getPost('info_extra', ''));
        $ubicacion  = trim((string) $request->getPost('ubicacion', ''));

        if ($codProceso <= 0) {
            return new JsonModel(['success' => false, 'message' => 'Identificador de proceso inválido']);
        }

        try {
            $procesoInfo = $this->examenManager->getProceso($codProceso);
            $faseActual  = $procesoInfo['fase_paso_actual'] ?? 'examen_privado';
            $estudiante  = $this->examenManager->getEstudiantePorProceso($codProceso);
            $terna       = $this->examenManager->getTerna($codProceso, $faseActual);

            if (!$estudiante) {
                return new JsonModel(['success' => false, 'message' => 'No se encontró información del estudiante']);
            }

            $cuerpo = $this->construirCuerpoNotificacion($estudiante, $terna, $infoExtra, $faseActual, $ubicacion);

            return new JsonModel([
                'success' => true,
                'html'    => $cuerpo['html'],
                'asunto'  => $cuerpo['asunto'],
            ]);
        } catch (\Exception $e) {
            return new JsonModel(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
            $success = $this->examenManager->avanzarPaso($codProceso, $userId);
            
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

        // 2. Obtener datos reales del estudiante y proceso
        $estudiante = $this->examenManager->getEstudiantePorProceso($idProceso);
        $proceso = $this->examenManager->getProceso($idProceso);

        // 3. Obtener requisitos y documentos ya subidos para este proceso
        //    Determinar el tipo de examen según la fase actual
        $faseActual = $proceso['fase_paso_actual'] ?? 'examen_privado';
        $codTipoExamenProceso = (int) ($proceso['tipo_cod_examen'] ?? 0);
        $codTipoExamenFase = $this->examenManager->getTipoExamenParaFase($codTipoExamenProceso, $faseActual);
        $requisitos = $this->examenManager->getRequisitosDocumento($pasoActual['cod_paso_actual'], $codTipoExamenFase);
        $documentosSubidos = $this->examenManager->getDocumentosProceso($idProceso);

        // 4. Si el paso requiere terna (ej: paso 4), cargarla
        $terna = [];
        if ($pasoActual['numero_orden'] >= 4) {
            $terna = $this->examenManager->getTerna($idProceso, $faseActual);
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
            
            // Permitir navegar por pasos vía query string, o cargar el actual por defecto
            $pasoEstudiante = (int) ($proceso['numero_orden'] ?? 1);
            $paso = (int) $this->params()->fromQuery('paso', $pasoEstudiante);

            // Bloquear acceso a pasos que el estudiante aún no ha alcanzado
            if ($paso > $pasoEstudiante) {
                $paso = $pasoEstudiante;
            }
            
            // Obtener fechas de los estados
            $fechas = $this->examenManager->getFechasPasosCompletado($idProceso);

            // Determinar la fase actual del proceso
            $faseActual = $proceso['fase_paso_actual'] ?? 'examen_privado';

            // Si la fase actual es examen_general, actualizar el nombre del tipo de examen
            if ($faseActual === 'examen_general') {
                $proceso['tipo_examen'] = 'General';
            }

            // Etiqueta legible de la fase para el encabezado de las vistas
            $etiquetaFase = ($faseActual === 'examen_general')
                ? 'Examen General (Público)'
                : 'Examen Privado';

            $estados = [
                1 => [
                    'titulo'    => 'Revisión de Papelería',
                    'subtitulo' => 'Sin fecha',
                    'partial'   => 'eep/examen/partial/paso1-papeleria',
                ],
                2 => [
                    'titulo'    => 'Entrega de Papelería',
                    'subtitulo' => 'Sin fecha',
                    'partial'   => 'eep/examen/partial/paso2-documentacion',
                ],
            ];

            // Examen privado tiene 4 pasos; examen general solo 2 pasos
            if ($faseActual !== 'examen_general') {
                $estados[3] = [
                    'titulo'    => 'Terna Examinadora',
                    'subtitulo' => 'Sin fecha',
                    'partial'   => 'eep/examen/partial/paso3-terna',
                ];
                $estados[4] = [
                    'titulo'    => 'Notificación',
                    'subtitulo' => 'Sin fecha',
                    'partial'   => 'eep/examen/partial/paso4-notificacion',
                ];
            }

            if (!empty($fechas)) {
                foreach ($estados as $num => &$e) {
                    $key = $faseActual . '_' . $num;
                    $e['subtitulo'] = isset($fechas[$key]) ? date('d/m/Y', strtotime($fechas[$key])) : 'Sin fecha';
                }
                unset($e);
            }

            // Determinar el cod_tipo_examen correcto según la fase:
            // - examen_privado  → usa el tipo del proceso (1=Privado General, 2=Privado Gerencia)
            // - examen_general  → siempre tipo 3 (Público General)
            $codTipoExamenProceso = (int) $proceso['tipo_cod_examen'];
            $codTipoExamenFase = $this->examenManager->getTipoExamenParaFase($codTipoExamenProceso, $faseActual);

            // Obtener los cod_paso correctos para la fase actual (paso 1 y 2)
            // Ejemplo: examen_privado paso 1 = cod_paso 1, examen_general paso 1 = cod_paso 6
            $codPasoPaso1 = $this->examenManager->getCodPasoPorFaseYOrden($faseActual, 1);
            $codPasoPaso2 = $this->examenManager->getCodPasoPorFaseYOrden($faseActual, 2);

            // T-25: Cargar documentos y requisitos para el paso 1 de la fase actual
            $documentos = $this->examenManager->getDocumentosYRequisitos($idProceso, $codPasoPaso1, $codTipoExamenFase);

            // T-17: Cargar checklist de documentos físicos para el paso 2 de la fase actual
            $docsFisicos = $this->examenManager->getDocumentosFisicos($idProceso, $codTipoExamenFase);

            // T-18: Cargar terna de examinadores y fecha/hora según la fase actual
            $terna = $this->examenManager->getTerna($idProceso, $faseActual);

            // Instrucciones generales para entrega física
            $instruccionesEntrega = $this->examenManager->getInstruccionesEntregaFisica($codTipoExamenFase);

            $vm = new ViewModel([
                'proceso'               => $proceso,
                'estudiante'            => $estudiante,
                'paso'                  => $paso,
                'fase'                  => $faseActual,
                'etiquetaFase'          => $etiquetaFase,
                'estados'               => $estados,
                'docsDigitales'         => $documentos,
                'docsFisicos'           => $docsFisicos,
                'terna'                 => $terna,
                'instruccionesEntrega'  => $instruccionesEntrega,
            ]);
            $vm->setTemplate('eep/examen/revisarpapeleria');
            return $vm;
        }

        // Listado de solicitudes (Paginado)
        $pagina        = (int) $this->params()->fromQuery('page', 1);
        $estado        = $this->params()->fromQuery('estado', null);
        $carne         = $this->params()->fromQuery('carne', null);
        $codTipoExamen = (int) $this->params()->fromQuery('cod_tipo_examen', 0) ?: null;

        $procesos = $this->examenManager->getProcesos([
            'pagina'          => $pagina,
            'limite'          => 10,
            'estado'          => $estado,
            'cod_tipo_examen' => $codTipoExamen,
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
                'estado'          => $estado,
                'carne'           => $carne,
                'cod_tipo_examen' => $codTipoExamen,
            ]
        ]);
    }

    // 4. CARTA DE EXAMINADORES --------------------------------
    /**
     * Lista los procesos que se encuentran en el paso 5 (Carta de Examinadores).
     * Desde aquí el director/coordinador accede al detalle de cada proceso
     * a través de examen/ver-carta/{id}.
     */
    public function cartaExaminadoresAction()
    {
        $pagina        = (int) $this->params()->fromQuery('page', 1);
        $codTipoExamen = (int) $this->params()->fromQuery('cod_tipo_examen', 0) ?: null;

        $resultado = $this->examenManager->getProcesos([
            'pagina'          => $pagina,
            'limite'          => 15,
            'numero_paso'     => 5,
            'cod_tipo_examen' => $codTipoExamen,
        ]);

        return new ViewModel([
            'procesos'   => $resultado['procesos'],
            'paginacion' => [
                'total'         => $resultado['total'],
                'pagina'        => $resultado['pagina'],
                'limite'        => $resultado['limite'],
                'paginas_total' => $resultado['paginas_total'],
            ],
            'filtros' => [
                'cod_tipo_examen' => $codTipoExamen,
            ],
        ]);
    }

    /**
     * Vista de detalle del paso 5 para un proceso específico (staff).
     * Ruta: /examen/ver-carta/{id}
     */
    public function verCartaAction()
    {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            return $this->redirect()->toRoute('examen', ['action' => 'carta-examinadores']);
        }

        $proceso = $this->examenManager->getProceso($idProceso);
        if (!$proceso) {
            $this->flashMessenger()->addErrorMessage('Proceso no encontrado.');
            return $this->redirect()->toRoute('examen', ['action' => 'carta-examinadores']);
        }

        $this->cartaManager->iniciarPasoCarta($idProceso);

        return new ViewModel([
            'proceso'     => $proceso,
            'cicloActual' => $this->cartaManager->getCicloActual($idProceso),
            'evidencias'  => $this->cartaManager->getEvidenciasPlanas($idProceso),
            'carta'       => $this->cartaManager->getCartaPorProceso($idProceso),
        ]);
    }

    /**
     * Vista de notificación grupal para acto de graduación (examen general).
     * Permite seleccionar múltiples estudiantes y enviar correo masivo.
     */
    public function notificacionGrupalAction()
    {
        $estudiantes = $this->examenManager->getProcesosGeneralCompletados();
        return new ViewModel([
            'estudiantes' => $estudiantes,
        ]);
    }

    /**
     * Construye el HTML del correo de notificación grupal (acto de graduación).
     */
    private function construirCuerpoNotificacionGrupal(string $fecha, string $hora, string $lugar, string $infoExtra): string
    {
        $html = '<p>Estimado(a) graduando:</p>'
              . '<p>Reciba un cordial saludo de parte de la administración de Escuela de Estudios de Posgrado, le extendemos desde ya una felicitación por haber llegado a esta etapa.</p>'
              . '<p><strong>FECHA:</strong> ' . htmlspecialchars($fecha) . '</p>'
              . '<p><strong>HORA:</strong> ' . htmlspecialchars($hora) . '</p>'
              . '<p><strong>LUGAR:</strong> ' . htmlspecialchars($lugar) . '</p>';
        if (!empty($infoExtra)) {
            $html .= '<p>' . nl2br(htmlspecialchars($infoExtra)) . '</p>';
        }
        $html .= '<p>Saludos cordiales.</p>';
        return $html;
    }

    /**
     * Vista previa del correo de notificación grupal. No envía correos.
     */
    public function previewNotificacionGrupalAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $fecha = trim((string) $request->getPost('fecha', ''));
        $hora  = trim((string) $request->getPost('hora', ''));
        $lugar = trim((string) $request->getPost('lugar', ''));
        $infoExtra = trim((string) $request->getPost('info_extra', ''));

        if (empty($fecha) || empty($hora) || empty($lugar)) {
            return new JsonModel(['success' => false, 'message' => 'Fecha, hora y lugar son obligatorios.']);
        }

        try {
            $fechaSel = new \DateTime($fecha);
            $fechaFormateada = $fechaSel->format('d/m/Y');
            $horaFormateada = date('g:i A', strtotime($hora));

            $html = $this->construirCuerpoNotificacionGrupal($fechaFormateada, $horaFormateada, $lugar, $infoExtra);

            return new JsonModel([
                'success' => true,
                'html'    => $html,
                'asunto'  => 'Acto de Graduación - Examen General',
            ]);
        } catch (\Exception $e) {
            return new JsonModel(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: envía notificación grupal a los estudiantes seleccionados.
     */
    public function enviarNotificacionGrupalAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['success' => false, 'message' => 'Método no permitido']);
        }

        $userId = $this->layout()->role->getUserCode();
        $codProcesos = $request->getPost('cod_procesos', []);
        $fecha = trim((string) $request->getPost('fecha', ''));
        $hora = trim((string) $request->getPost('hora', ''));
        $lugar = trim((string) $request->getPost('lugar', ''));
        $infoExtra = trim((string) $request->getPost('info_extra', ''));
        $correosCcRaw = trim((string) $request->getPost('correos_cc', ''));

        if (empty($codProcesos) || !is_array($codProcesos)) {
            return new JsonModel(['success' => false, 'message' => 'Debe seleccionar al menos un estudiante.']);
        }
        if (empty($fecha) || empty($hora) || empty($lugar)) {
            return new JsonModel(['success' => false, 'message' => 'Fecha, hora y lugar son obligatorios.']);
        }

        // Validar fecha (no anterior a hoy)
        $fechaSel = new \DateTime($fecha);
        $hoy = new \DateTime('today');
        if ($fechaSel < $hoy) {
            return new JsonModel(['success' => false, 'message' => 'La fecha no puede ser anterior al día de hoy.']);
        }

        // Parsear correos CC
        $correosCc = [];
        if ($correosCcRaw !== '') {
            $partes = preg_split('/[,;\n]+/', $correosCcRaw);
            foreach ($partes as $cc) {
                $cc = trim($cc);
                if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $correosCc[] = $cc;
                }
            }
        }

        $fechaFormateada = $fechaSel->format('d/m/Y');
        $horaFormateada = date('g:i A', strtotime($hora));

        $enviados = 0;
        $fallidos = 0;
        $errores = [];

        foreach ($codProcesos as $codProceso) {
            $codProceso = (int) $codProceso;
            if ($codProceso <= 0) continue;

            $estudiante = $this->examenManager->getEstudiantePorProceso($codProceso);
            if (empty($estudiante['correo'])) {
                $fallidos++;
                $errores[] = 'Proceso ' . $codProceso . ': sin correo del estudiante.';
                continue;
            }

            // Guardar fecha/hora en el proceso
            $this->examenManager->guardarProgramacionTerna($codProceso, [
                'fecha' => $fecha,
                'hora'  => $hora,
            ], $userId, 'examen_general');

            $html = $this->construirCuerpoNotificacionGrupal($fechaFormateada, $horaFormateada, $lugar, $infoExtra);

            try {
                $this->mailManager->sendHtmlMessage(
                    $estudiante['correo'],
                    'Acto de Graduación - Examen General',
                    $html,
                    [],
                    $correosCc
                );
                $enviados++;
            } catch (\Exception $e) {
                $fallidos++;
                $errores[] = 'Proceso ' . $codProceso . ': ' . $e->getMessage();
            }
        }

        return new JsonModel([
            'success'   => true,
            'enviados'  => $enviados,
            'fallidos'  => $fallidos,
            'errores'   => $errores,
            'message'   => "Se notificaron {$enviados} estudiantes correctamente." . ($fallidos > 0 ? " ({$fallidos} fallidos)" : ''),
        ]);
    }

    // ================================================================
    // MATRIZ DE EVALUACIÓN DEL EXAMEN PRIVADO
    // ================================================================

    /**
     * Listado de procesos de examen privado listos para evaluación
     * (notificación completada, terna asignada).
     */
    public function evaluacionPrivadoAction()
    {
        $pagina = (int) $this->params()->fromQuery('page', 1);
        $resultado = $this->examenManager->getProcesosEvaluables([
            'pagina' => $pagina,
            'limite' => 15,
        ]);

        return new ViewModel([
            'procesos' => $resultado['procesos'],
            'paginacion' => [
                'total'         => $resultado['total'],
                'pagina'        => $resultado['pagina'],
                'limite'        => $resultado['limite'],
                'paginas_total' => $resultado['paginas_total'],
            ],
        ]);
    }

    /**
     * Formulario de matriz de evaluación para un proceso específico.
     * Permite llenar la evaluación de cada examinador (1, 2, 3).
     */
    public function matrizEvaluacionAction()
    {
        $codProceso = (int) $this->params()->fromRoute('id', 0);
        if ($codProceso <= 0) {
            return $this->redirect()->toRoute('examen', ['action' => 'evaluacion-privado']);
        }

        $proceso = $this->examenManager->getProceso($codProceso);
        if (!$proceso) {
            $this->flashMessenger()->addErrorMessage('Proceso no encontrado.');
            return $this->redirect()->toRoute('examen', ['action' => 'evaluacion-privado']);
        }

        $estudiante = $this->examenManager->getEstudiantePorProceso($codProceso);
        $terna = $this->examenManager->getTerna($codProceso, 'examen_privado');
        $temaTesis = $this->examenManager->getTemaTesis($codProceso);

        // Detectar matriz según cod_carrera del estudiante
        $codMatrizTipo = null;
        $preguntas = [];
        $codCarrera = (int) ($estudiante['cod_carrera'] ?? 0);
        if ($codCarrera > 0) {
            $codMatrizTipo = $this->examenManager->getMatrizTipoPorCarrera($codCarrera);
            if ($codMatrizTipo) {
                $preguntas = $this->examenManager->getMatrizPreguntas($codMatrizTipo);
            }
        }

        // Cargar evaluaciones existentes por examinador
        $evaluaciones = [];
        for ($i = 1; $i <= 3; $i++) {
            $eval = $this->examenManager->getMatrizEvaluacion($codProceso, $i);
            $evaluaciones[$i] = $eval;
        }

        return new ViewModel([
            'proceso'       => $proceso,
            'estudiante'    => $estudiante,
            'terna'         => $terna,
            'temaTesis'     => $temaTesis,
            'codMatrizTipo' => $codMatrizTipo,
            'preguntas'     => $preguntas,
            'evaluaciones'  => $evaluaciones,
        ]);
    }

    /**
     * AJAX: guarda el tema de tesis y/o las respuestas de la matriz de evaluación.
     */
    public function guardarMatrizAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $userId = $this->layout()->role->getCode();
        $codProceso = (int) $request->getPost('cod_proceso', 0);
        $guardarTema = (int) $request->getPost('guardar_tema', 0);
        $tema = trim((string) $request->getPost('tema_tesis', ''));
        $posExaminador = (int) $request->getPost('posicion_examinador', 0);
        $observaciones = trim((string) $request->getPost('observaciones_generales', '')) ?: null;
        $respuestasRaw = $request->getPost('respuestas', []);

        if ($codProceso <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Proceso inválido']);
        }

        try {
            if ($guardarTema === 1) {
                $this->examenManager->guardarTemaTesis($codProceso, $tema !== '' ? $tema : null);
                return new JsonModel([
                    'status'  => 'success',
                    'message' => 'Tema de tesis guardado correctamente',
                ]);
            }

            if ($posExaminador < 1 || $posExaminador > 3) {
                return new JsonModel(['status' => 'error', 'message' => 'Examinador inválido']);
            }

            $respuestas = [];
            if (is_array($respuestasRaw)) {
                foreach ($respuestasRaw as $r) {
                    $respuestas[] = [
                        'cod_pregunta'    => (int) ($r['cod_pregunta'] ?? 0),
                        'tipo_campo'      => $r['tipo_campo'] ?? 'numero',
                        'punteo'          => isset($r['punteo']) && $r['punteo'] !== '' ? (float) $r['punteo'] : null,
                        'respuesta_texto' => isset($r['respuesta_texto']) && $r['respuesta_texto'] !== '' ? $r['respuesta_texto'] : null,
                    ];
                }
            }

            $this->examenManager->guardarMatrizEvaluacion([
                'cod_proceso'           => $codProceso,
                'posicion_examinador'   => $posExaminador,
                'evaluado_por'          => $userId,
                'observaciones_generales' => $observaciones,
                'respuestas'            => $respuestas,
            ]);

            return new JsonModel([
                'status'  => 'success',
                'message' => "Evaluación del Examinador {$posExaminador} guardada correctamente",
            ]);
        } catch (\Exception $e) {
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Vista de resumen de las 3 evaluaciones de un proceso.
     */
    public function verMatrizAction()
    {
        $codProceso = (int) $this->params()->fromRoute('id', 0);
        if ($codProceso <= 0) {
            return $this->redirect()->toRoute('examen', ['action' => 'evaluacion-privado']);
        }

        $proceso = $this->examenManager->getProceso($codProceso);
        if (!$proceso) {
            $this->flashMessenger()->addErrorMessage('Proceso no encontrado.');
            return $this->redirect()->toRoute('examen', ['action' => 'evaluacion-privado']);
        }

        $estudiante = $this->examenManager->getEstudiantePorProceso($codProceso);
        $terna = $this->examenManager->getTerna($codProceso, 'examen_privado');
        $temaTesis = $this->examenManager->getTemaTesis($codProceso);

        $codMatrizTipo = null;
        $preguntas = [];
        $codCarrera = (int) ($estudiante['cod_carrera'] ?? 0);
        if ($codCarrera > 0) {
            $codMatrizTipo = $this->examenManager->getMatrizTipoPorCarrera($codCarrera);
            if ($codMatrizTipo) {
                $preguntas = $this->examenManager->getMatrizPreguntas($codMatrizTipo);
            }
        }

        $evaluaciones = $this->examenManager->getResumenEvaluaciones($codProceso);

        return new ViewModel([
            'proceso'       => $proceso,
            'estudiante'    => $estudiante,
            'terna'         => $terna,
            'temaTesis'     => $temaTesis,
            'preguntas'     => $preguntas,
            'evaluaciones'  => $evaluaciones,
        ]);
    }

}

