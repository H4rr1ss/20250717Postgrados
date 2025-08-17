<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Result as R;
use Eep\Entity\Order;
use Zend\Db\Sql\Expression;
use RyE\Model\RyEWSClient;

class InscriptionManager extends Manager {

    private $userManager;

    //INSCRIPTION STATES
    const ERROR = -1;
    const NOT_INSCRIBED = 0;
    const FIRST_YEAR_AUTH = 0;
    const ORDER_PENDING = 2;
    const COMPLETE_INSCRIPTION = 3;
    const NOT_ASSIGNED_CAREER = 4;
    const MULTIPLE_CARRER_ASSIGNED = 5;
    const INCORRECT_CAREER = 6;
    const DEFAULTER_STUDENT = 7;

    public function __construct(Adapter $dbAdapter, UserManager $userManager) {
        parent::__construct($dbAdapter);
        $this->userManager = $userManager;
    }

    public function isInscriptionTimeOver() {
        //SEARCHING IF THE SECOND PERIOD FOR INSCRIPTION IS OVER
        //THE DIRECTOR HAS TO ESTABLISH IT
        $table = new TableGateway('fin_inscripcion', $this->dbAdapter);
        $result = $table->select(['anio' => new Expression('YEAR(curdate())')]);
        if ($result->count() > 0) {
            return true;
        } else {
            return false;
        }
    }

    //SET PERIOD OVER REGISTRY
    public function setInscriptionTimeOver($userId): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $table = new TableGateway('fin_inscripcion', $this->dbAdapter);
            $where = [
                'anio' => new Expression('YEAR(curdate())'),
                'cod_usuario' => $userId
            ];
            $result = $table->select($where);
            if ($result->count() > 0) {
                $res->setObj(false);
            } else {
                $where['tiempo'] = new Expression('current_timestamp()');
                $table->insert($where);
                $res->setObj(true);
            }
        } catch (InvalidQueryException $ex) {
            $res->failure('No se pudo consultar o insertar la finalización de inscripción');
        }
        return $res;
    }

    public function isInscriptionValid($userId, $year = null): R {
        $res = $this->getInscriptionStatus($userId, $year);
        $status = $res->get();
        $res->setObj($status);
        switch ($status) {
            case self::COMPLETE_INSCRIPTION:
            case self::ORDER_PENDING:
            case self::NOT_INSCRIBED:
                $res->set(true);
                break;
            case self::NOT_ASSIGNED_CAREER:
            case self::MULTIPLE_CARRER_ASSIGNED:
            case self::INCORRECT_CAREER:
            case self::DEFAULTER_STUDENT:
            default:
                $res->set(false);
                break;
        }
        return $res;
    }

    private function isDefaulterUser($userCode): R {
        $res = new R();
        $table = new TableGateway('moroso', $this->dbAdapter);
        try {
            $result = $table->select([
                'cod_usuario' => $userCode
            ]);
            $res->success();
            if ($result->count() == 0) { //USER WITHOUT PROBLEMS
                $res->setObj(false); //NOT DEFAULTER STUDENT
            } else {
                $res->setObj(true); //DEFAULTER STUDENT
            }
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo buscar a la lista de estudiantes morosos');
        }
        return $res;
    }

    public function getInscriptionStatus($userCode, $year = null): R {
        $res = new R();
        //SEARCHING IF THE STUDENT HAS PENDING PAYMENTS (THE STUDENT IS A DEFAULTER USER)
        $result = $this->isDefaulterUser($userCode);
        if ($result->get() == false) {
            $res->set(self::ERROR);
            $res->addMsg($result->getMsg());
        } else {
            $defaulter = $result->getObj();
            if ($defaulter) {
                //DEFAULTER USER FOR CURRENT COHORT
                $res->set(self::DEFAULTER_STUDENT);
                $res->addMsg('Tienes pagos pendientes de cursos. Debes solventarlos con la Escuela de Estudios de Postgrados para realizar asignaciones.');
            } else { //NO DEFAULTER USER -> PREVIOUS PAYMENTS FOR CURRENT COHORT IN ORDER
                //SEARCHING IN LOCAL DATABASE
                $table = new TableGateway('inscripcion', $this->dbAdapter);
                $queryResult = $table->select([
                    'cod_usuario' => $userCode,
                    'anio' => ($year ?? new Expression('YEAR(curdate())'))
                ]);
                if ($queryResult->count() > 1) {
                    //DOBLE INSCRIPTION ERROR
                    $res->addMsg('Doble inscripción registrada localmente. Contacta a Control Académico');
                    $res->set(self::ERROR);
                } elseif ($queryResult->count() == 1) {
                    if ($queryResult->current()['cod_orden'] != NULL) {
                        //CORRECTLY INSCRIBED
                        $res->success("Correctamente inscrito");
                        $res->set(self::COMPLETE_INSCRIPTION);
                    } else {
                        //INSCRIBED BUT PAYMENT ORDER NOT SPECIFIED
                        $res->success("Correctamente inscrito. Sin orden de pago registrada.");
                        $res->set(self::ORDER_PENDING);
                    }
                } else {//COUNT == 0; NOT LOCALLY INSCRIBED
                    //CHECK RYE TO UPDATE INSCRIPTION
                    $res = $this->updateInscription($userCode, $year);
                }
            }
        }

        return $res;
    }

    private function updateInscription($userCode, $year = null, $regAcad = null) {
        $res = new R();
        if ($year == null) {
            $year = date('Y');
        }
        //CHECK IF USER IS REGISTERED FOR ANY GRADE IN THE LOCAL DATABASE
        $careerAssignment = new TableGateway('asignacion_carrera', $this->dbAdapter);
        $careerResult = $careerAssignment->select([
//            'activa' => true, //COMMENTED BECAUSE THE QUERY MIGHT REQUEST FOR AN INACTIVE YEAR INSCRIPTION
            'cod_usuario' => $userCode,
            "fecha_asignacion = ("
            . "select max(fecha_asignacion) from asignacion_carrera"
            . " where fecha_asignacion <= '$year-12-31' and cod_usuario = $userCode"
            . ")" //SPECIFIED YEAR
        ]);
        if ($careerResult->count() == 0) {
            $res->set(self::NOT_ASSIGNED_CAREER);
            $res->addMsg('No estás inscrito actualmente en ninguna carrera en la Escuela de Estudios de Postgrados. Avocarse a esta unidad para más información.');
        } elseif ($careerResult->count() > 1) {
            $res->set(self::MULTIPLE_CARRER_ASSIGNED);
            $res->addMsg("Estás asignado a más de una carrera para el año $year. Avócate a Control Academico para solventar el inconveniente.");
        } else {
            //CHECK THE USER STATE IN RYE'S (REGISTRO Y ESTADÍSTICA) SITE
            if ($regAcad == null) {
                $user = $this->userManager->getUser($userCode);
                $id = !empty($user->getRegistroAcademico()) ? $user->getRegistroAcademico() : (empty($user->getCui()) ? $user->getPasaporte() : $user->getCui());
            } else {
                $id = $regAcad;
            }
            $ws = new RyEWSClient();
            $result = $ws->getInscripcion($id, $year);
            if ($result->get() == false) {
                $res->set(self::ERROR);
                $res->addMsg("No se pudo verificar el estado de inscripción en el Departamento de Registro y Estadística.");
            } else {
                $wsResult = $result->getObj();
                $details = $wsResult->xpath('//DETALLE_ACADEMICO');
                $isRyeInscribed = false;
                foreach ($details as $detail) {
                    /*
                    Modificado por: José Tobias
                    Fecha: 2019-04-03
                    Modificación: agregado "if(in_array($detail->{'CARRERA'}, ['01', '02', '03'])) continue;"
                    Razón: Los estudiantes que se encontraban inscritos en pregrado y que intentaban asignarse en la plataforma eran bloqueados por contar con multiple carrera
                            Solo les permitia asignarse cursos de especialización.
                    */
                    if(in_array($detail->{'CARRERA'}, ['01', '02', '03'])) continue;
                    $userStatus = (string) $detail->{'ESTADO'};
                    if ($userStatus == 2) { //STUDENT HAS PENSUM CLOSURE , SO IT'S INSCRIPTION IS NOT VALID FOR THE SYSTEM
                        continue;
                    }
                    $cycle = $detail->{'CICLO_ACTIVO'};
                    $unit = trim((string) $detail->{'UNIDAD'});
                    $inscriptionDate = (string) $detail->{'FECHA_INSCRITO'};
                    if (!empty(((string) $cycle))) {
                        if ($unit == Order::UNIDAD) {
                            $isRyeInscribed = true;
                            $ryeCarrerCode = (string) ($detail->{'CARRERA'});
                            break;
                        } else {
                            $res->set(self::INCORRECT_CAREER);
                            $ryeCareerName = $detail->{'NOMBRE_CARRERA'};
                            $res->addMsg("Estás inscrito a una Unidad Académica distinta a la Facultad de Arquitectura. Estás en la Unidad $unit, en la carrera $ryeCareerName");
                        }
                    }
                }

                //UPDATE ACADEMIC REGISTRY IF PENDING
                if (empty($user->getRegistroAcademico()) || empty($user->getCui())) {
                    try {
                        $carnet = (string) $wsResult->xpath('//CARNET')[0];
                        $cui = (string) $wsResult->xpath('//CUI')[0];
                        $update = false;
                        if (empty($user->getRegistroAcademico()) && !empty($carnet)) {
                            $update = true;
                            $user->setRegistroAcademico($carnet);
                        }
                        if (empty($user->getCui()) && !empty($cui)) {
                            $update = true;
                            $user->setCui($cui);
                        }
                        if ($update) {
                            $this->userManager->updateUser($user);
                        }
                    } catch (\Exception $exc) {
                        
                    }
                }

                if ($isRyeInscribed) {
                    //CHECKING IF USER IS ASSIGNED INTO THE SAME CAREER THAT RYE'S ONE
                    $careerPensum = $careerResult->current();
                    $pensumCode = $careerPensum['cod_pensum'];
                    $pensumTable = new TableGateway(['p' => 'pensum'], $this->dbAdapter);
                    $select = $pensumTable->getSql()->select();
                    $select->columns([]);
                    $select->where([
                        'p.cod_pensum' => $pensumCode
                    ]);
                    $select->join(['c' => 'carrera'], 'c.cod_carrera = p.cod_carrera');
                    $localCareer = $pensumTable->selectWith($select)->current();
                    $localCareerCode = $localCareer['cod_carrera'];
                    $localCareerName = $localCareer['nombre_actual'];
                    
                    if ($localCareerCode != $ryeCarrerCode) {//SUBSTITUTE FOR RYE'S CARRER CODE OBTAINED IN THE WS
                        $ryeCareerName = $detail->{'NOMBRE_CARRERA'};
                        $res->set(self::INCORRECT_CAREER);
                        $res->addMsg("En Registro y Estadística estás asignado a la carrera '$ryeCareerName' ($ryeCarrerCode), en la Escuela de Estudios de Postgrado de la Facultad de Arquitectura estás asignado a una carrera diferente: " .
                                "'$localCareerName' ($localCareerCode).");
                    } else {
                        //THE USER IS CURRENTLY ASSIGNED
                        //SEARCHING IF USER HAS INSCRIPTION ORDER
                        $orderTable = new TableGateway('orden_pago', $this->dbAdapter);
                        try {
                            $result = $orderTable->select([
                                'cod_usuario' => $userCode,
                                'cod_tipo_orden' => Order::INSCRIPTION,
                                "YEAR(fecha_generacion) = $year"
                            ]);
                            if ($result->count() == 1) {
                                $orderCode = $result->current()['cod_orden'];
                            } else {
                                $orderCode = null;
                            }
                            //INSERTING INSCRIPTION INTO LOCALDATABASE
                            $table = new TableGateway('inscripcion', $this->dbAdapter);
                            $table->insert([
                                'anio' => $year, //new Expression('YEAR(curdate())'),
                                'cod_usuario' => $userCode,
                                'cod_pensum' => $pensumCode,
                                'cod_orden' => $orderCode,
                                'fecha_verificacion' => new Expression('curdate()'),
                                'fecha_inscripcion' => $inscriptionDate ?? null
                            ]);
                            //$res->success("Presenta tu órden de pago a tesorería de la Escuela de Estudios de Postgrados para completar la inscripción");
                            $res->success('Inscrito correctamente.' . (($orderCode == null) ? ' Sin conocimiento de la orden de pago asociada.' : ''));
                            $res->set(self::ORDER_PENDING);
                        } catch (\Exception $ex) {
                            $res->set(self::ERROR);
                            $res->addMsg("No se pudo agregar la inscripción localmente. Contactar con Control Académico.");
                        }
                    }
                } else {
                    $res->set(self::NOT_INSCRIBED);
                    $res->setType(R::WARNING);
                    $res->addMsg("No estás inscrito en Registro y Estadística.");
                    $res->addMsg("Debes inscribirte lo antes posible para que tu asignación (que no sea de Curso de Actualización) se valide y la nota se oficialice correctamente");
                }
            }
        }
        return $res;
    }

    public function isUserFirstYear($id): R {
        $response = new R();
        $assignCareerTable = new TableGateway('asignacion_carrera', $this->dbAdapter);
        $tableResult = $assignCareerTable->select([
            'cod_usuario' => $id,
            'activa' => 1 //TRUE
        ]);
        if ($tableResult->count() == 0) {
            $response->addMsg("No hay carreras asignadas actualmente");
        } else if ($tableResult->count() >= 2) {
            $response->addMsg("Tiene más de una carrera asignada actualmente");
        } else {
            $assignedCareer = $tableResult->current();
            $startDate = $assignedCareer['fecha_asignacion'];
            $startYear = date('Y', strtotime($startDate));
            if ($startYear == date('Y')) {//FIRST YEAR
                $response->success();
            }
        }
        return $response;
    }

}
