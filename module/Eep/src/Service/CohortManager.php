<?php

namespace Eep\Service;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Eep\Entity\Result as R;
use Eep\Entity\User;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Eep\Entity\Timetable;
use Eep\Entity\Order;
use Eep\Entity\Role;

class CohortManager extends Manager {

    const UPG_COURSE_CODE = Order::CURSO_ACTUALIZACION;

    public function getCohorts($startDate = null, $finishDate = null) {
        $table = new TableGateway('cohorte', $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->order('fecha_cohorte DESC');
        if (empty($startDate) && empty($finishDate)) {
            $data = $table->selectWith($select)->toArray();
        } else {
            $where = [];
            if (!empty($startDate) && strtotime($startDate) !== false) {
                $where[] = " fecha_cohorte >= '$startDate' ";
            }
            if (!empty($finishDate) && strtotime($finishDate) !== false) {
                $where[] = " fecha_cohorte <= '$finishDate' ";
            }
            if (empty($where)) {
                $data = [];
            } else {
                $select->where(implode("and", $where));
                $data = $table->selectWith($select)->toArray();
            }
        }
        return $data;
    }

    public function addIfNotExists($cohortDate): R {
        $res = new R();
        try {
            $table = new TableGateway('cohorte', $this->dbAdapter);
            $result = $table->select([
                'fecha_cohorte' => $cohortDate
            ]);
            if ($result->count() > 0) {
                $res->setObj(false); //PREVIOUSLY CREATED
            } else {
                $result = $this->addCohort($cohortDate);
                if ($result->get() == false) {
                    return $result;
                } else {
                    $result->setObj(true); //JUST CREATED
                }
            }
            $res->success();
        } catch (\Exception $ex) {
            $res->failure("Error buscando cohorte '$cohortDate': " . $ex->getMessage());
        }
        return $res;
    }

    public function getCohortUsers($cohort) {
        $table = new TableGateway(['u' => 'usuario'], $this->dbAdapter);
        $select = $table->getSql()->select();
        //GETTING COUNTRY NAME
        $select->join(['pa' => 'pais'], 'pa.cod_pais = u.cod_pais', ['pais' => 'nombre']);

        //GETTING USERS ASSIGNED TO THAT CAREER
        if ($cohort == null) {//UPDATING COURSES
            $select->join(['ac' => 'asignacion_carrera'], 'u.cod_usuario = ac.cod_usuario', [], Select::JOIN_LEFT);
            $select->join(['ur' => 'usuario_rol'], 'u.cod_usuario = ur.cod_usuario', []);
            $select->where([
                'cod_rol' => Role::ESTUDIANTE
            ]);
        } else {
            $select->join(['ac' => 'asignacion_carrera'], 'u.cod_usuario = ac.cod_usuario', []);
            //GETTING CAREERS ASSIGNED TO THAT COHORT
            $select->join(['p' => 'pensum'], 'p.cod_pensum = ac.cod_pensum', []);
            //GETTING CAREERS' NAMES
            $select->join(['nc1' => 'nombre_carrera'], 'nc1.cod_carrera = p.cod_carrera', ['nombre_carrera' => 'nombre']);
            $select->where([
                "nc1.tiempo = ( select max(nc2.tiempo) from nombre_carrera nc2 where nc2.cod_carrera = nc1.cod_carrera and nc2.tiempo <= '$cohort' )"
            ]);
        }
        $select->where([
            'ac.fecha_cohorte' => $cohort
        ]);

        $result = $table->selectWith($select)->toArray();
        $careers = [];
        foreach ($result as $queriedUser) {
            $careerName = $queriedUser['nombre_carrera'] ?? 'Cursos de Actualización';
            $careers[$careerName][] = new User($queriedUser, TRUE);
        }
        return $careers;
    }

    public function deleteCohort($cohort) {
        $response = new R();
        $cohortString = date('d/m/Y', strtotime($cohort));
        //LOOKING FOR ASSIGNED STUDENTS
        $assignCareerTable = new TableGateway('asignacion_carrera', $this->dbAdapter);
        $assignedStudents = $assignCareerTable->select([
                    'fecha_cohorte' => $cohort
                ])->count();
        if ($assignedStudents > 0) {
            $response->addMsg("Existe(n) $assignedStudents estudiante(s) asignados a esta cohorte");
        } else {
            //LOOKING FOR RELATED TIMETABLES
            $timetableTable = new TableGateway('horario', $this->dbAdapter);
            $relatedTimetables = $timetableTable->select([
                        'fecha_cohorte' => $cohort
                    ])->count();

            if ($relatedTimetables > 0) {
                $response->addMsg("Existe(n) $relatedTimetables horario(s) de cursos asociado(s) a esta cohorte '$cohortString'. Se requiere eliminar los horarios asociados para poder eliminar la cohorte.");
            } else {
                try {
                    //DELETE ALL "pensum_cohorte" ASSOCIATION
                    $pensumCohortTable = new TableGateway('pensum_cohorte', $this->dbAdapter);
                    $pensumCohortTable->delete([
                        'fecha_cohorte' => $cohort
                    ]);

                    //DELETING COHORT ITSELF
                    $cohortTable = new TableGateway('cohorte', $this->dbAdapter);
                    $result = $cohortTable->delete([
                        'fecha_cohorte' => $cohort
                    ]);
                    if ($result == true) {
                        $response->success("Cohorte '$cohortString' eliminada correctamente");
                    } else {
                        $response->addMsg("No se eliminó la cohorte '$cohortString'. ¿Existe la cohorte?");
                    }
                } catch (InvalidQueryException $ex) {
                    $response->addMsg("No se pudo eliminar la cohorte '$cohortString'. Verifique que no tenga horarios asociados ni estudiantes asignados.");
                }
            }
        }
        return $response;
    }

    public function addCohort($cohort): R {
        $response = new R();
        $cohortTable = new TableGateway('cohorte', $this->dbAdapter);
        try {
            $result = $cohortTable->insert([
                'fecha_cohorte' => $cohort
            ]);
            if ($result == true) {
                //ADDING "pensum_cohorte" ASSOCIATIONS
                //GETTING MOST RECENT PENSUMS FOR COHORT DATE
                /*
                 * select p1.cod_pensum from pensum p1 
                 * where p1.fecha_inicio = 
                 * (
                 * 	select max(p2.fecha_inicio) from pensum p2
                 *     where p2.cod_carrera = p1.cod_carrera
                 *     and p2.fecha_inicio <= curdate()
                 * );
                 */
                $pensumTable = new TableGateway(['p1' => 'pensum'], $this->dbAdapter);
                $pensums = $pensumTable->select([
                            'p1.cod_pensum <> ' . self::UPG_COURSE_CODE,
                            "p1.fecha_inicio = ( select max(p2.fecha_inicio) from pensum p2 "
                            . "where p2.cod_carrera = p1.cod_carrera "
                            . "and p2.fecha_inicio <= '$cohort'	);"
                        ])->toArray();

                $pensumCohortTable = new TableGateway('pensum_cohorte', $this->dbAdapter);
                $inserted = true;
                foreach ($pensums as $pensum) {
                    $inserted = $pensumCohortTable->insert([
                        'fecha_cohorte' => $cohort,
                        'cod_pensum' => $pensum['cod_pensum']
                    ]);
                    if ($inserted === false) {
                        break;
                    }
                }
                if ($inserted) {
                    $response->success("Cohorte añadida correctamente");
                } else {
                    $response->addMsg("No se pudieron agregar los pensums a la cohorte seleccionada.");
                }
            } else {
                $response->addMsg("No se agregó la nueva cohorte");
            }
        } catch (InvalidQueryException $ex) {
            $response->addMsg("Error al agregar la nueva cohorte. Verifique si no existe ya la cohorte");
        }
        return $response;
    }

}
