<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Eep\Service\TimetableManager;
use Eep\Entity\User;
use Eep\Entity\Timetable;
use Eep\Service\AssignmentManager as Assignment;
use Eep\Service\InscriptionManager;
use Eep\Service\GeneralManager as GM;
use Eep\Entity\Role;
use Eep\Entity\Order as O;
use Eep\Form\PostActStudentsForm as PF;

class GradesManager extends Manager {

    private $timetableManager;
    private $inscriptionManager;

    //FINAL GRADE TYPES
    const FG_TYPE_REGULAR = 1;
    const FG_TYPE_POST_ACT_REGULAR = 2;
    const FG_TYPE_NO_TIMETABLE_REGULAR = 3;
    const FG_TYPE_SUFFICIENCY = 4;
    const FG_TYPE_EXTERNAL_EQUIVALENCE = 5;
    const FG_TYPE_CAREER_INTERNAL_EQUIVALENCE = 6;
    const FG_TYPE_PENSUM_INTERNAL_EQUIVALENCE = 7;
    //FINAL GRADE STATUS (COMPATIBLE WITH PREGRADE PLATFORM)
    const FG_STATUS_NO_PROBLEM = 1;
    const FG_STATUS_EXTERNAL_EQUIVALENCE = 2;
    const FG_STATUS_NOT_INSCRIBED = 7;
    const FG_STATUS_INVERSE_PENSUM_EQUIVALENCE = 10;
    const FG_STATUS_CA_OR_JD_AUTHORIZED = 11;
    const FG_STATUS_DEGRADED_FOR_GRADE_IMPROVEMENT = 13;

    public function __construct(Adapter $dbAdapter, TimetableManager $timetableManager, InscriptionManager $inscriptionManager) {
        parent::__construct($dbAdapter);
        $this->timetableManager = $timetableManager;
        $this->inscriptionManager = $inscriptionManager;
    }

    public function getPonderingBlocks($timetableCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //READING CURRENT PONDERING BLOCKS
        try {
            $table = new TableGateway(['b' => 'bloque'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['na' => 'nota_asignatura'], 'b.cod_bloque = na.cod_bloque', [], Select::JOIN_LEFT);
            $select->columns(['cod_bloque', 'nombre', 'valor', 'cod_horario', 'notas' => new Expression('COUNT(na.cod_bloque)')]);
            $select->group('b.cod_bloque');
            $select->where(['b.cod_horario' => $timetableCode]);
            $select->order('notas DESC');
            $select->order('b.nombre ASC');
            $blocks = $table->selectWith($select)->toArray();
            $keyedBlocks = [];
            foreach ($blocks as $block) {
                $keyedBlocks[$block['cod_bloque']] = $block;
            }
            $res->setObj($keyedBlocks);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron leer los bloques de ponderación del horario.');
            $res->addError($ex);
            return $res;
        }
        return $res;
    }

    public function updatePonderingBlocks($timetableCode, $ponderingBlocksData): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        /*
         * $ponderingBlocksData = [
         *  <cod_bloque> => [
         *      'cod_bloque' => <cod_bloque>,
         *      'nombre' => <nombre>,
         *      'valor' => <valor>
         *  ],
         *  "N1" => [
         *      'cod_bloque' => 'N1',
         *      'nombre' => <nombre>,
         *      'valor' => <valor>
         *  ],
         *  'N30' => [
         *      'cod_bloque' => 'N30',
         *      'nombre' => <nombre>,
         *      'valor' => <valor>
         *  ],
         * ]
         */
        //GETTING PREVIOUS BLOCK VALUES
        $result = $this->getPonderingBlocks($timetableCode);
        if (!$result->get()) {
            $res->failure($result);
        } else {
            $blocks = $result->getObj();
            $blocksToDelete = array_diff(array_keys($blocks), array_keys($ponderingBlocksData));
            $blocksToInsert = array_diff(array_keys($ponderingBlocksData), array_keys($blocks));
            $blocksToUpdate = array_intersect(array_keys($blocks), array_keys($ponderingBlocksData));
            $this->beginTransaction();
            $table = new TableGateway('bloque', $this->dbAdapter);
            //DELETING
            $successMsgs = [];
            if (count($blocksToDelete) != 0) {
                try {
                    foreach ($blocksToDelete as $blockCode) {
                        $result = $table->delete(['cod_bloque' => $blockCode]);
                        if ($result == 0) {
                            $res->failure("No se eliminó el bloque $blockCode.");
                            break;
                        }
                    }
                    $successMsgs[] = "Bloques previos eliminados.";
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron eliminar los bloques de ponderación correspondientes.");
                    $res->addError($ex);
                }
            }
            //UPDATING
            if (count($blocksToUpdate) != 0) {
                try {
                    $sum = 0;
                    foreach ($blocksToUpdate as $blockCode) {
                        if ($blocks[$blockCode]['notas'] != 0) {
                            continue;
                        }
                        //THE UPDATE RETURN IS NOT VALIDATED BECAUSE THE BLOCK MAY REMAIN WITH THE SAME VALUE AND NAME. IF SO, THE RESULT WOULD BE 0.
                        $result = $table->update([
                            'nombre' => $ponderingBlocksData[$blockCode]['nombre'],
                            'valor' => $ponderingBlocksData[$blockCode]['valor'],
                                ], ['cod_bloque' => $blockCode]);
                        $sum += $result;
                    }
                    if ($sum != 0) {
                        $successMsgs[] = "Bloques con cambios actualizados.";
                    }
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron actualizar los bloques de ponderación correspondientes.");
                    $res->addError($ex);
                }
            }
            if (count($blocksToInsert) != 0) {
                try {
                    foreach ($blocksToInsert as $blockCode) {
                        $result = $table->insert([
                            'nombre' => $ponderingBlocksData[$blockCode]['nombre'],
                            'valor' => $ponderingBlocksData[$blockCode]['valor'],
                            'cod_horario' => $timetableCode
                        ]);
                        if ($result == 0) {
                            $res->failure("No se actualizó el bloque $blockCode.");
                            break;
                        }
                    }
                    $successMsgs[] = 'Bloques nuevos agregados';
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron crear los bloques de ponderación correspondientes.");
                    $res->addError($ex);
                }
            }
            //COMMITING OR ROLLING BACK CHANGES
            if ($res->get()) {
                //GETTING NEW BLOCKS STRUCTURE
                $result = $this->getPonderingBlocks($timetableCode);
                if ($result->get()) {
                    $res->setObj($result->getObj());
                    $this->commit();
                    $res->addMsg($successMsgs);
                } else {
                    $res->addMsg("No se pudieron obtener nuevamente los bloques de ponderación modificados");
                    $res->failure($result);
                }
            } else {
                $this->rollback();
            }
        }
        return $res;
    }

    public function getGrades($timetableCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $table = new TableGateway(['a' => 'asignacion'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['o' => 'orden_pago'], 'a.cod_orden = o.cod_orden', ['cod_boleta'], Select::JOIN_LEFT);
            $select->join(['h' => 'horario'], 'a.cod_horario = h.cod_horario', []);
            $select->join(['b' => 'bloque'], 'h.cod_horario = b.cod_horario', ['cod_bloque']);
            $select->join(['na' => 'nota_asignatura'], 'b.cod_bloque = na.cod_bloque and a.cod_usuario = na.cod_usuario and a.cod_horario = na.cod_horario', ['cod_usuario', 'cod_horario', 'nota', 'historial_cambios'], Select::JOIN_LEFT);
            $select->join(['u' => 'usuario'], 'a.cod_usuario = u.cod_usuario');
            $select->join(['i' => 'inscripcion'], 'h.cod_pensum = i.cod_pensum and h.anio = i.anio and a.cod_usuario = i.cod_usuario', ['inscrito' => 'anio'], Select::JOIN_LEFT);
            $select->where([
                'h.cod_horario' => $timetableCode
            ]);
            $result = $table->selectWith($select)->toArray();
            //STRUCTURING DATA
            $data = [];
            /* FORMAT
             * $data = [
             *  <cod_usuario> => [
             *      'user' => <UserObject>,
             *      'valid' => <true/false>,
             *      'asistencia_cumplida' => <asistencia_cumplida>,
             *      'nota_final' => <nota_final>,
             *      'cod_estado_nota' => <cod_estado_nota>,
             *      'grades' => [
             *          <cod_bloque> => [
             *              'grade'=> <nota>,
             *              'error' => null,
             *          ],
             *          <cod_bloque> => [
             *              'grade'=> <nota>,
             *              'error' => null,
             *          ],
             *      ]
             *  ],
             *  <cod_usuario> => [
             *      'user' => <UserObject>,
             *      'valid' => <true/false>,
             *      'asistencia_cumplida' => <asistencia_cumplida>,
             *      'nota_final' => <nota_final>,
             *      'cod_estado_nota' => <cod_estado_nota>,
             *      'grades' => [
             *          <cod_bloque> => [
             *              'grade'=> <nota>,
             *              'error' => null,
             *          ],
             *          <cod_bloque> => [
             *              'grade'=> <nota>,
             *              'error' => null,
             *          ],
             *      ]
             *  ],
             * ]
             */
            foreach ($result as $val) {
                $userCode = $val['cod_usuario'];
                if (!isset($data[$userCode])) {
                    $data[$userCode]['user'] = new User($val);
                    $data[$userCode]['valid'] = ($val['valida'] == 1);
                    $data[$userCode]['asistencia_cumplida'] = $val['asistencia_cumplida'];
                    $data[$userCode]['nota_final'] = $val['nota_final'];
                    $data[$userCode]['cod_estado_nota'] = $val['cod_estado_nota'];
                    $data[$userCode]['cod_boleta'] = $val['cod_boleta'];
                }
                $blockCode = $val['cod_bloque'];
                $data[$userCode]['grades'][$blockCode]['grade'] = $val['nota'];
                $data[$userCode]['grades'][$blockCode]['history'] = json_decode($val['historial_cambios']);
            }
            $res->setObj($data);
        } catch (\Exception $ex) {
            $res->failure("No se pudieron consultar las notas del horario", $ex);
        }
        return $res;
    }

    public function updateGrades($timetableCode, $newGradesData, $previousGrades, $proffesorUserCode, $isRevision = false): R {
        /*  CHANGES HISTORY FORMAT:
         * [
         *  [
         *      'cod_usuario' => <cod_usuario>,
         *      'tiempo' => <curtime>,
         *      'nota' => <nota>
         *  ],
         *  [
         *      'cod_usuario' => <cod_usuario>,
         *      'tiempo' => <curtime>,
         *      'nota' => <nota>
         *  ],
         * ]
         */
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        $newGrades = [];
        $gradesUpdates = [];
        $assignmentUpdates = [];
        foreach ($newGradesData as $userCode => $userData) {
            $grades = $userData["grades"];
            $finalGrade = 0;
            $changesMade = false;
            foreach ($grades as $blockCode => $gradeData) {
                $previousGrade = $previousGrades[$userCode]["grades"][$blockCode]["grade"];
                $newGrade = $gradeData["grade"];
                $finalGrade += $newGrade;
                if ($previousGrade != $newGrade) {
                    $changesMade = true;
                    $changeDetail = [
                        'u' => $proffesorUserCode * 1, // USER CODE OF THE USER MODIFYING THE GRADE
                        't' => time(), //TIME
                        'n' => $newGrade * 1, //GRADE
                        'r' => $isRevision ? 1 : 0
                    ];
                    if ($previousGrade == null) {
                        $newGrades[] = [
                            'cod_bloque' => $blockCode,
                            'cod_usuario' => $userCode,
                            'cod_horario' => $timetableCode,
                            'nota' => $newGrade,
                            'historial_cambios' => json_encode([$changeDetail])
                        ];
                    } else {
                        $history = $gradeData['history'] ?? [];
                        array_unshift($history, $changeDetail);
                        $gradesUpdates[] = [
                            'where' => [
                                'cod_bloque' => $blockCode,
                                'cod_usuario' => $userCode,
                                'cod_horario' => $timetableCode,
                            ],
                            'values' => [
                                'nota' => $newGrade,
                                'historial_cambios' => json_encode($history)
                            ]
                        ];
                    }
                }
            }
            $values = [];
            if ($changesMade) {
                $values['nota_final'] = $finalGrade;
                $values['cod_estado_nota'] = Assignment::PARCIAL_ENTRY;
            }

            if ($userData['asistencia_cumplida'] != $previousGrades[$userCode]['asistencia_cumplida']) {
                $values['asistencia_cumplida'] = $userData['asistencia_cumplida'];
            }
            if (count($values) != 0) {
                $assignmentUpdates[] = [
                    'where' => [
                        'cod_usuario' => $userCode,
                        'cod_horario' => $timetableCode
                    ],
                    'values' => $values
                ];
            }
        }
        if (count($newGrades) == 0 && count($gradesUpdates) == 0 && count($assignmentUpdates) == 0) {
            $res->success("No se requirieron cambios");
        } else {
            $this->beginTransaction();
            //ADDING NEW GRADES
            try {
                if (count($newGrades) != 0) {
                    $gradeTable = new TableGateway('nota_asignatura', $this->dbAdapter);
                    foreach ($newGrades as $insertValues) {
                        $gradeTable->insert($insertValues);
                    }
                    $res->addMsg("Nuevas notas agregadas");
                }
                //SAVING GRADES CHANGES
                if (count($gradesUpdates) != 0) {
                    $gradeTable = $gradeTable ?? new TableGateway('nota_asignatura', $this->dbAdapter);
                    foreach ($gradesUpdates as $updateData) {
                        $gradeTable->update($updateData['values'], $updateData['where']);
                    }
                    $res->addMsg("Cambios de notas realizados");
                }
                //UPDATING FINAL GRADES AND ATTENDANCE STATUS
                if (count($assignmentUpdates) != 0) {
                    $assignmentTable = new TableGateway('asignacion', $this->dbAdapter);
                    foreach ($assignmentUpdates as $asgData) {
                        $assignmentTable->update($asgData['values'], $asgData['where']);
                    }
                    $res->addMsg("Cambios de asistencia y de nota final actualizados: ");
                }
            } catch (\Exception $ex) {
                $res->failure('No se pudieron actualizar las notas', $ex);
            }

            if ($res->get()) {
                $this->commit();
            } else {
                $this->rollback();
            }
        }
        return $res;
    }

    public function setGradesEntryComplete($timetableCode, $grades = null): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //CHECKING IF TIMETABLE HAS GRADES ENTRY COMPLETED AND IT IS A VALID TIME
        try {
            $table = new TableGateway('horario', $this->dbAdapter);
            $result = $table->select([
                'cod_horario' => $timetableCode
            ]);
            if ($result->count() == 0) {
                $res->failure("Horario " . $timetableCode . " no encontrado");
                return $res;
            } else {
                //IS THE TIMETABLE ALREADY COMPLETED
                $tt = $result->current();
                $completed = $tt['fecha_notas_completadas'];
                if ($completed != null) {
                    $res->warning("Al horario ya se le ha indicado que las notas han sido completadas (" . date('d/m/Y', strtotime($completed)) . ")");
                    return $res;
                }
                //IS THE PERIOD AVAILABLE
                $finishDate = strtotime($tt["fecha_fin"]);
                if ($finishDate > strtotime(date('Y-m-d'))) {
                    $res->warning("Se podrá completar el ingreso de notas desde el último día de clases (" . date('d/m/Y', $finishDate) . ")");
                    return $res;
                }
                //CHANGES IN GRADES ARE NOT LOCKED
                $gradingLimitDate = strtotime($tt["fecha_limite_calificacion"]);
                if ($gradingLimitDate < strtotime(date('Y-m-d'))) {
                    $res->warning("Está bloqueada la finalización del ingreso de notas. Se debe solicitar prórroga al Director de la Escuela de Postgrado.");
                    return $res;
                }
            }
        } catch (\Exception $ex) {
            $res->failure("No se pudo obtener información del horario");
            return $res;
        }
        //GETTING GRADES IF NOT PROVIDED
        if ($grades == null) {
            $result = $this->getGrades($timetableCode);
            if (!$result->get()) {
                return $result;
            } else {
                $grades = $result->getObj();
            }
        }
        $allGradesEntered = true;
        $user = null;
        foreach ($grades as $userData) {
            $gradesData = $userData["grades"];
            if ($userData["asistencia_cumplida"] != true) {
                //USERS WITHOUT ATTENDANCE CAN STAY WITHOUT GRADES
                continue;
            }
            foreach ($gradesData as $gradeData) {
                $grade = $gradeData["grade"];
                if ($grade == null) {
                    $allGradesEntered = false;
                    $user = $userData["user"];
                    break;
                }
            }
            if ($allGradesEntered == false) {
                break;
            }
        }
        if (!$allGradesEntered) {
            $res->warning("Para completar el ingreso, todos los estudiantes deben tener notas en cada bloque de ponderación.");
            $id = $user->getRegistroAcademico() ?? ($user->getCui() ?? ($user->getPasaporte()));
            $res->addMsg("El estudiante " . $user->getNombres() . " " . $user->getApellidos() . " ($id) no tiene sus notas completas.");
        } else {
            //UPDATING USERS INSCRIPTION STATUS
            $result = $this->updateTimetableUsersInscriptionStatus($timetableCode);
            if (!$result->get()) {
                $res = $result;
            } else {
                try {
                    //UPDATING TIMETABLE DATA
                    $table->update([
                        'fecha_notas_completadas' => new Expression('curdate()')
                            ], [
                        'cod_horario' => $timetableCode
                    ]);
                    //UPDATING ASSIGNMENT DATA
                    $assignmentTable = new TableGateway('asignacion', $this->dbAdapter);
                    $assignmentTable->update([
                        'cod_estado_nota' => Assignment::ENTRY_COMPLETED
                            ], [
                        'cod_horario' => $timetableCode
                    ]);
                    $res->success("Se ha indicado la finalización de ingreso de notas del horario");
                    $res->addMsg("A los estudiantes ahora se le mostrarán sus notas para que puedan revisarlas");
                    $res->addMsg("El periodo de revisión de notas ha empezado");
                } catch (\Exception $ex) {
                    $res->failure("No se pudo actualizar la información del horario");
                }
            }
        }
        return $res;
    }

    public function createAct($timetableCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC

        $result = $this->timetableManager->getTimetable($timetableCode);
        if (!$result->get()) {
            return $result;
        } else {
            $timetable = $result->getObj();
            if ($timetable->getActCode() != null) {
                $res->failure("El acta ya fue creada con anterioridad");
            } else {
                //UPDATING USERS INSCRIPTION STATUS
                $result = $this->updateTimetableUsersInscriptionStatus($timetableCode);
                if (!$result->get()) {
                    return $result;
                } else {
                    //GETTING USERS AND GRADES
                    $result = $this->getGrades($timetableCode);
                    if (!$result->get()) {
                        return $result;
                    } else {
                        $grades = $result->getObj();
                        //GETTING PONDER BLOCKS NAMES
                        $result = $this->getPonderingBlocks($timetableCode);
                        if (!$result->get()) {
                            return $result;
                        } else {
                            $ponderBlocks = $result->getObj();
                            //REMOVING NOT INSCRIBED USERS
                            $userCodes = array_keys($grades);
                            foreach ($userCodes as $userCode) {
                                $user = $grades[$userCode]['user'];
                                if ($user->getInscriptionStatus() == null) {
                                    //REMOVE IF HE'S NOT INSCRIBED
                                    unset($grades[$userCode]);
                                } elseif ($grades[$userCode]['cod_estado_nota'] == Assignment::OFFICIALIZED_NOTE) {
                                    //REMOVE IF THE GRADE HAS ALREADY BEEN OFFICIALIZED THROUGH POSTGRADE ACT ("Acta de Postgrado")
                                    //BEFORE THE OFFICIAL ACT CREATION
                                    unset($grades[$userCode]);
                                }
                            }
                            if (count($grades) == 0) {
                                $res->warning('No hay estudiantes inscritos para agregar al acta');
                            } else {
                                //READING ACT CORRELATIVE
                                $correlative = 1;
                                try {
                                    $actTable = new TableGateway('acta', $this->dbAdapter);
                                    $select = $actTable->getSql()->select();
                                    $select->columns([
                                        'max' => new Expression('MAX(correlativo)')
                                    ]);
                                    $select->where([
                                        'anio' => date('Y'),
                                        'cod_tipo_acta' => Assignment::EEP_REGULAR
                                    ]);
                                    $result = $actTable->selectWith($select);
                                    if ($result->current()['max'] != null) {
                                        $correlative = $result->current()['max'] * 1 + 1;
                                    }
                                } catch (\Exception $ex) {
                                    $res->failure("No se pudo buscar el correlativo correspondiente", $ex);
                                }
                                if ($res->get()) {
                                    $this->beginTransaction();
                                    //CREATING ACT
                                    $actCode = $correlative . '-' . date('Y');
                                    try {
                                        $actTable->insert([
                                            'cod_acta' => $actCode,
                                            'cod_tipo_acta' => Assignment::EEP_REGULAR,
                                            'fecha_generacion' => new Expression('CURDATE()'),
                                            'anio' => date('Y'),
                                            'correlativo' => $correlative
                                        ]);
                                    } catch (Exception $ex) {
                                        $res->failure("No se pudo crear el acta", $ex);
                                    }
                                    if ($res->get()) {
                                        //CREATING ACT DETAILS
                                        foreach ($grades as $userCode => $userData) {
                                            /* FORMAT:
                                             * [
                                             *  'as' => <asistencia_cumplida>,
                                             *  'nf' => <nota_final>
                                             *  'cb' => <cod_boleta>
                                             *  'ns' => [
                                             *      <cod_bloque> => [
                                             *          'b' => <nombre_bloque>,
                                             *          'vb' => <valor_bloque>,
                                             *          'n' => <nota>
                                             *      ],
                                             *      <cod_bloque> => [
                                             *          'b' => <nombre_bloque>,
                                             *          'vb' => <valor_bloque>,
                                             *          'n' => <nota>
                                             *      ],
                                             *      ...
                                             *  ]
                                             * ]
                                             */
                                            $data = [];
                                            $data['as'] = $userData['asistencia_cumplida'] ? 1 : 0;
                                            $data['nf'] = ($userData['nota_final'] == null) ? null : $userData['nota_final'] * 1;
                                            $data['cb'] = $userData['cod_boleta'];
                                            $gradesData = $userData['grades'];
                                            foreach ($gradesData as $blockCode => $gradeData) {
                                                $grade = $gradeData['grade'];
                                                $data['ns'][$blockCode] = [
                                                    'b' => $ponderBlocks[$blockCode]['nombre'],
                                                    'vb' => $ponderBlocks[$blockCode]['valor'] * 1,
                                                    'n' => ($grade == null) ? null : $grade * 1
                                                ];
                                            }
                                            //SAVING USER ACT DETAIL
                                            try {
                                                $actDetailTable = new TableGateway('detalle_acta_oficial', $this->dbAdapter);
                                                $set = [
                                                    'cod_acta' => $actCode,
                                                    'cod_tipo_acta' => Assignment::EEP_REGULAR,
                                                    'cod_usuario' => $userCode,
                                                    'cod_horario' => $timetableCode,
                                                    'data' => json_encode($data)
                                                ];
                                                $actDetailTable->insert($set);
                                            } catch (\Exception $ex) {
                                                $res->failure("No se pudieron agregar los detalles del acta", $ex);
                                                $res->addError("Set del insert: " . json_encode($set ?? '(No alcanzado)'));
                                            }
                                        }
                                        if ($res->get()) {
                                            //UPDATING TIMETABLE
                                            try {
                                                $ttTable = new TableGateway('horario', $this->dbAdapter);
                                                $ttTable->update([
                                                    'cod_acta_oficial' => $actCode,
                                                    'cod_tipo_acta' => Assignment::EEP_REGULAR,
                                                    'fecha_generacion_acta' => new Expression('CURDATE()')
                                                        ], [
                                                    'cod_horario' => $timetableCode
                                                ]);
                                            } catch (\Exception $ex) {
                                                $res->failure("No se pudo actualizar el estado del horario", $ex);
                                            }
                                            if ($res->get()) {
                                                //CHANGING ASSIGNMENT GRADE STATUS
                                                try {
                                                    $asgTable = new TableGateway('asignacion', $this->dbAdapter);
                                                    $where = new Where();
                                                    $where->equalTo('cod_horario', $timetableCode);
                                                    $where->in('cod_usuario', array_keys($grades));
                                                    $result = $asgTable->update([
                                                        //SET
                                                        'cod_estado_nota' => Assignment::ACT_CREATED,
                                                            ], $where);
                                                    if ($result != count($grades)) {
                                                        $res->failure("Se actualizaron $result estados de nota, cuando debieron ser " . count($grades));
                                                    } else {
                                                        $res->success("Acta $actCode creada satisfactoriamente");
                                                        $res->setObj($actCode);
                                                    }
                                                } catch (\Exception $ex) {
                                                    $res->failure("No se pudo actualizar el estado de la nota de las asignaciones", $ex);
                                                }
                                            }
                                        }
                                    }
                                    if ($res->get()) {
                                        $this->commit();
                                    } else {
                                        $this->rollback();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $res;
    }

    public function updateTimetableUsersInscriptionStatus($timetableCode): R {
        $res = new R();
        $res->success();

        try {
            //SEARCHING FOR USERS WITHOUT INSCRIPTION
            $asgTable = new TableGateway(['a' => 'asignacion'], $this->dbAdapter);
            $select = $asgTable->getSql()->select();
            $select->join(['h' => 'horario'], 'a.cod_horario = h.cod_horario', ['year' => 'anio']);
            $select->join(['i' => 'inscripcion'], 'a.cod_usuario = i.cod_usuario and h.anio = i.anio', ['inscrito' => 'anio'], Select::JOIN_LEFT);
            $select->where([
                'h.cod_horario' => $timetableCode,
                'i.anio' => null //NOT INSCRIBED USERS
            ]);
            $result = $asgTable->selectWith($select);
        } catch (\Exception $ex) {
            $res->failure("No se pudieron buscar los estudiantes asociados al horario para actualizar inscripciones", $ex);
        }
        if ($res->get()) {
            if ($result->count() != 0) {
                //UPDATING USERS
                $data = $result->toArray();
                foreach ($data as $user) {
                    $userCode = $user['cod_usuario'];
                    $year = $user['year'];
                    $result = $this->inscriptionManager->getInscriptionStatus($userCode, $year);
                    if ($result->get() == InscriptionManager::ERROR) {
                        $result->failure();
                        $result->addError("Error actualizando inscripción de usuario código $userCode");
                        $res = $result;
                        break;
                    }
                }
            }
        }
        return $res;
    }

    public function getAct($actCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            //GETTING PROFESSOR AND ACT GENERAL DATA
            $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['a' => 'acta'], 'h.cod_acta_oficial = a.cod_acta and h.cod_tipo_acta = a.cod_tipo_acta', ['cod_acta','cod_tipo_acta','fecha_generacion','acta_anio' => 'anio','correlativo']);
            $select->join(['p' => 'usuario'], 'h.cod_usuario_catedratico = p.cod_usuario', ['nombres_catedratico' => 'nombres', 'apellidos_catedratico' => 'apellidos']);
            $select->join(['co' => 'usuario'], 'h.cod_usuario_coordinador = co.cod_usuario', ['nombres_coordinador' => 'nombres', 'apellidos_coordinador' => 'apellidos'], Select::JOIN_LEFT);
            $select->join(['c' => 'curso_pensum'], 'h.cod_curso = c.cod_curso and h.cod_pensum = c.cod_pensum', ['nombre_curso' => 'nombre', 'alias']);
            $select->join(['pe' => 'pensum'], 'c.cod_pensum = pe.cod_pensum', []);
            $select->join(['ca' => 'carrera'], 'pe.cod_carrera = ca.cod_carrera', ['nombre_carrera' => 'nombre_actual', 'alias_carrera' => 'alias_actual']);
            $select->join(['g' => 'grado_academico'], 'ca.cod_grado = g.cod_grado', ['grado_academico' => 'nombre']);
            $select->where([
                'a.cod_acta' => $actCode,
                'a.cod_tipo_acta' => Assignment::EEP_REGULAR
            ]);
            $result = $table->selectWith($select);
            if ($result->count() == 0) {
                $res->failure("No se encontró el acta '$actCode'");
            }
            $actData = $result->current();
            //var_dump($actData); die;
        } catch (\Exception $ex) {
            $res->failure("No se pudo leer la información del acta $actCode", $ex);
        }
        if ($res->get()) {
            //GETTING CURRENT AND MORE RECENT DIRECTOR
            $found = false;
            try {
                $userTable = new TableGateway(['u' => 'usuario'], $this->dbAdapter);
                $select = $userTable->getSql()->select();
                $select->join(['ur' => 'usuario_rol'], 'u.cod_usuario = ur.cod_usuario', []);
                $select->order('fecha_inicio DESC');
                $select->where([
                    'fecha_inicio <= curdate()',
                    '(fecha_fin is NULL or fecha_fin>=curdate())',
                    'ur.cod_rol' => Role::DIRECTOR
                ]);
                $result = $userTable->selectWith($select);
                if ($result->count() == 0) {
                    $res->warning("No se encontró a un director que esté en funciones");
                } else {
                    $principal = new User($result->current());
                    $found = true;
                }
            } catch (\Exception $ex) {
                $res->failure("No se pudo consultar al Director de la EEP", $ex);
            }
            if ($res->get() && $found) {
                //STRUCTURING DATA
                /*  FORMAT:
                 * [
                 *  'actCode' => <cod_acta>,
                 *  'generationData' => <fecha_generacion>,
                 *  'grade' => <nombre_grado>,
                 *  'career' => <nombre_carrera>,
                 *  'timetable' => <timetable Object>,
                 *  'minApprove' => <min grade to approve>,
                 *  'principal' => <User Object>
                 *  'blocks' => [
                 *      [
                 *          'blockCode' => <cod_bloque>,
                 *          'value' => <valor_bloque>,
                 *          'name' => <nombre_bloque>
                 *      ],
                 *      [
                 *          'blockCode' => <cod_bloque>,
                 *          'value' => <valor_bloque>,
                 *          'name' => <nombre_bloque>
                 *      ],
                 *      ...
                 *  ]
                 *  'users' => [
                 *      <cod_usuario> => [
                 *          'user' => <User Object>,
                 *          'attendance' => <asistencia_cumplida>,
                 *          'finalGrade' => <nota_final>
                 *          'payCode' => <cod_boleta>,
                 *          'grades' => [
                 *              <cod_bloque> => <nota>,
                 *              <cod_bloque> => <nota>,
                 *              <cod_bloque> => <nota>
                 *          ]
                 *      ],
                 *      <cod_usuario> => [
                 *          'user' => <User Object>,
                 *          'attendance' => <asistencia_cumplida>,
                 *          'finalGrade' => <nota_final>
                 *          'payCode' => <cod_boleta>,
                 *          'grades' => [
                 *              <cod_bloque> => <nota>,
                 *              <cod_bloque> => <nota>,
                 *              <cod_bloque> => <nota>
                 *          ]
                 *      ],
                 *      ...
                 *  ]
                 * ]
                 */

                $data = [];
                $data['actCode'] = $actCode;
                $data['generationDate'] = $actData['fecha_generacion'];
                $data['timetable'] = new Timetable($actData);
                $data['grade'] = $actData['grado_academico'];
                $data['career'] = $actData['nombre_carrera'];
                $data['principal'] = $principal;
                $data['minApprove'] = $this->getGlobal(GM::MINIMUM_GRADE_APPROVAL, 70);

                //READING ACT DETAILS
                try {
                    $table = new TableGateway(['da' => 'detalle_acta_oficial'], $this->dbAdapter);
                    $select = $table->getSql()->select();
                    $select->join(['u' => 'usuario'], 'da.cod_usuario = u.cod_usuario');
                    $select->where([
                        'cod_acta' => $actCode,
                        'cod_tipo_acta' => Assignment::EEP_REGULAR
                    ]);
                    $result = $table->selectWith($select);
                    if ($result->count() == 0) {
                        $res->failure("No se encontró el detalle del acta");
                    } else {
                        $resultData = $result->toArray();
                        //STRUCTURING BLOCKS DATA
                        $blockGroupData = json_decode(current($resultData)['data'], true)['ns'];
                        $data['blocks'] = [];
                        foreach ($blockGroupData as $blockCode => $blockData) {
                            $data['blocks'][] = [
                                'blockCode' => $blockCode,
                                'value' => $blockData['vb'],
                                'name' => $blockData['b']
                            ];
                        }
                        //STRUCTURING GRADES
                        foreach ($resultData as $row) {
                            $userCode = $row['cod_usuario'];
                            $userData = json_decode($row['data'], true);
                            $data['users'][$userCode]['user'] = new User($row);
                            $data['users'][$userCode]['attendance'] = $userData['as'];
                            $data['users'][$userCode]['finalGrade'] = $userData['nf'];
                            $data['users'][$userCode]['payCode'] = $userData['cb'];
                            $gradesData = $userData['ns'];
                            foreach ($gradesData as $blockCode => $gradeData) {
                                $data['users'][$userCode]['grades'][$blockCode] = $gradeData['n'];
                            }
                        }
                        $res->setObj($data);
                    }
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron consultar los detalles del acta $actCode", $ex);
                }
            }
        }
        return $res;
    }

    public function getTimetableActGralData($actCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $result = $table->select([
                'cod_acta_oficial' => $actCode,
                'cod_tipo_acta' => Assignment::EEP_REGULAR
            ]);
            if ($result->count() == 0) {
                $res->warning("No se encontró el acta oficial '$actCode'");
            } else {
                $res->setObj($result->current());
            }
        } catch (\Exception $ex) {
            $res->failure("No se pudo leer la información de catedrático y coordinador del acta $actCode", $ex);
        }
        return $res;
    }

    public function getTimetableOfficializationData($careerCode, $year): R {
        $res = new R();
        try {
            //GRAL QUERY
            $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['cat' => 'usuario'], 'h.cod_usuario_catedratico= cat.cod_usuario', ['nombres_catedratico' => 'nombres', 'apellidos_catedratico' => 'apellidos']);
            $select->join(['c' => 'curso_pensum'], 'h.cod_curso = c.cod_curso and h.cod_pensum = c.cod_pensum', ['nombre_curso' => 'nombre', 'alias']);
            $select->join(['pe' => 'pensum'], 'c.cod_pensum = pe.cod_pensum', []);
            $select->where([
                'pe.cod_carrera' => $careerCode,
                'h.anio' => $year,
                'h.cod_pensum <> ' . O::CURSO_ACTUALIZACION
            ]);
            $obj = [];
            $ttData = $table->selectWith($select)->toArray();
            $grades = new TableGateway('asignacion', $this->dbAdapter);
            foreach ($ttData as $d) {
                //GETTING OFFICIALIZED GRADES DATA
                $timetableCode = $d['cod_horario'];
                $result = $grades->select([
                            'cod_horario' => $timetableCode
                        ])->toArray();
                $total = 0;
                $officialized = 0;
                foreach ($result as $asg) {
                    $total++;
                    if ($asg['cod_estado_nota'] == Assignment::OFFICIALIZED_NOTE) {
                        $officialized++;
                    }
                }
                //STRUCTURING RESULT OBJECT
                $month = $d['mes'];
                $obj[$month][] = [
                    'officialized' => $officialized,
                    'totalGrades' => $total,
                    'timetable' => new Timetable($d)
                ];
            }
            $res->success();
            $res->setObj($obj);
        } catch (\Exception $ex) {
            $res->addError("Carrera: $careerCode. Año: $year");
            $res->failure('No se pudo realizar la consulta de datos', $ex);
        }
        return $res;
    }

    public function getOfficialGradesData($timetableCode): R {
        //UPDATING USERS INSCRIPTIONS STATUS
        $result = $this->updateTimetableUsersInscriptionStatus($timetableCode);
        if (!$result->get()) {
            return $result;
        }
        $res = new R();
        $res->success(); //POSITVE LOGIC
        /* FORMAT
         * $data = [
         *  <cod_usuario> => [
         *      'user' => <User Object>,
         *      'valid' => <true/false>,
         *      'ballot' => <boleta>,
         *      'grades' => [
         *          <cod_bloque> => <nota>,
         *          <cod_bloque> => <nota>,
         *          ...
         *      ],
         *      'postActAvailable' = <true/false>,
         *      'attendance' => <asistencia_cumplida>,
         *      'finalGrade' => <nota_final>,
         *      'officialAct' => <cod_acta - asignacion>,
         *      'postAct' => <cod_acta - postgrados>,
         *      'gradeStatus' => <estado_nota>,
         *      'gradeStatusCode' => <cod_estado_nota>,
         *      'officialGrade' => [
         *          'grade' => <nota nota_final>,
         *          'approved' => <aprobado true|false>,
         *          'correctionsDetail' => [
         *              [
         *                  'nota_previa'=> <nota_previa>,
         *                  'nota_nueva'=> <nota_nueva>,
         *                  'justificacion'=> <justificacion>,
         *                  'fecha_correccion'=> <fecha_correccion>,
         *              ],
         *              ...
         *          ]
         *      ]
         *  ],
         *  ...
         * ]
         */
        try {
            $table = new TableGateway(['a' => 'asignacion'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['o' => 'orden_pago'], 'a.cod_orden = o.cod_orden', ['boleta' => 'cod_boleta']);
            $select->join(['u' => 'usuario'], 'a.cod_usuario= u.cod_usuario');
            $select->join(['h' => 'horario'], 'a.cod_horario = h.cod_horario', []);
            $select->join(['i' => 'inscripcion'], 'h.cod_pensum = i.cod_pensum and h.anio = i.anio and a.cod_usuario = i.cod_usuario', ['inscrito' => 'anio'], Select::JOIN_LEFT);
            $select->join(['b' => 'bloque'], 'h.cod_horario = b.cod_horario', ['cod_bloque']);
            $select->join(['na' => 'nota_asignatura'], 'b.cod_bloque = na.cod_bloque and a.cod_usuario = na.cod_usuario', ['grade' => 'nota'], Select::JOIN_LEFT);
            $select->join(['en' => 'estado_nota'], 'a.cod_estado_nota = en.cod_estado_nota', ['gradeStatus' => 'nombre']);
            $select->join(['ao' => 'detalle_acta_oficial'], 'a.cod_usuario = ao.cod_usuario and a.cod_horario = ao.cod_horario', ['officialAct' => 'cod_acta'], Select::JOIN_LEFT);
            $select->join(['nf' => 'nota_final'], 'a.cod_usuario = nf.cod_usuario and a.cod_horario = nf.cod_horario', ['cod_nota_final', 'officialFinalGrade' => 'nota', 'approved' => 'aprobado'], Select::JOIN_LEFT);
            $select->join(['dap' => 'detalle_acta_postgrados'], 'nf.cod_nota_final = dap.cod_nota_final', ['postAct' => 'cod_acta'], Select::JOIN_LEFT);
            $select->order('u.registro_academico ASC');
            $select->where([
                'a.cod_horario' => $timetableCode
            ]);
            $result = $table->selectWith($select)->toArray();
            $obj = [];
            foreach ($result as $d) {
                $userCode = $d['cod_usuario'];
                //ADDING USER DATA IF NOT SET
                $userObject = new User($d);
                if (!isset($obj[$userCode])) {
                    $obj[$userCode] = [
                        'user' => $userObject,
                        'valid' => ($d['valida'] == 1),
                        'attendance' => $d['asistencia_cumplida'],
                        'finalGrade' => $d['nota_final'],
                        'officialAct' => $d['officialAct'],
                        'ballot' => $d['boleta'],
                        'postAct' => $d['postAct'],
                        'gradeStatus' => $d['gradeStatus'],
                        'gradeStatusCode' => $d['cod_estado_nota'],
                        'officialGrade' => null,
                        'postActAvailable' => ($userObject->getInscriptionStatus() != null) && ($d['officialAct'] == null) && ($d['postAct'] == null) && ($d['valida'] == true)
                    ];
                    if ($d['cod_nota_final'] != null) {
                        $obj[$userCode]['officialGrade'] = [
                            'grade' => $d['officialFinalGrade'],
                            'approved' => ($d['approved'] == '1'),
                            'correctionDetail' => null
                        ];
                        $correctionsTable = new TableGateway('detalle_correccion', $this->dbAdapter);
                        $corrections = $correctionsTable->select([
                                    'cod_nota_final' => $d['cod_nota_final']
                                ])->toArray();
                        if (count($corrections) != 0) {
                            uasort($corrections, function($a, $b) {
                                $aVal = strtotime($a['fecha_correccion']);
                                $bVal = strtotime($b['fecha_correccion']);
                                if ($aVal == $bVal) {
                                    return 0;
                                }
                                return $aVal < $bVal ? 1 : -1; //BIGGEST TO LOWEST
                            });
                            $obj[$userCode]['officialGrade']['correctionDetail'] = $corrections;
                        }
                    }
                }
                //ADDING GRADES DETAIL
                $obj[$userCode]['grades'][$d['cod_bloque']] = $d['grade'];
            }
            $res->success();
            $res->setObj($obj);
        } catch (\Exception $ex) {
            $res->addError("TimetableCode: $timetableCode");
            $res->failure('No se pudo leer la información para el horario', $ex);
        }
        return $res;
    }

    /*
     * THIS FUNCTION MUST BE SURROUNDED WITH TRY-CATCH
     * RETURNS TRUE IF THERE IS A BETTER FINAL GRADE
     */

    private function degradePreviousFinalGrades($pensumCode, $courseCode, $userCode, $hasGrade, $grade, $approved) {
        $existsBetterFinalGrade = false;
        $finalGradeTable = new TableGateway('nota_final', $this->dbAdapter);
        //SEARCHING FOR COURSES WITH A BETTER GRADE OR APRROVED
        $select = $finalGradeTable->getSql()->select();
        $select->order('nota DESC');
        if ($hasGrade) {
            $select->where([
                'cod_pensum' => $pensumCode,
                'cod_curso' => $courseCode,
                'cod_usuario' => $userCode,
                "nota > $grade",
                'cod_estado_nota_final' => self::FG_STATUS_NO_PROBLEM //NOT ALREADY DEGRADED
            ]);
        } else {
            $select->where([
                'cod_pensum' => $pensumCode,
                'cod_curso' => $courseCode,
                'cod_usuario' => $userCode,
                'aprobado' => true,
                'cod_estado_nota_final' => self::FG_STATUS_NO_PROBLEM //NOT ALREADY DEGRADED
            ]);
        }
        $select->limit(1); //THE HIGHEST. THERE SHOULD BE 1 MAXIMUM
        $result = $finalGradeTable->selectWith($select)->toArray();
        if (count($result) != 0) {
            $betterFinalGrade = current($result);
            $betterFinalGradeCode = $betterFinalGrade['cod_nota_final'];
            $existsBetterFinalGrade = true;
        }
        //DEGRADING PREVIOUS FINAL GRADES (EXCLUDING THE BETTER GRADE IF EXISTS)
        $where = [
            'cod_pensum' => $pensumCode,
            'cod_curso' => $courseCode,
            'cod_usuario' => $userCode,
            'cod_estado_nota_final' => self::FG_STATUS_NO_PROBLEM
        ];
        if ($existsBetterFinalGrade) {
            $where[] = "cod_nota_final <> $betterFinalGradeCode";
        }
        $previousGradeData = $finalGradeTable->select($where)->toArray();
        if (count($previousGradeData) != 0) {
            //DEGRADING PREVIOUS FINAL GRADE STATUS
            foreach ($previousGradeData as $pd) {
                $finalGradeTable->update([
                    'cod_estado_nota_final' => self::FG_STATUS_DEGRADED_FOR_GRADE_IMPROVEMENT
                        ], [
                    'cod_nota_final' => $pd['cod_nota_final']
                ]);
            }
        }
        return $existsBetterFinalGrade;
    }

    public function approveAct($actCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        $this->beginTransaction();
        //GET ACT DETAILS
        try {
            $actDetailTable = new TableGateway(['d' => 'detalle_acta_oficial'], $this->dbAdapter);
            $select = $actDetailTable->getSql()->select();
            $select->join(['h' => 'horario'], 'd.cod_horario = h.cod_horario', ['cod_pensum', 'cod_curso', 'fecha_inicio', 'seccion']);
            $select->where([
                'd.cod_acta' => $actCode
            ]);
            $details = $actDetailTable->selectWith($select)->toArray();
            if (count($details) == 0) {
                $res->failure("El acta $actCode no se aprobó porque no tenía a ningún usuario asociado");
            }
        } catch (\Exception $ex) {
            $res->failure("No se pudo leer el detalle del acta $actCode", $ex);
        }
        //MODIFYING AND ADDING OFFICIAL FINAL GRADE
        if ($res->get()) {
            try {
                //GETTING MIN. GRADE TO APPROVE
                $minToApprove = $this->getGlobal(GM::MINIMUM_GRADE_APPROVAL, 70);
                $finalGradeTable = new TableGateway('nota_final', $this->dbAdapter);
                $assignments = [];
                foreach ($details as $d) {
                    $timetableCode = $d['cod_horario'];
                    $actDetailData = json_decode($d['data'], true);
                    $finalGrade = $actDetailData['nf'] ?? 0;
                    //SEARCHING IF USER HAS ALREADY TAKEN THE COURSE TO CHANGE STATUS
                    $existsBetterGrade = $this->degradePreviousFinalGrades($d['cod_pensum'], $d['cod_curso'], $d['cod_usuario'], true, $finalGrade, null);
                    //CREATING NEW OFFICIAL FINAL GRADE
                    $assignments[] = [new Expression("($d[cod_usuario],$d[cod_horario])")];
                    $set = [
                        'cod_pensum' => $d['cod_pensum'],
                        'cod_curso' => $d['cod_curso'],
                        'seccion' => $d['seccion'],
                        'cod_usuario' => $d['cod_usuario'],
                        'cod_horario' => $timetableCode,
                        'cod_tipo_nota_final' => self::FG_TYPE_REGULAR,
                        'cod_estado_nota_final' => $existsBetterGrade ? self::FG_STATUS_DEGRADED_FOR_GRADE_IMPROVEMENT : self::FG_STATUS_NO_PROBLEM,
                        'nota' => $finalGrade,
                        'fecha_oficializacion' => new Expression('now()'),
                        'fecha_cursado' => $d['fecha_inicio'], //TIMETABLE START DATE
                        'aprobado' => $finalGrade >= $minToApprove,
                        'ponderado_con_nota' => true, //WE ASSUME ALL TIMETABLES ARE PONDERED
                        'observacion' => null
                    ];
                    $finalGradeTable->insert($set);
                }
            } catch (\Exception $ex) {
                $res->failure("No se pudieron agregar y modificar las notas oficiales finales para el acta $actCode", $ex);
            }
        }
        //CHANGE ASSIGNMENT GRADE STATUS
        if ($res->get()) {
            try {
                $asgTable = new TableGateway('asignacion', $this->dbAdapter);
                $where = new Where();
                $where->in(new Expression('(cod_usuario,cod_horario)'), $assignments);
                $asgTable->update([
                    'cod_estado_nota' => Assignment::OFFICIALIZED_NOTE
                        ], $where);
            } catch (\Exception $ex) {
                $res->failure("No se pudo modificar el estado de de las notas de asignación para el acta $actCode", $ex);
            }
        }
        //SET TIMETABLE ACT APPROVEMENT DATE
        if ($res->get()) {
            try {
                $ttTable = new TableGateway('horario', $this->dbAdapter);
                $ttTable->update([
                    'fecha_acta_aprobada' => date('Y-m-d')
                        ], [
                    'cod_horario' => $timetableCode
                ]);
            } catch (\Exception $ex) {
                $res->failure("No se pudo grabar la fecha de oficilización de acta en el horario. Acta $actCode", $ex);
            }
        }
        if ($res->get()) {
            $this->commit();
        } else {
            $this->rollback();
        }
        return $res;
    }

    public function makeGradesOfficial($usersData, $timetableCode): R {
        //READING TIMETABLE DATA
        $actCode = $usersData[PF::ACT];
        $res = $this->timetableManager->getTimetable($timetableCode);
        if ($res->get()) {
            $minToApprove = $this->getGlobal(GM::MINIMUM_GRADE_APPROVAL, 70);
            $timetable = $res->getObj();
            $users = $usersData[PF::USERS];
            $comments = $usersData[PF::COMMENT];
            $finalGrades = $usersData[PF::FINAL_GRADE];
            $ballots = $usersData[PF::BALLOT];
            $finalGradeCodes = [];
            $this->beginTransaction();
            //CHANGING PREVIOUS GRADES
            $hasBetterGrades = [];
            try {
                //SEARCHING IF USER HAS ALREADY TAKEN THE COURSE TO CHANGE STATUS
                $finalGradesTable = new TableGateway('nota_final', $this->dbAdapter);
                foreach ($users as $userCode) {
                    $hasBetterGrades[$userCode] = $this->degradePreviousFinalGrades($timetable->getCodPensum(), $timetable->getCodCurso(), $userCode, true, $finalGrades[$userCode] ?? 0, null);
                }
            } catch (\Exception $ex) {
                $res->failure("No se pudieron actualizar las previas notas finales de usuarios que repiten el curso para mejora de nota", $ex);
                $res->addError("Horario: $timetableCode. Usuarios: " . json_encode($users));
            }
            //CREATING FINAL GRADES REGISTRY
            if ($res->get()) {
                try {
                    foreach ($users as $userCode) {
                        $finalGrade = $finalGrades[$userCode] ?? 0;
                        $set = [
                            'cod_pensum' => $timetable->getCodPensum(),
                            'cod_curso' => $timetable->getCodCurso(),
                            'seccion' => $timetable->getSeccion(),
                            'cod_usuario' => $userCode,
                            'cod_horario' => $timetableCode,
                            'cod_tipo_nota_final' => self::FG_TYPE_POST_ACT_REGULAR,
                            'cod_estado_nota_final' => $hasBetterGrades[$userCode] ? self::FG_STATUS_DEGRADED_FOR_GRADE_IMPROVEMENT : self::FG_STATUS_NO_PROBLEM,
                            'nota' => $finalGrade,
                            'fecha_oficializacion' => date('Y-m-d'),
                            'fecha_cursado' => $timetable->getFechaInicio(),
                            'aprobado' => $finalGrade >= $minToApprove,
                            'ponderado_con_nota' => true, //WE ASSUME ALL TIMETABLES ARE PONDERED. OTHER WAY THE "degradePreviousFinalGrades" METHOD USED ABOVE SHOULD BE CHANGED TOO
                            'observacion' => (empty($comments[$userCode])) ? null : $comments[$userCode]
                        ];
                        $finalGradesTable->insert($set);
                        $finalGradeCode = $finalGradesTable->getLastInsertValue();
                        $finalGradeCodes[$userCode] = [
                            'grade' => $finalGrade,
                            'gradeCode' => $finalGradeCode
                        ];
                    }
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron agregar las notas finales oficiales", $ex);
                    $res->addError("Set: " . json_encode($set ?? '(No declarado)'));
                }
            }
            //ADDING POSTGRADUATE ACT DETAIL ASSOCIATION
            if ($res->get()) {
                try {
                    $postDetailActTable = new TableGateway('detalle_acta_postgrados', $this->dbAdapter);
                    foreach ($users as $userCode) {
                        $set = [
                            'cod_acta' => $actCode,
                            'cod_tipo_acta' => Assignment::EEP_POSTGRADUATE,
                            'cod_nota_final' => $finalGradeCodes[$userCode]['gradeCode'],
                            'nota' => $finalGradeCodes[$userCode]['grade'] ?? 0,
                            'recibo' => $ballots[$userCode] ?? null
                        ];
                        $postDetailActTable->insert($set);
                    }
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron agregar los detalles del acta de postgrado", $ex);
                    $res->addError("Set: " . json_encode($set ?? '(No declarado)'));
                }
            }
            //CHANGING ASSIGNMENT STATUS TO OFFICIALIZED
            if ($res->get()) {
                try {
                    $asgTable = new TableGateway('asignacion', $this->dbAdapter);
                    $asgTable->update([
                        'cod_estado_nota' => Assignment::OFFICIALIZED_NOTE,
                            ], [
                        'cod_usuario' => $users, //IS LIKE AN "IN" STATEMENT
                        'cod_horario' => $timetableCode
                    ]);
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron actualizar los estados de las notas de asignación a oficializadas", $ex);
                    $res->addError("Horario: $timetableCode. Usuarios: " . json_encode($users));
                }
            }
            if ($res->get()) {
                $this->commit();
            } else {
                $this->rollback();
            }
        }
        return $res;
    }

    public function getFinalGradeTypes(): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $table = new TableGateway('tipo_nota_final', $this->dbAdapter);
            $result = $table->select([
                        'cod_tipo_nota_final <> ' . self::FG_TYPE_REGULAR, //BY OFFICIAL ACT
                        'cod_tipo_nota_final <> ' . self::FG_TYPE_POST_ACT_REGULAR, //BY SELECTING WHICH TIMETABLE IS THE FINAL GRADE RELATED TO
                    ])->toArray();
            $obj = [];
            foreach ($result as $d) {
                $obj[$d['cod_tipo_nota_final']] = $d['nombre'];
            }
            $res->setObj($obj);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron buscar los tipos de notas finales', $ex);
        }
        return $res;
    }

    public function addManualFinalGrade($userCode, $pensumCode, $courseCode, $section, $finalGradeType, $ponderType, $grade, $approved, $postAct, $ballot, $description, $date, $supportRollback = true): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        if ($supportRollback) {
            $this->beginTransaction();
        }

        //DEGRADING PREVIOUS FINAL GRADES
        try {
            $existsBetterGrade = $this->degradePreviousFinalGrades($pensumCode, $courseCode, $userCode, $ponderType, $grade, $approved);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron actualizar notas finales anteriores', $ex);
            $res->addError("Pensum: $pensumCode. Curso: $courseCode. Usuario: $userCode");
        }

        //ADDING FINAL GRADE DATA
        if ($res->get()) {
            try {
                $minToApprove = $this->getGlobal(GM::MINIMUM_GRADE_APPROVAL, 70);
                $set = [
                    'cod_pensum' => $pensumCode,
                    'cod_curso' => $courseCode,
                    'seccion' => $section,
                    'cod_usuario' => $userCode,
                    'cod_horario' => null,
                    'cod_tipo_nota_final' => $finalGradeType,
                    'cod_estado_nota_final' => $existsBetterGrade ? self::FG_STATUS_DEGRADED_FOR_GRADE_IMPROVEMENT : self::FG_STATUS_NO_PROBLEM,
                    'nota' => $ponderType ? $grade : null, //$ponderType DEFINES IF THE GRADE HAS GRADE OR NOT
                    'fecha_oficializacion' => date('Y-m-d'),
                    'fecha_cursado' => $date,
                    'aprobado' => $ponderType ? ($grade >= $minToApprove) : $approved,
                    'ponderado_con_nota' => $ponderType,
                    'observacion' => empty($description) ? null : $description
                ];
                $finalGradeTable = new TableGateway('nota_final', $this->dbAdapter);
                $finalGradeTable->insert($set);
                $finalGradeCode = $finalGradeTable->getLastInsertValue();
            } catch (\Exception $ex) {
                $res->failure("No se pudo agregar la nota final", $ex);
                $res->addError("Set: " + json_encode($set ?? null));
            }
        }

        //ADDING POSTGRADUATE ACT DETAILS
        if ($res->get()) {
            try {
                $set = [
                    'cod_acta' => $postAct,
                    'cod_tipo_acta' => Assignment::EEP_POSTGRADUATE,
                    'cod_nota_final' => $finalGradeCode,
                    //IF HAS GRADE, SETS THE GRADE; OTHERWISE WILL HAVE 100 IF APPROVED OR 0 IF NOT
                    'nota' => $ponderType ? $grade : $approved * 100,
                    'recibo' => empty($ballot) ? null : $ballot
                ];
                $postActDetailTable = new TableGateway('detalle_acta_postgrados', $this->dbAdapter);
                $postActDetailTable->insert($set);
            } catch (\Exception $ex) {
                $res->failure("No se pudo agregar el detalle del acta", $ex);
                $res->addError("Set: " + json_encode($set ?? null));
            }
        }

        if ($supportRollback) {
            if ($res->get()) {
                $this->commit();
            } else {
                $this->rollback();
            }
        }

        return $res;
    }

    public function getGradesDetail($timetableCode, $userCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $table = new TableGateway(['na' => 'nota_asignatura'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['b' => 'bloque'], 'na.cod_bloque = b.cod_bloque', ['bloque' => 'nombre', 'valor']);
            $select->where([
                'na.cod_horario' => $timetableCode,
                'na.cod_usuario' => $userCode
            ]);
            $result = $table->selectWith($select)->toArray();
            $detail = [];
            foreach ($result as $gradeData) {
                $blockCode = $gradeData['cod_bloque'];
                $detail[$blockCode] = [
                    'blockName' => $gradeData['bloque'],
                    'blockValue' => $gradeData['valor'],
                    'grade' => $gradeData['nota']
                ];
            }
            $res->setObj($detail);
        } catch (\Exception $ex) {
            $res->failure("No se pudo consultar el detalle de notas", $ex);
            $res->addError("Horario: $timetableCode. Usuario: $userCode");
        }
        return $res;
    }

}
