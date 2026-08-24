<?php

namespace Eep\Service;

use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Timetable;
use Eep\Entity\Result as R;
use Eep\Entity\Order;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Eep\Service\GeneralManager as GM;

class AssignmentManager extends Manager {

    //ACADEMIC CONSOUL 'CONSEJO ACADÉMICO'
    //THIS ACTS MAKE ASSIGNMENT AVAILABLE
    const CA_EXTEMPORARY = 1;
    const CA_EXTRAORDINARY = 2;
    //EEP ACTS FOR MAKING THE GRADE OFFICIAL
    const EEP_REGULAR = 3;
    const EEP_POSTGRADUATE = 4;
    //ASSIGNMENT STATE
    const NO_NOTE = 1;
    const PARCIAL_ENTRY = 2;
    const ENTRY_COMPLETED = 3;
    const ACT_CREATED = 4;
    const OFFICIALIZED_NOTE = 5;

    public function createActIfNotExists($actCode, $type): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        $table = new TableGateway('acta', $this->dbAdapter);
        //SEARCH EXISTENCE
        $actExists = true;
        try {
            $result = $table->select([
                'cod_acta' => $actCode,
                'cod_tipo_acta' => $type
            ]);
            if ($result->count() == 0) {
                $actExists = false;
            }
        } catch (\Exception $ex) {
            $res->failure("No se pudo consultar el acta '$actCode' de tipo '" . $this->strType($type) . "'");
        }
        //CREATE ACT IF NOT EXISTS
        if (!$actExists) {
            try {
                $table->insert([
                    'cod_acta' => $actCode,
                    'cod_tipo_acta' => $type,
                    'fecha_generacion' => date('Y-m-d')
                ]);
            } catch (\Exception $ex) {
                $res->failure("No se pudo agregar el acta '$actCode' de tipo '" . $this->strType($type) . "'");
            }
        }
        return $res;
    }

    public function createInvolvedAct($actCode, $type, $orderCode, $userCode, $timetableCode, $actSubsection = null): R {
        $res = new R();
        $table = new TableGateway('involucrado', $this->dbAdapter);
        try {
            $table->insert([
                'cod_acta' => $actCode,
                'cod_tipo_acta' => $type,
                'inciso' => $actSubsection,
                'cod_orden' => $orderCode,
                'cod_usuario' => $userCode,
                'cod_horario' => $timetableCode
            ]);
            $res->success();
        } catch (\Exception $ex) {
            $res->addMsg('No se pudieron agregar los involucrados en el acta.');
        }
        return $res;
    }

    public function addActData($actCode, $actSubsection, $userCode, $orderCode, $timetableCodes, $type, $supportRollback = true): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        if ($supportRollback) {
            $this->dbAdapter->getDriver()->getConnection()->beginTransaction(); //TRANSACTION START
        }
        //CREATE ACT
        $result = $this->createActIfNotExists($actCode, $type);
        if ($result->get() == true) {
            //CREATE INVOLVED STUDENTS
            foreach ($timetableCodes as $ttCode) {
                $result = $this->createInvolvedAct($actCode, $type, $orderCode, $userCode, $ttCode, $actSubsection);
                if ($result->get() == false) {
                    $res = $result;
                    break;
                }
            }
        } else {
            $res = $result;
        }
        //COMMITING OR ROLLING BACK
        if ($supportRollback) {
            if ($res->get() == false) {
                $this->dbAdapter->getDriver()->getConnection()->rollback();
            } else {
                $this->dbAdapter->getDriver()->getConnection()->commit();
            }
        }
        return $res;
    }

    private static function strType($type): string {
        switch ($type) {
            case self::CA_EXTEMPORARY:
                $text = 'Consejo Académico - Extemporánea';
                break;
            case self::CA_EXTRAORDINARY:
                $text = 'Consejo Académico - Extraordinaria';
                break;
            case self::EEP_REGULAR:
                $text = 'Acta Oficial de Notas';
                break;
            case self::EEP_EXTEMPORARY:
                $text = 'Acta de Postgrados - Extemporánea';
                break;
            case self::EEP_EXTRAORDINARY:
                $text = 'Acta de Postgrados - Extraordinaria';
                break;
            default:
                $text = "($type) Inexistente";
                break;
        }
        return $text;
    }

    /**
     * 
     * @param type $userCode
     * @param type $tt
     * @return R    //RETURNS AS OBJECT ALL THE NOT ASSIGNED TIMETABLES
     */
    public function assignPayedTimetables($userCode, $tt): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //CHECKING PAYED TIMETABLES
        $table = new TableGateway(['o' => 'orden_pago'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->columns(['cod_orden']);
        $select->join(['cop' => 'cursos_orden_pago'], 'o.cod_orden = cop.cod_orden', ['cod_horario']);
        $ttWhere = [];
        foreach ($tt as $ttCode) {
            $ttWhere[] = "cod_horario = $ttCode";
        }
        $ttWhereTxt = implode(' or ', $ttWhere);
        $select->where([
            'o.cod_usuario' => $userCode,
            'o.pagada' => 1,
            "( $ttWhereTxt )"
        ]);
        $select->order('o.cod_orden DESC');
        try {
            $return = $table->selectWith($select);
            $ttToAdd = $return->toArray();
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar los horarios pagados');
        }
        //ADDING TIMETABLES ASSIGNMENTS IF NEEDED
        if ($res->get() == true && count($ttToAdd) > 0) {//NO ERRORS
            $orderWithTimetables = [];
            foreach ($ttToAdd as $ttData) {
                $ttCode = $ttData['cod_horario'];
                $orderCode = $ttData['cod_orden'];
                $orderWithTimetables[$orderCode][] = $ttCode;
            }
            //USING TRANSACTIONALITY TO ADD TIMETABLES
            $this->dbAdapter->getDriver()->getConnection()->beginTransaction();
            foreach ($orderWithTimetables as $orderCode => $ttCodes) {
                $result = $this->assign($userCode, $ttCodes, $orderCode, false); //SUPPORTING ROLLBACK EXTERNALLY BECAUSE THERE MIGHT BE A COMPLETE LOOP WITH NO ERROR AND OTHER NEXT ONE WITH ERROR
                if ($result->get() == false) {
                    break;
                }
            }
            if ($result->get() == true) {
                $this->dbAdapter->getDriver()->getConnection()->commit();
                //DELETING FROM REQUESTED TIMETABLES ALL THE ASSIGNED TIMETABLES
                foreach ($orderWithTimetables as $ttCodes) {
                    $tt = array_diff($tt, $ttCodes);
                }
            } else {
                $this->dbAdapter->getDriver()->getConnection()->rollback();
                $res = $result;
            }
        }
        $res->setObj($tt);
        return $res;
    }

    public function assign($userCode, Array $timetableCodes, $orderCode, $supportRollback = true): R {
        $res = new R();
        $asgTable = new TableGateway('asignacion', $this->dbAdapter);
        $courseTtTable = new TableGateway('cursos_orden_pago', $this->dbAdapter);
        if ($supportRollback) {
            $this->dbAdapter->getDriver()->getConnection()->beginTransaction();
        }
        try {
            $date = date('Y-m-d');
            foreach ($timetableCodes as $ttCode) {
                $asgTable->insert([
                    'cod_usuario' => $userCode,
                    'cod_horario' => $ttCode,
                    'cod_orden' => $orderCode,
                    'valida' => 1,
                    'nota_final' => null,
                    'fecha_asignacion' => $date,
                    'cod_estado_nota' => self::NO_NOTE
                ]);
                //UPDATE TIMETABLE AND PAYMENT ORDER ASSOCCIATION IF EXISTS
                $result = $courseTtTable->select([
                    'cod_horario' => $ttCode,
                    'cod_orden' => $orderCode
                ]);
                if ($result->count() == 1) {
                    $courseTtTable->update([
                        //SET
                        'asignacion_efectuada' => 1
                            ], [
                        //WHERE
                        'cod_horario' => $ttCode,
                        'cod_orden' => $orderCode
                    ]);
                }
            }
            if ($supportRollback) {
                $this->dbAdapter->getDriver()->getConnection()->commit();
            }
            $res->success();
        } catch (\Exception $ex) {
            if ($supportRollback) {
                $this->dbAdapter->getDriver()->getConnection()->rollback();
            }
            $res->addMsg('No se pudieron agregar las asignaciones');
            $res->addMsg($ex->getMessage());
            $res->addError($ex);
        }
        return $res;
    }

    public function getUserCourses($userCode): R {
        $res = new R();
        $table = new TableGateway(['a' => 'asignacion'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['e' => 'estado_nota'], 'a.cod_estado_nota = e.cod_estado_nota', ['estado_nota' => 'nombre']);
        $select->join(['h' => 'horario'], 'a.cod_horario = h.cod_horario');
        $select->join(['cp' => 'curso_pensum'], 'h.cod_pensum = cp.cod_pensum and h.cod_curso = cp.cod_curso', ['nombre_curso' => 'nombre']);
        $select->join(['p' => 'pensum'], 'cp.cod_pensum = p.cod_pensum', []);
        $select->join(['nc1' => 'nombre_carrera'], 'p.cod_carrera = nc1.cod_carrera', ['nombre_carrera' => 'nombre', 'alias_carrera' => 'alias']);
        $select->join(['cop' => 'cursos_orden_pago'], 'h.cod_horario = cop.cod_horario');
        $select->join(['o' => 'orden_pago'], 'cop.cod_orden = o.cod_orden');
        $select->where([
            'a.cod_usuario' => $userCode,
            'nc1.tiempo = (  select max(nc2.tiempo) from nombre_carrera nc2 
                            where nc2.cod_carrera = nc1.cod_carrera 
                            and nc2.tiempo <= o.fecha_generacion   )',
            'o.cod_orden = a.cod_orden'
        ]);
        //CHECKING INSCRIPTION STATUS
        $select->join(['i' => 'inscripcion'], new Expression('a.cod_usuario = i.cod_usuario and YEAR(h.fecha_inicio) = i.anio'), ['official' => 'anio'], Select::JOIN_LEFT);
        $select->order('p.cod_carrera ASC');
        $select->order('a.fecha_asignacion ASC');
        try {
            $result = $table->selectWith($select)->toArray();
            $coursesByCareer = [];
            foreach ($result as $data) {
                $order = new Order($data);
                $timetable = new Timetable($data);
                $coursesByCareer[$data['nombre_carrera']][] = [
                    'timetable' => $timetable,
                    'order' => $order,
                    'userCode' => $data['cod_usuario']
                ];
            }
            $res->success();
            $res->setObj($coursesByCareer);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar los cursos asignados', $ex);
        }
        return $res;
    }

    public function assignIfValid(Order $order): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //EXTRACTING TIMETABLES
        $details = $order->getDetail();
        $timetables = [];
        $regularDays = $this->getGlobal(GM::ASSIGNMENT_DAYS, 5);
        foreach ($details as $d) {
            $ttCode = $d->getCodHorario();
            $starTDate = $d->getFechaInicio();
            $timetables[$ttCode]['date'] = $starTDate;
            $timetables[$ttCode]['days'] = $regularDays;
            $timetables[$ttCode]['detail'] = $d;
        }
        //LOOKING FOR ORDER ACT INVOLVEMENT
        try {
            $involvedTable = new TableGateway('involucrado', $this->dbAdapter);
            $result = $involvedTable->select([
                'cod_usuario' => $order->getCodUsuario(),
                'cod_orden' => $order->getCodOrden(),
                //'cod_tipo_acta' => self::CA_EXTEMPORARY
                '( cod_tipo_acta = ' . self::CA_EXTEMPORARY . ' or cod_tipo_acta = ' . self::CA_EXTRAORDINARY . ')'
            ]);
            if ($result->count() != 0) {
                $involvedTts = $result->toArray();
                $extAssignmentDays = $this->getGlobal(GM::EXT_ASSIGNMENT_DAYS, 5);
                foreach ($involvedTts as $i) {
                    $ttCode = $i['cod_horario'];
                    $type = $i['cod_tipo_acta'];
                    $generationDate = $order->getFechaGeneracion();
                    if ($type == self::CA_EXTEMPORARY) {
                        $timetables[$ttCode]['date'] = $generationDate;
                        $timetables[$ttCode]['days'] = $extAssignmentDays;
                    } else {//EXTRAORDINARY
                        $timetables[$ttCode]['date'] = $generationDate;
                        $timetables[$ttCode]['days'] = $extAssignmentDays; //BY NOW, THE TIME IS THE SAME FOR BOTH ACT TYPES
                    }
                }
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudieron buscar las actas de los horarios involucrados a la orden de pago: ' . $ex->getMessage());
        }
        //DISCARDING EXPIRED TIMETABLES
        $outOfTime = [];
        if ($res->get() == true) {
            $ttCount = count($timetables);
            $userId = $order->getIdPersona();
            $res->addMsg("Hay $ttCount horarios involucrados en la orden de pago del usuario $userId.");
            foreach ($timetables as $ttCode => $attr) {
                $days = $attr['days'];
                $startDate = $attr['date'];
                $limit = strtotime("+ $days weekdays $startDate");
                $paymentDate = strtotime($order->getFechaPago()); //COMPARING THE PAYMENT DATE
                if ($limit < $paymentDate) {
                    $res->addMsg("Horario Cód. $ttCode fuera de fecha. Fecha de pago: " . date('d/m/Y', $paymentDate) . ' - Fecha Límite: ' . date('d/m/Y', $limit));
                    $detail = $timetables[$ttCode]['detail'];
                    $courseName = $detail->getNombreCurso();
                    $sectionName = $detail->getSeccion();
                    $outOfTime[] = [
                        'course' => $courseName,
                        'section' => $sectionName,
                        'payedDay' => date('d/m/Y', $paymentDate),
                        'limitDay' => date('d/m/Y', $limit)
                    ];
                    unset($timetables[$ttCode]);
                } else {
                    $res->addMsg("Horario Cód. $ttCode en fecha.");
                }
            }
            $result = $this->assign($order->getCodUsuario(), array_keys($timetables), $order->getCodOrden());
            if ($result->get() == false) {
                $res->failure($result->getMsg());
                $res->addError($res->getError());
            }
        }
        $res->setObj($outOfTime);
        return $res;
    }

    public function getUserCourseStaff($userCode) {
        $table = new TableGateway(['a' => 'asignacion'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->columns([]);
        $select->quantifier(Select::QUANTIFIER_DISTINCT);

        $select->join(
            ['h' => 'horario'],
            'h.cod_horario = a.cod_horario',
            [
                'cod_horario',
                'cod_usuario_coordinador',
                'cod_usuario_catedratico',
                'seccion',
                'fecha_inicio',
                'fecha_fin',
                'mes',
                'anio'
            ]
        );
        $select->join(
            ['cp' => 'curso_pensum'],
            'cp.cod_pensum = h.cod_pensum AND cp.cod_curso = h.cod_curso',
            ['nombre_curso' => 'nombre']
        );
        $select->join(
            ['coord' => 'usuario'],
            'coord.cod_usuario = h.cod_usuario_coordinador',
            ['nombres_coordinador' => 'nombres', 'apellidos_coordinador' => 'apellidos'],
            Select::JOIN_LEFT
        );
        $select->join(
            ['cat' => 'usuario'],
            'cat.cod_usuario = h.cod_usuario_catedratico',
            ['nombres_catedratico' => 'nombres', 'apellidos_catedratico' => 'apellidos'],
            Select::JOIN_LEFT
        );

        $select->where([
            'a.cod_usuario' => $userCode,
            'a.valida' => 1
        ]);
        $select->order('h.fecha_inicio DESC');

        return $table->selectWith($select)->toArray();
    }

}
