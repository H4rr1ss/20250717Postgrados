<?php

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Service\FormularioAdmisionManager;
use Eep\ValueObject\Message;
use Eep\Service\LogManager as LM;
use Eep\Form\FormularioAdmisionForm;

class FormularioAdmisionController extends AbstractActionController {
    private $formularioAdmisionManager;
    
    public function __construct(FormularioAdmisionManager $formularioAdmisionManager) {
        $this->formularioAdmisionManager = $formularioAdmisionManager;
    }


















    /**
     * VISTA 5: Archivar formulario de admisión
     */
    public function archivarAction() {
        $idFormulario = (int) $this->params()->fromRoute('id', 0);
        if ($idFormulario <= 0) {
            $msg = new Message('Error', 'ID de formulario inválido', Message::RED);
            $this->pg()->log($msg, LM::FAILURE, LM::UPDATE);
        } else {
            $result = $this->formularioAdmisionManager->archivarFormulario($idFormulario);
            if ($result->get()) {
                $msg = new Message('Formulario archivado', 'El formulario fue archivado exitosamente.', Message::GREEN);
            } else {
                $msg = new Message('Error', $result->getMsg(), Message::RED);
            }
            $this->pg()->log($msg, $result->get() ? LM::SUCCESS : LM::FAILURE, LM::UPDATE);
        }
        // Obtener listas actualizadas
        $formulariosActivosResult = $this->formularioAdmisionManager->getFormulariosActivos();
        $formulariosActivos = $formulariosActivosResult->get() ? $formulariosActivosResult->getObj() : [];
        $formulariosArchivadosResult = $this->formularioAdmisionManager->getFormulariosArchivados();
        $formulariosArchivados = $formulariosArchivadosResult->get() ? $formulariosArchivadosResult->getObj() : [];
        $view = new ViewModel([
            'formulariosActivos' => $formulariosActivos,
            'formulariosArchivados' => $formulariosArchivados,
            'activosMsg' => $formulariosActivosResult->get() ? null : new Message('Error', $formulariosActivosResult->getMsg(), Message::RED),
            'archivadosMsg' => $formulariosArchivadosResult->get() ? null : new Message('Error', $formulariosArchivadosResult->getMsg(), Message::RED),
            'msg' => $msg
        ]);
        $view->setTemplate('eep/formulario-admision/index');
        return $view;
    }
    /**
     * VISTA 6: Eliminar formulario de admisión
     */
    public function eliminarAction() {
        $idFormulario = (int) $this->params()->fromRoute('id', 0);
        if ($idFormulario <= 0) {
            $msg = new Message('Error', 'ID de formulario inválido', Message::RED);
            $status = LM::FAILURE;
        } else {
            $result = $this->formularioAdmisionManager->eliminarFormulario($idFormulario);
            if ($result->get()) {
                $msg = new Message('Formulario Eliminado', 'El formulario fue eliminado exitosamente.', Message::GREEN);
                $status = LM::SUCCESS;
            } else {
                $msg = new Message('Error', $result->getMsg(), Message::RED);
                $status = LM::FAILURE;
            }
        }
        $this->pg()->log($msg, $status, LM::DELETE);
        // Refrescar listas
        $activos = $this->formularioAdmisionManager->getFormulariosActivos();
        $archivados = $this->formularioAdmisionManager->getFormulariosArchivados();
        $view = new ViewModel([
            'formulariosActivos' => $activos->get() ? $activos->getObj() : [],
            'formulariosArchivados' => $archivados->get() ? $archivados->getObj() : [],
            'activosMsg' => $activos->get() ? null : new Message('Error', $activos->getMsg(), Message::RED),
            'archivadosMsg' => $archivados->get() ? null : new Message('Error', $archivados->getMsg(), Message::RED),
            'msg' => $msg
        ]);
        $view->setTemplate('eep/formulario-admision/index');
        return $view;
    }
    


























    




    /**
     * VISTA 1: Lista de formularios (activos y archivados)
     */
    public function indexAction() {
        // Obtener formularios activos
        $formulariosActivosResult = $this->formularioAdmisionManager->getFormulariosActivos();
        $formulariosActivos = $formulariosActivosResult->get() ? $formulariosActivosResult->getObj() : [];
        
        // Obtener formularios archivados
        $formulariosArchivadosResult = $this->formularioAdmisionManager->getFormulariosArchivados();
        $formulariosArchivados = $formulariosArchivadosResult->get() ? $formulariosArchivadosResult->getObj() : [];
        
        // Log de acceso
        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        
        return new ViewModel([
            'formulariosActivos' => $formulariosActivos,
            'formulariosArchivados' => $formulariosArchivados,
            'activosMsg' => $formulariosActivosResult->get() ? null : new Message('Error', $formulariosActivosResult->getMsg(), Message::RED),
            'archivadosMsg' => $formulariosArchivadosResult->get() ? null : new Message('Error', $formulariosArchivadosResult->getMsg(), Message::RED)
        ]);
    }
    
    /**
     * VISTA 2: Lista de respuestas de un formulario específico
     */
    public function respuestasAction() {
        $idFormulario = (int) $this->params()->fromRoute('id', 0);
        
        if ($idFormulario <= 0) {
            $this->pg()->log('ID de formulario inválido', LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }
        
        // Obtener información del formulario
        $formularioResult = $this->formularioAdmisionManager->getFormulario($idFormulario);
        if (!$formularioResult->get()) {
            $this->pg()->log($formularioResult->getMsg(), LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }
        $formulario = $formularioResult->getObj();
        
        // Obtener respuestas del formulario
        $respuestasResult = $this->formularioAdmisionManager->getRespuestasFormulario($idFormulario);
        $respuestas = $respuestasResult->get() ? $respuestasResult->getObj() : [];
        
        // Manejar acciones POST (eliminar respuesta)
        $message = null;
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            
            if (isset($data['eliminar_respuesta'])) {
                $idRespuesta = (int) $data['eliminar_respuesta'];
                $eliminarResult = $this->formularioAdmisionManager->eliminarRespuesta($idRespuesta);
                
                $message = new Message(
                    $eliminarResult->get() ? 'Respuesta Eliminada' : 'Error al Eliminar',
                    $eliminarResult->getMsg(),
                    $eliminarResult->get() ? Message::GREEN : Message::RED
                );
                
                $this->pg()->log($eliminarResult->get() ? null : $eliminarResult->getMsg(), 
                               $eliminarResult->get() ? LM::SUCCESS : LM::FAILURE, LM::DELETE);
                
                // Refrescar lista de respuestas
                if ($eliminarResult->get()) {
                    $respuestasResult = $this->formularioAdmisionManager->getRespuestasFormulario($idFormulario);
                    $respuestas = $respuestasResult->get() ? $respuestasResult->getObj() : [];
                }
            }
        }
        
        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        
        return new ViewModel([
            'formulario' => $formulario,
            'respuestas' => $respuestas,
            'message' => $message,
            'respuestasMsg' => $respuestasResult->get() ? null : new Message('Error', $respuestasResult->getMsg(), Message::RED)
        ]);
    }
    
    /**
     * VISTA 3: Ver/Editar respuesta específica de un aspirante
     */
    public function editarRespuestaAction() {
        $idRespuesta = (int) $this->params()->fromRoute('id', 0);
        
        if ($idRespuesta <= 0) {
            $this->pg()->log('ID de respuesta inválido', LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }
        
        // Obtener respuesta detallada (solo campos)
        $respuestaResult = $this->formularioAdmisionManager->getRespuestaDetallada($idRespuesta);
        if (!$respuestaResult->get()) {
            $this->pg()->log($respuestaResult->getMsg(), LM::FAILURE, LM::READ);
            return $this->redirect()->toRoute('formulario-admision');
        }
        $respuestasCampos = $respuestaResult->getObj();
        
        // Obtener ID del formulario desde el primer campo (todos pertenecen al mismo formulario)
        $idFormulario = !empty($respuestasCampos) ? $respuestasCampos[0]['id_formulario'] ?? null : null;
        $formulario = null;
        if ($idFormulario) {
            $formularioResult = $this->formularioAdmisionManager->getFormulario($idFormulario);
            $formulario = $formularioResult->get() ? $formularioResult->getObj() : null;
        }
        
        $message = null;
        
        // Manejar edición de respuesta
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            
            // TODO: Implementar lógica de actualización de respuesta
            // Por ahora solo mostrar mensaje
            $message = new Message('Función en desarrollo', 'La edición de respuestas se implementará en el siguiente paso', Message::YELLOW);
        }
        
        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        
        return new ViewModel([
            'respuestasCampos' => $respuestasCampos,
            'formulario' => $formulario,
            'message' => $message
        ]);
    }
    
    /**
     * VISTA 4: Crear nuevo formulario de admisión
     */
    public function crearAction() {
    // Crear formulario usando la clase Zend Form
    $formUrl = $this->url()->fromRoute('formulario-admision', ['action' => 'crear']);
    $form = new FormularioAdmisionForm($formUrl);
        
        $message = null;
        
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            $status = LM::SUCCESS;
            
            if ($form->isValid()) {
                $validData = $form->getData();
                
                // Agregar usuario que crea
                $validData['creado_por'] = $this->identity();
                
                $result = $this->formularioAdmisionManager->crearFormulario($validData);
                
                if ($result->get()) {
                    $formularioId = $result->getObj();
                    $message = new Message('Formulario Creado', 
                        "Formulario creado correctamente", 
                        Message::GREEN);
                    $form->clearData();
                } else {
                    $message = new Message('Error', $result->getMsg(), Message::RED);
                    $status = LM::FAILURE;
                }
            } else {
                $message = new Message('Campos faltantes', 'Hay campos que requieren corrección', Message::YELLOW);
                $status = LM::FAILURE;
            }
            
            $this->pg()->log($message ?? null, $status, LM::CREATE);
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        
        return new ViewModel([
            'form' => $form,
            'message' => $message
        ]);
    }
    /**
     * VISTA PÚBLICA: Mostrar formulario activo para aspirantes
     */
    public function publicAction() {
        // Usar layout minimal (sin sidebar ni menús)
        $this->layout('layout/empty');
        // Obtener el primer formulario activo
        $activosResult = $this->formularioAdmisionManager->getFormulariosActivos();
        $formularios = $activosResult->get() ? $activosResult->getObj() : [];
        if (empty($formularios)) {
            $msg = new Message('Info', 'No hay formularios de admisión disponibles en este momento.', Message::YELLOW);
            $this->pg()->log($msg, LM::SUCCESS, LM::VIEW);
            return new ViewModel(['message' => $msg]);
        }
        $formulario = $formularios[0];
        // Obtener campos activos
        $camposResult = $this->formularioAdmisionManager->getCamposFormulario($formulario->getIdFormulario());
        $campos = $camposResult->get() ? $camposResult->getObj() : [];
        $message = null;
        // Manejar envío público
        if ($this->getRequest()->isPost()) {
            // Obtener datos y archivos enviados
            $data = $this->params()->fromPost();
            $files = $this->getRequest()->getFiles()->toArray();
            $errors = [];
            foreach ($campos as $campo) {
                if ($campo->getRequerido()) {
                    if ($campo->getTipoCampo() === 'archivo') {
                        // Validar carga de archivo
                        $fileInfo = $files[$campo->getNombreCampo()] ?? null;
                        if (!$fileInfo || $fileInfo['error'] !== UPLOAD_ERR_OK) {
                            $errors[] = $campo->getEtiqueta();
                        }
                    } else {
                        if (empty($data[$campo->getNombreCampo()])) {
                            $errors[] = $campo->getEtiqueta();
                        }
                    }
                }
            }
            if (!empty($errors)) {
                $message = new Message('Errores en el formulario', 'Faltan campos obligatorios: ' . implode(', ', $errors), Message::RED);
                $this->pg()->log($message, LM::FAILURE, LM::CREATE);
            } else {
                // Guardar respuesta en la base de datos
                $files = $this->getRequest()->getFiles()->toArray();
                $result = $this->formularioAdmisionManager
                    ->registrarRespuestaPublica(
                        $formulario->getIdFormulario(),
                        $campos,
                        $data,
                        $files
                    );
                if ($result->get()) {
                    $message = new Message('Enviado', 'Formulario enviado correctamente', Message::GREEN);
                    $this->pg()->log($message, LM::SUCCESS, LM::CREATE);
                } else {
                    // Limpiar etiquetas HTML de mensaje de error
                    $message = new Message('Error', $result->getMsg(), Message::RED);
                    $this->pg()->log($message, LM::FAILURE, LM::CREATE);
                }
             }
        }
        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        return new ViewModel([
            'formulario' => $formulario,
            'campos'     => $campos,
            'message'    => $message,
        ]);
    }
}
                    
