<?php

namespace Eep\Service;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Eep\Entity\User;
use Eep\ValueObject\Message;
use Eep\Entity\Order;

class AcademyManager extends Manager {

    //CAREER ASSIGNMENT STATUS
    const REGULAR = 0;
    const INCORP = 1;
    const FINAL_EXAMS_PENDING = 2;
    const GRADUATED = 3;

    public function assignCareer($userCode, $careerCode, $cohortDate, $situation = self::REGULAR, $migration = false): R {
        $response = new R();
        if (empty($userCode) || empty($careerCode) || empty($cohortDate)) {
            $response->addMsg("Identificadores vacíos");
        } else {
            //SEARCHING FOR CURRENT PENSUM FOR THE SELECTED CAREER
            try {
                $pensumTable = new TableGateway(['pc' => 'pensum_cohorte'], $this->dbAdapter);
                $select = $pensumTable->getSql()->select();
                $select->columns([]);
                $select->join(['p' => 'pensum'], 'p.cod_pensum = pc.cod_pensum', ['cod_pensum']);
                $select->where([
                    'pc.fecha_cohorte' => $cohortDate,
                    'p.cod_carrera' => $careerCode
                ]);
                $pensum = $pensumTable->selectWith($select);
            } catch (\Exception $ex) {
                $response->failure('No se pudo leer la correspondencia de carrera y pensum con la cohorte especificada', $ex);
                return $response;
            }
            if ($pensum->count() == 0) {
                $response->addMsg("Para la cohorte seleccionada '" . date('d/m/Y', strtotime($cohortDate)) . "', no hay un pensum que corresponda a la carrera '$careerCode'. Más información, avocarse a Control Académico.");
            } else {
                $pensumCode = $pensum->current()['cod_pensum'];
                try {
                    //CHECKING IF THE CAREER HAS ALREADY BEEN ASSIGNED
                    $table = new TableGateway(['ac' => 'asignacion_carrera'], $this->dbAdapter);
                    $select = $table->getSql()->select();
                    $select->join(['p' => 'pensum'], 'ac.cod_pensum = p.cod_pensum');
                    $select->where([
                        'cod_usuario' => $userCode,
                        'cod_carrera' => $careerCode//RESTRICTS ONLY ONE ASSIGNMENT PER CAREER. OTHERWAY, THE CAREER ASSIGNMENT MUST BE DONE BY THE UDICA
                            //'cod_pensum' => $codPensum
                    ]);
                    $assignedCareers = $table->selectWith($select);
                    if ($assignedCareers->count() > 0) {
                        $previousCohort = $assignedCareers->current()['fecha_cohorte'];
                        $previousPensum = $assignedCareers->current()['cod_pensum'];
                        $response->addMsg("Este usuario ya se ha asignado a esta carrera para la cohorte " . date('d/m/Y', $previousCohort) . " con el pensum $previousPensum.");
                    } else {
                        $inserted = $table->insert([
                            'cod_usuario' => $userCode,
                            'cod_pensum' => $pensumCode,
                            'fecha_cohorte' => $cohortDate,
                            'activa' => $situation == self::REGULAR, //ONLY REGULAR
                            'fecha_asignacion' => $migration ? $cohortDate : new Expression('curdate()'),
                            'cod_situacion' => $situation
                        ]);
                        if ($inserted == true) {
                            if ($situation == self::REGULAR) {
                                //DISABLE ALL OTHER CAREERS
                                $table->update(['activa' => 0], [//FALSE
                                    'cod_usuario' => $userCode,
                                    "cod_pensum  != $pensumCode"
                                ]);
                            }
                            $response->success();
                        } else {
                            $response->addMsg("No se pudo agregar al usuario a la carrera especificada.");
                        }
                    }
                } catch (\Exception $ex) {
                    $response->addMsg("No se pudo realizar la asignación de carrera del usuario: " . $ex->getMessage());
                }
            }
        }
        return $response;
    }

    public function getUserCareer($userCode, $year = null): R {
        $res = new R();
        $table = new TableGateway(['ac' => 'asignacion_carrera'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['p' => 'pensum'], 'ac.cod_pensum = p.cod_pensum');
        $select->join(['ca' => 'carrera'], 'p.cod_carrera = ca.cod_carrera');
        $select->join(['nc1' => 'nombre_carrera'], 'ca.cod_carrera = nc1.cod_carrera', ['carrera' => 'nombre']);
        $select->where([
            'ac.cod_usuario' => $userCode,
            'nc1.tiempo = (  select max(nc2.tiempo) from nombre_carrera nc2 
                                    where nc2.cod_carrera = nc1.cod_carrera 
                                    and nc2.tiempo <= ac.fecha_asignacion   )'
        ]);
        if ($year == null) {
            $select->where([
                'ac.activa' => 1
            ]);
        } else {
            $select->where([
                "ac.fecha_asignacion = ( "
                . " select max(fecha_asignacion) from asignacion_carrera "
                . " where cod_usuario = $userCode "
                . " and fecha_asignacion < '$year-12-31'"
                . ") "
            ]);
        }
        $select->order('ac.fecha_asignacion DESC');
        try {
            $result = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($result);
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo realizar la consulta de la carrera activa del estudiante especificado');
        }
        return $res;
    }

    public function getAcademicDegrees() {
        $table = new TableGateway('grado_academico', $this->dbAdapter);
        $data = $table->select()->toArray();
        return $data;
    }

    public function getCareers() {
        $table = new TableGateway('carrera', $this->dbAdapter);
        $data = $table->select()->toArray();
        return $data;
    }

    public function getCurrentPensums($date = null) {
        $table = new TableGateway(['p1' => 'pensum'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['c' => 'carrera'], 'p1.cod_carrera = c.cod_carrera');
        if ($date == null) {
            $date = 'curdate()';
        } else {
            $date = "'" . $date . "'";
        }
        $select->where([
            "p1.fecha_inicio = 
            (	select max(p2.fecha_inicio) from pensum p2
                where p2.cod_carrera = p1.cod_carrera
                and p2.fecha_inicio <= $date	)"
        ]);
        $data = $table->selectWith($select)->toArray();
        return $data;
    }

    public function getPensums() {
        $table = new TableGateway(['p' => 'pensum'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['ca' => 'carrera'], 'p.cod_carrera = ca.cod_carrera', ['cod_grado']);
        $select->join(['nc1' => 'nombre_carrera'], 'ca.cod_carrera = nc1.cod_carrera', ['nombre_carrera' => 'nombre', 'alias_carrera' => 'alias']);
        $select->where([
            'nc1.tiempo = (  select max(nc2.tiempo) from nombre_carrera nc2 
                                    where nc2.cod_carrera = nc1.cod_carrera 
                                    and nc2.tiempo <= p.fecha_inicio   )'
        ]);
        $select->order('p.cod_carrera ASC');
        $select->order('p.fecha_inicio DESC');
        $data = $table->selectWith($select)->toArray();
        return $data;
    }

    /*
     * IF CALLED, ALL STUDENTS WITHOUT INSCRIPTION DONE AND ASSIGNED TO COURSES WILL BE UNASSIGNED
     */

    public function unsigneNotInscribedUsers() {
        $response = new R();
        //GETTING ASSIGNED TIMETABLES TO INVALIDATE
        /*
          select a.cod_usuario,a.cod_horario from (select ii.cod_usuario from inscripcion ii where  ii.anio = 2018) i
          right outer join  asignacion a on a.cod_usuario = i.cod_usuario
          inner join horario h on h.cod_horario = a.cod_horario
          where i.cod_usuario is NULL and YEAR(h.fecha_inicio) = YEAR(curdate());
         */
        //SUBQUERY
        $subSelect = new Select(['ii' => 'inscripcion']);
        $subSelect->columns(['cod_usuario']);
        $subSelect->where(['anio' => new Expression('YEAR(curdate())')]);
        //GETTING INSCRIPTIONS
        $assignTable = new TableGateway(['i' => $subSelect], $this->dbAdapter);
        $select = $assignTable->getSql()->select();
        $select->join(['a' => 'asignacion'], 'a.cod_usuario = i.cod_usuario', ['cod_usuario', 'cod_horario'], Select::JOIN_RIGHT_OUTER);
        $select->join(['h' => 'horario'], 'h.cod_horario = a.cod_horario', []);
        $select->where([
            'h.anio = YEAR(curdate())', //CURRENT YEAR
            'i.cod_usuario is NULL', //REMOVING ASSINGNED TIMETABLES WITHOUT INSCRIPTION RESULTING FROM THE NEXT RITH OUTER JOIN
            'h.cod_pensum <> ' . Order::CURSO_ACTUALIZACION //EXCLUDING UPDATING COURSES TIMETABLES
        ]);

        //GETTING COURSES TIMETABLE WITH CURRENT YEAR START TIME
        try {
            $assignments = $assignTable->selectWith($select)->toArray();
            $response->success();
        } catch (InvalidQueryException $ex) {
            $response->addMsg("No se pudo obtener las asignaciones a invalidar " . $ex->getMessage());
        }

        if ($response->get() == true && count($assignments) > 0) {
            $response->failure(); //NEGATIVE LOGIC
            //ADDING ALL ASSIGNMENTS WHERE CLAUSES
            $where = [];
            foreach ($assignments as $assignment) {
                $timeTable = $assignment['cod_horario'];
                $user = $assignment['cod_usuario'];
                $where[] = "(cod_usuario,cod_horario) = ($user,$timeTable)";
            }

            /* QUERY FORMAT:
             * UPDATE asignacion SET valida = FALSE WHERE (a.cod_usuario,a.cod_horario) = (1,1) OR.....;
             */
            try {
                $table = new TableGateway('asignacion', $this->dbAdapter);
                $result = $table->update([
                    'valida' => 0 //FALSE
                        ], [implode(" or ", $where)]);
                $response->success("$result desasignaciones realizadas");
            } catch (InvalidQueryException $ex) {
                $response->addMsg("No se pudo cambiar el estado de las asignaciones ");
            }
        }
        return $response;
    }

    public function getCourses($codPensum, $priceDate = null, $withPrice = true): R {
        $res = new R();
        if (empty($codPensum)) {
            $res->addMsg("Se debe especificar un pensum");
            return $res;
        }
        $coursesTable = new TableGateway(['cp' => 'curso_pensum'], $this->dbAdapter);
        $select = $coursesTable->getSql()->select();
        $select->where([
            'cp.cod_pensum' => $codPensum
        ]);
        if ($withPrice) {
            $select->join(['p1' => 'precio'], 'cp.cod_pensum = p1.cod_pensum and cp.cod_curso = p1.cod_curso', ['precio', 'descripcion'], Select::JOIN_LEFT);
            if (!empty($priceDate)) {
                if (strtotime($priceDate) !== false) {
                    $priceDate = "'$priceDate 23:59:59'";
                } else {
                    $res->addMsg("La fecha '$priceDate' no es válida con el formato Y-m-d");
                    return $res;
                }
            } else {
                $priceDate = 'now()';
            }
            $select->where([
                "(p1.inicio_vigencia = 
                (
                    select max(p2.inicio_vigencia) from precio p2
                    where p2.cod_pensum = p1.cod_pensum and p2.cod_curso = p1.cod_curso
                    and p2.inicio_vigencia <= $priceDate
                )
                or p1.inicio_vigencia is NULL 
             )"
            ]);
        }
        $select->order('cp.nombre ASC');
        try {
            $result = $coursesTable->selectWith($select)->toArray();
            $res->success();
            $res->setObj($result);
        } catch (InvalidQueryException $ex) {
            $res->addMsg('Error consultando cursos.' . $ex->getMessage());
        }
        return $res;
    }

    public function getCareer($userCode, $active = null, $year = null, $cohort = null) {
        $res = new R();
        //MANAGING PARAMETERS
        $where = [
            'ac.cod_usuario' => $userCode
        ];
        if ($active != null) {
            //SEARCHING FOR ACTIVE CAREER
            $where['ac.activa'] = $active;
        }
        if ($cohort != null) {
            //SEARCHING SPECIFIC COHORT
            $where['ac.fecha_cohorte'] = $cohort;
        }
        //PREPARING QUERY
        $table = new TableGateway(['ac' => 'asignacion_carrera'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['p' => 'pensum'], 'ac.cod_pensum = p.cod_pensum');
        $select->join(['c' => 'carrera'], 'p.cod_carrera = c.cod_carrera');
        $select->where($where);
        //QUERYING
        try {
            $result = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($result);
        } catch (InvalidQueryException $ex) {
            $res->addMsg('No se pudieron consultar las carreras asignadas.');
        }
        return $res;
    }

    public function getUserCareerMsg(User $user, $year = null): Message {
        $error = false;
        $msg = [];
        //FILLING USER DATA
        $title = '<strong>' . $user->getApellidos() . ', ' . $user->getNombres() . '</strong>';
        if (!empty($user->getRegistroAcademico())) {
            $msg[] = 'Registro Académico: <strong>' . $user->getRegistroAcademico() . '</strong>';
        }
        if (!empty($user->getCui())) {
            $msg[] = 'CUI: <strong>' . $user->getCui() . '</strong>';
        }
        if (!empty($user->getPasaporte())) {
            $msg[] = 'Pasaporte: <strong>' . $user->getPasaporte() . '</strong>';
        }
        //ADDING USER CAREER
        $result = $this->getUserCareer($user->getCode(), $year);
        if ($result->get() == false) {
            $msg = array_merge($result->getMsg(), $msg);
            $error = true;
        } else {
            //COLLECTING DATA
            $careersData = $result->getObj();
            if (count($careersData) > 1) {
                $msg[] = '<strong>Estudiante con Múltiples Carreras Activas</strong>';
            } elseif (count($careersData) == 0) {
                $msg[] = '<strong>Usuario sin carreras activas</strong>';
            }
            foreach ($careersData as $data) {
                $career = $data['cod_carrera'] . ' - ' . $data['carrera'];
                $pensum = $data['cod_pensum'] . (empty($data['descripcion']) ? '' : (' - ' . $data['descripcion']));
                $cohort = date('d/m/Y', strtotime($data['fecha_cohorte']));
                //ADDING MESSAGES
                $msg[] = "Carrera: <strong>$career</strong>";
                $msg[] = "Pensum: <strong>$pensum</strong>";
                $msg[] = "Cohorte: <strong>$cohort</strong>";
            }
        }
        return new Message($title, $msg, $error ? Message::YELLOW : Message::BLUE);
    }

    public function getPensumCohorts($careerCode = null, $cohortDate = null) {
        $table = new TableGateway(['pc' => 'pensum_cohorte'], $this->dbAdapter);
        if ($careerCode == null && $cohortDate == null) {
            $pensumCohorts = $table->select()->toArray();
        } else {
            $select = $table->getSql()->select();
            $select->join(['p' => 'pensum'], 'pc.cod_pensum = p.cod_pensum', ['cod_carrera', 'cod_pensum']);
            $select->where([
                'pc.fecha_cohorte' => $cohortDate,
                'p.cod_carrera' => $careerCode
            ]);
            $pensumCohorts = $table->selectWith($select)->toArray();
        }
        return $pensumCohorts;
    }

    public function createCourse($name, $alias, $price, $pensumCode, $courseCode = null): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        if ($courseCode == null) {
            //GETTING COURSE CODE
            $coursesTable = new TableGateway('curso_pensum', $this->dbAdapter);
            $select = $coursesTable->getSql()->select();
            $select->columns([
                'codigo' => new Expression('MAX(cod_curso)')
            ]);
            $select->where([
                'cod_pensum' => $pensumCode
            ]);
            try {
                $result = $coursesTable->selectWith($select);
                if ($result->count() == 0) {
                    $courseCode = 1;
                } else {
                    $courseCode = intval($result->current()['codigo']) + 1;
                }
            } catch (\Exception $ex) {
                $res->failure('No se pudieron consultar los códigos existentes del pensum indicado');
            }
        }
        if ($res->get() == true) {
            //SUPPORTING TRASNSACTIONALITY
            $this->beginTransaction();
            $courseCode = str_pad($courseCode, 3, "0", STR_PAD_LEFT);
            //CREATING COURSE
            if (!isset($coursesTable)) {
                $coursesTable = new TableGateway('curso_pensum', $this->dbAdapter);
            }
            try {
                $coursesTable->insert([
                    'cod_pensum' => $pensumCode,
                    'cod_curso' => $courseCode,
                    'nombre' => $name,
                    'alias' => $alias
                ]);
            } catch (\Exception $ex) {
                $res->failure('No se pudo agregar el curso');
                $this->rollback();
            }
            //ADDING PRICE
            $result = $this->addPrice($courseCode, $pensumCode, $price, false);
            if ($result->get() == false) {
                $this->rollback();
            } else {
                $this->commit();
            }
        }
        return $res;
    }

    public function addPrice($courseCode, $pensumCode, $price, $supportRollback = true, $description = null): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        if ($supportRollback) {
            $this->beginTransaction();
        }
        //EDITING PRICES
        $priceTable = new TableGateway('precio', $this->dbAdapter);
        //UPDATING PRICES
        try {
            $priceTable->update([
                //SET
                'activo' => false
                    ], [
                //WHERE
                'activo' => true,
                'cod_curso' => $courseCode,
                'cod_pensum' => $pensumCode
            ]);
        } catch (\Exception $ex) {
            $this->failure('No se pudieron actualizar los precios anteriores');
        }
        //ADDING PRICE
        try {
            $priceTable->insert([
                'precio' => $price,
                'inicio_vigencia' => date('Y-m-d H:i:s', time()),
                'activo' => true,
                'cod_pensum' => $pensumCode,
                'cod_curso' => $courseCode,
                'descripcion' => $description
            ]);
        } catch (\Exception $ex) {
            $this->failure('No se pudo insertar el nuevo precio');
        }
        if ($supportRollback) {
            if ($res->get() == true) {
                $this->commit();
            } else {
                $this->rollback();
            }
        }
        return $res;
    }

    public function getCourse($courseCode, $pensumCode): R {
        $res = new R();
        try {
            $table = new TableGateway('curso_pensum', $this->dbAdapter);
            $result = $table->select([
                'cod_curso' => $courseCode,
                'cod_pensum' => $pensumCode
            ]);
            if ($result->count() == 0) {
                $res->addMsg("No se encontró el curso $courseCode del pensum $pensumCode.");
            } else {
                $course = $result->current();
                $res->success();
                $res->setObj($course);
            }
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo buscar el curso solicitado');
        }
        return $res;
    }

    public function deleteCourse($courseCode, $pensumCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //SEARCHING EXISTENCE
        $courseTable = new TableGateway('curso_pensum', $this->dbAdapter);
        $where = [
            'cod_curso' => $courseCode,
            'cod_pensum' => $pensumCode
        ];
        $result = $courseTable->select($where);
        if ($result->count() == 0) {
            $res->failure("No existe el curso '$courseCode' de pensum '$pensumCode'.");
        } else {
            //TIMETABLE ASSOCIATION
            $ttTable = new TableGateway('horario', $this->dbAdapter);
            $result = $ttTable->select($where);
            if ($result->count() > 0) {
                $res->failure('El curso no se puede eliminar porque ya tiene horarios asociados.');
            } else {
                //DELETING PRICES
                $this->beginTransaction();
                $priceTable = new TableGateway('precio', $this->dbAdapter);
                try {
                    $priceTable->delete($where);
                } catch (\Exception $ex) {
                    $res->failure('No se pudieron eliminar los precios asociados al curso');
                    $this->rollback();
                }
                if ($res->get() == true) {
                    //DELETING COURSES
                    try {
                        $courseTable->delete($where);
                        $this->commit();
                    } catch (\Exception $ex) {
                        $res->failure('No se pudo eliminar el curso.');
                        $this->rollback();
                    }
                }
            }
        }
        return $res;
    }

    public function getUserPensums($userCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $table = new TableGateway('asignacion_carrera', $this->dbAdapter);
            $result = $table->select([
                        'cod_usuario' => $userCode
                    ])->toArray();
            $pensums = [];
            foreach ($result as $pensumAssignment) {
                $pensumCode = $pensumAssignment['cod_pensum'];
                $pensums[$pensumCode] = $pensumCode;
            }
            $res->setObj($pensums);
        } catch (\Exception $ex) {
            $res->failure("Error buscando pensums del usuario", $ex);
            $res->addError("Usuario: $userCode");
        }
        return $res;
    }

}
