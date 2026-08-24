<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Authentication\AuthenticationService;
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
     * @var AuthenticationService
     */
    private $authService;

    /**
     * @var array
     */
    private $config;

    /**
     * Constructor: inyecta los managers de los distintos pasos del módulo
     * de graduación (papelería 1-4, carta examinadores 5, autorización 6).
     */
    public function __construct(
        ExamenManager $examenManager,
        CartaExaminadoresManager $cartaManager,
        AutorizacionImpresionManager $autorizacionManager,
        UserManager $userManager,
        MailManager $mailManager,
        AuthenticationService $authService,
        array $config = []
    ) {
        $this->examenManager       = $examenManager;
        $this->cartaManager        = $cartaManager;
        $this->autorizacionManager = $autorizacionManager;
        $this->userManager         = $userManager;
        $this->mailManager         = $mailManager;
        $this->authService         = $authService;
        $this->config              = $config;
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
        $busqueda = $this->params()->fromQuery('busqueda', '') ?: null;
        $instrucciones = $this->autorizacionManager->getInstruccionesAmbas();
        $this->pg()->log('Se consultó el listado de procesos en fase de Autorización de Impresión.', LM::SUCCESS, LM::VIEW);
        return new ViewModel([
            'procesos'            => $this->autorizacionManager->getProcesosEnFase($busqueda),
            'instruccionesParte1' => $instrucciones['parte1'],
            'instruccionesParte2' => $instrucciones['parte2'],
            'documentos'          => $this->autorizacionManager->getDocumentosSoporte(false),
            'profesionales'       => $this->autorizacionManager->getProfesionales(false),
            'cartas'              => $this->autorizacionManager->getCartasDescarga(false),
            'miembros'            => $this->autorizacionManager->getMiembrosJunta(false),
            'formatosDoc'         => $this->autorizacionManager->getFormatosDocumentoSoporte(),
            'formatosCarta'       => $this->autorizacionManager->getFormatosCartaDescarga(),
            'tamanoMaxMb'         => $this->autorizacionManager->getTamanoMaxMb(),
            'filtros'             => ['busqueda' => $busqueda],
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
        $instrucciones = $this->autorizacionManager->getInstruccionesAmbas();

        return new ViewModel([
            'proceso'             => $proceso,
            'estudiante'          => $estudiante,
            'estado'              => $estado,
            'profesional'         => $profesional,
            'enFase'              => $this->autorizacionManager->procesoEstaEnFase($codProceso),
            'instruccionesParte1' => $instrucciones['parte1'],
            'instruccionesParte2' => $instrucciones['parte2'],
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
            $this->pg()->log('Se actualizaron las instrucciones de la ' . $nombreParte . ' de Autorización de Impresión.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel(['status' => 'success', 'message' => "Instrucciones de {$nombreParte} guardadas correctamente"]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al actualizar las instrucciones de la Parte ' . $parte . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
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
            $this->pg()->log('Se eliminó el documento de soporte código ' . $cod . '.', LM::SUCCESS, LM::DELETE);
            return new JsonModel(['status' => 'success', 'message' => 'Documento eliminado']);
        } catch (\Exception $e) {
            $this->pg()->log('Error al eliminar el documento de soporte código ' . $cod . ': ' . $e->getMessage(), LM::FAILURE, LM::DELETE);
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
            $this->pg()->log($data['cod_profesional'] ? 'Se actualizó el profesional calificado código ' . $id . '.' : 'Se creó el profesional calificado código ' . $id . '.', LM::SUCCESS, $data['cod_profesional'] ? LM::UPDATE : LM::CREATE);
            return new JsonModel([
                'status'  => 'success',
                'message' => $data['cod_profesional'] ? 'Profesional actualizado' : 'Profesional creado',
                'id'      => $id,
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al ' . ($data['cod_profesional'] ? 'actualizar' : 'crear') . ' el profesional calificado: ' . $e->getMessage(), LM::FAILURE, $data['cod_profesional'] ? LM::UPDATE : LM::CREATE);
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
            $this->pg()->log('Se desactivó el profesional calificado código ' . $cod . '.', LM::SUCCESS, LM::DELETE);
            return new JsonModel(['status' => 'success', 'message' => 'Profesional desactivado']);
        } catch (\Exception $e) {
            $this->pg()->log('Error al desactivar el profesional calificado código ' . $cod . ': ' . $e->getMessage(), LM::FAILURE, LM::DELETE);
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
            $this->pg()->log('Se eliminó la carta genérica de descarga código ' . $cod . '.', LM::SUCCESS, LM::DELETE);
            return new JsonModel(['status' => 'success', 'message' => 'Carta eliminada']);
        } catch (\Exception $e) {
            $this->pg()->log('Error al eliminar la carta genérica de descarga código ' . $cod . ': ' . $e->getMessage(), LM::FAILURE, LM::DELETE);
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
            $this->pg()->log($data['cod_miembro'] ? 'Se actualizó el miembro de junta directiva código ' . $id . '.' : 'Se creó el miembro de junta directiva código ' . $id . '.', LM::SUCCESS, $data['cod_miembro'] ? LM::UPDATE : LM::CREATE);
            return new JsonModel([
                'status'  => 'success',
                'message' => $data['cod_miembro'] ? 'Miembro actualizado' : 'Miembro creado',
                'id'      => $id,
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al ' . ($data['cod_miembro'] ? 'actualizar' : 'crear') . ' el miembro de junta directiva: ' . $e->getMessage(), LM::FAILURE, $data['cod_miembro'] ? LM::UPDATE : LM::CREATE);
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
            $this->pg()->log('Se eliminó el miembro de junta directiva código ' . $cod . '.', LM::SUCCESS, LM::DELETE);
            return new JsonModel(['status' => 'success', 'message' => 'Miembro eliminado']);
        } catch (\Exception $e) {
            $this->pg()->log('Error al eliminar el miembro de junta directiva código ' . $cod . ': ' . $e->getMessage(), LM::FAILURE, LM::DELETE);
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
            $estudianteAut = $this->examenManager->getEstudiantePorProceso($codProceso);
            $this->pg()->log('Se aprobó la revisión presencial del estudiante ' . $estudianteAut['nombre_completo'] . ' y se avanzó a la fase de Examen General.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel([
                'status'  => 'success',
                'message' => 'Revisión presencial aprobada. El proceso avanzó a la fase de Examen General.',
                'data'    => $res,
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al aprobar la revisión presencial del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
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
            $this->pg()->log('Error al subir el archivo global tipo ' . $tipo . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return new JsonModel(['status' => 'error', 'message' => 'Error al registrar: ' . $e->getMessage()]);
        }

        $this->pg()->log('Se subió exitosamente el archivo global tipo ' . $tipo . ' con identificador ' . $id . '.', LM::SUCCESS, LM::CREATE);
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

                $this->pg()->log('Se inició el proceso de graduación para el estudiante ' . $estudiante->getNombreCompleto() . ' con examen ' . $nombreTipoExamenTexto . '.', LM::SUCCESS, LM::CREATE);
                $this->flashMessenger()->addSuccessMessage('Proceso de graduacion iniciado correctamente.');
                return $this->redirect()->toRoute('examen', [
                    'action' => 'solicitudes',
                    'id'     => $idProceso
                ], ['query' => ['paso' => 1, 'cod_tipo_examen' => $codTipoExamen]]);
            } catch (\Exception $e) {
                $this->pg()->log('Error al iniciar el proceso de graduación para el estudiante ' . $codUsuario . ' con examen ' . $nombreTipoExamenTexto . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
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
            $this->pg()->log('Se realizó una búsqueda de estudiantes con el término "' . $termino . '".', LM::SUCCESS, LM::READ);
            return new JsonModel(['status' => 'success', 'data' => $estudiantes]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al buscar estudiantes con el término "' . $termino . '": ' . $e->getMessage(), LM::FAILURE, LM::READ);
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

        $this->pg()->log('Se consultó la gestión de requisitos de papelería para el tipo de examen ' . $nombreExamen . '.', LM::SUCCESS, LM::VIEW);

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
            $this->pg()->log(($data['id'] ? 'Se actualizó' : 'Se creó') . ' el requisito de papelería código ' . $id . ' para el tipo de examen ' . $nombreExamen . '.', LM::SUCCESS, LM::CREATE);
            return new JsonModel([
                'status' => 'success',
                'message' => $data['id'] ? 'Requisito actualizado' : 'Requisito creado',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            error_log('[guardarRequisito] Excepción: ' . $e->getMessage() . ' | Traza: ' . $e->getTraceAsString());
            $this->pg()->log('Error al guardar el requisito de papelería para el tipo de examen ' . $nombreExamen . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
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
            $this->pg()->log('Se eliminó el requisito de papelería código ' . $id . '.', LM::SUCCESS, LM::DELETE);
            return new JsonModel(['status' => 'success', 'message' => 'Requisito eliminado']);
        } catch (\Exception $e) {
            $this->pg()->log('Error al eliminar el requisito de papelería código ' . $id . ': ' . $e->getMessage(), LM::FAILURE, LM::DELETE);
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
            $nombreTipoExamenInst = $this->examenManager->getNombreTipoExamen($codTipoExamen);
            $this->pg()->log('Se actualizaron las instrucciones de entrega física para el tipo de examen ' . $nombreTipoExamenInst . '.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel(['status' => 'success', 'message' => 'Instrucciones guardadas']);
        } catch (\Exception $e) {
            $this->pg()->log('Error al guardar las instrucciones de entrega física para el tipo de examen ' . $codTipoExamen . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
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

            $this->pg()->log('Se subió el documento del requisito para revisión de papelería.', LM::SUCCESS, LM::CREATE);

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
            $this->pg()->log('Error al subir el documento del requisito ' . $codRequisito . ' del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
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
                                htmlspecialchars($faseLabel) . ' - Revisión de Papelería',
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

        $statusLog = !empty($response['status']) ? LM::SUCCESS : LM::FAILURE;
        $mensajeRevision = !empty($response['avanzado'])
            ? 'El proceso ' . ($codProceso ?? '??') . ' avanzó al siguiente paso tras aprobar toda la papelería.'
            : 'Se guardó la revisión de documentos del proceso ' . ($codProceso ?? '??') . '.';
        $this->pg()->log($mensajeRevision, $statusLog, LM::UPDATE);

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
                                htmlspecialchars($faseLabel) . ' - Documentación física completada',
                                $html
                            );
                        }

                        $this->pg()->log('Se completó la documentación física del estudiante ' . $procesoInfo['nombres'] . ' ' . $procesoInfo['apellidos'] . ' y se avanzó al siguiente paso.', LM::SUCCESS, LM::UPDATE);
                        return new JsonModel([
                            'status' => 'success',
                            'message' => 'Documentación física completada. El proceso ha avanzado al siguiente paso.',
                            'avanzado' => true
                        ]);
                    }

                    $this->pg()->log('Se actualizó la recepción de documentación física del estudiante ' . $procesoInfo['nombres'] . ' ' . $procesoInfo['apellidos'] . '.', LM::SUCCESS, LM::UPDATE);
                    return new JsonModel([
                        'status' => 'success',
                        'message' => 'Documentación física actualizada',
                        'avanzado' => false
                    ]);
                }

                $this->pg()->log('No se pudo guardar la información de documentación física del proceso ' . $codProceso . '.', LM::FAILURE, LM::UPDATE);
                return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar la información']);

            } catch (\Exception $e) {
                $this->pg()->log('Error al registrar la documentación física del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
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

        // Normalizar título profesional: agregar punto final si no lo tiene
        foreach ($terna as $key => $datos) {
            $titulo = trim($datos['titulo'] ?? '');
            if (!empty($titulo) && substr($titulo, -1) !== '.') {
                $titulo .= '.';
            }
            $terna[$key]['titulo'] = $titulo;
        }

        // Validar datos según tipo de examinador
        foreach ($terna as $datos) {
            $tipo = $datos['tipo_examinador'] ?? 'externo';
            if ($tipo === 'interno') {
                if (empty($datos['cod_usuario'])) {
                    return new JsonModel(['status' => 'error', 'message' => 'Debe seleccionar un docente para los examinadores internos.']);
                }
            } else {
                // Externo: validar nombre, titulo, colegiado y correo
                if (empty($datos['nombre']) || empty($datos['titulo']) || empty($datos['colegiado']) || empty($datos['correo'])) {
                    return new JsonModel(['status' => 'error', 'message' => 'Los examinadores externos deben tener título, nombre, colegiado y correo.']);
                }
                if (!empty($datos['titulo']) && mb_strlen($datos['titulo']) > 20) {
                    return new JsonModel(['status' => 'error', 'message' => 'El título profesional no puede exceder 20 caracteres.']);
                }
            }
        }

        try {
            $statusRes = false;

            if (!empty($terna)){
                // Guardar terna con la fase correspondiente (examen_privado o examen_general)
                $success = $this->examenManager->guardarTerna($codProceso, $terna, $userAdminId);
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
                $ternaGuardada = $this->examenManager->getTerna($codProceso);
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
                    $this->pg()->log('Se guardó la terna completa y se avanzó al siguiente paso.', LM::SUCCESS, LM::CREATE);
                    return new JsonModel([
                        'status' => 'success',
                        'message' => 'Terna completa. El proceso ha avanzado al siguiente paso.',
                        'avanzado' => true
                    ]);
                }

                $this->pg()->log('Se guardó la terna o programación (aún incompleta).', LM::SUCCESS, LM::CREATE);
                return new JsonModel([
                    'status' => 'success',
                    'message' => 'Terna y programación guardadas correctamente',
                    'avanzado' => false
                ]);
            }

            $this->pg()->log('No se pudo guardar la terna del proceso ' . $codProceso . '.', LM::FAILURE, LM::CREATE);
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo guardar la terna']);

        } catch (\Exception $e) {
            $this->pg()->log('Error al guardar la terna del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
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
            $nombreExaminador = ($ex['titulo'] ?? '') ? trim($ex['titulo'] . ' ' . ($ex['nombre'] ?? '')) : ($ex['nombre'] ?? '');
            $listaExaminadoresHtml .= '<li style="margin-bottom:4px;"><strong>' . htmlspecialchars($nombreExaminador) . '</strong></li>';
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
            'asunto'    => htmlspecialchars($faseLabel) . ' - Notificación Examen de Graduación',
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
            // Obtener datos ANTES de avanzar el paso
            $procesoInfo = $this->examenManager->getProceso($codProceso);
            $faseActual  = $procesoInfo['fase_paso_actual'] ?? 'examen_privado';
            $estudiante  = $this->examenManager->getEstudiantePorProceso($codProceso);
            $terna       = $this->examenManager->getTerna($codProceso);

            // Validar que el estudiante tenga correo antes de avanzar
            if (empty($estudiante) || empty($estudiante['correo'])) {
                return new JsonModel(['success' => false, 'message' => 'No se puede notificar: el estudiante no tiene un correo electrónico registrado. Por favor actualice los datos del estudiante.']);
            }

            $advanced = $this->examenManager->avanzarPaso($codProceso, $userId);

            if ($advanced) {
                $cuerpo = $this->construirCuerpoNotificacion($estudiante, $terna, $infoExtra, $faseActual, $ubicacion);

                error_log('[Notificar] CC examinadores: ' . implode(', ', $cuerpo['correosCc']));
                $this->mailManager->sendHtmlMessage(
                    $estudiante['correo'],
                    $cuerpo['asunto'],
                    $cuerpo['html'],
                    [],
                    $cuerpo['correosCc']
                );

                $this->pg()->log('Se notificó al estudiante ' . $estudiante['nombre_completo'] . ' y se cerró el paso 4.', LM::SUCCESS, LM::CREATE);
                return new JsonModel(['success' => true, 'message' => 'Estudiante notificado y proceso cerrado correctamente']);
            }

            $this->pg()->log('No se pudo cerrar el paso 4 del proceso ' . $codProceso . ' al intentar notificar al estudiante.', LM::FAILURE, LM::CREATE);
            return new JsonModel(['success' => false, 'message' => 'No se pudo cerrar el paso']);

        } catch (\Exception $e) {
            $this->pg()->log('Error al notificar al estudiante del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
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
            $terna       = $this->examenManager->getTerna($codProceso);

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
                $estudianteAvance = $this->examenManager->getEstudiantePorProceso($codProceso);
                $this->pg()->log('Se avanzó el proceso del estudiante ' . $estudianteAvance['nombre_completo'] . ' al siguiente paso.', LM::SUCCESS, LM::UPDATE);
                return new JsonModel(['status' => 'success', 'message' => 'Proceso avanzado correctamente']);
            }

            $this->pg()->log('El proceso ' . $codProceso . ' no pudo avanzar al siguiente paso.', LM::FAILURE, LM::UPDATE);
            return new JsonModel(['status' => 'error', 'message' => 'No se pudo avanzar al siguiente paso']);

        } catch (\Exception $e) {
            $this->pg()->log('Error al avanzar el proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
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
            $terna = $this->examenManager->getTerna($idProceso);
        }

        $this->pg()->log('Se consultó la revisión de papelería del estudiante ' . $estudiante['nombre_completo'] . '.', LM::SUCCESS, LM::VIEW);
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
            $terna = $this->examenManager->getTerna($idProceso);

            // T-18b: Cargar lista de docentes internos para dropdown
            $docentes = $this->examenManager->getDocentes();

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
                'docentes'              => $docentes,
                'instruccionesEntrega'  => $instruccionesEntrega,
                'codTipoExamenFase'     => $codTipoExamenFase,
            ]);
            $vm->setTemplate('eep/examen/revisarpapeleria');
            return $vm;
        }

        // Listado de solicitudes (Paginado)
        $pagina        = (int) $this->params()->fromQuery('page', 1);
        $estado        = $this->params()->fromQuery('estado', 'pendiente');
        $carne         = $this->params()->fromQuery('carne', null);
        $codTipoExamen = (int) $this->params()->fromQuery('cod_tipo_examen', 0) ?: null;

        $procesos = $this->examenManager->getProcesos([
            'pagina'          => $pagina,
            'limite'          => 12,
            'estado_paso'     => $estado,
            'cod_tipo_examen' => $codTipoExamen,
        ]);

        $nombreTipoExamen = $codTipoExamen
            ? $this->examenManager->getNombreTipoExamen($codTipoExamen)
            : null;

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
            ],
            'nombreTipoExamen' => $nombreTipoExamen,
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
        $busqueda      = $this->params()->fromQuery('busqueda', '') ?: null;

        $resultado = $this->examenManager->getProcesos([
            'pagina'          => $pagina,
            'limite'          => 15,
            'numero_paso'     => 5,
            'cod_tipo_examen' => $codTipoExamen,
            'busqueda'        => $busqueda,
        ]);

        $this->pg()->log('Se consultó el listado de procesos en paso 5 (Carta de Examinadores).', LM::SUCCESS, LM::VIEW);
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
                'busqueda'        => $busqueda,
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

        $this->pg()->log('Se consultó el detalle de la Carta de Examinadores del estudiante ' . $proceso['nombres'] . ' ' . $proceso['apellidos'] . '.', LM::SUCCESS, LM::VIEW);
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
        $docentes = $this->examenManager->getDocentes();
        return new ViewModel([
            'estudiantes' => $estudiantes,
            'docentes'    => $docentes,
        ]);
    }

    /**
     * Construye el HTML del correo de notificación grupal (acto de graduación).
     */
    private function construirCuerpoNotificacionGrupal(string $fecha, string $hora, string $lugar, string $infoExtra): string
    {
        // Convertir fecha "21/07/2026" → "21 de julio de 2026"
        $fechaTexto = $fecha;
        $dt = \DateTime::createFromFormat('d/m/Y', $fecha);
        if ($dt) {
            $meses = ['enero','febrero','marzo','abril','mayo','junio',
                      'julio','agosto','septiembre','octubre','noviembre','diciembre'];
            $fechaTexto = (int)$dt->format('j') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
        }

        $html = '<p>Estimado(a) graduando(a):</p>'
              . '<p>Reciba un cordial saludo y nuestras más sinceras felicitaciones por haber llegado a esta importante etapa de su formación académica.</p>'
              . '<p><strong>Datos del examen</strong></p>'
              . '<p><strong>Fecha:</strong> ' . htmlspecialchars($fechaTexto) . '<br>'
              . '<strong>Hora:</strong> ' . htmlspecialchars($hora) . '<br>'
              . '<strong>Lugar:</strong> ' . htmlspecialchars($lugar) . '</p>'
              . '<p><strong>Indicaciones importantes</strong></p>'
              . '<p>Presentarse una hora antes del inicio del examen. Durante ese tiempo se les brindarán instrucciones sobre el protocolo, el desfile y la entrega de la toga.</p>'
              . '<p>El video de su Proyecto de Graduación deberá enviarse con un mínimo de 48 horas de anticipación al examen. No se recibirá una presentación en PowerPoint; únicamente deberá enviarse el video.</p>'
              . '<p>El video deberá incluir:</p>'
              . '<ul>'
              . '  <li>Título del proyecto.</li>'
              . '  <li>Agradecimientos.</li>'
              . '  <li>Breve descripción del proyecto, enfocándose en su rentabilidad.</li>'
              . '  <li>La duración máxima del video es de 5 minutos.</li>'
              . '</ul>'
              . '<p><strong>Entrega de toga</strong></p>'
              . '<p>Para recibir la toga deberán presentar su documento de identificación original. Este será devuelto al momento de entregar nuevamente la toga.</p>'
              . '<p>Si alguno de sus padrinos es arquitecto egresado de esta casa de estudios, deberá gestionar su toga con al menos un día de anticipación al examen.</p>'
              . '<p>Los examinadores podrán solicitar su toga con la señora Diana Campos (jornada de la tarde) o con el Lic. Héctor Medrano (jornada de la mañana), preferiblemente con un día de anticipación al examen.</p>';

        if (!empty($infoExtra)) {
            $html .= '<p>' . nl2br(htmlspecialchars($infoExtra)) . '</p>';
        }

        $html .= '<p>Se solicita confirmar la recepción y enterado del presente correo.</p>'
              . '<p>Saludos cordiales.</p>';

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
        $codExaminador1 = (int) $request->getPost('examinador_1', 0);
        $codExaminador2 = (int) $request->getPost('examinador_2', 0);
        $codExaminador3 = (int) $request->getPost('examinador_3', 0);
        $infoExtra = trim((string) $request->getPost('info_extra', ''));
        $correosCcRaw = trim((string) $request->getPost('correos_cc', ''));

        if (empty($codProcesos) || !is_array($codProcesos)) {
            return new JsonModel(['success' => false, 'message' => 'Debe seleccionar al menos un estudiante.']);
        }
        if (empty($fecha) || empty($hora) || empty($lugar)) {
            return new JsonModel(['success' => false, 'message' => 'Fecha, hora y lugar son obligatorios.']);
        }

        // Resolver nombres y correos de examinadores desde docentes internos
        $examinador1 = '';
        $examinador2 = '';
        $examinador3 = '';
        $correoEx1 = '';
        $correoEx2 = '';
        $correoEx3 = '';

        if ($codExaminador1 > 0) {
            $doc1 = $this->examenManager->getDocentePorCodUsuario($codExaminador1);
            $examinador1 = $doc1 ? $doc1['nombre_completo'] : '';
            $correoEx1 = $doc1 ? ($doc1['correo'] ?? '') : '';
        }
        if ($codExaminador2 > 0) {
            $doc2 = $this->examenManager->getDocentePorCodUsuario($codExaminador2);
            $examinador2 = $doc2 ? $doc2['nombre_completo'] : '';
            $correoEx2 = $doc2 ? ($doc2['correo'] ?? '') : '';
        }
        if ($codExaminador3 > 0) {
            $doc3 = $this->examenManager->getDocentePorCodUsuario($codExaminador3);
            $examinador3 = $doc3 ? $doc3['nombre_completo'] : '';
            $correoEx3 = $doc3 ? ($doc3['correo'] ?? '') : '';
        }

        if (empty($examinador1) || empty($examinador2) || empty($examinador3)) {
            return new JsonModel(['success' => false, 'message' => 'Los tres examinadores son obligatorios.']);
        }

        // Validar que los 3 examinadores sean diferentes
        if ($examinador1 === $examinador2 || $examinador1 === $examinador3 || $examinador2 === $examinador3) {
            return new JsonModel(['success' => false, 'message' => 'Los tres examinadores deben ser personas diferentes.']);
        }

        // Validar que todos los estudiantes seleccionados tengan madrina/padrino
        $sinMadrina = $this->examenManager->verificarMadrinaPadrinoPorProcesos($codProcesos);
        if (!empty($sinMadrina)) {
            $nombres = array_map(function ($e) {
                return trim(($e['apellidos'] ?? '') . ', ' . ($e['nombres'] ?? ''));
            }, $sinMadrina);
            return new JsonModel([
                'success' => false,
                'message' => 'No se puede enviar la notificación. Los siguientes estudiantes aún no han configurado su madrina/padrino: ' .
                    implode(', ', $nombres) . '. Solicíteles que completen este dato desde su panel de Proceso de Graduación.'
            ]);
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

        // Agregar automáticamente los correos de los examinadores al CC
        foreach ([$correoEx1, $correoEx2, $correoEx3] as $correoEx) {
            if ($correoEx !== '' && filter_var($correoEx, FILTER_VALIDATE_EMAIL)) {
                if (!in_array($correoEx, $correosCc, true)) {
                    $correosCc[] = $correoEx;
                }
            }
        }

        // Crear o actualizar el acto grupal compartido con examinadores
        $this->examenManager->obtenerOCrearActoGraduacion($fecha, $hora, [
            'lugar'         => $lugar,
            'examinador_1'  => $examinador1,
            'examinador_2'  => $examinador2,
            'examinador_3'  => $examinador3,
        ]);

        $fechaFormateada = $fechaSel->format('d/m/Y');
        $horaFormateada = date('g:i A', strtotime($hora));

        $correosEstudiantes = [];
        $fallidos = 0;
        $errores = [];

        // Recolectar correos y guardar programación por cada proceso
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

            $correosEstudiantes[] = $estudiante['correo'];
        }

        if (empty($correosEstudiantes)) {
            $this->pg()->log('No se pudo enviar notificación grupal: ningún estudiante tiene correo.', LM::FAILURE, LM::CREATE);
            return new JsonModel([
                'success'   => false,
                'enviados'  => 0,
                'fallidos'  => $fallidos,
                'errores'   => $errores,
                'message'   => 'No se pudo enviar la notificación. Ninguno de los estudiantes seleccionados tiene correo registrado.',
            ]);
        }

        // Enviar UN SOLO correo con todos los estudiantes en "Para:"
        $html = $this->construirCuerpoNotificacionGrupal($fechaFormateada, $horaFormateada, $lugar, $infoExtra);

        try {
            $this->mailManager->sendHtmlMessage(
                $correosEstudiantes,
                'Acto de Graduación - Examen General',
                $html,
                [],
                $correosCc
            );
            $enviados = count($correosEstudiantes);
        } catch (\Exception $e) {
            $fallidos += count($correosEstudiantes);
            $errores[] = 'Error al enviar notificación grupal: ' . $e->getMessage();
            $enviados = 0;
        }

        $this->pg()->log('Se envió 1 notificación grupal a ' . $enviados . ' estudiante(s) de acto de graduación.' . ($fallidos > 0 ? ' (' . $fallidos . ' fallidos)' : ''), $fallidos > 0 ? LM::FAILURE : LM::SUCCESS, LM::CREATE);
        return new JsonModel([
            'success'   => true,
            'enviados'  => $enviados,
            'fallidos'  => $fallidos,
            'errores'   => $errores,
            'message'   => "Se notificaron {$enviados} estudiante(s) correctamente en un solo correo." . ($fallidos > 0 ? " ({$fallidos} fallidos)" : ''),
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

        $this->pg()->log('Se consultó el listado de procesos listos para evaluación de examen privado.', LM::SUCCESS, LM::VIEW);
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
     * AJAX: abre la evaluación de un proceso generando un código de 8 dígitos.
     * Retorna el link para compartir con los examinadores.
     */
    public function abrirEvaluacionAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $codProceso = (int) $request->getPost('cod_proceso', 0);
        if ($codProceso <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Proceso inválido']);
        }

        try {
            $codigo = $this->examenManager->abrirEvaluacion($codProceso);
            $url = $this->url()->fromRoute('eval-privado', [
                'cod_proceso' => $codProceso
            ], ['query' => ['cod' => $codigo], 'force_canonical' => true]);

            $this->pg()->log('Se abrió la evaluación del examen privado con código de acceso ' . $codigo . '.', LM::SUCCESS, LM::CREATE);
            return new JsonModel([
                'status' => 'success',
                'codigo' => $codigo,
                'url'    => $url,
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al abrir la evaluación del examen privado del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: cierra la evaluación de un proceso invalidando el código.
     */
    public function cerrarEvaluacionAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $codProceso = (int) $request->getPost('cod_proceso', 0);
        if ($codProceso <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Proceso inválido']);
        }

        try {
            $this->examenManager->cerrarEvaluacion($codProceso);
            $this->pg()->log('Se cerró la evaluación del examen privado.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel([
                'status'  => 'success',
                'message' => 'Evaluación cerrada correctamente',
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al cerrar la evaluación del examen privado del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: reprograma un examen privado cerrado.
     * Elimina evaluaciones, resetea estado, actualiza fecha/hora.
     */
    public function reprogramarExamenPrivadoAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $codProceso = (int) $request->getPost('cod_proceso', 0);
        $nuevaFecha = (string) $request->getPost('fecha_examen', '');
        $nuevaHora  = (string) $request->getPost('hora_examen', '');

        if ($codProceso <= 0 || $nuevaFecha === '' || $nuevaHora === '') {
            return new JsonModel(['status' => 'error', 'message' => 'Datos incompletos']);
        }

        $codUsuario = (int) $this->authService->getIdentity();
        if ($codUsuario <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Usuario no autenticado']);
        }

        try {
            $this->examenManager->reprogramarExamenPrivado($codProceso, $nuevaFecha, $nuevaHora, $codUsuario);
            $this->pg()->log('Se reprogramó el examen privado.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel([
                'status'  => 'success',
                'message' => 'Examen reprogramado correctamente. Se ha enviado notificación al estudiante y examinadores.',
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al reprogramar el examen privado del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: cancela un proceso de examen privado.
     */
    public function cancelarProcesoPrivadoAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $codProceso = (int) $request->getPost('cod_proceso', 0);
        $motivo     = (string) $request->getPost('motivo', '');

        if ($codProceso <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Datos incompletos']);
        }

        $codUsuario = (int) $this->authService->getIdentity();
        if ($codUsuario <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Usuario no autenticado']);
        }

        try {
            $this->examenManager->cancelarProcesoPrivado($codProceso, $motivo, $codUsuario);
            $this->pg()->log('Se canceló el proceso de examen privado.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel([
                'status'  => 'success',
                'message' => 'Proceso cancelado correctamente.',
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al cancelar el proceso de examen privado ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::UPDATE);
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

        $preguntas = [];
        $codCarrera = (int) ($estudiante['cod_carrera'] ?? 0);
        if ($codCarrera > 0) {
            $preguntas = $this->examenManager->getMatrizPreguntas($codCarrera);
        }

        $evaluaciones = $this->examenManager->getResumenEvaluaciones($codProceso);
        $estado = $this->examenManager->getEstadoEvaluacion($codProceso);

        $role = $this->layout()->role;
        $isSecretario = $role && $role->isSecretarioExamenPrivado();
        $evaluacionPendiente = !empty($estado) && empty($estado['hora_apertura_evaluacion']);

        $this->pg()->log('Se consultó la matriz de evaluaciones del estudiante ' . $estudiante['nombre_completo'] . '.', LM::SUCCESS, LM::VIEW);
        return new ViewModel([
            'proceso'       => $proceso,
            'estudiante'    => $estudiante,
            'terna'         => $terna,
            'temaTesis'     => $temaTesis,
            'preguntas'     => $preguntas,
            'evaluaciones'  => $evaluaciones,
            'estado'        => $estado,
            'isSecretario'  => $isSecretario,
            'evaluacionPendiente' => $evaluacionPendiente,
        ]);
    }

    /**
     * AJAX: lista de docentes internos y secretario para dropdown de sustitución.
     */
    public function listaDocentesAction()
    {
        $docentes = $this->examenManager->getDocentes();

        // Asegurar que el Secretario de Examen Privado (rol 11) esté incluido explícitamente
        $secretario = $this->examenManager->getSecretarioParaSustitucion();
        if ($secretario) {
            $encontrado = false;
            foreach ($docentes as $d) {
                if ((int) ($d['cod_usuario'] ?? 0) === (int) $secretario['cod_usuario']) {
                    $encontrado = true;
                    break;
                }
            }
            if (!$encontrado) {
                array_unshift($docentes, $secretario);
            }
        }

        return new JsonModel([
            'status' => 'success',
            'docentes' => $docentes
        ]);
    }

    /**
     * AJAX: sustituye un examinador en la terna de un proceso.
     * Solo Secretario (rol 11) cuando la evaluación está pendiente.
     */
    public function sustituirExaminadorAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $role = $this->layout()->role;
        if (!$role || !$role->isSecretarioExamenPrivado()) {
            return new JsonModel(['status' => 'error', 'message' => 'Sin permiso']);
        }

        $codProceso = (int) $request->getPost('cod_proceso', 0);
        $posicion = (int) $request->getPost('posicion', 0);
        $tipo = (string) $request->getPost('tipo', 'interno');
        $codUsuario = (int) $request->getPost('cod_usuario', 0) ?: null;
        $colegiado = trim((string) $request->getPost('colegiado', '')) ?: null;
        $titulo = trim((string) $request->getPost('titulo', '')) ?: null;
        $correo = trim((string) $request->getPost('correo', '')) ?: null;

        if ($codProceso <= 0 || $posicion < 1 || $posicion > 3) {
            return new JsonModel(['status' => 'error', 'message' => 'Datos inválidos']);
        }

        if ($tipo !== 'interno') {
            return new JsonModel(['status' => 'error', 'message' => 'Solo se permiten examinadores internos.']);
        }

        if (!$codUsuario) {
            return new JsonModel(['status' => 'error', 'message' => 'Debe seleccionar un docente o secretario.']);
        }

        // Validar que el docente/secretario no esté ya en la terna actual
        $ternaActual = $this->examenManager->getTerna($codProceso);
        foreach ($ternaActual['examinadores'] as $ex) {
            if ((int)$ex['cod_usuario'] === $codUsuario) {
                return new JsonModel(['status' => 'error', 'message' => 'El docente o secretario seleccionado ya es examinador de este proceso. No puede haber duplicados en la terna.']);
            }
        }

        $faltantes = [];
        if (!$colegiado) $faltantes[] = 'número de colegiado';
        if (!$titulo) $faltantes[] = 'título profesional';
        if (!$correo) $faltantes[] = 'correo electrónico';

        if (!empty($faltantes)) {
            $msg = 'El docente o secretario seleccionado no puede ser examinador sustituto porque le falta: ' . implode(', ', $faltantes) . '. Edite el usuario antes de continuar.';
            return new JsonModel(['status' => 'error', 'message' => $msg]);
        }

        $datos = [
            'tipo' => $tipo,
            'cod_usuario' => $codUsuario,
            'colegiado' => $colegiado,
            'titulo' => $titulo,
            'correo' => $correo
        ];

        $resultado = $this->examenManager->sustituirExaminador($codProceso, $posicion, $datos);

        if ($resultado['success']) {
            $this->pg()->log('Se sustituyó el examinador en la posición ' . $posicion . ' de la terna.', LM::SUCCESS, LM::UPDATE);
            return new JsonModel([
                'status' => 'success',
                'message' => $resultado['message'],
                'cod_examinador' => $resultado['cod_examinador']
            ]);
        }

        $this->pg()->log('Error al sustituir el examinador en la posición ' . $posicion . ' del proceso ' . $codProceso . ': ' . $resultado['message'], LM::FAILURE, LM::UPDATE);
        return new JsonModel([
            'status' => 'error',
            'message' => $resultado['message']
        ]);
    }

    /**
     * Página pública de evaluación de examen privado.
     * Tanto internos como externos acceden aquí. Sin login requerido.
     * URL: /eval-privado/:cod_proceso?cod=:codigo
     */
    public function evaluacionExamenPrivadoAction()
    {
        $codProceso = (int) $this->params()->fromRoute('cod_proceso', 0);
        $codigo = (string) $this->params()->fromQuery('cod', '');
        $posicion = (int) $this->params()->fromQuery('pos', 0);

        if ($codProceso <= 0 || $codigo === '') {
            return $this->getResponse()->setStatusCode(404);
        }

        if (!$this->examenManager->validarCodigo($codProceso, $codigo)) {
            return $this->getResponse()->setStatusCode(403);
        }

        $proceso = $this->examenManager->getProceso($codProceso);
        if (!$proceso) {
            return $this->getResponse()->setStatusCode(404);
        }

        $estudiante = $this->examenManager->getEstudiantePorProceso($codProceso);
        $terna = $this->examenManager->getTernaParaEvaluacion($codProceso);
        $estado = $this->examenManager->getEstadoEvaluacion($codProceso);

        // Si ya seleccionó posición, verificar que no esté completado
        if ($posicion > 0) {
            $col = "ex{$posicion}_completado";
            if (!empty($estado[$col])) {
                $this->flashMessenger()->addErrorMessage('Esta evaluación ya fue completada.');
                return $this->redirect()->toRoute('eval-privado', [
                    'cod_proceso' => $codProceso
                ], ['query' => ['cod' => $codigo]]);
            }

            $preguntas = [];
            $codCarrera = (int) ($estudiante['cod_carrera'] ?? 0);
            if ($codCarrera > 0) {
                $preguntas = $this->examenManager->getMatrizPreguntas($codCarrera);
            }

            $evaluacion = $this->examenManager->getMatrizEvaluacion($codProceso, $posicion);

            $vm = new ViewModel([
                'proceso'       => $proceso,
                'estudiante'    => $estudiante,
                'terna'         => $terna,
                'posicion'      => $posicion,
                'codigo'        => $codigo,
                'codCarrera'    => $codCarrera,
                'preguntas'     => $preguntas,
                'evaluacion'    => $evaluacion,
            ]);
            $vm->setTemplate('eep/examen/evaluacion-examen-privado');
            $vm->setTerminal(true);
            return $vm;
        }

        // Pantalla de selección de examinador
        $vm = new ViewModel([
            'proceso'    => $proceso,
            'estudiante' => $estudiante,
            'terna'      => $terna,
            'codigo'     => $codigo,
            'estado'     => $estado,
        ]);
        $vm->setTemplate('eep/examen/evaluacion-examen-privado');
        $vm->setTerminal(true);
        return $vm;
    }

    /**
     * AJAX: guarda la evaluación de un examinador desde la página pública.
     */
    public function guardarEvaluacionExaminadorAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(['status' => 'error', 'message' => 'Método no permitido']);
        }

        $codProceso = (int) $request->getPost('cod_proceso', 0);
        $codigo = (string) $request->getPost('codigo', '');
        $posExaminador = (int) $request->getPost('posicion_examinador', 0);
        $observaciones = trim((string) $request->getPost('observaciones_generales', '')) ?: null;
        $respuestasRaw = $request->getPost('respuestas', []);

        if ($codProceso <= 0 || $codigo === '' || $posExaminador < 1 || $posExaminador > 3) {
            return new JsonModel(['status' => 'error', 'message' => 'Datos inválidos']);
        }

        if (!$this->examenManager->validarCodigo($codProceso, $codigo)) {
            return new JsonModel(['status' => 'error', 'message' => 'Código inválido o evaluación cerrada']);
        }

        // Validar que la evaluación esté abierta
        $estadoEval = $this->examenManager->getEstadoEvaluacion($codProceso);
        if (empty($estadoEval['hora_apertura_evaluacion'])) {
            return new JsonModel(['status' => 'error', 'message' => 'La evaluación aún no ha sido abierta por la secretaría.']);
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

        try {
            $ternaEval = $this->examenManager->getTerna($codProceso);
            $examinadorEval = $ternaEval['examinadores'][$posExaminador - 1] ?? null;
            $evaluadoPor = !empty($examinadorEval['cod_usuario'])
                ? (int) $examinadorEval['cod_usuario']
                : (int) ($examinadorEval['cod_examinador'] ?? 0);

            $this->examenManager->guardarMatrizEvaluacion([
                'cod_proceso'           => $codProceso,
                'posicion_examinador'   => $posExaminador,
                'evaluado_por'          => $evaluadoPor,
                'observaciones_generales' => $observaciones,
                'respuestas'            => $respuestas,
            ]);

            $this->examenManager->marcarExaminadorCompletado($codProceso, $posExaminador);

            $this->pg()->log('El examinador completó la evaluación (posición ' . $posExaminador . ').', LM::SUCCESS, LM::CREATE);
            return new JsonModel([
                'status'  => 'success',
                'message' => 'Evaluación guardada correctamente',
            ]);
        } catch (\Exception $e) {
            $this->pg()->log('Error al guardar la evaluación del examinador del proceso ' . $codProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return new JsonModel(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Vista del acta de examen privado.
     * Se muestra tras completar la evaluación por los 3 examinadores.
     */
    public function actaExamenPrivadoAction()
    {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            $this->flashMessenger()->addErrorMessage('Proceso no válido');
            return $this->redirect()->toRoute('examen', ['action' => 'evaluacion-privado']);
        }

        $proceso = $this->examenManager->getProceso($idProceso);
        $estudiante = $this->examenManager->getEstudiantePorProceso($idProceso);
        $terna = $this->examenManager->getTerna($idProceso);
        $estado = $this->examenManager->getEstadoEvaluacion($idProceso);

        // DEBUG: Ver estructura de datos
        error_log("DEBUG Proceso: " . print_r($proceso, true));
        error_log("DEBUG Estudiante: " . print_r($estudiante, true));

        // Si no hay evaluación completada (mínimo 2 examinadores), redirigir
        $ex1 = (int) ($estado['ex1_completado'] ?? 0);
        $ex2 = (int) ($estado['ex2_completado'] ?? 0);
        $ex3 = (int) ($estado['ex3_completado'] ?? 0);
        if (($ex1 + $ex2 + $ex3) < 2) {
            $this->flashMessenger()->addWarningMessage('La evaluación aún no ha sido completada por al menos 2 examinadores');
            return $this->redirect()->toRoute('examen', ['action' => 'evaluacion-privado']);
        }

        $formData = $_SESSION['acta_examen_privado_form'] ?? [];
        unset($_SESSION['acta_examen_privado_form']);

        // Obtener configuración de Decano y Secretario
        $decano = $this->config['decano']['nombre'] ?? 'Decano';
        $secretario = $this->examenManager->getNombreSecretarioExamenPrivado();

        // Obtener notas de examinadores para saber quiénes evaluaron
        $notasExaminadores = $this->examenManager->getNotasExaminadores($idProceso);

        // Verificar si ya existe un acta privada generada para este proceso
        $actaPrivado = $this->examenManager->getActaPrivado($idProceso);

        $this->pg()->log('Se consultó el acta de examen privado del estudiante ' . $estudiante['nombre_completo'] . '.', LM::SUCCESS, LM::VIEW);
        return new ViewModel([
            'proceso'    => $proceso,
            'estudiante' => $estudiante,
            'terna'      => $terna,
            'estado'     => $estado,
            'formData'   => $formData,
            'decano'     => $decano,
            'secretario' => $secretario,
            'notasExaminadores' => $notasExaminadores,
            'actaPrivado' => $actaPrivado,
        ]);
    }

    /**
     * Generar acta de examen privado en formato DOCX.
     */
    public function generarActaExamenPrivadoAction()
    {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            $this->flashMessenger()->addErrorMessage('Proceso no válido');
            return $this->redirect()->toRoute('examen', ['action' => 'evaluacion-privado']);
        }

        try {
            $proceso = $this->examenManager->getProceso($idProceso);
            $estudiante = $this->examenManager->getEstudiantePorProceso($idProceso);
            $terna = $this->examenManager->getTerna($idProceso);
            $examinadores = $terna['examinadores'] ?? [];

            $nombreEstudiante = trim($estudiante['nombre_completo'] ?? '');
            $carrera = $estudiante['carrera'] ?? 'N/A';
            $registroAcademico = $estudiante['registro_academico'] ?? '';
            $temaTesis = $proceso['tema_tesis'] ?? '';
            $fechaExamen = $this->params()->fromPost('fecha_examen', '');

            // Determinar prefijo según sexo (F/M, Mujer/Hombre, 2/1)
            $sexo = $estudiante['sexo'] ?? '';
            $esFemenino = in_array(strtoupper($sexo), ['F', 'M', 'MUJER', '2'], true);
            $prefijoAlumno = $esFemenino ? 'la alumna' : 'el alumno';

            // Usar hora real de inicio (hora_apertura_evaluacion) en vez de la programada
            $horaExamen = '';
            if (!empty($proceso['hora_apertura_evaluacion'])) {
                $horaExamen = date('H:i', strtotime($proceso['hora_apertura_evaluacion']));
            }
            // Fallback a POST si no hay hora real
            if (empty($horaExamen)) {
                $horaExamen = $this->params()->fromPost('hora_examen', '');
            }

            // Usar hora de cierre para el final del acta
            $horaCierre = '';
            if (!empty($proceso['hora_cierre_evaluacion'])) {
                $horaCierre = date('H:i', strtotime($proceso['hora_cierre_evaluacion']));
            }

            // Formatear fecha para el acta: "viernes 10 de abril de 2026"
            $fechaExamenFormateada = $fechaExamen;
            if (!empty($fechaExamen)) {
                $dtF = \DateTime::createFromFormat('d/m/Y', $fechaExamen);
                if ($dtF) {
                    $diasEs   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
                    $mesesEs  = ['enero','febrero','marzo','abril','mayo','junio',
                                 'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    $fechaExamenFormateada = $diasEs[(int)$dtF->format('w')]
                        . ' ' . (int)$dtF->format('j')
                        . ' de ' . $mesesEs[(int)$dtF->format('n') - 1]
                        . ' de ' . $dtF->format('Y');
                }
            }

            // Obtener configuración de Decano y Secretario
            $decano = $this->config['decano']['nombre'] ?? 'Decano';
            error_log("DEBUG DECANOOOOOOOOOOOOOOOOOOOOO: " . print_r($decano, true));
            $secretarioAcademico = $this->examenManager->getNombreSecretarioExamenPrivado();
            $justificacionModalidad = trim((string) $this->params()->fromPost('justificacion_modalidad', ''));

            if ($justificacionModalidad === '') {
                $this->flashMessenger()->addErrorMessage('La justificación de modalidad del examen es obligatoria.');
                $_SESSION['acta_examen_privado_form'] = $this->params()->fromPost();
                return $this->redirect()->toRoute('examen', ['action' => 'acta-examen-privado', 'id' => $idProceso]);
            }

            $recibo = trim((string) $this->params()->fromPost('recibo', ''));

            // Verificar si ya existe un acta privada en la tabla dedicada
            $actaExistente = $this->examenManager->getActaPrivado($idProceso);

            // Calcular notas de examinadores (se necesitan tanto para el texto del acta como para la nota final)
            $notasExaminadores = $this->examenManager->getNotasExaminadores($idProceso);

            if ($actaExistente !== null) {
                // Reutilizar el número de acta ya asignado
                $numeroActa = $actaExistente['numero_acta'];
            } else {
                // Generar número de acta autoincrementable global
                $anio = (int) date('Y');
                $datosActa = $this->examenManager->generarNumeroActa($anio);
                $numeroActa = $datosActa['numero_acta'];

                // Calcular nota final para determinar estado
                $notasValidas = array_filter($notasExaminadores, function ($n) {
                    return $n !== null;
                });
                $notaFinal = null;
                if (!empty($notasValidas)) {
                    $promedio = array_sum($notasValidas) / count($notasValidas);
                    $notaFinal = (int) round($promedio);
                }
                $estado = $notaFinal !== null && $notaFinal > 61 ? 'aprobado' : 'reprobado';

                // Guardar en la tabla dedicada examen_acta_privado
                $examinadoresGuardar = [];
                foreach ($examinadores as $idx => $ex) {
                    $nombre = ($ex['titulo'] ?? '') ? trim($ex['titulo'] . ' ' . ($ex['nombre'] ?? '')) : ($ex['nombre'] ?? '');
                    $examinadoresGuardar[$idx + 1] = $nombre;
                }

                $this->examenManager->guardarActaPrivado([
                    'cod_proceso'           => $idProceso,
                    'numero_acta'           => $datosActa['numero_acta'],
                    'anio_acta'             => $datosActa['anio'],
                    'correlativo_acta'      => $datosActa['correlativo'],
                    'recibo'                => $recibo,
                    'nota_final'            => $notaFinal,
                    'estado'                => $estado,
                    'examinador_1'          => $examinadoresGuardar[1] ?? null,
                    'examinador_2'          => $examinadoresGuardar[2] ?? null,
                    'examinador_3'          => $examinadoresGuardar[3] ?? null,
                    'fecha_examen'          => !empty($fechaExamen)
                        ? \DateTime::createFromFormat('d/m/Y', $fechaExamen)->format('Y-m-d')
                        : null,
                    'hora_examen'           => $horaExamen ?: null,
                    'hora_firma'            => $this->params()->fromPost('hora_firma', null),
                    'lugar'                 => $this->params()->fromPost('lugar', null),
                    'justificacion_modalidad' => $justificacionModalidad,
                    'generado_por'          => (int) $this->identity(),
                ]);
            }

            // Filtrar examinadores que sí evaluaron para el texto del acta
            error_log("DEBUG notasExaminadores: " . print_r($notasExaminadores, true));
            error_log("DEBUG examinadores count: " . count($examinadores));
            $nombresExaminadores = [];
            foreach ($examinadores as $idx => $ex) {
                $posicion = $idx + 1;
                error_log("DEBUG examinador[$idx] pos=$posicion: nombre=" . ($ex['nombre'] ?? 'NULL') . ", titulo=" . ($ex['titulo'] ?? 'NULL') . ", nota=" . (isset($notasExaminadores[$posicion]) ? ($notasExaminadores[$posicion] ?? 'NULL') : 'NO_KEY'));
                if (isset($notasExaminadores[$posicion]) && $notasExaminadores[$posicion] !== null) {
                    $nombre = ($ex['titulo'] ?? '') ? trim($ex['titulo'] . ' ' . ($ex['nombre'] ?? '')) : ($ex['nombre'] ?? '');
                    if (!empty($nombre)) {
                        $nombresExaminadores[] = $nombre;
                    }
                }
            }
            error_log("DEBUG nombresExaminadores result: " . print_r($nombresExaminadores, true));
            $examinadoresTexto = implode(', ', $nombresExaminadores);

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultFontName('Lustria');
            $phpWord->setDefaultFontSize(12);

            // Estilo de lista con guion para observaciones/correcciones
            $phpWord->addNumberingStyle(
                'listaGuion',
                [
                    'type' => 'hybridMultilevel',
                    'levels' => [
                        [
                            'format' => 'bullet',
                            'text' => '-',
                            'left' => 720,
                            'hanging' => 360,
                        ]
                    ]
                ]
            );

            $section = $phpWord->addSection();

            // Título del acta
            $section->addTextBreak(7);
            
            $section->addText('ACTA DE EXAMEN PRIVADO DE MAESTRÍA', ['bold' => true, 'size' => 14], ['alignment' => 'right']);

            // Subtítulo: Maestría + número de acta
            $subtituloCarrera = 'Maestría ' . $numeroActa;
            $section->addText($subtituloCarrera, ['bold' => true, 'size' => 16], ['alignment' => 'right']);

            // Sustentante y recibo
            $section->addText('Sustentante: ' . $nombreEstudiante, ['bold' => true, 'size' => 14], ['alignment' => 'right']);
            if ($recibo !== '') {
                $section->addText('Recibo: ' . $recibo, ['bold' => true, 'size' => 14], ['alignment' => 'right']);
            }
            $section->addTextBreak(1);

            // Cuerpo
            $textRun = $section->addTextRun(['alignment' => 'both']);
            $textRun->addText(
                "El {$fechaExamenFormateada}, {$justificacionModalidad}, la terna examinadora designada por el Señor Decano, " .
                "e integrada por los siguientes profesionales: {$examinadoresTexto}, {$decano}, Decano y {$secretarioAcademico}, " .
                "Secretario; para realizar el examen final de su trabajo de graduación titulado: "
            );
            $textRun->addText("\"{$temaTesis}\"", ['bold' => true]);
            $textRun->addText(", presentado por {$prefijoAlumno} ");
            $textRun->addText($nombreEstudiante, ['bold' => true]);
            $textRun->addText(", Registro Académico {$registroAcademico}, ");
            $textRun->addText("para optar al título de ");
            $textRun->addText(mb_strtoupper($carrera, 'UTF-8'), ['bold' => true]);
            $textRun->addText(", de la Facultad de Arquitectura de la Universidad de San Carlos de Guatemala.");
            $section->addTextBreak(1);

            $section->addText('Previo al inicio del examen se instruye y exhorta a los examinadores a cumplir con los fines de la Universidad de San Carlos de Guatemala y la misión de la Facultad de Arquitectura, verificando que el trabajo de investigación que hoy se presenta, cumpla con los requisitos y la calidad correspondiente y se demuestre que la sustentante posee el conocimiento y destrezas propias de su especialidad.', [], ['alignment' => 'both']);
            $section->addTextBreak(1);

            // Formatear hora en palabras
            if (!empty($horaExamen)) {
                $horaPartes = $this->horaATextoPartes($horaExamen);
                $horaTextoRun = $section->addTextRun(['alignment' => 'both']);
                $horaTextoRun->addText("Y siendo las ");
                $horaTextoRun->addText($horaPartes['hora'], ['bold' => true]);
                $horaTextoRun->addText(" horas con ");
                $horaTextoRun->addText($horaPartes['minutos'], ['bold' => true]);
                $horaTextoRun->addText(" minutos, el infrascrito Secretario, da apertura al mismo para su desarrollo, como la terna examinadora lo crea conveniente.");
                $section->addTextBreak(1);
            }

            $section->addText('Luego de la evaluación correspondiente del proyecto de graduación presentado, los Infrascritos Miembros del Jurado Examinador, habiendo deliberado y considerando que sí llena los requisitos: ACORDAMOS');

            // Salto de página y saltos de línea
            $section->addPageBreak();
            $section->addTextBreak(5);

            // Calcular nota final
            $notasValidas = array_filter($notasExaminadores, function ($n) {
                return $n !== null;
            });
            $notaFinal = null;
            if (!empty($notasValidas)) {
                $promedio = array_sum($notasValidas) / count($notasValidas);
                $notaFinal = (int) round($promedio);
            }

            // Obtener observaciones/correcciones de cada examinador
            $observacionesExaminadores = $this->examenManager->getObservacionesExaminadores($idProceso);

            if ($notaFinal !== null) {
                $notaTexto = $this->numeroATexto($notaFinal);
                $estadoExamen = $notaFinal > 61 ? 'APROBADO' : 'REPROBADO';
                $notaTextRun = $section->addTextRun(['alignment' => 'both']);
                $notaTextRun->addText("{$estadoExamen} el proyecto de graduación, con la nota de ");
                $notaTextRun->addText($notaTexto . ' (' . $notaFinal . ') puntos. ', ['bold' => true]);
                $notaTextRun->addText('Previo a la impresión final del mismo, la alumna ');
                $notaTextRun->addText($nombreEstudiante, ['bold' => true]);
                $notaTextRun->addText(', deberá realizar las correcciones siguientes:');
            }

            // Mostrar observaciones/correcciones de cada examinador arriba de las firmas
            $tieneObservaciones = false;
            foreach ($observacionesExaminadores as $pos => $obs) {
                if (!empty($obs)) {
                    $tieneObservaciones = true;
                    break;
                }
            }

            $section->addTextBreak(1);

            if ($tieneObservaciones) {
                foreach ($observacionesExaminadores as $pos => $obs) {
                    if (!empty($obs)) {
                        $lineas = array_filter(array_map('trim', explode("\n", $obs)));
                        foreach ($lineas as $linea) {
                            $section->addListItem($linea, 0, null, 'listaGuion');
                        }
                    }
                }
                $section->addTextBreak(1);
            }

            $cierreRun = $section->addTextRun(['alignment' => 'both']);
            $cierreRun->addText('No habiendo más que hacer constar se cierra la presente acta a las ');
            $cierreRun->addText($this->horaATexto($horaCierre ?: $horaExamen), ['bold' => true]);
            $horaFormato24Cierre = date('H:i', strtotime($horaCierre ?: $horaExamen));
            $cierreRun->addText(' (' . $horaFormato24Cierre . '), en el mismo lugar y fecha de su inicio.  DAMOS FE: ');

            $section->addTextBreak(4);

            // Filtrar examinadores que realizaron la evaluación (tienen nota asignada)
            $examinadoresConNota = [];
            foreach ($examinadores as $idx => $ex) {
                // Verificar si este examinador tiene nota asignada
                // Las notas están indexadas por posición (1, 2, 3) no por índice (0, 1, 2)
                $posicion = $idx + 1;
                if (isset($notasExaminadores[$posicion]) && $notasExaminadores[$posicion] !== null) {
                    $examinadoresConNota[] = $ex;
                }
            }

            // Crear tabla para firmas en dos columnas
            $tableStyle = [
                'borderSize' => 0,
                'borderColor' => 'FFFFFF',
                'cellMargin' => 80,
                'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                'width' => 100 * 50 // 100% del ancho
            ];
            
            $cellStyle = [
                'valign' => 'top'
            ];
            
            $cellTextStyle = [
                'alignment' => 'center'
            ];

            $table = $section->addTable($tableStyle);

            // Primera fila: Decano y Secretario
            $table->addRow();
            $cellDecano = $table->addCell(4500, $cellStyle);
            $cellDecano->addText('', [], $cellTextStyle);
            $cellDecano->addText('', [], $cellTextStyle);
            $cellDecano->addText('', [], $cellTextStyle);
            $cellDecano->addText($decano, [], $cellTextStyle);
            $cellDecano->addText('Decano', [], $cellTextStyle);
            
            $cellSecretario = $table->addCell(4500, $cellStyle);
            $cellSecretario->addText('', [], $cellTextStyle);
            $cellSecretario->addText('', [], $cellTextStyle);
            $cellSecretario->addText('', [], $cellTextStyle);
            $cellSecretario->addText($secretarioAcademico, [], $cellTextStyle);
            $cellSecretario->addText('Secretario', [], $cellTextStyle);

            // Filas de examinadores (en pares) y sustentante
            $totalExaminadores = count($examinadoresConNota);
            $numFilasExaminadores = (int) ceil($totalExaminadores / 2);
            
            for ($i = 0; $i < count($examinadoresConNota); $i += 2) {
                $table->addRow();
                
                // Examinador izquierdo
                $exIzq = $examinadoresConNota[$i];
                $nombreExIzq = ($exIzq['titulo'] ?? '') ? trim($exIzq['titulo'] . ' ' . ($exIzq['nombre'] ?? '')) : ($exIzq['nombre'] ?? '');
                
                $cellExIzq = $table->addCell(4500, $cellStyle);
                $cellExIzq->addText('', [], $cellTextStyle);
                $cellExIzq->addText('', [], $cellTextStyle);
                $cellExIzq->addText('', [], $cellTextStyle);
                $cellExIzq->addText($nombreExIzq, [], $cellTextStyle);
                $cellExIzq->addText('Examinador', [], $cellTextStyle);
                
                // Examinador derecho (si existe)
                if (isset($examinadoresConNota[$i + 1])) {
                    $exDer = $examinadoresConNota[$i + 1];
                    $nombreExDer = ($exDer['titulo'] ?? '') ? trim($exDer['titulo'] . ' ' . ($exDer['nombre'] ?? '')) : ($exDer['nombre'] ?? '');
                    
                    $cellExDer = $table->addCell(4500, $cellStyle);
                    $cellExDer->addText('', [], $cellTextStyle);
                    $cellExDer->addText('', [], $cellTextStyle);
                    $cellExDer->addText('', [], $cellTextStyle);
                    $cellExDer->addText($nombreExDer, [], $cellTextStyle);
                    $cellExDer->addText('Examinador', [], $cellTextStyle);
                } else {
                    // Si es el último examinador y es impar, poner sustentante en columna derecha
                    $cellSustentante = $table->addCell(4500, $cellStyle);
                    $cellSustentante->addText('', [], $cellTextStyle);
                    $cellSustentante->addText('', [], $cellTextStyle);
                    $cellSustentante->addText('', [], $cellTextStyle);
                    $cellSustentante->addText($nombreEstudiante, [], $cellTextStyle);
                    $cellSustentante->addText('Sustentante', [], $cellTextStyle);
                }
            }

            // Si hay número par de examinadores, agregar fila para sustentante
            if ($totalExaminadores % 2 == 0) {
                $table->addRow();
                $table->addCell(4500, $cellStyle); // Celda vacía izquierda
                $cellSustentante = $table->addCell(4500, $cellStyle);
                $cellSustentante->addText('', [], $cellTextStyle);
                $cellSustentante->addText('', [], $cellTextStyle);
                $cellSustentante->addText('', [], $cellTextStyle);
                $cellSustentante->addText($nombreEstudiante, [], $cellTextStyle);
                $cellSustentante->addText('Sustentante', [], $cellTextStyle);
            }

            $filename = 'Acta_Examen_Privado_' . $idProceso . '.docx';
            $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord') . '.docx';
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempFile);

            $fileContent = file_get_contents($tempFile);
            unlink($tempFile);

            $response = $this->getResponse();
            $response->setContent($fileContent);
            $headers = $response->getHeaders();
            $headers->clearHeaders();
            $headers->addHeaderLine('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $headers->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $headers->addHeaderLine('Content-Length', strlen($fileContent));
            $headers->addHeaderLine('Pragma', 'public');
            $headers->addHeaderLine('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
            $response->setHeaders($headers);
            $this->pg()->log('Se generó y descargó el acta de examen privado del estudiante ' . $nombreEstudiante . '.', LM::SUCCESS, LM::CREATE);
            return $response;
        } catch (\Exception $e) {
            $this->flashMessenger()->addErrorMessage('Error al generar el acta: ' . $e->getMessage());
            $_SESSION['acta_examen_privado_form'] = $this->params()->fromPost();
            $this->pg()->log('Error al generar el acta de examen privado del proceso ' . $idProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return $this->redirect()->toRoute('examen', ['action' => 'acta-examen-privado', 'id' => $idProceso]);
        }
    }

    /**
     * Previsualizar acta de examen privado antes de iniciar el examen.
     * Muestra HTML con el contenido hasta el párrafo de apertura.
     */
    public function previsualizarActaExamenPrivadoAction()
    {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            return new JsonModel(['status' => 'error', 'message' => 'Proceso no válido']);
        }

        $proceso = $this->examenManager->getProceso($idProceso);
        $estudiante = $this->examenManager->getEstudiantePorProceso($idProceso);
        $terna = $this->examenManager->getTerna($idProceso);

        $nombreEstudiante = trim($estudiante['nombre_completo'] ?? '');
        $carrera = $estudiante['carrera'] ?? 'N/A';
        $registroAcademico = $estudiante['registro_academico'] ?? '';
        $temaTesis = $proceso['tema_tesis'] ?? '';

        $sexo = $estudiante['sexo'] ?? '';
        $esFemenino = in_array(strtoupper($sexo), ['F', 'M', 'MUJER', '2'], true);
        $prefijoAlumno = $esFemenino ? 'la alumna' : 'el alumno';

        $fechaExamen = $proceso['fecha_examen_privado'] ?? '';
        $horaExamen = $proceso['hora_examen_privado'] ?? '';

        $fechaExamenFormateada = $fechaExamen;
        if (!empty($fechaExamen)) {
            $dtF = \DateTime::createFromFormat('Y-m-d', $fechaExamen);
            if (!$dtF) {
                $dtF = \DateTime::createFromFormat('d/m/Y', $fechaExamen);
            }
            if ($dtF) {
                $diasEs   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
                $mesesEs  = ['enero','febrero','marzo','abril','mayo','junio',
                             'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                $fechaExamenFormateada = $diasEs[(int)$dtF->format('w')]
                    . ' ' . (int)$dtF->format('j')
                    . ' de ' . $mesesEs[(int)$dtF->format('n') - 1]
                    . ' de ' . $dtF->format('Y');
            }
        }

        $decano = $this->config['decano']['nombre'] ?? 'Decano';
        $secretarioAcademico = $this->examenManager->getNombreSecretarioExamenPrivado();

        $examinadores = $terna['examinadores'] ?? [];
        $nombresExaminadores = [];
        foreach ($examinadores as $ex) {
            $nombre = ($ex['titulo'] ?? '') ? trim($ex['titulo'] . ' ' . ($ex['nombre'] ?? '')) : ($ex['nombre'] ?? '');
            if (!empty($nombre)) {
                $nombresExaminadores[] = $nombre;
            }
        }
        $examinadoresTexto = implode(', ', $nombresExaminadores);

        $horaTextoPartes = !empty($horaExamen) ? $this->horaATextoPartes($horaExamen) : ['hora' => '', 'minutos' => ''];

        $vm = new ViewModel([
            'nombreEstudiante'      => $nombreEstudiante,
            'carrera'               => $carrera,
            'registroAcademico'     => $registroAcademico,
            'temaTesis'             => $temaTesis,
            'prefijoAlumno'         => $prefijoAlumno,
            'fechaExamenFormateada' => $fechaExamenFormateada,
            'horaExamen'            => $horaExamen,
            'horaTexto'             => $horaTextoPartes['hora'] . ' horas con ' . $horaTextoPartes['minutos'],
            'horaTextoHora'         => $horaTextoPartes['hora'],
            'horaTextoMinutos'      => $horaTextoPartes['minutos'],
            'decano'                => $decano,
            'secretarioAcademico'   => $secretarioAcademico,
            'examinadoresTexto'     => $examinadoresTexto,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    /**
     * Convierte un número entero (0-100) a texto en español.
     */
    private function numeroATexto(int $numero): string
    {
        if ($numero === 0) {
            return 'cero';
        }
        if ($numero === 100) {
            return 'cien';
        }

        $unidades = [
            1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
            6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
            11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
            16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
        ];

        $decenas = [
            2 => 'veinte', 3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta',
            6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa',
        ];

        if ($numero < 20) {
            return $unidades[$numero] ?? '';
        }

        if ($numero < 30) {
            return 'veinti' . ($unidades[$numero - 20] ?? '');
        }

        $decena = (int) ($numero / 10);
        $unidad = $numero % 10;

        if ($unidad === 0) {
            return $decenas[$decena] ?? '';
        }

        return ($decenas[$decena] ?? '') . ' y ' . ($unidades[$unidad] ?? '');
    }

    /**
     * Convierte una hora en formato 12h (AM/PM) o 24h a texto en español.
     * Ejemplo: "01:10 PM" -> "trece horas con diez"
     */
    private function horaATextoPartes(string $hora): array
    {
        $numerosEs = [
            0 => 'cero', 1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
            6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
            11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
            16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
            20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés',
            24 => 'veinticuatro', 25 => 'veinticinco', 26 => 'veintiséis', 27 => 'veintisiete',
            28 => 'veintiocho', 29 => 'veintinueve', 30 => 'treinta', 31 => 'treinta y uno',
            32 => 'treinta y dos', 33 => 'treinta y tres', 34 => 'treinta y cuatro',
            35 => 'treinta y cinco', 36 => 'treinta y seis', 37 => 'treinta y siete',
            38 => 'treinta y ocho', 39 => 'treinta y nueve', 40 => 'cuarenta',
            41 => 'cuarenta y uno', 42 => 'cuarenta y dos', 43 => 'cuarenta y tres',
            44 => 'cuarenta y cuatro', 45 => 'cuarenta y cinco', 46 => 'cuarenta y seis',
            47 => 'cuarenta y siete', 48 => 'cuarenta y ocho', 49 => 'cuarenta y nueve',
            50 => 'cincuenta', 51 => 'cincuenta y uno', 52 => 'cincuenta y dos',
            53 => 'cincuenta y tres', 54 => 'cincuenta y cuatro', 55 => 'cincuenta y cinco',
            56 => 'cincuenta y seis', 57 => 'cincuenta y siete', 58 => 'cincuenta y ocho',
            59 => 'cincuenta y nueve'
        ];

        // Si viene en formato 12h (AM/PM), convertir a 24h
        $hora24 = $hora;
        if (stripos($hora, 'AM') !== false || stripos($hora, 'PM') !== false) {
            $dt = \DateTime::createFromFormat('h:i A', strtoupper(trim($hora)));
            if ($dt) {
                $hora24 = $dt->format('H:i');
            }
        }

        $partes = explode(':', $hora24);
        if (count($partes) < 2) {
            return ['hora' => $hora, 'minutos' => ''];
        }

        $h = (int)$partes[0];
        $m = (int)$partes[1];

        return [
            'hora'    => $numerosEs[$h] ?? (string)$h,
            'minutos' => $numerosEs[$m] ?? (string)$m,
        ];
    }

    private function horaATexto(string $hora): string
    {
        $partes = $this->horaATextoPartes($hora);
        return $partes['hora'] . ' horas con ' . $partes['minutos'];
    }

    // 5. ACTAS DE EXAMEN GENERAL (ACTO DE GRADUACIÓN) --------------------------------

    /**
     * Listado de estudiantes con notificación grupal enviada
     * que aún no tienen acta de graduación generada.
     */
    public function actasExamenGeneralAction()
    {
        $busqueda   = $this->params()->fromQuery('busqueda', '') ?: null;
        $estadoActa = $this->params()->fromQuery('estado_acta', '') ?: null;

        $procesos = $this->examenManager->getProcesosConNotificacionGeneral([
            'busqueda'    => $busqueda,
            'estado_acta' => $estadoActa,
        ]);

        $this->pg()->log('Se consultó el listado de actas de examen general pendientes.', LM::SUCCESS, LM::VIEW);
        return new ViewModel([
            'procesos'     => $procesos,
            'busqueda'     => $busqueda,
            'estado_acta'  => $estadoActa,
        ]);
    }

    /**
     * Formulario para generar el acta de examen general.
     */
    public function actaExamenGeneralAction()
    {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            $this->flashMessenger()->addErrorMessage('Proceso no válido');
            return $this->redirect()->toRoute('examen', ['action' => 'actas-examen-general']);
        }

        $datos = $this->examenManager->getDatosActaGeneral($idProceso);
        if (!$datos) {
            $this->flashMessenger()->addErrorMessage('Proceso no encontrado o no apto para generar acta.');
            return $this->redirect()->toRoute('examen', ['action' => 'actas-examen-general']);
        }

        // Obtener configuración de Decano y Secretario
        $decano = $this->config['decano']['nombre'] ?? 'Decano';
        $secretario = $this->examenManager->getNombreSecretarioExamenPrivado();

        // Lista de docentes internos para dropdown de examinadores
        $docentes = $this->examenManager->getDocentes();

        $this->pg()->log('Se consultó el formulario de generación de acta general del estudiante ' . $datos['nombre_completo'] . '.', LM::SUCCESS, LM::VIEW);
        return new ViewModel([
            'datos'      => $datos,
            'decano'     => $decano,
            'secretario' => $secretario,
            'docentes'   => $docentes,
        ]);
    }

    /**
     * Genera el acta de examen general en formato DOCX y la descarga.
     * Al mismo tiempo guarda los datos en la base de datos.
     */
    public function generarActaGeneralAction()
    {
        $idProceso = (int) $this->params()->fromRoute('id', 0);
        if ($idProceso <= 0) {
            $this->flashMessenger()->addErrorMessage('Proceso no válido');
            return $this->redirect()->toRoute('examen', ['action' => 'actas-examen-general']);
        }

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->redirect()->toRoute('examen', ['action' => 'acta-examen-general', 'id' => $idProceso]);
        }

        try {
            $datosProceso = $this->examenManager->getDatosActaGeneral($idProceso);
            if (!$datosProceso) {
                $this->flashMessenger()->addErrorMessage('Proceso no encontrado');
                return $this->redirect()->toRoute('examen', ['action' => 'actas-examen-general']);
            }

            $numeroRecibo   = trim((string) $request->getPost('numero_recibo', ''));
            $promedio       = trim((string) $request->getPost('promedio', ''));
            $horaFirma      = trim((string) $request->getPost('hora_firma', ''));
            $lugar          = trim((string) $request->getPost('lugar', ''));
            $examinador1    = trim((string) $request->getPost('examinador_1', ''));
            $examinador2    = trim((string) $request->getPost('examinador_2', ''));
            $examinador3    = trim((string) $request->getPost('examinador_3', ''));
            $acuerdoDecanato = trim((string) $request->getPost('acuerdo_decanato', ''));

            // DEBUG: Log datos recibidos para diagnóstico
            error_log('[ActaGeneral DEBUG] Proceso ' . $idProceso . ' - POST datos: ' . json_encode([
                'numero_recibo' => $numeroRecibo,
                'hora_firma' => $horaFirma,
                'lugar' => $lugar,
                'examinador_1' => $examinador1,
                'examinador_2' => $examinador2,
                'examinador_3' => $examinador3,
            ]));

            $camposFaltantes = [];
            if ($numeroRecibo === '')      $camposFaltantes[] = 'número de recibo';
            if ($horaFirma === '')         $camposFaltantes[] = 'hora de firma';
            if ($lugar === '')             $camposFaltantes[] = 'lugar';
            if ($examinador1 === '')       $camposFaltantes[] = 'examinador 1';
            if ($examinador2 === '')       $camposFaltantes[] = 'examinador 2';
            if ($examinador3 === '')       $camposFaltantes[] = 'examinador 3';
            if ($acuerdoDecanato === '')   $camposFaltantes[] = 'acuerdo de decanato';
            if ($promedio === '')          $camposFaltantes[] = 'promedio';

            if (!empty($camposFaltantes)) {
                $this->flashMessenger()->addErrorMessage('Faltan los siguientes campos obligatorios: ' . implode(', ', $camposFaltantes) . '.');
                return $this->redirect()->toRoute('examen', ['action' => 'acta-examen-general', 'id' => $idProceso]);
            }

            $userId = (int) $this->identity();

            // Preparar datos comunes para guardar o actualizar
            $datosGuardar = [
                'cod_proceso'      => $idProceso,
                'fecha_examen'     => $datosProceso['fecha_examen_general'],
                'hora_examen'      => $datosProceso['hora_examen_general'],
                'numero_recibo'    => $numeroRecibo,
                'promedio'         => $promedio !== '' ? $promedio : null,
                'acuerdo_decanato' => $acuerdoDecanato !== '' ? $acuerdoDecanato : null,
                'hora_firma'       => $horaFirma,
                'lugar'            => $lugar,
                'examinador_1'     => $examinador1,
                'examinador_2'     => $examinador2,
                'examinador_3'     => $examinador3,
                'generado_por'     => $userId,
            ];

            $numeroActaExistente = $datosProceso['numero_acta'] ?? '';
            if (empty($numeroActaExistente)) {
                $this->examenManager->guardarActaGeneral($datosGuardar);
            } else {
                $this->examenManager->actualizarActaGeneral($datosGuardar);
            }

            // Recargar datos para obtener el número de acta recién generado o actualizado
            $datosProceso = $this->examenManager->getDatosActaGeneral($idProceso);

            // ── Generar DOCX ────────────────────────────────────────────────
            $nombreEstudiante = trim($datosProceso['nombre_completo'] ?? '');
            $apellidosEstudiante = mb_strtolower($datosProceso['apellidos'] ?? '', 'UTF-8');
            $carrera         = $datosProceso['carrera'] ?? 'N/A';
            $registro        = $datosProceso['registro_academico'] ?? '';
            $temaTesis       = $datosProceso['tema_tesis'] ?? '';
            $madrinaTipo   = $datosProceso['madrina_tipo'] ?? '';
            $madrinaNombre = $datosProceso['madrina_nombre'] ?? '';
            $madrinaTitulo = $datosProceso['madrina_titulo'] ?? '';
            $fechaExamen     = $datosProceso['fecha_examen_general'] ?? '';
            $horaExamen      = $datosProceso['hora_examen_general'] ?? '';

            $sexo = strtoupper($datosProceso['sexo'] ?? '');
            $esFemenino = in_array($sexo, ['F', 'M', 'MUJER', '2'], true);
            $prefijoAlumno = $esFemenino ? 'la alumna' : 'el alumno';

            // Formatear fecha: "sábado uno de agosto de 2026" (día en palabras)
            $fechaExamenFormateada = $fechaExamen;
            $fechaExamenFormateadaGeneral = $fechaExamen;
            if (!empty($fechaExamen)) {
                $dtF = \DateTime::createFromFormat('Y-m-d', $fechaExamen);
                if ($dtF) {
                    $diasEs  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
                    $mesesEs = ['enero','febrero','marzo','abril','mayo','junio',
                                'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    $diasTexto = [
                        1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
                        6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
                        11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
                        16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
                        20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés',
                        24 => 'veinticuatro', 25 => 'veinticinco', 26 => 'veintiséis', 27 => 'veintisiete',
                        28 => 'veintiocho', 29 => 'veintinueve', 30 => 'treinta', 31 => 'treinta y uno',
                    ];
                    $diaNumero = (int)$dtF->format('j');
                    $fechaExamenFormateada = $diasEs[(int)$dtF->format('w')]
                        . ' ' . ($diasTexto[$diaNumero] ?? (string)$diaNumero)
                        . ' de ' . $mesesEs[(int)$dtF->format('n') - 1]
                        . ' de ' . $dtF->format('Y');
                    $fechaExamenFormateadaGeneral = ($diasTexto[$diaNumero] ?? (string)$diaNumero)
                        . ' de ' . $mesesEs[(int)$dtF->format('n') - 1]
                        . ' de ' . $dtF->format('Y');
                }
            }

            // Hora sin segundos: "22:10"
            if (!empty($horaExamen)) {
                $horaExamen = date('H:i', strtotime($horaExamen));
            }

            $horaTexto = '';
            if (!empty($horaExamen)) {
                $horaTexto = $this->horaATexto($horaExamen);
            }

            $decano = $this->config['decano']['nombre'] ?? 'Decano';
            $secretarioAcademico = $this->examenManager->getNombreSecretarioExamenPrivado();

            // Reutilizar el número de acta que acaba de generarse
            // (ya está en $datosProceso tras guardarActaGeneral → getDatosActaGeneral lo recarga)
            $numeroActa = $datosProceso['numero_acta'] ?? 'S/N';

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultFontName('Lustria');
            $phpWord->setDefaultFontSize(11);

            // Configuración de página tipo Carta (Letter 8.5x11 pulgadas)
            $section = $phpWord->addSection([
                'paperSize'   => 'Legal',
                'marginTop'   => 1417,  // 2.5 cm
                'marginBottom'=> 1417,  // 2.5 cm
                'marginLeft'  => 1134,  // 2 cm
                'marginRight' => 1134,  // 2 cm
            ]);

            // Encabezado centrado
            $section->addText('Universidad de San Carlos de Guatemala', [], ['alignment' => 'center']);
            $section->addText('Facultad de Arquitectura', [], ['alignment' => 'center']);
            $section->addText('Exámenes Públicos', [], ['alignment' => 'center']);
            $section->addText('Libro de Actas', ['bold' => true], ['alignment' => 'center']);

            $section->addTextBreak(1);

            // Datos del estudiante alineados a la derecha
            if ($acuerdoDecanato !== '') {
                $section->addText('Acuerdo. ' . $acuerdoDecanato, [], ['alignment' => 'right']);
            }
            $section->addText('Carnet No. ' . $registro, [], ['alignment' => 'right']);
            $section->addText('No Recibo. ' . $numeroRecibo, [], ['alignment' => 'right']);
            if ($promedio !== '') {
                $section->addText('Promedio. ' . $promedio, [], ['alignment' => 'right']);
            }
            
            $section->addTextBreak(1);

            // Número de acta centrado después de los datos del estudiante
            $section->addText('ARQ-' . $numeroActa, ['bold' => true], ['alignment' => 'center']);
            $section->addTextBreak(1);

            // Determinar verbo de aprobación según promedio (>61 = APROBAR, <=61 = DESAPROBAR)
            $verboAprobacion = 'APROBAR';
            if ($promedio !== '') {
                $notaNum = (float) $promedio;
                $verboAprobacion = $notaNum > 61 ? 'aprobar' : 'desaprobar';
            }

            // Artículo según género: masculino = 'al', femenino = 'a la', por defecto = 'al'
            $articuloEstudiante = $esFemenino ? 'a la' : 'al';
            $preposicionEstudiante = $esFemenino ? 'de la' : 'del';

            // Cuerpo del acta (un solo párrafo, sin salto de línea entre el título y la deliberación)
            $cuerpoRun = $section->addTextRun(['alignment' => 'both']);
            $cuerpoRun->addText(
                "El {$fechaExamenFormateadaGeneral}, a las {$horaExamen} horas, en el {$lugar}, nos reunimos los infrascritos miembros del Tribunal Examinador, presidido por el Señor Decano de la Facultad de Arquitectura, para realizar el Examen Público de Graduación {$preposicionEstudiante} estudiante {$nombreEstudiante} quien presenta el proyecto de graduación \"" . mb_strtoupper($temaTesis, 'UTF-8') . "\". "
            );
            $egresadoTexto = $esFemenino ? 'egresada' : 'egresado';
            $horaFirma24 = date('H:i', strtotime($horaFirma));

            $cuerpoRun->addText(
                "Luego de la presentación y defensa del proyecto, los miembros del Tribunal Examinador deliberamos sobre la calificación del mismo y considerando que sí llena los requisitos de ley, acordamos {$verboAprobacion} {$articuloEstudiante} estudiante {$apellidosEstudiante}, y conferirle; previo el juramento de ley; el título de {$carrera}, {$egresadoTexto} de la Escuela de Arquitectura de la Facultad de Arquitectura de la Universidad de San Carlos de Guatemala, en fe de lo cual firmamos la presente Acta a las {$horaFirma24} horas, en el mismo lugar y fecha indicados al inicio del acta."
            );
            $section->addTextBreak(1);

            // Tribunal Examinador centrado y en negrita
            $section->addText('Tribunal Examinador', ['bold' => true], ['alignment' => 'center']);
            $section->addTextBreak(1);

            // Tabla de firmas
            $tableStyle = [
                'borderSize'  => 0,
                'borderColor' => 'FFFFFF',
                'cellMargin'  => 80,
                'alignment'   => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                'width'       => 100,
            ];
            $cellStyle = ['valign' => 'top'];
            $cellTextStyle = ['alignment' => 'center'];

            // Tabla 1: Decano y Secretario (2 columnas, centrada)
            $tableDecSec = $section->addTable($tableStyle);
            $tableDecSec->addRow();
            $cellDecano = $tableDecSec->addCell(4900, $cellStyle);
            $cellDecano->addText('', [], $cellTextStyle);
            $cellDecano->addText('', [], $cellTextStyle);
            $cellDecano->addText('', [], $cellTextStyle);
            $cellDecano->addText($decano, [], $cellTextStyle);
            $cellDecano->addText('Decano', [], $cellTextStyle);

            $cellSecretario = $tableDecSec->addCell(4900, $cellStyle);
            $cellSecretario->addText('', [], $cellTextStyle);
            $cellSecretario->addText('', [], $cellTextStyle);
            $cellSecretario->addText('', [], $cellTextStyle);
            $cellSecretario->addText($secretarioAcademico, [], $cellTextStyle);
            $cellSecretario->addText('Secretario', [], $cellTextStyle);

            $section->addTextBreak(1);

            // Tabla 2: Examinador 1, 2 y 3 (tres columnas)
            $tableEx = $section->addTable($tableStyle);
            $tableEx->addRow();
            $cellEx1 = $tableEx->addCell(3400, $cellStyle);
            $cellEx1->addText('', [], $cellTextStyle);
            $cellEx1->addText('', [], $cellTextStyle);
            $cellEx1->addText('', [], $cellTextStyle);
            $cellEx1->addText($examinador1, [], $cellTextStyle);
            $cellEx1->addText('Examinador', [], $cellTextStyle);

            $cellEx2 = $tableEx->addCell(3400, $cellStyle);
            $cellEx2->addText('', [], $cellTextStyle);
            $cellEx2->addText('', [], $cellTextStyle);
            $cellEx2->addText('', [], $cellTextStyle);
            $cellEx2->addText($examinador2, [], $cellTextStyle);
            $cellEx2->addText('Examinador', [], $cellTextStyle);

            $cellEx3 = $tableEx->addCell(3400, $cellStyle);
            $cellEx3->addText('', [], $cellTextStyle);
            $cellEx3->addText('', [], $cellTextStyle);
            $cellEx3->addText('', [], $cellTextStyle);
            $cellEx3->addText($examinador3, [], $cellTextStyle);
            $cellEx3->addText('Examinador', [], $cellTextStyle);

            // Sustentante fuera de la tabla, centrado
            $section->addTextBreak(1);
            $section->addText('Sustentante', ['bold' => true], ['alignment' => 'center']);
            $section->addTextBreak(4);

            $firmaSust = $section->addTextRun(['alignment' => 'center']);
            $firmaSust->addText('', [], $cellTextStyle);
            $firmaSust->addText('', [], $cellTextStyle);
            $firmaSust->addText('', [], $cellTextStyle);
            $firmaSust->addText($nombreEstudiante, [], $cellTextStyle);

            // Madrina / Padrino (si aplica)
            if ($madrinaNombre !== '') {
                $lineaMadrina = ($madrinaTitulo !== '')
                    ? $madrinaTitulo . ' ' . $madrinaNombre
                    : $madrinaNombre;
                $etiquetaMadrina = ($madrinaTipo === 'padrino') ? 'Padrino' : 'Madrina';

                $section->addTextBreak(5);
                $section->addText($lineaMadrina, [], ['alignment' => 'center']);
                $section->addText($etiquetaMadrina, [], ['alignment' => 'center']);
            }

            // Generar archivo y descargar
            $filename = 'Acta_Examen_General_' . $idProceso . '.docx';
            $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord') . '.docx';
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempFile);

            $fileContent = file_get_contents($tempFile);
            unlink($tempFile);

            $response = $this->getResponse();
            $response->setContent($fileContent);
            $headers = $response->getHeaders();
            $headers->clearHeaders();
            $headers->addHeaderLine('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $headers->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $headers->addHeaderLine('Content-Length', strlen($fileContent));
            $headers->addHeaderLine('Pragma', 'public');
            $headers->addHeaderLine('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
            $response->setHeaders($headers);
            $this->pg()->log('Se generó y descargó el acta de examen general del estudiante ' . $datos['nombre_completo'] . '.', LM::SUCCESS, LM::CREATE);
            return $response;
        } catch (\Exception $e) {
            $mensajeError = 'Error al generar el acta: ' . $e->getMessage();
            error_log('[ActaGeneral] Proceso ' . $idProceso . ': ' . $mensajeError);
            error_log('[ActaGeneral] Trace: ' . $e->getTraceAsString());
            $this->flashMessenger()->addErrorMessage($mensajeError);
            $this->pg()->log('Error al generar el acta de examen general del proceso ' . $idProceso . ': ' . $e->getMessage(), LM::FAILURE, LM::CREATE);
            return $this->redirect()->toRoute('examen', ['action' => 'acta-examen-general', 'id' => $idProceso]);
        }
    }

}

