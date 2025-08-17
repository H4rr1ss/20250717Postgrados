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
use Zend\View\Model\JsonModel;
use Zend\InputFilter\InputFilter;
use Zend\Validator\StringLength;
use Zend\Validator\Digits;
use Eep\Form\FieldError;
//SERVICES
use Eep\Service\TimetableManager;
use Eep\Service\GradesManager;
use Eep\Service\SatuManager;
//FORMS
use Eep\Form\PonderBlocksForm;
use Eep\Service\LogManager as LM;
use Eep\Service\GeneralManager as GM;
use Eep\Form\GradesEntryForm;
use Eep\Entity\Result as R;
use Eep\Entity\Order as O;

class GradesController extends AbstractActionController {

    private $timetableManager;
    private $gradesManager;
    private $satuManager;

    public function __construct(TimetableManager $timetableManager, GradesManager $gradesManager, SatuManager $satuManager) {
        $this->timetableManager = $timetableManager;
        $this->gradesManager = $gradesManager;
        $this->satuManager = $satuManager;
    }

    public function ponderAction() {
        $action = $this->getRequest()->isPost() ? LM::READ : LM::UPDATE;
        //GETTING TIMETABLECODE
        $timetableCode = $this->params()->fromRoute('timetableCode', null);
        if ($timetableCode == null) {
            $msg = new Message('Horario No Obtenido', 'La ruta que se obtuvo, no identifica un horario asociado.', Message::RED);
            $this->pg()->log($msg, LM::FAILURE, $action);
        } else {
            //GETTING TIMETABLE
            $result = $this->timetableManager->getTimetable($timetableCode);
            if ($result->get() == false) {
                $msg = new Message('Inconveniente en la búsqueda del horario', $result);
                $this->pg()->log($msg, LM::ERROR, $action);
            } else {
                $timetable = $result->getObj();
                //CHECKING AUTHORIZATION
                if ($timetable->getCodCatedratico() != $this->identity()) {
                    $msg = new Message('No Permitido', 'La ponderación del horario solicitado no corresponde a tus cursos impartidos.');
                    $this->pg()->log($msg, LM::FAILURE, $action);
                } else {
                    //GETTING CHANGES IN PONDERING BLOCKS
                    if ($this->getRequest()->isPost()) {
                        $changes = $this->params()->fromPost();
                    } else {
                        $changes = null;
                    }
                    //GETTING CURRENT DATA
                    $result = $this->gradesManager->getPonderingBlocks($timetableCode);
                    if ($result->get() == false) {
                        $msg = new Message('Error', $result);
                        $this->pg()->log($result, LM::ERROR, $action);
                    } else {
                        $ponderingBlocksData = $result->getObj();
                        if ($changes != null) {
                            //UPDATING IF REQUESTED
                            $form = new PonderBlocksForm($ponderingBlocksData);
                            $form->setData($changes);
                            if ($form->isValid()) {
                                //UPDATING BLOCKS
                                $ponderingBlocksData = $form->getBlocks();
                                $result = $this->gradesManager->updatePonderingBlocks($timetableCode, $ponderingBlocksData);
                                if ($result->get()) {
                                    $msg = new Message('Actualización Realizada', $result, Message::GREEN);
                                    $ponderingBlocksData = $result->getObj();
                                } else {
                                    $msg = new Message('Error de Actualización de Ponderación', $result);
                                }
                                $request[] = "Horario (Cod. $timetableCode): Curso Cod. $timetable";
                                foreach ($ponderingBlocksData as $blockCode => $block) {
                                    if (intval($blockCode) != 0) {
                                        $request["Anteriores:"][] = $block['nombre'] . ' - ' . $block['valor'] . ' pts';
                                    } else {
                                        $request["Nuevos:"] [] = $block['nombre'] . ' - ' . $block['valor'] . ' pts';
                                    }
                                }
                                $this->pg()->log(Message::makeHtmlList($request, true), $result->get() ? LM::SUCCESS : LM::ERROR, $action);
                            } else {
                                //ADD GENERAL ERRORS IF ANY
                                $ttErrors = $form->get(PonderBlocksForm::TIMETABLE)->getMessages();
                                if (count($ttErrors) != 0) {
                                    $msg = new Message('Errores en solicitud', Message::makeHtmlList($ttErrors), Message::RED);
                                }
                                $ponderingBlocksData = $form->getBlocks();
                                $this->pg()->log($msg, LM::FAILURE, $action);
                            }
                        } else {
                            $this->pg()->log($result, LM::SUCCESS, $action);
                        }
                    }
                }
            }
        }
        return new ViewModel([
            'timetable' => $timetable ?? null,
            'blocks' => $ponderingBlocksData ?? null,
            'msg' => $msg ?? null
        ]);
    }

    private function getEntryView($data, $log = false) {
        //GETTING TIMETABLECODE
        $status = LM::SUCCESS;
        $timetableCode = $this->params()->fromRoute('timetableCode', null);
        if ($timetableCode == null) {
            $msg = new Message('Horario No Obtenido', 'La ruta que se obtuvo, no identifica un horario asociado.', Message::RED);
            $status = LM::FAILURE;
        } else {
            //GETTING TIMETABLE
            $result = $this->timetableManager->getTimetable($timetableCode);
            if ($result->get() == false) {
                $msg = new Message('Inconveniente en la búsqueda del horario', $result);
                $status = LM::ERROR;
            } else {
                $timetable = $result->getObj();
                if ($timetable->getCodCatedratico() != $this->identity() && $timetable->getCodCoordinador() != $this->identity() && !$this->layout()->role->hasAdminRole()) {
                    $msg = new Message('No Permitido', 'La ponderación del horario solicitado no corresponde a tus cursos impartidos.');
                    $status = LM::FAILURE;
                } else {
                    $lockedUpdates = strtotime($timetable->getFechaLimiteCalificacion()) < strtotime(date('Y-m-d')) && $timetable->getFechaNotasCompletadas() == null;
                    if ($lockedUpdates) {
                        $msg = new Message('Modificación Bloqueada', ['El periodo de ingreso de notas ha vencido',
                            'Se debe solicitar al Director de Postgrado prórroga para el ingreso y finalización de ingreso de notas',
                                ], Message::YELLOW);
                    }
                    //CHECKING IF PONDERING BLOCKS WERE CREATED
                    $result = $this->gradesManager->getPonderingBlocks($timetableCode);
                    if (!$result->get()) {
                        $msg = new Message('Error Obteniendo Bloques de Ponderación', $result);
                        $status = LM::ERROR;
                    } else {
                        $blocks = $result->getObj();
                        if (count($blocks) == 0) {
                            $txt[] = "Los bloques de ponderación no han sido creados.";
                            if ($timetable->getCodCatedratico() == $this->identity()) {
                                $url = $this->url()->fromRoute('grades', ['action' => 'ponder', 'timetableCode' => $timetableCode]);
                                $txt[] = "<a href=\"$url\">Crea los bloques de ponderación aquí</a>";
                            }
                            $msg = new Message('Bloques de Ponderación Pendientes', $txt, Message::YELLOW);
                            $status = LM::FAILURE;
                        } else {
                            //SORTING BY BLOCK CODE
                            uasort($blocks, function($a, $b) {
                                $aVal = $a['cod_bloque'] * 1;
                                $bVal = $b['cod_bloque'] * 1;
                                if ($aVal == $bVal) {
                                    return 0;
                                }
                                return $aVal > $bVal ? 1 : -1;
                            });
                            //GETTING GRADES DATA
                            //Consulta de inscripción agregado 20241016
                            $result = $this->gradesManager->updateTimetableUsersInscriptionStatus($timetableCode);
                            if(!$result->get()){
                                $msg = new Message('Error',$result);
                                $status = LM::ERROR;
                            }else {
                            $result = $this->gradesManager->getGrades($timetableCode);
                            if (!$result->get()) {
                                $msg = new Message('Error', $result);
                                $status = LM::ERROR;
                            } else {
                                $status = LM::SUCCESS;
                                $grades = $result->getObj();
                                $minToApproval = $this->gradesManager->getGlobal(GM::MINIMUM_GRADE_APPROVAL, 70);
                            }
                        }
                        }
                    }
                }
            }
        }
        if ($log) {
            $this->pg()->log($status == LM::ERROR ? ($result ?? ($msg ?? "")) : $msg ?? "", $status, LM::READ);
        }
        $revisionDays = $this->gradesManager->getGlobal(GM::REVISION_DAYS, 14);
        $view = new ViewModel(array_merge([
                    'timetable' => $timetable ?? null,
                    'msg' => $msg ?? null,
                    'grades' => $grades ?? null,
                    'blocks' => $blocks ?? null,
                    'minToApproval' => $minToApproval ?? null,
                    'revisionDays' => $revisionDays ?? null,
                        ], $data));
        $view->setTemplate('eep/grades/entry');
        return $view;
    }

    public function viewAction() {
        return $this->getEntryView([], true);
    }

    public function entryAction() {
        $params = [];
        $status = LM::SUCCESS;

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $timetableCode = $this->params()->fromRoute('timetableCode', null);
            if ($timetableCode == null) {
                $msg = new Message('Horario No Obtenido', 'La ruta que se obtuvo, no identifica un horario asociado.', Message::RED);
                $status = LM::FAILURE;
            } else {
                //GETTING TIMETABLE
                $result = $this->timetableManager->getTimetable($timetableCode);
                if (!$result->get()) {
                    $status = LM::ERROR;
                    $msg = new Message('Error de Consulta de Horario', $result);
                } else {
                    //VALIDATING EDITABLE PERIOD AVAILABLE
                    $timetable = $result->getObj();
                    $today = strtotime(date('Y-m-d'));
                    $revisionDays = $this->gradesManager->getGlobal(GM::REVISION_DAYS, 14);
                    $revisionLimitDate = strtotime($timetable->getFechaLimiteCalificacion() . " +$revisionDays days");
                    $isRevisionPeriod = $revisionLimitDate >= $today && $timetable->getFechaNotasCompletadas() != null;
                    $areGradesEditable = $timetable->getCodCatedratico() == $this->identity() && //THE USER IS THE PROFESSOR
                            strtotime($timetable->getFechaInicio()) <= $today && //TIMETABLE HAS STARTED
                            (
                            strtotime($timetable->getFechaLimiteCalificacion()) >= $today || //GRADING IS STILL AVAILABLE
                            $isRevisionPeriod  //REVISION DATE IS NOT OVER
                            );

                    if (!$areGradesEditable) {
                        $status = LM::FAILURE;
                        if ($timetable->getCodCatedratico() != $this->identity()) {
                            $reason = 'Sólo el catedrático del curso puede modificar las notas';
                        } elseif (strtotime($timetable->getFechaInicio()) > $today) {
                            $reason = 'La modificación de notas se habilitará al iniciar el curso (' . date('d/m/Y', strtotime($timetable->getFechaInicio())) . ')';
                        } elseif (strtotime($timetable->getFechaLimiteCalificacion()) < $today && !$isRevisionPeriod) {
                            $reason = 'La edición está bloqueada porque la fecha límite de calificación terminó. Debe solicitar ampliar la fecha de calificación a la Dirección de Postgrado';
                        } else {
                            $reason = 'El periodo de revisión de notas ha culminado. No puedes editarlas.';
                        }
                        $msg = new Message('Notas No Modificables', $reason);
                    } else {
                        //READING PREVIOUS GRADES
                        $result = $this->gradesManager->getGrades($timetableCode);
                        if (!$result->get()) {
                            $status = LM::ERROR;
                            $msg = new Message('Error de Consulta de Notas', $result);
                        } else {
                            $previousGrades = $result->getObj();
                            //CHECKING FOR EMPTY USER STACK GRADES
                            if (count($previousGrades) == 0) {
                                $msg = new Message('Sin Usuarios Asignados', 'Para guardar notas, debe haber al menos 1 estudiante asignado');
                                $status = LM::FAILURE;
                            } else {
                                //GETTING BLOCKS DATA
                                $result = $this->gradesManager->getPonderingBlocks($timetableCode);
                                if (!$result->get()) {
                                    $status = LM::ERROR;
                                    $msg = new Message('Error de Consulta de Bloques de Ponderación', $result);
                                } else {
                                    $blocks = $result->getObj();
                                    //VALIDATING DATA
                                    $form = new GradesEntryForm($previousGrades, $blocks);
                                    $form->setData($data);
                                    if ($form->isValid()) {
                                        $newGradesData = $form->getGradesData();
                                        $result = $this->gradesManager->updateGrades($timetableCode, $newGradesData, $previousGrades, $this->identity(), $isRevisionPeriod);
                                        if (!$result->get()) {
                                            $status = LM::ERROR;
                                            $msg = new Message('Error de Actualización de Notas', $result);
                                        } else {
                                            $msg = new Message('Actualizado Correctamente', $result, Message::GREEN);
                                        }
                                    } else {
                                        //ADD GENERAL ERRORS IF ANY
                                        $ttErrors = $form->getMessages();
                                        if (count($ttErrors) != 0) {
                                            $msg = new Message('Errores en solicitud', Message::makeHtmlList($ttErrors), Message::RED);
                                        }
                                        $status = LM::FAILURE;
                                        $grades = $form->getGradesData();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->pg()->log($status == LM::ERROR ? ($result ?? ($msg ?? "")) : $msg ?? "", $status, LM::UPDATE);
        if (($msg ?? null) != null) {
            $params['msg'] = $msg;
        }
        if (($grades ?? null) != null) {
            $params['grades'] = $grades;
        }
        return $this->getEntryView($params);
    }

    public function completeAction() {
        $status = LM::FAILURE;
        //GETTING TIMETABLECODE
        $timetableCode = $this->params()->fromRoute('timetableCode', null);
        if ($timetableCode == null) {
            $msg = new Message('Horario No Obtenido', 'La ruta que se obtuvo, no identifica un horario asociado.', Message::RED);
        } else {
            //GETTING TIMETABLE
            $result = $this->timetableManager->getTimetable($timetableCode);
            if ($result->get() == false) {
                $msg = new Message('Inconveniente en la búsqueda del horario', $result);
            } else {
                $timetable = $result->getObj();
                //CHECKING AUTHORIZATION
                if ($timetable->getCodCatedratico() != $this->identity()) {
                    $msg = new Message('No Permitido', 'Sólo el catedrático del curso puede indicar la conclusión del ingreso de notas');
                } else {
                    //VALIDATING GRADES ARE NOT EMPTY
                    $result = $this->gradesManager->getGrades($timetableCode);
                    if ($result->get() == false) {
                        $msg = new Message('Error', $result);
                    } else {
                        $grades = $result->getObj();
                        if (count($grades) == 0) {
                            $msg = new Message('Sin Estudiantes', 'No hay estudiantes asignados al horario');
                        } else {
                            //GETTING CURRENT DATA
                            $result = $this->gradesManager->setGradesEntryComplete($timetableCode, $grades);
                            if ($result->get() == false) {
                                $msg = new Message('Error', $result);
                            } else {
                                $msg = new Message('Información', $result);
                                $status = LM::SUCCESS;
                            }
                        }
                    }
                }
            }
        }
        $this->pg()->log($result ?? ($msg ?? ''), $status, LM::UPDATE);
        $params = [];
        if (isset($msg)) {
            $params['msg'] = $msg;
        }
        return $this->getEntryView($params);
    }

    public function generateActAction() {
        $status = LM::FAILURE;
        //GETTING TIMETABLECODE
        $timetableCode = $this->params()->fromRoute('timetableCode', null);
        if ($timetableCode == null) {
            $msg = new Message('Horario No Obtenido', 'La ruta que se obtuvo, no identifica un horario asociado.', Message::RED);
        } else {
            $result = $this->timetableManager->getTimetable($timetableCode);
            if (!$result->get()) {
                $msg = new Message('Error de Horario', $result);
                $status = LM::ERROR;
            } else {
                $timetable = $result->getObj();
                if ($timetable->getCodPensum() == O::CURSO_ACTUALIZACION) {
                    $msg = new Message('Curso de Actualización', 'Este es un curso de actualización y no se le puede crear un Acta Oficial de Notas');
                } else {
                    $result = $this->gradesManager->createAct($timetableCode);
                    $msg = new Message('Información de Creación de Acta', $result);
                    if ($result->getType() == R::ERROR) {
                        $status = LM::ERROR;
                    } elseif ($result->getType() == R::SUCCESS) {
                        $status = LM::SUCCESS;
                    }
                }
            }
        }
        $this->pg()->log($result ?? ($msg ?? ''), $status, LM::CREATE);
        return $this->getEntryView([
                    'msg' => $msg
        ]);
    }

    //AJAX
    public function gradeDetailAction() {
        $response = ['status' => false];
        $logStatus = LM::FAILURE;
        if ($this->getRequest()->isPost()) {
            //VALIDATING DATA
            $params = $this->params()->fromPost();
            $filter = new InputFilter();
            $filter->add([
                'name' => 'code',
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 9,
                            'messages' => FieldError::STRING_LENGTH,
                        ],
                    ],
                    [
                        'name' => Digits::class,
                        'options' => [
                            'messages' => FieldError::DIGITS,
                        ],
                    ]
                ]
            ]);
            $paramNames = ['userCode', 'timetableCode'];
            $valid = true;
            foreach ($paramNames as $paramName) {
                $filter->setData(['code' => $params[$paramName]]);
                if (!$filter->isValid()) {
                    $response['error'] = (!empty($response['error']) ? '. ' : '') . "$paramName: " . current(current($filter->getMessages()));
                    $valid = false;
                } else {
                    $params[$paramName] = $filter->getValue('code');
                }
            }
            if ($valid) {
                //VALIDATING AUTHORIZATION
                $timetableCode = $params['timetableCode'];
                $userCode = $params['userCode'];
                $valid = false;
                $role = $this->layout()->role;
                if ($this->identity() == $userCode || $role->hasAdminRole() || $role->hasUdicaRole()) {
                    //GETTIND DETAIL
                    $result = $this->gradesManager->getGradesDetail($timetableCode, $userCode);
                    if (!$result->get()) {
                        $response['error'] = implode(', ', $result->getMsg());
                        $logStatus = LM::ERROR;
                    } else {
                        $response['status'] = true;
                        $logStatus = LM::SUCCESS;
                        $response['gradeDetail'] = $result->getObj();
                    }
                } else {
                    $this->getResponse()->setStatusCode(403); //FORBIDDEN
                    $response['error'] = 'No estás autorizado para ver este detalle';
                }
            }
        } else {
            $this->getResponse()->setStatusCode(400);
            $response['error'] = 'Solicitud sin datos (No POST)';
        }
        if ($logStatus != LM::SUCCESS) {
            $this->pg()->log($response['error'] ?? null, $logStatus, LM::READ);
        }
        $view = new JsonModel($response);
        $view->setTerminal(true);
        return $view;
    }

}
