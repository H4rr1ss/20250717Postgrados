<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Eep\Entity\Timetable;
use Zend\Db\Sql\Select;
use Eep\Entity\Order as O;
use Eep\Service\AcademyManager;
use Eep\Form\AssignmentForm;
use Eep\Service\GeneralManager as GM;

class TimetableManager extends Manager {

    private $academyManager;
    private $inscriptionManager;

    public function __construct(Adapter $dbAdapter, AcademyManager $academyManager, InscriptionManager $inscriptionManager) {
        parent::__construct($dbAdapter);
        $this->academyManager = $academyManager;
        $this->inscriptionManager = $inscriptionManager;
    }

    /* RESPONSE WILL HAVE:
     * - RESULT TRUE IF SUCCESS, FALSE IF FAIL
     * - DETAIL: COURSES TIMETABLE IF SUCCESS, ERROR DETAIL IF FAILS
     */

    public function getUserTimetable($userCode = null, $excludeActivePaymentOrderAsoc = false, $excludePayedPaymentOrderAsoc = false, $includeUpdatingCourses = true, $includeWholeCareer = false, $getActiveTimetables = false, $year = null, $excludeAssignedCourses = false, $changeExcludeToInclude = false): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['cp' => 'curso_pensum'], 'h.cod_curso = cp.cod_curso and h.cod_pensum = cp.cod_pensum', ['nombre_curso' => 'nombre', 'alias']);
        $select->join(['p' => 'pensum'], 'cp.cod_pensum = p.cod_pensum', ['cod_carrera']);
        $select->join(['nc1' => 'nombre_carrera'], 'p.cod_carrera = nc1.cod_carrera', ['carrera' => 'nombre']);
        $select->order('p.cod_carrera asc');

        //TIME PERIOD FILTERING
        $dateWhere = "";
        if ($year != null) {
            $startDateLimit = "$year-01-01";
            $finishDateLimit = "$year-12-31";
            //FILTERING ASSIGNMENT TIME INTERVAL
            $dateWhere = "h.fecha_fin >= '$startDateLimit' and h.fecha_inicio<= '$finishDateLimit'";
        } else {
            $daysAfterBegginingOfTimetable = $this->getGlobal(GM::ASSIGNMENT_DAYS, 5);
            if ($getActiveTimetables == false) {
                $startDateLimit = date('Y-m-d', strtotime('+ ' . $daysAfterBegginingOfTimetable . ' weekdays'));
                $finishDateLimit = date('Y-m-d', strtotime('- ' . $daysAfterBegginingOfTimetable . ' weekdays'));
                //FILTERING ASSIGNMENT TIME INTERVAL
                $dateWhere = "'$startDateLimit' >= h.fecha_inicio and '$finishDateLimit' <=h.fecha_inicio";
            } else {
                $startDateLimit = date('Y-m-d', strtotime('+ ' . $daysAfterBegginingOfTimetable . ' weekdays'));
                $finishDateLimit = date('Y-m-d'); //, strtotime('- ' . $daysAfterBegginingOfTimetable . ' weekdays'));
                //FILTERING ASSIGNMENT TIME INTERVAL
                $dateWhere = "'$startDateLimit' >= h.fecha_inicio and '$finishDateLimit' <=h.fecha_fin";
            }
        }
        $select->where([$dateWhere]);

        //TIMETABLE INFO
        $select->join(['s' => 'salon'], 'h.cod_salon = s.cod_salon', ['cod_salon', 'salon' => 'nombre'], Select::JOIN_LEFT);
        $select->join(['u' => 'ubicacion'], 's.cod_ubicacion = u.cod_ubicacion', ['cod_ubicacion', 'ubicacion' => 'nombre'], Select::JOIN_LEFT);
        $select->join(['tc' => 'tipo_curso'], 'tc.cod_tipo_curso = h.cod_tipo_curso', ['cod_tipo_curso', 'tipo_curso' => 'nombre']);
        $select->join(['uco' => 'usuario'], 'h.cod_usuario_coordinador = uco.cod_usuario', ['nombres_coordinador' => 'nombres', 'apellidos_coordinador' => 'apellidos'], Select::JOIN_LEFT);
        $select->join(['uca' => 'usuario'], 'h.cod_usuario_catedratico = uca.cod_usuario    ', ['nombres_catedratico' => 'nombres', 'apellidos_catedratico' => 'apellidos'], Select::JOIN_LEFT); //JOIN_LEFT
        //USER SPECIFIC VALIDATION
        if ($userCode != null) {
            //GETTING USER ACTIVE CARRER AND PENSUM
            $result = $this->academyManager->getUserCareer($userCode, $year);
            if ($result->get() == false) {
                return $result;
            } else {
                $assignedCareers = $result->getObj();
                if (count($assignedCareers) > 1) {
//                    $res->failure('Tienes múltiples carreras asignadas a la vez' . ($year == null ? '.' : " para el año $year."));
//                    return $res;
                    $assignedCareers = [current($assignedCareers)];
                }

                $hasCareer = count($assignedCareers) == 1;
                //VALIDATING INSCRIPTION
                if ($hasCareer) {
                    $result = $this->inscriptionManager->isInscriptionValid($userCode, $year);
                    $inscribed = $result->get();
                    $inscriptionStatus = $result->getObj();
                    if ($inscriptionStatus != InscriptionManager::COMPLETE_INSCRIPTION && $inscriptionStatus != InscriptionManager::ORDER_PENDING) {
                        $res->addMsg($result->getMsg()); //SHOW THE STUDENT INSCRIPTION DENIED STATUS
                        $res->setType($result->getType());
                    }
                    if ($inscribed) {
                        $careerData = array_pop($assignedCareers);
                        $careerCode = $careerData['cod_carrera'];
                        $pensumCode = $careerData['cod_pensum'];
                        $cohortDate = "'" . $careerData['fecha_cohorte'] . "'";
                        $date = "'" . $careerData['fecha_asignacion'] . "'";
                    } else {
                        if ($includeUpdatingCourses) {
                            $res->addMsg('Únicamente podrás ver y asignarte a los Cursos de Actualización Profesional disponibles');
                            $careerCode = O::CURSO_ACTUALIZACION;
                            $pensumCode = O::CURSO_ACTUALIZACION;
                            $cohortDate = null;
                            $date = 'curdate()';
                        } else {
                            return $res;
                        }
                    }
                } else {
                    if ($includeUpdatingCourses) {
                        $careerCode = O::CURSO_ACTUALIZACION;
                        $pensumCode = O::CURSO_ACTUALIZACION;
                        $cohortDate = null;
                        $date = 'curdate()';
                    } else {
                        $res->failure('Sin carreras asignadas ni se solicita ver horarios de los Cursos de Actualización Profesional');
                        return $res;
                    }
                }
                //FILTERING CARREER AND PENSUM PARAMETERS
                if ($includeWholeCareer == true && $careerCode != O::CURSO_ACTUALIZACION) {
                    //USER CAREER CODE
                    $where = "p.cod_carrera = $careerCode and h.fecha_cohorte <> $cohortDate"; //EXCLUDING ITS OWN COHORT TIMETABLES
                } else {
                    //USER PENSUM CODE
                    $where = "p.cod_pensum = $pensumCode" . ($cohortDate == null ? '' : " and h.fecha_cohorte = $cohortDate");
                }
                //INCLUDING PROFESSOR TIMETABLES
                $where = "(($where) or h.cod_usuario_catedratico = $userCode or h.cod_usuario_coordinador = $userCode)";
                //ADDING UPDATING COURSES IF SPECIFIED
                if ($includeUpdatingCourses == true && $pensumCode != O::CURSO_ACTUALIZACION) {
                    $where = "(($where) or p.cod_pensum = " . O::CURSO_ACTUALIZACION . ')';
                }
                $select->where([$where]);
            }
        } else {
            $date = 'curdate()';
        }
        //GETTING CAREER NAME FOR THAT DATE
        $select->where([
            "nc1.tiempo = 
            (   select max(nc2.tiempo) from nombre_carrera nc2 
                where nc2.cod_carrera = nc1.cod_carrera 
                and nc2.tiempo <= $date )"
        ]);
        $select->order('h.cod_pensum ASC');
        $select->order('h.cod_curso ASC');
        //EXCLUDING TIMETABLES WITH A PAYMENT ORDER ASSOCCIATED
        if (($excludeActivePaymentOrderAsoc || $excludePayedPaymentOrderAsoc) && $userCode != null) {
            //CREATING SUBQUERY FOR THE PAYMENT ORDERS
            $subSelect = new Select(['o' => 'orden_pago']);
            $subSelect->columns([]);
            $subSelect->join(['cop1' => 'cursos_orden_pago'], 'o.cod_orden = cop1.cod_orden', []);
            $subSelect->join(['h2' => 'horario'], 'cop1.cod_horario = h2.cod_horario', ['cod_pensum', 'cod_curso', 'cod_horario', 'mes_inicio' => 'mes', 'anio', 'mes_fin' => new Expression("MONTH(fecha_fin)")]); //THIS JOIN ALLOW US TO LINK THE TIMETABLE WITH ALL THE TIMETABLES WITH THAT COURSE/PENSUM
            $exclusionArray = [];
            if ($excludeActivePaymentOrderAsoc) {
                $exclusionArray[] = 'o.activa = 1';
            }
            if ($excludePayedPaymentOrderAsoc) {
                $exclusionArray[] = 'o.pagada = 1';
            }
            $exclusionWhere['o.cod_usuario'] = $userCode;
            if (!empty($exclusionArray)) {
                //THIS OPTION WILL EXCLUDE ONLY THE TIMETABLES WITH AN ACTIVE PAYMENT ORDER AND TIMETABLES WITH PAYED ORDER
                $exclusionWhere[] = '(' . implode(' or ', $exclusionArray) . ')';
            }
            $subSelect->where($exclusionWhere);
            //ADDING SUBQUERY
            //EXCLUDING COURSES WITH A PAYMENT ORDER FOR ANY TIMETABLE
            if ($changeExcludeToInclude) {
                //JOINS DIRECTLY WITH DE TIMETABLE CODE
                $select->join(['cop' => $subSelect], 'h.cod_horario = cop.cod_horario', [], Select::JOIN_INNER);
            } else {
                //JOINS WITH PENSUM AND COURSE CODE BECAUSE THIS WAY IT EXCLUDES OTHER TIMETABLES OF THE SAME COURSE
                $select->join(['cop' => $subSelect], new Expression('h.cod_pensum = cop.cod_pensum and h.cod_curso = cop.cod_curso'
                        //MONTH AND YEAR IS ADDED BECAUSE USERS MIGHT 
                        . ' and (h.mes = cop.mes_inicio or month(h.fecha_fin) = cop.mes_fin) and h.anio = cop.anio '), [], Select::JOIN_LEFT);
                $select->where([
                    //'cop.cod_horario is NULL'
                    'cop.cod_pensum is NULL and cop.cod_curso is NULL'
                ]);
            }
        }
        //EXCLUDING ASSIGNED TIMETABLES
        //NOTE: OTHER WAY TO DO IT IS TO EXCLUDE REGISTRIES OF 'cursos_orden_pago" WHERE THE COLUMN 'asignacion_efectuada' IS FALSE (0). BUT THIS ONE IS MORE SECURE
        if ($excludeAssignedCourses == true && $userCode != null) {
            //CREATING SUBQUERY FOR THE ASSIGNED TIMETABLES
            $subSelect = new Select(['a' => 'asignacion']);
            $subSelect->columns([]);
            $subSelect->join(['h3' => 'horario'], 'a.cod_horario = h3.cod_horario', ['cod_pensum', 'cod_curso', 'mes_inicio' => 'mes', 'anio', 'mes_fin' => new Expression("MONTH(fecha_fin)")]); //THIS JOIN ALLOW US TO LINK THE ASSIGNED TIMETABLES WITH ALL THE TIMETABLES WITH THAT COURSE/PENSUM
            $subSelect->where([
                'a.cod_usuario' => $userCode
            ]);
            //ADDING SUBQUERY - HAS TO BE SUBQUERIED, OTHER WAY, THE USER CODE VALIDATION WILL BE DONE AFTER THE LEFT JOIN
            $select->join(['asgC' => $subSelect], new Expression('h.cod_curso = asgC.cod_curso and h.cod_pensum = asgC.cod_pensum'
                    //THIS EXTRA FIELDS ARE ADDED TO REMOVE OTHER TIMETABLES FOR THE SAME COURSE BUT ONLY IF THEY ARE IN THE SAME PERIOD OF TIME
                    //BASED ON THE START MONTH 
                    . ' and (h.mes = asgC.mes_inicio or month(h.fecha_fin) = asgC.mes_fin) and h.anio = asgC.anio'), [], Select::JOIN_LEFT);
            //), [], Select::JOIN_LEFT);
            $select->where([
                'asgC.cod_curso is NULL and asgC.cod_pensum is NULL'
            ]);
        }

        try {
            $result = $table->selectWith($select)->toArray();
            $careers = [];
            foreach ($result as $queriedTimetable) {
                $loopCareerName = $queriedTimetable['carrera'];
                $careers[$loopCareerName][] = new Timetable($queriedTimetable);
            }
            $res->setObj($careers);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar los horarios');
            $res->addError($ex);
        }
        return $res;
    }

    /*
     * UNSIGNE COURSES WHO'S TIMETABLE'S START YEAR TIME IS ON THE CURRENT YEAR
     */

    public function getUnsignedCourses($userCode) {
        $response = new R();
        $table = new TableGateway(['a' => 'asignacion'], $this->dbAdapter);
        try {
            $select = $table->getSql()->select();
            $select->columns([]);
            $select->where([
                'a.valida' => 0, //FALSE
                'a.cod_usuario' => $userCode,
                'YEAR(a.fecha_asignacion) = YEAR(curdate())'
            ]);
            $select->join(['h' => 'horario'], 'h.cod_horario = a.cod_horario');
            $select->join(['cp' => 'curso_pensum'], 'h.cod_curso = cp.cod_curso and h.cod_pensum = cp.cod_pensum');
            $result = $table->selectWith($select);
            $resultArray = $result->toArray();
            $timetables = [];
            foreach ($resultArray as $timetableData) {
                $timetables[] = new Timetable($timetableData);
            }
            $response->success();
            $response->setObj($timetables);
        } catch (InvalidQueryException $ex) {
            $response->addMsg("No se pudo ejecutar la búsqueda de cursos desasignados");
        }
        return $response;
    }

    /*
     * cod_horario
      fecha_cohorte
     * cod_curso
     * cod_carrera
     */

    public function getTimetables($pensumCode = null, $cohort = null, $includeUpdatingCourses = false, $startDate = null, $finishDate = null, $closedTimeInterval = true) {
        $res = new R();
        $or = [];
        $andIn = []; //OR OPTIONS ARE NOT INFLUENCED BY THIS ONES
        $andOut = []; //OR OPTIONS ARE IMMERSED INSIDE THIS ONE
        //ADDING PARAMETERS
        if ($cohort != null) {
            $andIn[] = "fecha_cohorte = '$cohort'";
        }
        if ($pensumCode != null) {
            $andIn[] = "h.cod_pensum = $pensumCode";
        }
        if ($includeUpdatingCourses == true) {
            //$or[] = 'fecha_cohorte is NULL';
            $or[] = 'h.cod_pensum = ' . O::CURSO_ACTUALIZACION;
        }
        if ($startDate != null) {
            if ($startDate != 'curdate()') {
                $startDate = "'$startDate'";
            }
            $andOut[] = $closedTimeInterval ? "fecha_inicio >= $startDate" : "fecha_inicio <= $startDate";
        }
        if ($finishDate != null) {
            if ($finishDate != 'curdate()') {
                $finishDate = "'$finishDate'";
            }
            $andOut[] = $closedTimeInterval ? "fecha_fin <= $finishDate" : "fecha_fin >= $finishDate";
        }
        //SETTING PRECEDENCE
        if (!empty($andIn)) {
            $or[] = '(' . implode(" and ", $andIn) . ')';
        }
        if (!empty($or)) {
            $andOut[] = '(' . implode(" or ", $or) . ')';
        }
        $where = implode(" and ", $andOut);
        try {
            //GETTING TIMETABLES
            $timetableTable = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $select = $timetableTable->getSql()->select();
            //GETTING OTHER TABLES DATA
            $select->join(['s' => 'salon'], 'h.cod_salon = s.cod_salon', ['cod_salon', 'salon' => 'nombre'], Select::JOIN_LEFT);
            $select->join(['u' => 'ubicacion'], 's.cod_ubicacion = u.cod_ubicacion', ['cod_ubicacion', 'ubicacion' => 'nombre'], Select::JOIN_LEFT);
            $select->join(['tc' => 'tipo_curso'], 'tc.cod_tipo_curso = h.cod_tipo_curso', ['cod_tipo_curso', 'tipo_curso' => 'nombre']);
            $select->join(['uco' => 'usuario'], 'h.cod_usuario_coordinador = uco.cod_usuario', ['nombres_coordinador' => 'nombres', 'apellidos_coordinador' => 'apellidos'], Select::JOIN_LEFT);
            $select->join(['uca' => 'usuario'], 'h.cod_usuario_catedratico = uca.cod_usuario    ', ['nombres_catedratico' => 'nombres', 'apellidos_catedratico' => 'apellidos'], Select::JOIN_LEFT); //JOIN_LEFT
            $select->join(['c' => 'curso_pensum'], 'h.cod_curso = c.cod_curso and h.cod_pensum = c.cod_pensum', ['nombre_curso' => 'nombre', 'alias']);
            if (!empty($where)) {
                $select->where($where);
            }
            $select->order('h.cod_pensum ASC');
            $select->order('h.cod_curso ASC');
            $select->order('h.fecha_fin DESC');
//            $res->success();
//            $res->setObj($select->getSqlString());
//            return $res;
            $result = $timetableTable->selectWith($select);
            $resultData = $result->toArray();
            $timetables = [];
            foreach ($resultData as $resultTimetable) {
                $timetables[] = new Timetable($resultTimetable);
            }
            $res->success();
            $res->setObj($timetables);
        } catch (InvalidQueryException $ex) {
            $res->addMsg('No se pudo realizar la consulta de horarios.');
        }
        return $res;
    }

    public function getCoursesTypes() {
        $res = new R();
        try {
            $table = new TableGateway('tipo_curso', $this->dbAdapter);
            $coursesTypes = $table->select()->toArray();
            $res->success();
            $res->setObj($coursesTypes);
        } catch (\Exception $ex) {
            $res->addMsg("No se pudieron obtener los tipos de cursos.");
        }
        return $res;
    }

    public function getLocations() {
        $res = new R();
        try {
            $table = new TableGateway('ubicacion', $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->order('nombre ASC');
            $coursesTypes = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($coursesTypes);
        } catch (\Exception $ex) {
            $res->addMsg("No se pudieron obtener las ubicaciones.");
        }
        return $res;
    }

    public function getRooms() {
        $res = new R();
        try {
            $table = new TableGateway('salon', $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->order('nombre ASC');
            $coursesTypes = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($coursesTypes);
        } catch (\Exception $ex) {
            $res->addMsg("No se pudieron obtener los salones.");
        }
        return $res;
    }

    public function createTimetable($timetable): R {
        if ($timetable == null) {
            return new R(NULL, ['El horario de cursos no debe estar vacío.']);
        }
        $res = new R();
        //GETTING TIMETABLE PRICE
        $priceTable = new TableGateway('precio', $this->dbAdapter);
        $select = $priceTable->getSql()->select();
        $date = $timetable->getFechaCohorte() ?? date('Y-m-d'); //CURRENT PRICE FOR UPGRADING COURSES
        $select->where([
            'cod_pensum' => $timetable->getCodPensum(),
            'cod_curso' => $timetable->getCodCurso(),
            "inicio_vigencia <= '$date 23:59:59'"
        ]);
        $select->order('inicio_vigencia DESC');
        $result = $priceTable->selectWith($select);
        if ($result->count() == 0) {
            $date = date('d \d\e M. \d\e\l Y', strtotime($date));
            $res->addMsg("No se encontró el precio para el curso que esté anterior a la fecha '$date'.");
        } else {
            $price = $result->current()['precio'];
            if ($timetable->getLaboratorio == true) {
                $price += Timetable::LABORATORY_PRICE;
            }
            $timetable->setPrecio($price);
            //INSERTING TIMETABLE TO DATABASE
            $table = new TableGateway('horario', $this->dbAdapter);
            try {
                $result = $table->insert([
                    'cod_pensum' => $timetable->getCodPensum(),
                    'cod_curso' => $timetable->getCodCurso(),
                    'fecha_cohorte' => $timetable->getFechaCohorte(),
                    'cod_salon' => $timetable->getCodSalon(),
                    'cod_tipo_curso' => $timetable->getCodTipoCurso(),
                    'cod_usuario_coordinador' => $timetable->getCodCoordinador(),
                    'cod_usuario_catedratico' => $timetable->getCodCatedratico(),
                    'mes' => $timetable->getMes(),
                    'anio' => $timetable->getAnio(),
                    'hora_inicio' => $timetable->getHoraInicio(),
                    'hora_fin' => $timetable->getHoraFin(),
                    'fecha_inicio' => $timetable->getFechaInicio(),
                    'fecha_fin' => $timetable->getFechaFin(),
                    'fecha_limite_calificacion' => $timetable->getFechaLimiteCalificacion(),
                    'seccion' => $timetable->getSeccion(),
                    'cupo' => $timetable->getCupo(),
                    'laboratorio' => $timetable->getLaboratorio(),
                    'lunes' => $timetable->getLunes(),
                    'martes' => $timetable->getMartes(),
                    'miercoles' => $timetable->getMiercoles(),
                    'jueves' => $timetable->getJueves(),
                    'viernes' => $timetable->getViernes(),
                    'sabado' => $timetable->getSabado(),
                    'domingo' => $timetable->getDomingo(),
                    'precio' => $timetable->getPrecio()
                ]);
                if ($result == true) {
                    $res->success();
                    $timetableCod = $table->getLastInsertValue();
                    $timetable->setCode($timetableCod);
                    //ADDING TIMETABLE WITH TIMETABLE CODE TO THE RESPONSE
                    $res->setObj($timetable);
                } else {
                    $res->getMsg('No se agregó el horario a la base de datos.');
                }
            } catch (InvalidQueryException $ex) {
                $res->addMsg('Hubo un error al intentar agregar el horario.');
            }
        }
        return $res;
    }

    public function deleteTimetable($timetableCode) {
        $res = new R();
        if (!isset($timetableCode) || $timetableCode == '') {
            $res->addMsg('El código de horario no debe estar en blanco');
        } else {
            //CHECKING IF THE TIMETABLE HAS BEEN USED SOMEWHERE IN THE DATABASE
            //CHECKING PAYMENT ORDER USAGE
            $table = new TableGateway('cursos_orden_pago', $this->dbAdapter);
            $result = $table->select([
                'cod_horario' => $timetableCode
            ]);
            if ($result->count() > 0) {
                $res->addMsg('No se puede eliminar el horario porque existen órdenes de pago generadas que están asociadas a este horario');
                return $res;
            }
            //CHECKING ASSIGNMENTS
            $table = new TableGateway('asignacion', $this->dbAdapter);
            $result = $table->select([
                'cod_horario' => $timetableCode
            ]);
            if ($result->count() > 0) {
                $res->addMsg('No se puede eliminar el horario porque existen asignaciones asociadas a este horario');
                return $res;
            }
            //CHECKING INVOLVED ACTS
            $table = new TableGateway('involucrado', $this->dbAdapter);
            $result = $table->select([
                'cod_horario' => $timetableCode
            ]);
            if ($result->count() > 0) {
                $res->addMsg('No se puede eliminar el horario porque existen actas asociadas a este horario');
                return $res;
            }
            try {
                $this->beginTransaction();
                //DELITING PONDERING BLOCKS
                $ponderTable = new TableGateway('bloque', $this->dbAdapter);
                $ponderTable->delete([
                    'cod_horario' => $timetableCode
                ]);
                //TIMETABLE DELETING AVAILABLE
                $table = new TableGateway('horario', $this->dbAdapter);
                $result = $table->delete([
                    'cod_horario' => $timetableCode
                ]);
                if ($result == false) {
                    $res->addMsg("El horario '$timetableCode' no se eliminó porque no existe");
                } else {
                    $res->success();
                }
                $this->commit();
            } catch (InvalidQueryException $ex) {
                $res->addMsg('No se pudo eliminar el horario');
                $this->rollback();
            }
        }
        return $res;
    }

    public function getTimetable($timetableCode) {
        $res = new R();
        try {
            $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->where([
                'cod_horario' => $timetableCode
            ]);
            //TIMETABLE INFO
            $select->join(['c' => 'curso_pensum'], 'h.cod_curso = c.cod_curso and h.cod_pensum = c.cod_pensum', ['nombre_curso' => 'nombre']);
            $select->join(['s' => 'salon'], 'h.cod_salon = s.cod_salon', ['cod_salon', 'salon' => 'nombre'], Select::JOIN_LEFT);
            $select->join(['u' => 'ubicacion'], 's.cod_ubicacion = u.cod_ubicacion', ['cod_ubicacion', 'ubicacion' => 'nombre'], Select::JOIN_LEFT);
            $select->join(['tc' => 'tipo_curso'], 'tc.cod_tipo_curso = h.cod_tipo_curso', ['cod_tipo_curso', 'tipo_curso' => 'nombre']);
            $select->join(['uco' => 'usuario'], 'h.cod_usuario_coordinador = uco.cod_usuario', ['nombres_coordinador' => 'nombres', 'apellidos_coordinador' => 'apellidos'], Select::JOIN_LEFT);
            $select->join(['uca' => 'usuario'], 'h.cod_usuario_catedratico = uca.cod_usuario    ', ['nombres_catedratico' => 'nombres', 'apellidos_catedratico' => 'apellidos'], Select::JOIN_LEFT); //JOIN_LEFT
            $result = $table->selectWith($select);
            if ($result->count() == 0) {
                $res->addMsg('No se encontró el horario especificado');
            } else {
                $res->success();
                $res->setObj(new Timetable($result->current()));
            }
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo obtener el horario especificado');
        }
        return $res;
    }

    public function updateTimetable(Timetable $timetable) {
        $res = new R();
        if (empty($timetable)) {
            $res->addMsg('No se obtuvo un horario para editar');
        } else {
            try {
                $table = new TableGateway('horario', $this->dbAdapter);
                $table->update($set = [
                    'cod_salon' => $timetable->getCodSalon(),
                    'cod_tipo_curso' => $timetable->getCodTipoCurso(),
                    'cod_usuario_coordinador' => $timetable->getCodCoordinador(),
                    'cod_usuario_catedratico' => $timetable->getCodCatedratico(),
                    'mes' => $timetable->getMes(),
                    'anio' => $timetable->getAnio(),
                    //'fecha_inicio' => $timetable->getFechaInicio(),
                    'fecha_fin' => $timetable->getFechaFin(),
                    'fecha_limite_calificacion' => $timetable->getFechaLimiteCalificacion(),
                    'hora_inicio' => $timetable->getHoraInicio(),
                    'hora_fin' => $timetable->getHoraFin(),
                    //'seccion' => $timetable->getSeccion(),
                    'cupo' => $timetable->getCupo(),
                    //'laboratorio' => $timetable->getLaboratorio(),
                    'lunes' => $timetable->getLunes(),
                    'martes' => $timetable->getMartes(),
                    'miercoles' => $timetable->getMiercoles(),
                    'jueves' => $timetable->getJueves(),
                    'viernes' => $timetable->getViernes(),
                    'sabado' => $timetable->getSabado(),
                    'domingo' => $timetable->getDomingo()
                        ], [
                    'cod_horario' => $timetable->getCode()
                        ]
                );
                $res->success();
            } catch (InvalidQueryException $ex) {
                $res->addMsg("No se pudo editar el horario");
            }
        }
        return $res;
    }

    const EXTENSION = '00';
    const RUBRO_MAESTRIAS = '19';
    const RUBRO_DOCTORADOS = '47';
    const VARIANTE_MAESTRIAS_INSCRIPCION = '1';
    const VARIANTE_MAESTRIAS_CURSOS = '15';
    const VARIANTE_DOCTORADOS_INSCRIPCION = '1';
    const VARIANTE_DOCTORADOS_CURSOS = '33';
    const MAESTRIA = 3;
    const ESPECIALIZACION = 6;
    const DOCTORADO = 7;

    private function getCourseCatalog($year = null) {
        $res = new R();
        //CONSTANTS
        $filename = './data/catalogo-cursos.txt';
        $unidad = O::UNIDAD;
        $extension = O::EXTENSION;
        //GETTING ALL COURSES THAT HAVE A PENSUM FROM THE LAST YEAR AND PRESENT YEAR
        if ($year == null) {
            $year = date('Y');
        }
        $table = new TableGateway(['p' => 'pensum'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->columns(['cod_carrera']);
        $select->join(['cp' => 'curso_pensum'], 'p.cod_pensum = cp.cod_pensum', ['cod_curso', 'nombre' => new Expression('MAX(cp.nombre)')]);
        $select->join(['h' => 'horario'], 'cp.cod_pensum = h.cod_pensum and cp.cod_curso = h.cod_curso', []);
        $select->where([
            'h.anio' => $year
        ]);
//        $year = ((int) $year) - 1;
//        $select->where([
//            "(p.fecha_fin is NULL or p.fecha_fin > '$year-01-01')"
//        ]);
        $select->group('cp.cod_curso');
        $select->group('p.cod_carrera');
        try {
            $result = $table->selectWith($select);
            $res->success();
            $courses = ($result->toArray());
        } catch (InvalidQueryException $ex) {
            $res->addMsg('No se pudo obtener el listado de cursos.' . $ex->getMessage());
        }
        //CREATING STRING
        if ($res->get() == true) {
            $text = "";
            foreach ($courses as $course) {
                $careerCode = $course['cod_carrera'];
                $courseCode = $course['cod_curso'];
                $courseName = $course['nombre'];
                $text .= "$unidad|$extension|$careerCode|$careerCode.$courseCode|$courseName|CURSO\n";
            }
            $result = $this->writeFile($filename, $text);
            if ($result->get() == false) {
                $res = $result;
            } else {
                $res->setObj($filename);
            }
        }
        return $res;
    }

    private function getSectionCatalog($year = null, $excludeUpgCourses = true) {
        $res = new R();
        //CONSTANTS
        $filename = './data/catalogo-secciones.txt';
        $unidad = O::UNIDAD;
        //GETTING ALL TIMETABLES
        if ($year == null) {
            $year = date('Y');
        }
        $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['p' => 'pensum'], 'h.cod_pensum = p.cod_pensum', []);
        $select->join(['c' => 'carrera'], 'p.cod_carrera = c.cod_carrera', ['cod_carrera', 'cod_grado']);
        $select->order('cod_curso');
        $select->where([
            'h.anio' => $year
        ]);
	//quitando registros duplicados para evitar problemas con el SIIF...
	$select->group(["cod_curso", "cod_carrera", "seccion"]);
        if ($excludeUpgCourses) {
            $upgCourseCode = O::CURSO_ACTUALIZACION;
            $select->where([
                "(p.cod_pensum <> $upgCourseCode and c.cod_carrera <> $upgCourseCode)"
            ]);
        }
        try {
            $result = $table->selectWith($select);
            $res->success();
            $timetables = ($result->toArray());
        } catch (InvalidQueryException $ex) {
            $res->addMsg('No se pudo obtener el listado de horarios.' . $ex->getMessage());
        }

        //CREATING STRING
        if ($res->get() == true) {
            $text = "";
            foreach ($timetables as $timetable) {
                $degree = $timetable['cod_grado'];
                switch ($degree) {
                    case O::MAESTRIA:
                    case O::ESPECIALIZACION:
                        $rubro = O::RUBRO_MAESTRIAS;
                        $variante = O::VARIANTE_MAESTRIAS_CURSOS;
                        break;
                    case O::DOCTORADO:
                        $rubro = O::RUBRO_DOCTORADOS;
                        $variante = O::VARIANTE_DOCTORADOS_CURSOS;
                        break;
                    case O::CURSO_ACTUALIZACION:
                        $rubro = O::RUBRO_CURSOS_ACTUALIZACION;
                        $variante = O::VARIANTE_CURSOS_ACTUALIZACION;
                        break;
                    default:
                        continue;
                }
                $courseCode = $timetable['cod_curso'];
                $careerCode = $timetable['cod_carrera'];
                $section = $timetable['seccion'];
                $price = $timetable['precio'];
//                $startTime = strtotime($timetable['hora_inicio']);
//                $finishTime = strtotime($timetable['hora_fin']);
//                $hours = ($finishTime - $startTime) / 3600; //HOUR
                //HOURS SETTED TO 4
                $hours = 4; //HOUR
                $text .= "$unidad|$year|$rubro|$variante|$careerCode.$courseCode|$section|$price|$hours|CURSO\n";
            }
            $result = $this->writeFile($filename, $text);
            if ($result->get() == false) {
                $res = $result;
            } else {
                $res->setObj($filename);
            }
        }
        return $res;
    }

    private function writeFile($filename, $content) {
        $res = new R();
        if (!$handle = fopen($filename, 'w')) {
            $res->addMsg("No se pudo obtener el archivo ($filename). Los permisos de escritura no están disponibles.");
        } else {
            if (fwrite($handle, $content) === false) {
                $res->addMsg("No se puede escribir sobre el archivo ($filename)");
            } else {
                $res->success();
            }
        }
        return $res;
    }

    public function getSeasonsFile(): R {
        $res = new R();
        $zipFilename = './data/temporadas.zip';
        $result = $this->getCourseCatalog();
        if ($result->get() == false) {
            $res = $result;
        } else {
            $files[] = $result->getObj(); //FILENAME
            $result = $this->getSectionCatalog(null, false);
            if ($result->get() == false) {
                $res = $result;
            } else {
                $files[] = $result->getObj(); //FILENAME
                $zip = new \ZipArchive();
                $zip->open($zipFilename, \ZipArchive::CREATE);
                foreach ($files as $file) {
                    $zip->addFile($file);
                }
                if ($zip->close() == false) {
                    $res->addMsg("No se pudo crear el archivo comprimido");
                } else {
                    $res->success();
                    $res->setObj($zipFilename);
                }
            }
        }
        return $res;
    }

    public function getCoursesTimetableTrees($pensum, $cohort, $emptyLabel, $startDate = null, $finishDate = null): R {
        $res = new R();
        $result = $this->academyManager->getCourses($pensum, $cohort);
        if ($result->get() == false) {
            $res = $result;
        } else {
            $courses = $result->getObj();
            $result = $this->getTimetables($pensum, $cohort, false, $finishDate, $startDate, false);
            if ($result->get() == false) {
                $res = $result;
            } else {
                $timetables = $result->getObj();
                /* [
                 * tree = [
                 *      <cod_curso> => [
                 *          <cod_horario> => <seccion>,
                 *              .....
                 *      ],
                 *      .....
                 * ],
                 * courses => [
                 *      <cod_curso> => <alias>,
                 *              ....
                 * ],
                 * sections => [
                 *      <cod_horario> => <seccion>,
                 *              ....
                 * ]
                 */
                $tree = [];
                $sectionList = [];
                $courseList = [];
                foreach ($timetables as $tt) {
                    if (empty($cohort) && !empty($tt->getFechaCohorte())) {
                        $cohortText = ' - C:' . date('d/m/Y', strtotime($tt->getFechaCohorte()));
                    }
                    $sectionName = $tt->getSeccion() . ' [' . AssignmentForm::MONTHS[$tt->getMes()] . ($cohortText ?? '') . ']';
                    $tree[$tt->getCodCurso()][$tt->getCode()] = $sectionName;
                    $longSectionName = $tt->getCodCurso() . ' - ' . $tt->getNombreCurso() . ' - ' . $sectionName;
                    $tree[''][$tt->getCode()] = $longSectionName;
                    $sectionList[$tt->getCode()] = $longSectionName;
                }
                $sectionList[''] = $emptyLabel;
                $tree[''][''] = $emptyLabel;
                foreach ($courses as $course) {
                    $courseCode = $course['cod_curso'];
                    $courseAlias = $course['alias'];
                    $courseName = "$courseCode - $courseAlias";
                    $courseList[$courseCode] = $courseName;
                    $tree[$courseCode][''] = $emptyLabel;
                }
                $courseList[''] = $emptyLabel;
                $res->success();
                $res->setObj([
                    'tree' => $tree,
                    'courses' => $courseList,
                    'sections' => $sectionList
                ]);
            }
        }
        return $res;
    }

    public function getTaughtTimetables($year, $userCode): R {
        $res = new R();
        try {
            $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $select = $table->getSql()->select();
            if ($userCode == null) {
                $select->join(['prof' => 'usuario'], 'h.cod_usuario_catedratico = prof.cod_usuario');
            } else {
                $select->where("(cod_usuario_catedratico = $userCode or cod_usuario_coordinador = $userCode)");
            }
            $select->where([
                'anio' => $year
            ]);
            $select->join(['c' => 'curso_pensum'], 'h.cod_pensum = c.cod_pensum and h.cod_curso = c.cod_curso', ['nombre_curso' => 'alias']);
            $select->join(['p' => 'pensum'], 'c.cod_pensum = p.cod_pensum', []);
            $select->join(['car' => 'carrera'], 'p.cod_carrera = car.cod_carrera', ['cod_carrera', 'nombre_carrera' => 'nombre_actual']);
            $select->order('car.cod_carrera ASC');
            $select->order('h.cod_curso ASC');
            $result = $table->selectWith($select)->toArray();
            $obj = [];
            foreach ($result as $row) {
                /*
                 * 'careerCode' =>
                 *      [
                 *       'careerName' => <nombre_carrera>
                 *      'timetables' => [
                 *           [<timetableData>],
                 *           [<timetableData>],
                 *           [<timetableData>]
                 *        ]
                 *      ]
                 *  
                 */
                $careerCode = $row['cod_carrera'];
                $obj[$careerCode]['careerName'] = $row['nombre_carrera'];
                $obj[$careerCode]['timetables'][] = $row;
            }
            $res->setObj($obj);
            $res->success();
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar los horarios');
            $res->addError($ex);
        }
        return $res;
    }

}
