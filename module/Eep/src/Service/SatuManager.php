<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Expression;
use Eep\Entity\User;
use Eep\Entity\Role;
use Zend\Db\Sql\Select;
//SERVICES
use Eep\Service\UserManager;
use Eep\Service\InscriptionManager;

class SatuManager extends Manager {

    private $satuAdapter;
    private $eepAdapter;
    private $userManager;
    private $inscriptionManager;

    public function __construct(Adapter $eepAdapter, Adapter $satuAdapter, UserManager $userManager, InscriptionManager $inscriptionManager) {
        parent::__construct($eepAdapter);
        $this->eepAdapter = $eepAdapter;
        $this->satuAdapter = $satuAdapter;
        $this->userManager = $userManager;
        $this->inscriptionManager = $inscriptionManager;
    }

    private function updateUser($userCode, $updateFinalGrades = false): R {
        $res = $this->addUser($userCode);
        if ($res->get() && $res->getType() == R::SUCCESS) {
            //IF USER IS ADDED AND HAS ACADEMIC REGISTRY
            $user = $res->getObj();
            $res = $this->updateCareers($user);
            if ($res->get() && $updateFinalGrades) {
                $res = $this->updateFinalGrades($userCode);
            }
        }
        return $res;
    }

    private function addUser($userCode): R {
        $res = new R();
        $res->success();
        //SEARCHING EEP DATA
        try {
            $userTable = new TableGateway('usuario', $this->eepAdapter);
            $result = $userTable->select([
                'cod_usuario' => $userCode
            ]);
        } catch (\Exception $ex) {
            $res->failure("No se pudo buscar al usuario código '$userCode'");
            $res->addError($ex);
            return $res;
        }
        //VALIDATING IF USER IS GOING TO BE UPDATED IN SATU
        if ($result->count() == 0) {
            $res->failure("Usuario '$userCode' no encontrado.");
        } else {
            $user = new User($result->current());
            $res->setObj($user);
            if ($user->getRegistroAcademico() == null) {
                $res->success("El estudiante no tiene registro académico.");
                $res->setType(R::WARNING);
            } else {
                try {
                    //SEARCHING FOR USER IN SATU
                    $studentSatuTable = new TableGateway('estudiante', $this->satuAdapter);
                    $result = $studentSatuTable->select([
                        'carnet' => $user->getRegistroAcademico()
                    ]);
                    if ($result->count() > 0) {
                        $res->success('Usuario ya agregado en SATU');
                    } else {
                        $studentSatuTable->insert([
                            'carnet' => $user->getRegistroAcademico(),
                            'nombre' => ($user->getNombreCompleto() == null) ? $user->getApellidos() . " " . $user->getNombres() : $user->getNombreCompleto(),
                            'departamento' => null,
                            'municipio' => null,
                            'zona' => null,
                            'direccion' => null,
                            'telefono' => $user->getTelefono(),
                            'celular' => null,
                            'sexo' => $user->getSexo() == 'H' ? '1' : '2',
                            'dpi' => $user->getCui() ?? null,
                            'estado_civil' => null,
                            'nit' => null,
                            'cohorte' => null,
                            'fecha_nacimiento' => $user->getFechaNacimiento(),
                            'solvencia_general' => null,
                            'password' => new \Zend\Db\Sql\Expression("md5('{$user->getRegistroAcademico()}')"),
                            'fotografia' => null,
                            'extension' => 0,
                            'usuario' => 'estudiante',
                            'fecha_actualizacion' => date('Y-m-d'),
                            'fecha_pin' => null,
                            'direccion_fda' => null,
                            'telefono_fda' => null,
                            'telefono_celular_fda' => null,
                            'telefono_secundario_fda' => null,
                            'email_fda' => $user->getCorreo(),
                            'contrasena_temporal' => null,
                            'correo_institucional' => null,
                            'proyecto_graduacion' => null
                        ]);
                        $res->success();
                    }
                } catch (\Exception $ex) {
                    $res->failure("No se pudo grabar al usuario código '$userCode' en SATU.");
                    $res->addError($ex);
                    return $res;
                }
            }
        }
        return $res;
    }

    private function updateCareers(User $user): R {
        $res = new R();
        $res->success();
        //CHECKING IF USER EXISTS IN SATU
        if ($user->getRegistroAcademico() == null) {
            $res->failure('El usuario (' . $user->getCode() . ') no tiene aún registro académico.');
            return $res;
        }
        try {
            $student = new TableGateway('estudiante', $this->satuAdapter);
            $result = $student->select([
                'carnet' => $user->getRegistroAcademico()
            ]);
            if ($result->count() == 0) {
                $res->failure('El estudiante ' . $user->getNombres() . ' ' . $user->getApellidos() . ' (' . $user->getRegistroAcademico() . ') no existe aún en SATU.');
                return $res;
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudo consultar SATU para buscar al estudiante');
            $res->addError($ex);
        }
        //CHECKING EEP CAREERS
        try {
            $eepCareers = new TableGateway(['ac' => 'asignacion_carrera'], $this->eepAdapter);
            $select = $eepCareers->getSql()->select();
            $select->join(['p' => 'pensum'], 'ac.cod_pensum = p.cod_pensum', ['cod_carrera']);
            $select->where([
                'cod_usuario' => $user->getCode()
            ]);
            $select->order('fecha_cohorte DESC');
            $result = $eepCareers->selectWith($select)->toArray();
            $eepCareerArray = [];
            foreach ($result as $value) {
                $eepCareerArray[$value['cod_carrera']] = $value;
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudieron buscar las carreras del usuario.');
            $res->addError($ex);
            return $res;
        }
        //SEARCHING SATU CAREERS
        try {
            $satuCareers = new TableGateway('carrera_estudiante', $this->satuAdapter);
            $result = $satuCareers->select([
                        'carnet' => $user->getRegistroAcademico()
                    ])->toArray();
            $satuCareerArray = [];
            foreach ($result as $value) {
                $satuCareerArray[$value['carrera']] = $value;
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudieron buscar las carreras del usuario en SATU.');
            $res->addError($ex);
            return $res;
        }
        //ADDING EEP MISSING CAREERS TO SATU
        try {
            $missingCareers = array_diff_key($eepCareerArray, $satuCareerArray);
            foreach ($missingCareers as $d) {
                $set = [
                    'carnet' => $user->getRegistroAcademico(),
                    'carrera' => $d['cod_carrera'],
                    'fecha_inicio' => $d['fecha_cohorte'],
                    'fecha_cierre' => null,
                    'fecha_eps' => null,
                    'fecha_privado' => null,
                    'acta_privado' => null,
                    'fecha_publico' => null,
                    'acta_publico' => null,
                    'fecha_ingreso_registro' => date('Y-m-d'),
                    'fecha_ultima_actualizacion' => date('Y-m-d'),
                    'usuario' => 'sync_postgrados',
                    'status_estudiante' => 1,
                ];
                $satuCareers->insert($set);
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudieron insertar las carreras faltantes del SEEP del usuario en SATU.');
            $res->addError($ex);
            $res->addError('Set: ' . json_encode($set ?? '(No declarada)'));
            return $res;
        }
        return $res;
    }

    public function beginTransaction() {
        $this->satuAdapter->getDriver()->getConnection()->beginTransaction();
    }

    public function commit() {
        $this->satuAdapter->getDriver()->getConnection()->commit();
    }

    public function rollback() {
        $this->satuAdapter->getDriver()->getConnection()->rollback();
    }

    /*
     * RESULT OBJECT:
     *  [
     *      'SATU' => [
     *          'added' => <countAdded>,
     *          'updated' => <updated>
     *      ],
     *      'SEEP' => [
     *          'added' => <countAdded>
     *      ]
     *  ]
     */

    private function updateFinalGrades($userCode): R {
        $res = new R();
        $res->success();
        $counterArray = [
            'SATU' => [
                'added' => 0,
                'updated' => 0
            ],
            'SEEP' => [
                'added' => 0
            ]
        ];
        try {
            //GETTING AND FORMATTING SEEP GRADES
            $user = $this->userManager->getUser($userCode, true);
            $eep = new TableGateway(['n' => 'nota_final'], $this->eepAdapter);
            $select = $eep->getSql()->select();
            $select->join(['h' => 'horario'], 'n.cod_horario = h.cod_horario', ['seccion', 'fecha_inicio'], Select::JOIN_LEFT);
            $select->where([
                'cod_usuario' => $userCode
            ]);
            $eepGradesData = $eep->selectWith($select)->toArray();
            //GETTING SATU GRADES
            $satu = new TableGateway(['n' => 'nota'], $this->satuAdapter);
            $select = $satu->getSql()->select();
            $select->join(['p' => 'pensum'], 'n.pensum = p.pensum', []);
            $select->join(['c' => 'carrera'], 'p.carrera = c.carrera', []);
            $select->where([
                'c.nivel > 2',
                'carnet' => $user->getRegistroAcademico()
            ]);
            $satuGradesData = $satu->selectWith($select)->toArray();
        } catch (\Exception $ex) {
            $res->failure('Hubo un problema con la lectura de las notas finales de SATU o el SEEP', $ex);
            $res->addError("Usuario: $userCode");
        }
        if ($res->get()) {
            //FORMATTING EEP GRADES
            /*
             * PENSUM CODE, COURSE CODE, AND DATE WILL MATCH BOTH FINAL GRADES
             */
            $eepGrades = [];
            foreach ($eepGradesData as $d) {
                $pensumCode = $d['cod_pensum'];
                $courseCode = $d['cod_curso'];
                $date = $d['fecha_oficializacion'];
                $eepGrades[$pensumCode][$courseCode][$date] = $d;
            }
            //FINDING MATCHS
            $satuUpdates = [];
            $satuGrades = [];
            foreach ($satuGradesData as $d) {
                $pensumCode = $d['pensum'];
                $courseCode = $d['codigo'];
                $date = $d['fecha_ingreso'];
                //FINDING REFLECTED FINAL GRADE
                if (isset($eepGrades[$pensumCode][$courseCode][$date])) {
                    $eepD = $eepGrades[$pensumCode][$courseCode][$date];
                    unset($eepGrades[$pensumCode][$courseCode][$date]); //DO NOT ADD TO SATU
                    if (empty($eepGrades[$pensumCode][$courseCode])) {
                        unset($eepGrades[$pensumCode][$courseCode]);
                        if (empty($eepGrades[$pensumCode])) {
                            unset($eepGrades[$pensumCode]);
                        }
                    }
                    $isGradeType = $eepD['ponderado_con_nota'] == true;
                    $eepApproved = $eepD['aprobado'] == true;
                    $eepGrade = $eepD['nota'];
                    $satuGrade = $d['nota'];
                    $satuApproved = $d['aprobado'] == true;
                    //CHECKING GRADE DIFFERENCE
                    if ($eepApproved != $satuApproved || ($isGradeType && ($eepGrade != $satuGrade))) {
                        $satuUpdates[] = [
                            'where' => [
                                'pensum' => $pensumCode,
                                'codigo' => $pensumCode,
                                'carnet' => $user->getRegistroAcademico(),
                                'fecha_ingreso' => $date,
                            ],
                            'set' => [
                                'nota' => $isGradeType ? $eepGrade : ($eepApproved ? 100 : 0),
                                'aprobado' => $eepApproved
                            ]
                        ];
                    }
                } else {
                    //NOT FOUND, SO ADD RECORD IN SEEP
                    $satuGrades[$pensumCode][$courseCode][$date] = $d;
                }
            }
            //UPDATING SATU GRADES
            if (count($satuUpdates) > 0) {
                try {
                    $count = 0;
                    foreach ($satuUpdates as $update) {
                        $result = $satu->update($update['set'], $update['where']);
                        if ($result == 0) {
                            $res->failure('No se generó alguna actualización para la nota a actualizar');
                            $res->addError(json_encode($update));
                            return $res;
                        }
                        $count++;
                    }
                    $counterArray['SATU']['updated'] = $count;
                } catch (\Exception $ex) {
                    $res->failure('No se pudieron actualizar las notas de SATU', $ex);
                    $res->addError('Update: ' . ($update ?? 'No definido'));
                }
            }
            if ($res->get() && count($eepGrades) > 0) {//eepGrades CONTAINS ALL EEP GRADES NOT FOUND IN SATU
                //INSERTING SATU DATA
                try {
                    $count = 0;
                    foreach ($eepGrades as $pensumCode => $pensums) {
                        foreach ($pensums as $courseCode => $courses) {
                            foreach ($courses as $date => $data) {
                                //TRANSFORMING GRADE
                                $hasGrade = $data['ponderado_con_nota'] == true;
                                $approved = $data['aprobado'];
                                $grade = $hasGrade ? $data['nota'] : ($approved ? 100 : 0);
                                //EXTRACTING DATE
                                $time = strtotime($date);
                                if ($time === false) {
                                    $res->failure("La fecha obtenida del SEEP no es una fecha válida: '$date'");
                                    return $res;
                                }
                                $type = $data['cod_tipo_nota_final'];
                                $set = [
                                    'anio' => date('Y', $time),
                                    'semestre' => (date('m', $time) * 1 <= 6 ? 1 : 2),
                                    'evaluacion' => 1,
                                    'pensum' => $pensumCode,
                                    'codigo' => $courseCode,
                                    'seccion' => $data['seccion'], //TAL VEZ LA ASOCIACIÓN DE HORARIO NO ES NECEARIO, O RESTRINGIR PARA NO TENER PROBLEMA CON SOBREESCRITURA DE "SECCION" POR HORARIO EN NULL
                                    'carnet' => $user->getRegistroAcademico(),
                                    'nota' => $grade,
                                    'aprobado' => $data['aprobado'],
                                    'fecha_ingreso' => $data['fecha_oficializacion'],
                                    'estado' => $data['cod_estado_nota_final'],
                                    'faltan' => 0,
                                    'acta' => null,
                                    'referencia' => null,
                                    'equivalencia_interna' => $type == GradesManager::FG_TYPE_CAREER_INTERNAL_EQUIVALENCE || $type == GradesManager::FG_TYPE_PENSUM_INTERNAL_EQUIVALENCE,
                                    'equivalencia_externa' => $type == GradesManager::FG_TYPE_EXTERNAL_EQUIVALENCE,
                                    'fecha' => date('m/Y', strtotime($data['fecha_cursado'])),
                                    'usuario' => 'sync_postgrados',
                                    'nota_oficial' => true,
                                ];
                                $satu->insert($set);
                                $count++;
                            }
                        }
                    }
                    $counterArray['SATU']['added'] = $count;
                } catch (\Exception $ex) {
                    $res->failure('No se pudieron agregar las notas finales pendientes a SATU', $ex);
                    $res->addError("Set de inserción a SATU: " . json_encode($set ?? '(No declarada)'));
                }
            }
            if ($res->get() && count($satuGrades) > 0) {//satuGrades CONTAINS ALL SATU GRADES NOT FOUND IN EEP
                //INSERTING EEP DATA
                $count = 0;
                try {
                    foreach ($satuGrades as $pensumCode => $pensums) {
                        foreach ($pensums as $courseCode => $courses) {
                            foreach ($courses as $date => $data) {
                                $time = strtotime($date);
                                if ($time === false) {
                                    $res->failure("La fecha obtenida de SATU no es una fecha válida: '$date'");
                                    return $res;
                                }
                                //FINDING FINAL GRADE TYPE
                                $type = GradesManager::FG_TYPE_NO_TIMETABLE_REGULAR;
                                if ($data['estado'] == GradesManager::FG_STATUS_EXTERNAL_EQUIVALENCE) {
                                    $type = GradesManager::FG_TYPE_EXTERNAL_EQUIVALENCE;
                                }
                                //GETTING COURSED DATE
                                if ($data['fecha'] == null) {
                                    $semester = $data['semestre'];
                                    $courseDate = $data['anio'] . '-' . ($semester == 1 ? '01' : '07') . '-01';
                                    if (strtotime($courseDate) === false) {
                                        $courseDate = false;
                                    }
                                } else {
                                    $typeParts = explode('/', $data['fecha']);
                                    if (count($typeParts) == 2) {
                                        $courseDate = $typeParts[1] . '-' . $typeParts[0] . '-01';
                                        if (strtotime($courseDate) === false) {
                                            $courseDate = false;
                                        }
                                    }
                                }
                                $set = [
                                    'cod_pensum' => $data['pensum'],
                                    'cod_curso' => $data['codigo'],
                                    'cod_usuario' => $user->getCode(),
                                    'cod_horario' => null,
                                    'cod_tipo_nota_final' => $type, //THERE IS NO WAY TO CHECK IF IS IT WAS APPROVED BY SUFFICIENCY
                                    'cod_estado_nota_final' => $data['estado'],
                                    'nota' => $data['nota'],
                                    'fecha_oficializacion' => $data['fecha_ingreso'],
                                    'aprobado' => $data['aprobado'],
                                    'ponderado_con_nota' => true, //THERE IS NO WAY TO KNOW IT. TRUE IS DEFAULT
                                    'observacion' => empty($data['acta'] . $data['referencia']) ? null : "Acta:$data[acta]. Referencia:$data[referencia]",
                                    'fecha_cursado' => $courseDate,
                                    'seccion' => $data['seccion'] ?? null,
                                ];
                                $eep->insert($set);
                                $count++;
                            }
                        }
                    }
                    $counterArray['SEEP']['added'] = $count;
                } catch (\Exception $ex) {
                    $res->failure('No se pudieron agregar las notas finales pendientes al SEEP', $ex);
                    $res->addError("Set de inserción a SEEP: " . json_encode($set ?? '(No declarada)'));
                }
            }
        }
        $res->setObj($counterArray);
        return $res;
    }

    public function updateUsers(): R {
        $res = new R();
        $res->success();
        $counterArray = [
            'SATU' => [
                'added' => 0,
                'updated' => 0
            ],
            'SEEP' => [
                'added' => 0
            ]
        ];
        //GETTING ALL STUDENTS WITH ACADEMIC REGISTRY
        try {
            $table = new TableGateway(['u' => 'usuario'], $this->eepAdapter);
            $select = $table->getSql()->select();
            $select->join(['ur' => 'usuario_rol'], 'u.cod_usuario = ur.cod_usuario', []);
            $select->columns([
                'cod_usuario'// => new Expression('DISTINCT u.cod_usuario')
            ]);
            $select->where([
                'cod_rol' => Role::ESTUDIANTE,
                'registro_academico is not NULL'
	    ]);
	    $select->where->greaterThan("registro_academico", 0);
            $result = $table->selectWith($select)->toArray();
        } catch (\Exception $ex) {
            $res->failure('No se pudieron leer los usuarios de la BD', $ex);
        }
        if ($res->get()) {
            $this->beginTransaction(); //SATU
            parent::beginTransaction(); //SEEP
            //CLEANING STUDENTS DATA
            foreach ($result as $studentData) {
                $userCode = $studentData['cod_usuario'];
                $result = $this->updateUser($userCode, true);
                $res->addMsg($result);
                if (!$result->get()) {
                    $res->failure();
                    break;
                } else {
                    $userCounter = $result->getObj();
                    $counterArray['SATU']['added'] += $userCounter['SATU']['added'];
                    $counterArray['SATU']['updated'] += $userCounter['SATU']['updated'];
                    $counterArray['SEEP']['added'] += $userCounter['SEEP']['added'];
                }
            }
            //ADDING MESSAGE
            $changes = false;
            if ($counterArray['SATU']['added'] > 0) {
                $changes = true;
                $res->addMsg($counterArray['SATU']['added'] . ' notas añadidas a SATU');
            }
            if ($counterArray['SATU']['updated'] > 0) {
                $changes = true;
                $res->addMsg($counterArray['SATU']['updated'] . ' notas actualizadas en SATU');
            }
            if ($counterArray['SEEP']['added'] > 0) {
                $changes = true;
                $res->addMsg($counterArray['SEEP']['added'] . ' notas añadidas a SEEP');
            }
            if (!$changes) {
                $res->addMsg('SIN CAMBIOS');
            }
            if ($res->get()) {
                $this->commit(); //SATU
                parent::commit(); //SEEP
            } else {
                $this->rollback();
                parent::rollback();
                $res->addMsg('SE HIZO ROLLBACK DE LAS NOTAS AÑADIDAS/ACTUALIZADAS');
            }
        }
        return $res;
    }

}
