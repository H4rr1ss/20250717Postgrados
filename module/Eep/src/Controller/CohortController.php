<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Form\CohortUsersForm;
use Eep\Service\CohortManager;
use Eep\Form\CohortForm as CF;
use Eep\ValueObject\Message;
use Eep\Service\LogManager as LM;

class CohortController extends AbstractActionController {

    private $cohortManager;

    public function __construct(CohortManager $cohortManager) {
        $this->cohortManager = $cohortManager;
    }

    private function getCohortView($params = []) {
        /* PARAMETERS:
         * - candidateForm
         * - candidateMsg
         * - fileForm
         * - fileMsg
         */
        if (!isset($params['queryForm'])) {
            $formUrl = $this->url()->fromRoute('cohort', ['action' => 'cohorts']);
            $params['queryForm'] = new CF(CF::TYPE_QUERY, $formUrl);
        }
        if (!isset($params['newForm'])) {
            $formUrl = $this->url()->fromRoute('cohort', ['action' => 'createCohort']);
            $params['newForm'] = new CF(CF::TYPE_NEW, $formUrl);
        }
        if (!isset($params['deleteForm'])) {
            $formUrl = $this->url()->fromRoute('cohort', ['action' => 'deleteCohort']);
            $params['deleteForm'] = new CF(CF::DELETE_COHORT, $formUrl);
        }
        $view = new ViewModel($params);
        $view->setTemplate('eep/cohort/cohorts');
        return ($view);
    }

    public function cohortsAction() { //$formUrl = $this->url()->fromRoute('user', ['action' => 'candidates']);
        $formUrl = $this->url()->fromRoute('cohort', ['action' => 'cohorts']);
        $queryForm = new CF(CF::TYPE_QUERY, $formUrl);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            //LOOKING FOR SUBMIT TYPE
            if (isset($params[CF::TYPE_QUERY])) {
                $queryForm->setData($params);
                if ($queryForm->isValid()) {
                    $params = $queryForm->getData();
                    $cohorts = $this->cohortManager->getCohorts($params[CF::START_DATE], $params[CF::FINISH_DATE]);
                    $this->pg()->log(null, LM::SUCCESS, LM::READ);
                } else {
                    $this->pg()->log($queryForm->getMessages(), LM::FAILURE, LM::READ);
                }
            } else {
                $deleteMsg = new Message("Solicitud Incorrecta", "Se solicitó la búsqueda de cohortes, pero se presionó un botón diferente.", Message::YELLOW);
                $this->pg()->log($deleteMsg, LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return $this->getCohortView([
                    'queryForm' => $queryForm,
                    'deleteMsg' => empty($deleteMsg) ? null : $deleteMsg,
                    'cohorts' => $cohorts ?? null
        ]);
    }

    public function createCohortAction() {
        $formUrl = $this->url()->fromRoute('cohort', ['action' => 'createCohort']);
        $newForm = new CF(CF::TYPE_NEW, $formUrl);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            if (isset($params[CF::TYPE_NEW])) {
                $newForm->setData($params);
                if ($newForm->isValid()) {
                    $params = $newForm->getData();
                    $res = $this->cohortManager->addCohort($params[CF::COHORT]);
                    $newMsg = new Message($res->get() ? "Cohorte Añadida" : "Cohorte No Añadida", $res);
                    if ($res->get() == false) {
                        $this->pg()->log($res, LM::FAILURE, LM::CREATE);
                    } else {
                        $this->pg()->log(null, LM::SUCCESS, LM::CREATE);
                    }
                } else {
                    $this->pg()->log($newForm->getMessages(), LM::FAILURE, LM::READ);
                }
            } else {
                $newMsg = new Message("Solicitud Incorrecta", "Se solicitó la creación de cohortes, pero se presionó un botón diferente.", Message::YELLOW);
                $this->pg()->log($newMsg, LM::FAILURE, LM::READ);
            }
        } else {
            $this->log(null, LM::SUCCESS, LM::VIEW);
        }
        return $this->getCohortView([
                    'newForm' => $newForm,
                    'newMsg' => empty($newMsg) ? null : $newMsg
        ]);
    }

    public function deleteCohortAction() {
        $formUrl = $this->url()->fromRoute('cohort', ['action' => 'deleteCohort']);
        $deleteForm = new CF(CF::TYPE_NEW, $formUrl);
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            if (isset($params[CF::DELETE_COHORT])) {
                $cohort = $params[CF::DELETE_COHORT];
                if (strtotime($cohort) == false) {
                    $deleteMsg = new Message('Cohorte Recibida Incorrecta', "La cohorte '$cohort' no parece ser una fecha.", Message::RED);
                    $this->pg()->log($deleteMsg, LM::FAILURE, LM::DELETE);
                } else {
                    $result = $this->cohortManager->deleteCohort($cohort);
                    $deleteMsg = new Message($result->get() ? "Cohorte Eliminada" : "Cohorte No Eliminada", $result);
                    if ($result->get() == false) {
                        $this->pg()->log($result, LM::FAILURE, LM::DELETE);
                    } else {
                        $this->pg()->log(null, LM::SUCCESS, LM::DELETE);
                    }
                }
            } else {
                $deleteMsg = new Message("Solicitud Incorrecta", "Se solicitó la eliminación de cohortes, pero se presionó un botón diferente.", Message::YELLOW);
                $this->pg()->log($deleteMsg, LM::FAILURE, LM::DELETE);
            }
        }
        return $this->getCohortView([
                    'deleteForm' => $deleteForm,
                    'deleteMsg' => empty($deleteMsg) ? null : $deleteMsg
        ]);
    }

    public function cohortStudentsAction() {
        //CREATING SIMPLE FORM
        $cohorts = $this->cohortManager->getCohorts();
        $form = new CohortUsersForm($cohorts);
        $careers = [];
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $cohortInput = $form->getData()[CohortUsersForm::COHORT];
                $cohort = empty($cohortInput) ? null : $cohortInput;
                $careers = $this->cohortManager->getCohortUsers($cohort);
                $this->pg()->log(null, LM::SUCCESS, LM::READ);
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::READ);
            }
        } else {
            $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        }
        return new ViewModel([
            'form' => $form,
            'careers' => $careers
        ]);
    }

}
