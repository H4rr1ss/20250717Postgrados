<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\ValueObject\Message;
use Eep\Service\MassiveLoadManager as MLM;
use Eep\Controller\GP;
use Eep\Entity\Result as R;
use Eep\Form\FileCandidatesForm as FileForm;
use Eep\Service\SatuManager;
use Eep\Service\GeneralManager as GM;
use Zend\View\Model\JsonModel;
use Eep\Service\LogManager as LM;

class MassiveLoadController extends AbstractActionController {

    private $loadManager;
    private $satuManager;

    public function __construct(MLM $massiveLoadManager, SatuManager $satuManager) {
        $this->loadManager = $massiveLoadManager;
        $this->satuManager = $satuManager;
    }

    public function getView($params) {
        $url = $this->url()->fromRoute('etl', ['action' => 'professor']);
        $params['professorForm'] = new FileForm($url, [], [], [], []);
        $url = $this->url()->fromRoute('etl', ['action' => 'student']);
        $params['studentForm'] = new FileForm($url, [], [], [], []);
        $url = $this->url()->fromRoute('etl', ['action' => 'timetable']);
        $params['timetableForm'] = new FileForm($url, [], [], [], []);
        $url = $this->url()->fromRoute('etl', ['action' => 'order']);
        $params['orderForm'] = new FileForm($url, [], [], [], []);
        $url = $this->url()->fromRoute('etl', ['action' => 'assignment']);
        $params['assignmentForm'] = new FileForm($url, [], [], [], []);
        $url = $this->url()->fromRoute('etl', ['action' => 'studentInscriptions']);
        $params['studentInscriptionsForm'] = new FileForm($url, [], [], [], []);
        //REDIRECTING VIEW
        $view = new ViewModel($params);
        $view->setTemplate('eep/etl/etl');
        return $view;
    }

    public function indexAction() {
        try {
            $result = $this->loadManager->prueba();
            if ($result->get()) {
                $msg = GP::Msg($result->getObj());
            } else {
                $msg = GP::Msg($result->getMsg());
            }
        } catch (\Exception $ex) {
            $msg = GP::Msg($ex->getMessage());
        }
        return $this->getView([
                    'msg' => $msg
        ]);
    }

    public function professorAction() {
        $msg = $this->upload(MLM::PROFESSOR);
        return $this->getView([
                    'msg' => $msg
        ]);
    }

    public function studentAction() {
        $msg = $this->upload(MLM::STUDENT);
        return $this->getView([
                    'msg' => $msg
        ]);
    }

    public function timetableAction() {
        $msg = $this->upload(MLM::TIMETABLE);
        return $this->getView([
                    'msg' => $msg
        ]);
    }

    public function orderAction() {
        $msg = $this->upload(MLM::ORDER);
        return $this->getView([
                    'msg' => $msg
        ]);
    }

    public function assignmentAction() {
        $msg = $this->upload(MLM::ASSIGNMENT);
        return $this->getView([
                    'msg' => $msg
        ]);
    }

    public function studentInscriptionsAction() {
        $msg = $this->upload(MLM::STUDENT_INSCRIPTIONS);
        return $this->getView([
                    'msg' => $msg
        ]);
    }

    public function updateUsersAction() {
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $user = $data['user'];
            $password = $data['password'];
            $response['status'] = false;
            $serverPassword = $this->satuManager->getGlobal(GM::SERVER_REQUEST_PASSWORD, false);

            if ($user == GM::USER && $password == $serverPassword) {
                $syncData = $this->satuManager->getGlobal(GM::SYNC_SATU, false) == true;
                if ($syncData) {
                    $result = $this->satuManager->updateUsers();
                    if ($result->get() == false) {
                        $response['description'] = GM::resultToText($result);
                    } else {
                        $response['status'] = true;
                        //$result->addMsg($result->getObj());
                        $response['description'] = GM::resultToText($result);
                    }
                } else {
                    $response['status'] = true;
                    $response['description'] = 'DESHABILITADO';
                }
            } else {
                $status = LM::FAILURE;
                $response['description'] = 'Autenticación fallida';
            }

            $this->pg()->log($response['description'] ?? null, $status ?? ($response['status'] ? LM::SUCCESS : LM::ERROR), LM::UPDATE, true);
            $view = new JsonModel($response);
            $view->setTerminal(true);
            return $view;
        }
        $this->pg()->log('Solicitud errónea, no se realizó la petición POST.', LM::ERROR, LM::UPDATE, true);
        $msg = new Message('Solicitud Errónea', 'No se realizó la solicitud POST', Message::RED);
        $viewModel = new ViewModel([
            'msg' => $msg ?? null
        ]);
        $viewModel->setTemplate('eep/msg');
        return $viewModel;
    }

    private function upload($etlType) {
        $fileForm = new FileForm(null, [], [], [], []);
        if ($this->getRequest()->isPost()) {

            //MERGE FILE INFORMATION
            $request = $this->getRequest();
            $data = array_merge_recursive(
                    $request->getPost()->toArray(), $request->getFiles()->toArray()
            );

            $fileForm->setData($data);
            //GENERATING VALIDATORS
            if ($fileForm->isValid()) {
                //GET DATA AND EXECUTE FILTERS (SAME FUNCTION)
                $data = $fileForm->getData();
                $filename = $data['file']['tmp_name'];
                $readingSuccessful = true;
                try {
                    //GETTING FILE DATA
                    $content = file($filename);
                    if (count($content) <= 1) {
                        $msg = new Message("Archivo Vacío", "El archivo está vacío", Message::YELLOW);
                        $readingSuccessful = false;
                    }
                } catch (\Exception $ex) {
                    $msg = new Message("Error", "No se pudo leer el archivo", Message::RED);
                    $readingSuccessful = false;
                }
                if ($readingSuccessful) {
                    //VALIDATING FILE INFO
                    $this->loadManager->beginTransaction();
                    $result = $this->loadManager->load($content, $etlType);
                    if ($result->get() == false) {
                        $this->loadManager->rollback();
                        $msg = new Message('Error', $result);
                    } else {
                        $this->loadManager->commit();
                        $msg = new Message('Exitoso', $result);
                    }
                }
            } else {
                $msg = new Message('Formulario Inválido', 'Formulario de archivo resultó inválido', Message::RED);
            }
        }
        return $msg ?? null;
    }

}
