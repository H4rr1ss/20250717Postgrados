<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Service\AcademyManager;
use Eep\Form\UpgCourseForm as upgForm;
use Eep\Entity\Order;
use Eep\ValueObject\Message;
use Eep\Form\UserIdForm as IdForm;
use Eep\Service\LogManager as LM;

class UpgCourseController extends AbstractActionController {

    private $academyManager;

    public function __construct(AcademyManager $academyManager) {
        $this->academyManager = $academyManager;
    }

    private function getUpgCourseView($msg = null, $form = null, $logView = false) {
        if ($form == null) {
            //CREATING NEW TYPE FORM
            $url = $this->url()->fromRoute('upgCourse', ['action' => 'create']);
            $form = new upgForm(upgForm::TYPE_NEW, $url);
        }
        //SEARCHING COURSES
        $result = $this->academyManager->getCourses(Order::CURSO_ACTUALIZACION);
        if ($result->get() == false) {
            $listMsg = new Message('Error Obteniendo Cursos', $result);
        } else {
            $coursesList = $result->getObj();
        }
        if ($logView) {
            $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::READ);
        }
        $view = new ViewModel([
            'form' => $form,
            'msg' => $msg,
            'listMsg' => $listMsg ?? null,
            'coursesList' => $coursesList ?? null,
            'editForm' => new upgForm(upgForm::TYPE_EDIT, $this->url()->fromRoute('upgCourse', ['action' => 'edit'])),
            'deleteForm' => new upgForm(upgForm::TYPE_DELETE, $this->url()->fromRoute('upgCourse', ['action' => 'delete']))
        ]);
        $view->setTemplate('eep/upgCourse/upg-course');
        return $view;
    }

    public function viewAction() {
        return $this->getUpgCourseView(null, null, true);
    }

    public function createAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $url = $this->url()->fromRoute('upgCourse', ['action' => 'create']);
            $form = new upgForm(upgForm::TYPE_NEW, $url);
            $form->setData($params);
            if ($form->isValid()) {
                $data = $form->getData();
                $name = $data[upgForm::NAME];
                $alias = $data[upgForm::ALIAS];
                $price = $data[upgForm::PRICE];
                $result = $this->academyManager->createCourse($name, $alias, $price, Order::CURSO_ACTUALIZACION);
                if ($result->get() == false) {
                    $msg = new Message('Curso No Agregado', $result);
                } else {
                    $msg = new Message('Curso Agregado', 'El curso fue agregado exitosamente', Message::GREEN);
                }
                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::CREATE);
            } else {
                $this->pg()->log($form->getMessages(), LM::FAILURE, LM::CREATE);
            }
        } else {
            $logView = true;
        }
        return $this->getUpgCourseView($msg ?? null, $form ?? null, $logView ?? false);
    }

    public function editAction() {
        $status = LM::FAILURE;
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $idForm = new IdForm(null, true);
            $idForm->setData([
                IdForm::USER => $params[upgForm::COURSE]
            ]);
            if ($idForm->isValid()) {
                $courseCode = $idForm->getData()[IdForm::USER];
                //SEARCHING COURSE
                $result = $this->academyManager->getCourse($courseCode, Order::CURSO_ACTUALIZACION);
                if ($result->get() == false) {
                    $msg = new Message('Error', $result);
                    $status = LM::ERROR;
                } else {
                    $data = $result->getObj();
                    $courseName = $data['nombre'];
                    $courseAlias = $data['alias'];
                    //CREATING FORM
                    $url = $this->url()->fromRoute('upgCourse', ['action' => 'save']);
                    $form = new upgForm(upgForm::TYPE_SAVE, $url);
                    $form->setData([
                        upgForm::COURSE => $courseCode,
                        upgForm::NAME => $courseName,
                        upgForm::ALIAS => $courseAlias
                    ]);
                    $status = LM::SUCCESS;
                }
            } else {
                $msg = new Message('Parámetros Incorrectos', 'Codigo de Curso: ' . $params[upgForm::COURSE], Message::RED);
            }
        } else {
            $msg = new Message('Error de Solicitud', 'Se solicitó edición sin datos', Message::RED);
        }
        $this->pg()->log($result ?? ($msg ?? null), $status, LM::VIEW);
        return $this->getUpgCourseView($msg ?? null, $form ?? null);
    }

    public function saveAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $url = $this->url()->fromRoute('upgCourse', ['action' => 'save']);
            $form = new upgForm(upgForm::TYPE_SAVE, $url);
            $form->setData($params);
            if ($form->isValid()) {
                $data = $form->getData();
                $courseCode = $data[upgForm::COURSE];
                $price = $data[upgForm::PRICE];
                $description = $data[upgForm::DESCRIPTION];
                //ADDING COURSE PRICE
                $result = $this->academyManager->addPrice($courseCode, Order::CURSO_ACTUALIZACION, $price, true, $description);
                if ($result->get() == false) {
                    $msg = new Message('Error', $result);
                } else {
                    $msg = new Message('Precio Agregado', 'Se agregó el nuevo precio al curso.', Message::GREEN);
                    $form = null; //NEW FORM
                }
                $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::UPDATE);
            } else {
                $msg = new Message('Parámetros Incorrectos', 'Parámetro incorrecto de codigo de curso: ' . $params[upgForm::COURSE], Message::RED);
                $this->pg()->log($msg, LM::FAILURE, LM::UPDATE);
            }
        } else {
            $logView = true;
        }
        return $this->getUpgCourseView($msg ?? null, $form ?? null, $logView ?? false);
    }

    public function deleteAction() {
        $status = LM::FAILURE;
        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            $idForm = new IdForm(null, true);
            $idForm->setData([
                IdForm::USER => $params[upgForm::COURSE]
            ]);
            if ($idForm->isValid()) {
                $courseCode = $idForm->getData()[IdForm::USER];
                //SEARCHING COURSE
                $result = $this->academyManager->deleteCourse($courseCode, Order::CURSO_ACTUALIZACION);
                if ($result->get() == false) {
                    $msg = new Message('Curso No Eliminado', $result);
                    $status = LM::ERROR;
                } else {
                    $msg = new Message('Eliminación Realizada', 'Se eliminó correctamente el curso', Message::GREEN);
                    $status = LM::SUCCESS;
                }
            } else {
                $msg = new Message('Parámetros Incorrectos', 'Codigo de Curso: ' . $params[upgForm::COURSE], Message::RED);
                $elementsErrors = $idForm->getMessages();
            }
        } else {
            $msg = new Message('Error de Solicitud', 'Se solicitó eliminación del curso sin datos', Message::RED);
        }
        $this->pg()->log($elementsErrors ?? ($result ?? $msg), $status, LM::DELETE);
        return $this->getUpgCourseView($msg ?? null, $form ?? null);
    }

}
