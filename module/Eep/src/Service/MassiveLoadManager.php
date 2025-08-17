<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Order;
use Eep\Entity\User;
use Eep\Entity\Timetable;
use Eep\Entity\Role;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use SIIF\Model\SIIFOrdenPago;
use RyE\Model\RyEWSClient;
use Eep\ValueObject\Message;
//SERVICES
use Eep\Service\AuthManager;
use Eep\Service\AcademyManager;
use Eep\Service\CohortManager;
use Eep\Service\UserManager;
use Eep\Service\OrderManager;
use Eep\Service\InscriptionManager;
use Eep\Service\TimetableManager;
use Eep\Service\AssignmentManager;
use Eep\Service\LogManager;

class MassiveLoadManager extends Manager {

    private $satuAdapter;
//    private $table;
//    private $select;
    private $authManager;
    private $asignmentManager;
    private $userManager;
    private $academyManager;
    private $orderManager;
    private $inscriptionManager;
    private $cohortManager;
    private $timetableManager;
    private $logManager;

    const PROFESSOR = 1;
    const STUDENT = 2;
    const TIMETABLE = 3;
    const ORDER = 4;
    const ASSIGNMENT = 5;
    const STUDENT_INSCRIPTIONS = 6;

    public function __construct(Adapter $dbAdapter, Adapter $satuAdapter, AuthManager $authManager, AssignmentManager $asignmentManager, UserManager $userManager, AcademyManager $academyManager, OrderManager $orderManager, InscriptionManager $inscriptionManager, CohortManager $cohortManager, TimetableManager $timetableManager, LogManager $logManager) {//OR ENTITYMANAGER
        $this->dbAdapter = $dbAdapter;
        $this->satuAdapter = $satuAdapter;
//        $this->table = new TableGateway(['s' => 'seccion'], $satuAdapter);
//        $select = $this->table->getSql()->select();
//        $select->join(['p' => 'pensum'], 's.pensum = p.pensum', ['pensum']);
//        $select->join(['c' => 'carrera'], 'p.carrera = c.carrera', ['carrera']);
//        $select->where(['c.nivel > 2']);
//        $this->select = $select;

        $this->authManager = $authManager;
        $this->asignmentManager = $asignmentManager;
        $this->userManager = $userManager;
        $this->academyManager = $academyManager;
        $this->orderManager = $orderManager;
        $this->inscriptionManager = $inscriptionManager;
        $this->cohortManager = $cohortManager;
        $this->timetableManager = $timetableManager;
        $this->logManager = $logManager;
    }

    public function prueba(): R {
        $res = new R();
        try {
            $res->success();
            $res->setObj('Mensaje de prueba');
        } catch (\Exception $ex) {
            $res->addMsg($ex->getMessage());
        }
        return $res;
    }

//
//    public function loadProfessors(): R {
//        $res = new R();
//        try {
//            $table = new TableGateway(['st' => 'staff'], $this->satuAdapter);
//            $select = $table->getSql()->select();
//            
//        } catch (\Exception $ex) {
//            
//        }
//        return $res;
//    }

    public function load($content, $etlType): R {
        $res = new R();
        $res->success();
        $headers = str_getcsv($content[0]);
        unset($content[0]);
        $count = 0;
        if ($etlType == self::STUDENT_INSCRIPTIONS) {
            //CREATING COHORTS
            $currentYear = intval(date('Y'));
            for ($year = 1988; $year <= $currentYear; $year++) {
                $result = $this->cohortManager->addIfNotExists("$year-01-15");
                if ($result->get() == false) {
                    return $result;
                }
            }
        }
        foreach ($content as $line) {
            $fields = str_getcsv($line);
            $data = array_combine($headers, $fields);
            foreach ($data as $key => $value) {
                if ($value == 'NULL' || trim($value) == '' || trim($value) == '0000-00-00') {
                    $data[$key] = null;
                }
            }
            switch ($etlType) {
                case self::STUDENT:
                    $result = $this->addUser($data, [Role::ESTUDIANTE]);
                    break;
                case self::PROFESSOR:
                    $result = $this->addUser($data, [Role::CATEDRATICO, Role::COORDINADOR]);
                    break;
                case self::TIMETABLE:
                    $result = $this->addTimetable($data);
                    break;
                case self::ORDER:
                    $result = $this->addOrder($data);
                    break;
                case self::ASSIGNMENT:
                    $result = $this->addAssignment($data);
                    break;
                case self::STUDENT_INSCRIPTIONS:
                    $result = $this->addStudentInscriptions($data);
                    break;
                default:
                    $result = new R();
                    $result->failure("Se solicitó la carga de tipo '$etlType', pero no existe en la carga del Manejador de ETL.");
                    break;
            }
            $res->addMsg($result);
            if ($result->get() == false) {
                $res->failure($line . Message::makeHtmlList($data, true));
                break;
            } elseif ($etlType == self::STUDENT) {
//                $res->addMsg('Registro Académico: ' . $data['registro_academico'] . ' ingresado y guardado correctamente.');
//                $this->commit();
//                $this->beginTransaction();
            }
            $count ++;
        }
        if ($etlType == self::STUDENT_INSCRIPTIONS) {
            try {
                //SEARCHING EMPTY COHORTS
                $cohortTable = new TableGateway(['c' => 'cohorte'], $this->dbAdapter);
                $select = $cohortTable->getSql()->select();
                $select->columns([]);
                $select->join(['ac' => 'asignacion_carrera'], 'c.fecha_cohorte = ac.fecha_cohorte', [], \Zend\Db\Sql\Select::JOIN_LEFT);
                $select->columns([
                    'asignaciones' => new Expression('COUNT(ac.cod_usuario)'),
                    'fecha_cohorte'
                ]);
                $select->group('c.fecha_cohorte');
                $select->having('asignaciones = 0');
                $resultData = $cohortTable->selectWith($select)->toArray();
            } catch (\Exception $ex) {
                $res->failure('No se pudieron buscar las cohortes para eliminar las vacías', $ex);
            }
            if (count($resultData) > 0) {
                //DELETING COHORTS
                foreach ($resultData as $cohortDateData) {
                    $cohortDate = $cohortDateData['fecha_cohorte'];
                    $result = $this->cohortManager->deleteCohort($cohortDate);
                    if (!$result->get()) {
                        $res->addMsg($result);
                    } else {
                        $res->addMsg('Cohorte "' . date('d/m/Y', strtotime($cohortDate)) . ' eliminada porque no tiene estudiantes asociados');
                    }
                }
            } else {
                $res->addMsg('Ninguna cohorte fue eliminada porque todas contenían estudiantes asignados');
            }
        }
        $res->addMsg("$count registros ingresados");
        return $res;
    }

    private function addUser($data, $roleCodes): R {
        $res = new R();
        $res->success();
        ini_set('max_execution_time', 60 * 3);
        foreach ($data as $key => $value) {
            if (empty($value)) {
                $data[$key] = null;
            }
        }
        /*if (!isset($data['cui_quantity']) || $data['cui_quantity'] != 1 || true) {
            $data['cui'] = null;
        }*/
        $data['nombres'] = empty($data['nombres']) ? ' ' : $data['nombres'];
        $data['apellidos'] = empty($data['apellidos']) ? ' ' : $data['apellidos'];
        $data['cod_pais'] = $data['cod_pais'] == 30 ? 73 : 1000;
        $birthDate = $data['fecha_nacimiento'];
        if (strtotime($birthDate) == false) {
            $data['fecha_nacimiento'] = null;
        }
        $email = $data['correo'];
        if ($email == null) {
            $data['correo'] = ' ';
        }
        $result = $this->userManager->addUser($data);
        if ($result->get() == false) {
            $result->addMsg("Registro con error: " . var_export($data, true));
        } else {
            if (empty($data['cod_usuario'])) {
                $userCode = $result->get();
            } else {
                $userCode = $data['cod_usuario'];
            }
            foreach ($roleCodes as $roleCode) {
                $result = $this->authManager->addUserRole($userCode, $roleCode);
                if ($result->get() == false) {
                    break;
                }
            }
//            if ($result->get() && $roleCode == Role::ESTUDIANTE) {
//                $acadReg = $data['registro_academico'];
//                $startYear = $data['anio_inicio'] ?? 2014;
//                $result = $this->updateInscriptions($userCode, $acadReg, $startYear);
//            }
        }
        if (!$result->get()) {
            $res->failure($result);
        }
        return $res;
    }

    private function addTimetable($data): R {
        $res = new R();
        $data['cod_curso'] = str_pad($data['cod_curso'], 3, '0', STR_PAD_LEFT);
        $tt = new Timetable($data);
        $tt->setCodTipoCurso(1); //PRESENTIAL
        $table = new TableGateway('horario', $this->dbAdapter);
        try {
            $result = $table->insert([
                'cod_horario' => $tt->getCode(),
                'cod_pensum' => $tt->getCodPensum(),
                'cod_curso' => $tt->getCodCurso(),
                'fecha_cohorte' => $tt->getFechaCohorte(),
                'cod_salon' => $tt->getCodSalon(),
                'cod_tipo_curso' => $tt->getCodTipoCurso(),
                'cod_usuario_coordinador' => $tt->getCodCoordinador(),
                'cod_usuario_catedratico' => $tt->getCodCatedratico(),
                'mes' => $tt->getMes(),
                'anio' => $tt->getAnio(),
                'fecha_inicio' => $tt->getFechaInicio(),
                'fecha_fin' => $tt->getFechaFin(),
                'hora_inicio' => $tt->getHoraInicio(),
                'hora_fin' => $tt->getHoraFin(),
                'seccion' => $tt->getSeccion(),
                'cupo' => $tt->getCupo(),
                'laboratorio' => $tt->getLaboratorio(),
                'lunes' => $tt->getLunes(),
                'martes' => $tt->getMartes(),
                'miercoles' => $tt->getMiercoles(),
                'jueves' => $tt->getJueves(),
                'viernes' => $tt->getViernes(),
                'sabado' => $tt->getSabado(),
                'domingo' => $tt->getDomingo(),
                'precio' => $tt->getPrecio()
            ]);
            if ($result == true) {
                $res->success();
            } else {
                $res->getMsg('No se agregó el horario a la base de datos.');
            }
        } catch (InvalidQueryException $ex) {
            $res->addMsg('Hubo un error al intentar agregar el horario: ' . $ex->getMessage());
        }
        return $res;
    }

    private function updateInscriptions($userCode, $acadReg, $initialYear): R {
        $res = new R();
        $curyear = intval(date('Y'));
        $firstCareerYear = null;
        $currentCareerCode = null;
        $pensumCode = null;
        $ws = new RyEWSClient();
        //LOOPING YEARS
        for ($year = $initialYear; $year <= $curyear; $year++) {
            //GETTING RYE'S INFORMATION
            $inscriptionYearStatusSuccess = false;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $result = $ws->getInscripcion($acadReg, $year);
                if ($result->get() == true) {
                    break;
                } else {
                    unset($ws);
                    sleep(2); //WAIT A LITTLE WHILE
                    $ws = new RyEWSClient();
                    $res->addMsg("Error de $attempt ° Intento:");
                    $res->addMsg($result->getMsg());
                }
            }

            if ($result->get() == false) {
                $result->addMsg("Registro Académico: $acadReg.");
                $result->addMsg("Código de Usuario: $userCode.");
                $result->addMsg("Año: $year.");
                $res->addMsg($result->getMsg());
                break;
            } else {
                $wsResult = $result->getObj();
                $details = $wsResult->xpath('//DETALLE_ACADEMICO');
                $isRyeInscribed = false;
                //PARSING RESULT
                foreach ($details as $detail) {
                    $cycle = $detail->{'CICLO_ACTIVO'};
                    $unit = trim((string) $detail->{'UNIDAD'});
                    if (!empty(((string) $cycle)) && $unit == Order::UNIDAD) {
                        $isRyeInscribed = true;
                        $ryeCarrerCode = (string) ($detail->{'CARRERA'});
                        $inscriptionDate = (string) ($detail->{'FECHA_INSCRITO'});
                        break;
                    }
                }
                if ($isRyeInscribed) {
                    $ryeCarrerCode = (string) ($detail->{'CARRERA'});
                    if ($firstCareerYear == null || $currentCareerCode != $ryeCarrerCode) {
                        //CHECKING IF PENSUM EXISTS FOR THAT YEAR
                        $cohort = "$year-01-01";
                        $result = $this->cohortManager->addIfNotExists($cohort);
                        if ($result->get() == false) {
                            $res->addMsg($result->getMsg());
                            break;
                        } else {
                            $recentlyCreatedCohort = $result->getObj();
                            $pensumCohorts = $this->academyManager->getPensumCohorts($ryeCarrerCode, $cohort);
                            if (count($pensumCohorts) == 0) {
                                $res->failure("No existía la carrera '$ryeCarrerCode' para el año $year, porque no se encontró el pensum asociado.");
                                //DELETE COHORT IF CREATED FOR THIS USER
                                if ($recentlyCreatedCohort) {
                                    $result = $this->cohortManager->deleteCohort($cohort);
                                    if ($result->get() == false) {
                                        return $result;
                                    }
                                }
                                continue;
                            } elseif (count($pensumCohorts) > 1) {
                                $quantity = count($pensumCohorts);
                                $res->failure("Hay $quantity pensums asociadas a la cohorte del año $year, lo cual es un error en el sistema. Sólo debe haber una.");
                                break;
                            } else {
                                $firstCareerYear = $year;
                                $currentCareerCode = $ryeCarrerCode;
                                $pensumCode = array_pop($pensumCohorts)['cod_pensum'];
                                //ASSIGNING CARRER
                                $result = $this->academyManager->assignCareer($userCode, $ryeCarrerCode, $cohort);
                                if ($result->get() == false) {
                                    $result->addMsg("Año: $year");
                                    $res->addMsg($result->getMsg());
                                    break;
                                }
                            }
                        }
                    }

                    //UPDATING INSCRIPTION
                    try {
                        $table = new TableGateway('inscripcion', $this->dbAdapter);
                        $result = $table->insert([
                            'anio' => $year, //new Expression('YEAR(curdate())'),
                            'cod_usuario' => $userCode,
                            'cod_pensum' => $pensumCode,
                            'cod_orden' => null,
                            'fecha_verificacion' => new Expression('curdate()'),
                            'fecha_inscripcion' => $inscriptionDate ?? null
                        ]);
                        if ($result == 0) {
                            $res->failure("Inscripción no agregada: Año $year, Pensum $pensumCode, Registro Académico $acadReg");
                            break;
                        }
                    } catch (\Exception $ex) {
                        $res->failure("Error agregando inscripción año $year: " . $ex->getMessage());
                        break;
                    }
                    //INSCRIPTION UPDATED
                }
            }
            $inscriptionYearStatusSuccess = true;
        }
        if (isset($inscriptionYearStatusSuccess) && $inscriptionYearStatusSuccess) {
            $res->success();
        }
        return $res;
    }

    private function addOrder($data): R {
        $res = new R();
        try {
            $table = new TableGateway('orden_pago', $this->dbAdapter);
            $result = $table->insert($data);
            if ($result = true) {
                $res->success();
            } else {
                $res->failure("Orden de pago no insertada");
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudo ingresar la orden de pago: ' . $ex->getMessage());
        }
        return $res;
    }

    private function addAssignment($data): R {
        $res = new R();
        $data['cod_estado_nota'] = 3; //GRADE COMPLETED
        try {
            $table = new TableGateway('asignacion', $this->dbAdapter);
            $result = $table->insert($data);
            if ($result = true) {
                //AGREGAR ASOCIACIÓN DE ÓRDENES DE PAGO CON EL HORARIO
                $orderCode = $data['cod_orden'];
                if (!empty($orderCode)) {
                    $ttTable = new TableGateway('horario', $this->dbAdapter);
                    $ttCode = $data['cod_horario'];
                    $result = $ttTable->select([
                        'cod_horario' => $ttCode
                    ]);
                    if ($result->count() == 0) {
                        $res->failure("No se encontró el horario $ttCode");
                    } else {
                        $ttPrice = $result->current()['precio'];
                    }
                }
                $res->success();
            } else {
                $res->failure("Asignación no insertada");
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudo ingresar la asignación: ' . $ex->getMessage());
        }
        return $res;
    }

    private function updateTimetableCohorts($data): R {
        $res = new R();
        $timetableCode = $data['cod_horario'];
        $cohort = $data['fecha_cohorte'];
        try {
            $table = new TableGateway('horario', $this->dbAdapter);
            $table->update([
                'fecha_cohorte' => $cohort
                    ], [
                'cod_horario' => $timetableCode
            ]);
            $res->success();
        } catch (\Exception $ex) {
            $res->failure("Error actualizando horarios: " . $ex->getMessage());
        }
        return $res;
    }

    public function updateUserAssignmentInscriptions(): R {
        $res = new R();
        //SEARCHING LAST USER
        $table = new TableGateway(['moroso'], $this->dbAdapter);
        try {
            $select = $table->getSql()->select();
            $select->columns([
                "MAX(cod_usuario) as cod_usuario"
            ]);
            $result = $table->selectWith($select);
            if ($result->count() == 0) {
                $firstUser = 0;
            } else {
                $firstUser = $result->current()['cod_usuario'];
            }
        } catch (\Exception $ex) {
            $res->failure("Error leyendo el último usuario procesado: " . $ex->getMessage());
            return $res;
        }
        //UPDATING
        $userTable = new TableGateway(['u' => 'usuario'], $this->dbAdapter);
        try {
            $usersData = $userTable->select([
                        "cod_usuario > $firstUser"
                    ])->toArray();
        } catch (\Exception $ex) {
            $res->failure("Error leyendo los usuarios mayores a $userCode: " . $ex->getMessage());
            return $res;
        }
        $this->beginTransaction();
        $lastInsertedUser = $firstUser;
        if (count($usersData) == 0) {
            $res->success('No hay usuarios pendientes');
        } else {
            foreach ($usersData as $user) {
                $regAcad = $user['registro_academico'];
                $userCode = $user['cod_usuario'];
                if ($regAcad != null) {
                    $result = $this->updateInscriptions($userCode, $regAcad, 2014);
                    if ($result->get() == false) {
                        $this->rollback();
                        $res->failure($result->getMsg());
                        break;
                    } else {
                        $res->success("Usuario $userCode verificado");
                        $this->commit();
                        $this->beginTransaction();
                        $lastInsertedUser = $userCode;
                    }
                }
            }
            //ADDING LAST INSERTED USER TO TABLE
            if ($lastInsertedUser != $firstUser) {
                try {
                    $table->insert([
                        'cod_usuario' => $lastInsertedUser,
                        'fecha_generacion' => date('Ymd')
                    ]);
                } catch (\Exception $ex) {
                    $res->failure("Error agregando el último usuario agregado ($lastInsertedUser): " . $ex->getMessage());
                    return $res;
                }
            }
            if ($res->get() == true) {
                $this->commit();
            }
        }
        return $res;
    }

    public function addStudentInscriptions($data, $startYear = 1988): R {
        $res = new R();
        $res->success();
        //GETTING USER AND CAREER
        $userAcadReg = $data['registro_academico'];
        $careerCode = $data['cod_carrera'];
        $usersResult = $this->userManager->getPossibleUsers($userAcadReg);
        if (count($usersResult) == 0) {
            $res->failure("No se encontró al usuario '$userAcadReg'.");
            return $res;
        } else {
            $user = current($usersResult);
            $userCode = $user->getCode();
        }
        $length = count($data);
        $year = $startYear;
        $cohort = null;
        $pensumCode = null;
        for ($index = 2; $index < $length; $index += 2) {
            $situation = trim($data["situacion_$year"]);
            if ($situation == '') {
                $year ++;
                continue;
            } else {
                //SEARCHING IF USER ALREADY ASSIGNED TO THAT CAREER
                try {
                    $previousAsg = new TableGateway(['ac' => 'asignacion_carrera'], $this->dbAdapter);
                    $select = $previousAsg->getSql()->select();
                    $select->join(['pc' => 'pensum_cohorte'], 'ac.fecha_cohorte = pc.fecha_cohorte and ac.cod_pensum = pc.cod_pensum');
                    $select->join(['p' => 'pensum'], 'pc.cod_pensum = p.cod_pensum');
                    $select->where([
                        'p.cod_carrera' => $careerCode,
                        'ac.cod_usuario' => $userCode,
                    ]);
                    $result = $previousAsg->selectWith($select)->toArray();
                    if (count($result) != 0) {
                        $result = current($result);
                        $pensumCode = $result['cod_pensum'];
                        $cohort = $result['fecha_cohorte'];
                    }
                } catch (\Exception $ex) {
                    $res->failure("No se pudieron consultar previas asignaciones de carrera", $ex);
                    return $res;
                }

                if ($cohort == null) {
                    //ASSIGN USER TO PENSUM_CAREER-COHORT
                    $cohort = "$year-01-15"; //JANUARY 15 IS THE DEFAULT
                    //CHECKING IF CORRESPONDING COHORT-PENSUM EXISTS
                    try {
                        $pensumTable = new TableGateway(['pc' => 'pensum_cohorte'], $this->dbAdapter);
                        $select = $pensumTable->getSql()->select();
                        $select->columns([]);
                        $select->join(['p' => 'pensum'], 'p.cod_pensum = pc.cod_pensum', ['cod_pensum']);
                        $select->where([
                            'pc.fecha_cohorte' => $cohort,
                            'p.cod_carrera' => $careerCode
                        ]);
                        $pensums = $pensumTable->selectWith($select);
                    } catch (\Exception $ex) {
                        $res->failure('No se pudo leer la correspondencia de carrera y pensum con la cohorte especificada', $ex);
                        return $res;
                    }
                    if ($pensums->count() > 0) {
                        //THERE IS A PENSUM ASSOCIATION
                        $result = $this->academyManager->assignCareer($userCode, $careerCode, $cohort, $situation, true);
                        if ($result->get() == false) {
                            $res->failure($result);
                            return $res;
                        }
                    } else {
                        //THERE IS NO PENSUM FOR THAT DATE AND THAT CAREER
                        try {
                            $satuGrades = new TableGateway(['n' => 'nota'], $this->satuAdapter);
                            $select = $satuGrades->getSql()->select();
                            $select->columns([]);
                            $select->join(['p' => 'pensum'], 'n.pensum = p.pensum', []);
                            $select->columns([
                                'pensum' => new Expression('DISTINCT n.pensum')
                            ]);
                            $select->where([
                                'carnet' => $userAcadReg,
                                'carrera' => $careerCode
                            ]);
                            $result = $satuGrades->selectWith($select)->toArray();
                            $grades = count($result);
                        } catch (\Exception $ex) {
                            $res->failure("No se pudieron encontrar las notas en SATU correlacionadas al usuario $userAcadReg", $ex);
                            return $res;
                        }
                        $msg = "$userAcadReg con carrera $careerCode sin pensum vigente para el año $year.";
                        if ($grades > 0) {
                            $pensumCodes = [];
                            foreach ($result as $p) {
                                $pensumCodes[] = $p['pensum'];
                            }
                            $msg .= "Las notas en SATU tienen pensums: " . implode(', ', $pensumCodes) . '.';
                        }
                        $res->addMsg($msg);
                        return $res;
                    }
                }
                if ($pensumCode == null) {
                    try {
                        //GETTING PENSUM
                        $careerAssignment = new TableGateway('asignacion_carrera', $this->dbAdapter);
                        $result = $careerAssignment->select([
                            'cod_usuario' => $userCode,
                            "fecha_asignacion = ("
                            . "select max(fecha_asignacion) from asignacion_carrera"
                            . " where fecha_cohorte <= '$cohort' and cod_usuario = $userCode"
                            . ")" //SPECIFIED YEAR
                        ]);
                        if ($result->count() == 0) {
                            $res->failure("No se encontraron asignaciones de carrera para el usuario $userAcadReg, cohorte $cohort.");
                            return $res;
                        }
                        $careerResult = $result->current();
                        $pensumCode = $careerResult['cod_pensum'];
                    } catch (\Exception $ex) {
                        $res->failure("No se pudo buscar la carrera de la fecha $cohort para el usuario $userAcadReg.");
                        $res->addMsg($ex->getMessage());
                        return $res;
                    }
                }

                try {
                    //ADDING INSCRIPTION IF NOT YET
                    $inscriptionTable = new TableGateway('inscripcion', $this->dbAdapter);
                    $resultData = $inscriptionTable->select([
                        'anio' => $year,
                        'cod_usuario' => $userCode
                    ]);
                    if ($resultData->count() == 0) {
                        $dateText = trim($data["fecha_inscripcion_$year"]);
                        $time = strtotime($dateText);
                        if ($time == false || $dateText == '1899-12-30') {
                            $inscriptionDate = null;
                        } else {
                            $inscriptionDate = date('Y-m-d', $time);
                        }
                        $set = [
                            'anio' => $year,
                            'cod_usuario' => $userCode,
                            'cod_pensum' => $pensumCode,
                            'fecha_verificacion' => date('Y-m-d'),
                            'cod_orden' => null,
                            'fecha_inscripcion' => $inscriptionDate
                        ];
                        $result = $inscriptionTable->insert($set);
                        if ($result == 0) {
                            $res->failure("No se agregó la inscripción de $userAcadReg para el año $year y carrera $careerCode.");
                            return $res;
                        }
                    }
                } catch (\Exception $ex) {
                    $res->failure("No se pudo buscar agregar la inscripción: " . json_encode($set ?? null));
                    $res->addMsg($ex->getMessage());
                }
                $year++;
            }
        }

        return $res;
    }

}
