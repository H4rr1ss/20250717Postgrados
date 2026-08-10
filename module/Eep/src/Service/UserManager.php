<?php

namespace Eep\Service;

use Eep\Entity\User;
use Eep\Entity\Role;
use Eep\Entity\InfoLaboral;
use Eep\Form\CandidateForm as CF;
use Zend\Crypt\Password\Bcrypt;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;

class UserManager extends Manager {

    const SEARCH_RESULT_LIMIT = 100;
    const PASSPORT_PATTERN = '#^(([0-9]|[a-z]|[A-Z])+)(-([0-9]|[a-z]|[A-Z])+)?$#';

    public function addUser($data): R {
        $response = new R();
        //GETTING INFO INTO OBJECTS
        $user = new User($data);
        //var_dump($user); die;
        if (!empty($data[CF::CURRENTLY_WORKS]) && ($data[CF::CURRENTLY_WORKS] == 'yes')) {
            $infoLaboral = new InfoLaboral($data);
            $user->setInfoLaboral($infoLaboral);
        } else {
            $infoLaboral = null;
        }
        //CUSTOMIZING WHERE QUERY
        $userTable = new TableGateway('usuario', $this->dbAdapter);
        $where = [];
        //var_dump($user); die;
        if (!empty($user->getCui())) {
            $where[] = 'cui =' . $user->getCui();
        }
        if (!empty($user->getPasaporte())) {
            $where[] = 'pasaporte = "' . $user->getPasaporte() . '"';
        }
        if (!empty($user->getRegistroAcademico())) {
            $where[] = 'registro_academico = ' . $user->getRegistroAcademico();
        }
        if (!empty($user->getRegistroPersonal())) {
            $where[] = 'registro_personal = ' . $user->getRegistroPersonal();
        }

        if (empty($where)) {
            $response->addMsg("No se ha obtenido identificador alguno del usuario a agregar");
        } else {
            try {
                $userResult = $userTable->select([implode(' or ', $where)]);
            } catch (\Exception $ex) {
                $response->failure("No se pudo buscar el usuario", $ex);
                return $response;
            }
            //CHECKING IF THERE'S ANY USER WITH THOSE ID'S
            $users = $userResult->toArray();
            if ($userResult->count() != 0) {
                $errorMsg = "";
                if ($userResult->count() == 1) {
                    //CHECKING IF THERE IS CONFLICT WITH ANY DATA ON THE EXISTING USER
                    $queriedUser = current($users); //GET THE ONLY USER
                    $dbUser = new User($queriedUser);
                    //CHECKING CUI EQUALITY
                    if ((!empty($dbUser->getCui()) && !empty($user->getCui()) && $dbUser->getCui() != $user->getCui())) {
                        $response->addMsg('Conflicto de CUI. '
                                . 'Ingresado: "' . $user->getCui() . '". '
                                . 'Usuario: "' . $dbUser->getCui() . '"'
                        );
                    }
                    //CHECKING ACADEMIC REGISTRY EQUALITY
                    elseif (!empty($dbUser->getRegistroAcademico()) && !empty($user->getRegistroAcademico()) && $dbUser->getRegistroAcademico() != $user->getRegistroAcademico()) {
                        $response->addMsg('Conflicto de registro académico. '
                                . 'Ingresado: "' . $user->getRegistroAcademico() . '". '
                                . 'Usuario: "' . $dbUser->getRegistroAcademico() . '"'
                        );
                    }
                    //CHECKING PASSPORT EQUALITY
                    elseif (!empty($dbUser->getPasaporte()) && !empty($user->getPasaporte()) && $dbUser->getPasaporte() != $user->getPasaporte()) {
                        $response->addMsg('Conflicto de pasaporte. '
                                . 'Ingresado: "' . $user->getPasaporte() . '". '
                                . 'Usuario: "' . $dbUser->getPasaporte() . '"'
                        );
                    }
                    //CHECKING PERSONAL REGISTRY EQUALITY
                    elseif (!empty($dbUser->getRegistroPersonal()) && !empty($user->getRegistroPersonal()) && $dbUser->getRegistroPersonal() != $user->getRegistroPersonal()) {
                        $response->addMsg('Conflicto de registro de personal. '
                                . 'Ingresado: "' . $user->getRegistroPersonal() . '". '
                                . 'Usuario: "' . $dbUser->getRegistroPersonal() . '"'
                        );
                    }
                    //CHECKING BIRTHDATE
                    elseif (!empty($dbUser->getFechaNacimiento()) && !empty($user->getFechaNacimiento()) && $dbUser->getFechaNacimiento() != $user->getFechaNacimiento()) {
                        $response->addMsg('Conflicto de fecha de nacimiento. '
                                . 'Ingresado: "' . $user->getFechaNacimiento() . '". '
                                . 'Usuario: "' . $dbUser->getFechaNacimiento() . '"'
                        );
                    } else {
                        //LOOKS LIKE IT IS THE SAME PERSON,
                        //IF THE USER FORM RETURNED LABORAL INFO, IT HAS TO BE UPDATED
                        $user->setCodUsuario($dbUser->getCode());
                        $user->setCodInfoLaboral($dbUser->getCodInfoLaboral());
                        $updateResult = $this->updateUser($user, $infoLaboral, true);
                        if ($updateResult !== true) {
                            $response->addMsg("No se pudo actualizar la información del usuario");
                        } else {
                            $response->success();
                            $response->set($dbUser->getCode());
                        }
                    }
                } else {
                    //ERRROR, THERE CANNOT BE MORE THAN ONE USER REPEATED (OR WITH IDENTIFICATION NUMBER PROBLEMS)
                    //CROSSING OVER INFORMATION GIVEN BY FORM TO SHOW THE SPECIFIC ERROR DETAILS
                    //$users = $userResult->toArray();
                    $errorMsg .= "Los identificadores del usuario con  "
                            . (empty($user->getCui()) ? "" : "CUI: \"" . $user->getCui() . "\" ")
                            . (empty($user->getRegistroAcademico()) ? "" : "Registro Académico: \"" . $user->getRegistroAcademico() . "\" ")
                            . (empty($user->getRegistroPersonal()) ? "" : "Registro de Personal: \"" . $user->getRegistroPersonal() . "\" ")
                            . (empty($user->getPasaporte()) ? "" : "Pasaporte: \"" . $user->getPasaporte() . "\" ")
                            . "; tienen conflicto con otros usuarios: ";
                    foreach ($users as $conflictedUser) {
                        $errorMsg .= "<br/>— Nombres: \"" . $conflictedUser['nombres'] . "\" ";
                        $errorMsg .= "Apellidos: \"" . $conflictedUser['apellidos'] . "\" ";
                        if (!empty($conflictedUser['cui']) && $conflictedUser['cui'] == $user->getCui()) {
                            $errorMsg .= "CUI: \"" . $user->getCui() . "\" ";
                        }
                        if (!empty($conflictedUser['registro_academico']) && $conflictedUser['registro_academico'] == $user->getRegistroAcademico()) {
                            $errorMsg .= "Registro Académico: \"" . $user->getRegistroAcademico() . "\" ";
                        }
                        if (!empty($conflictedUser['pasaporte']) && $conflictedUser['pasaporte'] == $user->getPasaporte()) {
                            $errorMsg .= "Pasaporte: \"" . $user->getPasaporte() . "\" ";
                        }
                        if (!empty($conflictedUser['registro_personal']) && $conflictedUser['registro_personal'] == $user->getRegistroPersonal()) {
                            $errorMsg .= "Registro de Personal: \"" . $user->getRegistroPersonal() . "\" ";
                        }
                    }
                    $response->addMsg($errorMsg);
                }
            } else {//ADD NEW USER
                //CHECKING FOR LABORAL INFO
                if (!empty($infoLaboral)) {
                    $infoLaboral->setCode($this->insertInfoLaboral($infoLaboral));
                }
                //CREATE NEW USER
                try {
                    $values = [
                        'cui' => $user->getCui(),
                        'pasaporte' => $user->getPasaporte(),
                        'registro_academico' => $user->getRegistroAcademico(),
                        'registro_personal' => $user->getRegistroPersonal(),
                        'nombres' => $user->getNombres(),
                        'apellidos' => $user->getApellidos(),
                        'fecha_nacimiento' => $user->getFechaNacimiento(),
                        'telefono' => $user->getTelefono(),
                        'correo' => $user->getCorreo(),
                        'contrasenia' => $this->createPassword($user->getFechaNacimiento()),
                        'cod_pais' => $user->getCodPais(),
                        'sexo' => $user->getSexo(),
                        'grado_academico' => $user->getGradoAcademico(),
                        'titulo_profesional' => $user->getTituloProfesional(),
                        'numero_colegiado' => $user->getNumeroColegiado(),
                        'fecha_creacion' => new Expression('curdate()'),
                        'cod_info_laboral' => empty($infoLaboral) ? null : $infoLaboral->getCode(),
                        'nombre_completo' => $user->getNombreCompleto()
                    ];
                    if ($user->getCode() != null) {
                        $values['cod_usuario'] = $user->getCode();
                    }
                    $userTable->insert($values);
                    $response->success();
                    $response->set($userTable->getLastInsertValue());
                } catch (\Exception $ex) {
                    $response->addMsg("No se pudo agregar el nuevo usuario: " . $ex->getMessage());
                }
            }
        }
        return $response;
    }

    public function updateUser(User $user, InfoLaboral $infoLaboral = null, $forceLaboralInfoUpdate = false) {
        /* FORM RECEIVED DATA TO BE IGNORED (NOT UPDATED) BECAUSE IT SHOULDN'T CHANGE IN TIME:
         * - sexo
         * - pais
         * - nombres
         * - apellidos
         * - fecha_nacimiento
         * - contrasenia ##THERE IS AN SPECIALIZED METHOD FOR THIS CHANGE
         */
        //GETTING DATA TO UPDATE
        $userTable = new TableGateway('usuario', $this->dbAdapter);
        $updateData = [];
        if (!empty($user->getGradoAcademico())) {
            $updateData['grado_academico'] = $user->getGradoAcademico();
        }
        if (!empty($user->getTituloProfesional())) {
            $updateData['titulo_profesional'] = $user->getTituloProfesional();
        } else {
            $updateData['titulo_profesional'] = null;
        }
        if (!empty($user->getNumeroColegiado())) {
            $updateData['numero_colegiado'] = $user->getNumeroColegiado();
        } else {
            $updateData['numero_colegiado'] = null;
        }
        if (!empty($user->getCui())) {
            $updateData['cui'] = $user->getCui();
        }
        if (!empty($user->getPasaporte())) {
            $updateData['pasaporte'] = $user->getPasaporte();
        }
        if (!empty($user->getRegistroAcademico())) {
            $updateData['registro_academico'] = $user->getRegistroAcademico();
        }
        if (!empty($user->getRegistroPersonal())) {
            $updateData['registro_personal'] = $user->getRegistroPersonal();
        }
        if (!empty($user->getTelefono())) {
            $updateData['telefono'] = $user->getTelefono();
        }
        if (!empty($user->getCorreo())) {
            $updateData['correo'] = $user->getCorreo();
        }
        if (!empty($user->getGradoAcademico())) {
            $updateData['grado_academico'] = $user->getGradoAcademico();
        }
        if ($forceLaboralInfoUpdate) {

            //CHECK IF USER HAS A LABORAL INFO ALREADY TO UPDATE IT AND GET THE CODE

            $codPreviousInfoLaboral = $user->getCodInfoLaboral(); //$queryUser['cod_info_laboral'];
            if (!empty($codPreviousInfoLaboral)) {
                //USER HAS PREVIOUS LABORAL INFO
                if (empty($infoLaboral)) {
                    //USER UPDATE WILL HAVE NO LABORAL INFO -> DELETE PREVIOUS LABORAL INFO
                    $this->deleteInfoLaboral($codPreviousInfoLaboral);
                    $updateData['cod_info_laboral'] = null;
                } else {
                    //USER LABORAL INFO WILL JUST CHANGE
                    $infoLaboral->setCode($codPreviousInfoLaboral);
                    $this->updateInfoLaboral($infoLaboral);
                    $updateData['cod_info_laboral'] = $infoLaboral->getCode();
                }
            } else {
                //USER HAS NO ACTUAL LABORAL INFO. CREATE NEW LABORAL INFO IF PROVIDED AND GET THE CODE
                if (!empty($infoLaboral)) {
                    $infoLaboral->setCode($this->insertInfoLaboral($infoLaboral));
                    $updateData['cod_info_laboral'] = $infoLaboral->getCode();
                }
            }
        }



        //UPDATING USER INFO
        try {
            $userTable->update($updateData, ['cod_usuario' => $user->getCode()]);
            return true;
        } catch (InvalidQueryException $ex) {
            return false;
        }
    }

    public function getPossibleUsers($id, $addProtection = false) {
        $userTable = new TableGateway(['u' => 'usuario'], $this->dbAdapter);
        $select = $userTable->getSql()->select();
        $select->join(['p' => 'pais'], 'u.cod_pais = p.cod_pais', ['pais' => 'nombre'], Select::JOIN_LEFT);
        $select->where("u.registro_academico = '$id' or "
                . "u.registro_personal = '$id' or "
                . "u.cui = '$id' or "
                . "u.pasaporte = '$id'");
        $usersResult = $userTable->selectWith($select)->toArray();
        $users = [];
        foreach ($usersResult as $userData) {
            $users[] = new User($userData, $addProtection);
        }
        return $users;
    }

    public function getUser($id, $addProtection = false): User {
        $userTable = new TableGateway(['u' => 'usuario'], $this->dbAdapter);
        $select = $userTable->getSql()->select();
        $select->join(['p' => 'pais'], 'u.cod_pais = p.cod_pais', ['pais' => 'nombre'], Select::JOIN_LEFT);
        $select->where('u.cod_usuario = ' . $id);
        $resultSet = $userTable->selectWith($select);
        return new User($resultSet->current(), $addProtection);
    }

    public function validatePassword($user, $password) {
        $bcrypt = new Bcrypt();
        $passwordHash = $user->getContrasenia();
        if ($bcrypt->verify($password, $passwordHash)) {
            return true;
        }
        return false;
    }

    public function changePassword($userId, $newPassword) {
        if (strlen($newPassword) < 6 || strlen($newPassword) > 64) {
            return false;
        }

        //ENCRYPTING THE NEW PASSWORD
        $bcrypt = new Bcrypt();
        $passwordHash = $bcrypt->create($newPassword);

        //SAVING IN DATABASE
        $userTable = new TableGateway('usuario', $this->dbAdapter);
        try {
            $userTable->update(
                    ['contrasenia' => $passwordHash], ['cod_usuario' => $userId]);
        } catch (InvalidQueryException $ex) {
            return false;
        }
        return true;
    }

    private function createPassword($date, $YmdDate = false) {
        $time = strtotime($date);
        if ($time === false) {
            $pass = "1234ABCD!\"#$"; //FIRST FOUR NUMBERS, FIRST FOUR LETTERS, FIRST FOUR SYMBOLS (IN SPANISH KEYBOARD)
        } else {
            $pass = date('dmY', $time);
        }
        $bcrypt = new Bcrypt();
        return $bcrypt->create($pass); //DDMMYYYY
    }

    private function insertInfoLaboral(InfoLaboral $infoLaboral) {
        $labInfoTable = new TableGateway('info_laboral', $this->dbAdapter);
        $labInfoTable->insert([
            'ubicacion' => $infoLaboral->getUbicacion(),
            'hora_inicio' => $infoLaboral->getHoraInicio(),
            'hora_fin' => $infoLaboral->getHoraFin(),
            'lunes' => $infoLaboral->getLunes(),
            'martes' => $infoLaboral->getMartes(),
            'miercoles' => $infoLaboral->getMiercoles(),
            'jueves' => $infoLaboral->getJueves(),
            'viernes' => $infoLaboral->getViernes(),
            'sabado' => $infoLaboral->getSabado(),
            'domingo' => $infoLaboral->getDomingo()
        ]);
        return $labInfoTable->getLastInsertValue();
    }

    private function deleteInfoLaboral($codInfoLaboral) {
        $res = new R();
        //REMOVING FROM USER
        $userTable = new TableGateway('usuario', $this->dbAdapter);
        $userTable->update([
            'cod_info_laboral' => null
                ], [
            'cod_info_laboral' => $codInfoLaboral
        ]);

        try {
            //DELETING INFO LABORAL ITSELF
            $infoTable = new TableGateway('info_laboral', $this->dbAdapter);
            $deleted = $infoTable->delete([
                'cod_info_laboral' => $codInfoLaboral
            ]);
            if ($deleted != true) {
                $res->addMsg("No se eliminó la información laboral previa del estudiante.");
            } else {
                $res->success();
            }
        } catch (InvalidQueryException $ex) {
            $res->addMsg("Hubo un error al intentar eliminar la información laboral previa del estudiante.");
        }
        return $res;
    }

    private function updateInfoLaboral(InfoLaboral $infoLaboral) {
        $labInfoTable = new TableGateway('info_laboral', $this->dbAdapter);
        $labInfoTable->update([
            'ubicacion' => $infoLaboral->getUbicacion(),
            'hora_inicio' => $infoLaboral->getHoraInicio(),
            'hora_fin' => $infoLaboral->getHoraFin(),
            'lunes' => $infoLaboral->getLunes(),
            'martes' => $infoLaboral->getMartes(),
            'miercoles' => $infoLaboral->getMiercoles(),
            'jueves' => $infoLaboral->getJueves(),
            'viernes' => $infoLaboral->getViernes(),
            'sabado' => $infoLaboral->getSabado(),
            'domingo' => $infoLaboral->getDomingo()
                ], [
            'cod_info_laboral' => $infoLaboral->getCode()
        ]);
        return $labInfoTable->getLastInsertValue();
    }

    public function getCountries() {
        $table = new TableGateway('pais', $this->dbAdapter);
        return $table->select()->toArray();
    }

    public function getInfoLaboral($infoId) {
        if (empty($infoId)) {
            return null;
        } else {
            $table = new TableGateway('info_laboral', $this->dbAdapter);
            $result = $table->select([
                'cod_info_laboral' => $infoId
            ]);
            return new InfoLaboral($result->current());
        }
    }

    public function getUsersByName($student) {
        if (empty($student)) {
            return [];
        } else {
            $userTable = new TableGateway(['u' => 'usuario'], $this->dbAdapter);
            $select = $userTable->getSql()->select();
            $select->where("MATCH (nombres,apellidos) AGAINST (\"" . $student . "\" IN BOOLEAN MODE)");
//            $select->join(['ur' => 'usuario_rol'], 'ur.cod_usuario = u.cod_usuario', []);
//            $select->where([
//                'ur.cod_rol' => Role::ESTUDIANTE
//            ]);
            $select->join(['p' => 'pais'], "u.cod_pais = p.cod_pais", ['pais' => 'nombre'], Select::JOIN_LEFT);
            $select->limit(self::SEARCH_RESULT_LIMIT);
            $result = $userTable->selectWith($select)->toArray();
            $users = [];
            foreach ($result as $userData) {
                $users[] = new User($userData);
            }
            return $users;
        }
    }

    public function getUsersByRole($role, $currentRole = true) {
        $res = new R();
        $table = new TableGateway(['ur' => 'usuario_rol'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['u' => 'usuario'], 'ur.cod_usuario = u.cod_usuario');
        $select->where([
            'cod_rol' => $role
        ]);
        if ($currentRole) {
            $select->where([
                'fecha_inicio <= curdate() and (fecha_fin >= curdate() or fecha_fin is null)'
            ]);
        }
        $select->order('nombres ASC');
        try {
            $usersData = $table->selectWith($select)->toArray();
//            $users = [];
//            foreach ($usersData as $userData) {
//                $users[] = new User($userData, true);
//            }
            $res->success();
//            $res->setObj($users);
            $res->setObj($usersData);
        } catch (InvalidQueryException $ex) {
            $roleStr = Role::getStr($role);
            $res->addMsg("No se pudo realizar la búsqueda de usuarios con rol '$roleStr'");
        }

        return $res;
    }

    public function getMatchUsers($userId): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $userTable = new TableGateway('usuario', $this->dbAdapter);
            $attributes = ['registro_academico', 'cui', 'pasaporte'];
            $usersData = [];
            foreach ($attributes as $attribute) {
                $select = new Select(['u' => 'usuario']);
                $select->join(['ac' => 'asignacion_carrera'], 'u.cod_usuario = ac.cod_usuario', []); //ONLY USERS WITH ASIGNED CARRERS WILL BE SELECTED
                $select->join(['p' => 'pensum'], 'ac.cod_pensum = p.cod_pensum', []);
                $select->join(['ca' => 'carrera'], 'p.cod_carrera = ca.cod_carrera', ['nombre_carrera' => 'alias_actual']);
                //INCLUDING ALL PENSUM FROM THE USER CAREER
                $select->join(['pAll' => 'pensum'], 'ca.cod_carrera = pAll.cod_carrera');
                $where = new Where();
                $where->like($attribute, "$userId%");
                $select->limit(20);
                $select->where($where);
                $result = $userTable->selectWith($select)->toArray();
                foreach ($result as $d) {
                    $userCode = $d['cod_usuario'];
                    if (!isset($usersData[$userCode])) {
                        $usersData[$userCode] = [
                            'label' => $d[$attribute],
                            'name' => $d['apellidos'] . ', ' . $d['nombres'],
                            'userCode' => $d['cod_usuario']
                        ];
                    }
                    $pensumCode = $d['cod_pensum'];
                    $careerCode = $d['cod_carrera'];
                    $careerName = $d['nombre_carrera'];
                    $startDate = date('d/m/Y', strtotime($d['fecha_inicio']));
                    $finishDate = empty($d['fecha_fin']) ? 'vigente' : date('d/m/Y', strtotime($d['fecha_fin']));
                    $usersData[$userCode]['pensums'][$pensumCode] = "Pensum $pensumCode | Carrera $careerCode - $careerName ($startDate - $finishDate)";
                }
            }
            uasort($usersData, function($a, $b) {
                $aVal = $a['label'];
                $bVal = $b['label'];
                if ($aVal == $bVal) {
                    return 0;
                }
                return $aVal > $bVal ? 1 : -1;
            });
            $res->setObj($usersData);
        } catch (\Exception $ex) {
            $res->failure("Error consultando el usuario '$userId': " . $ex->getMessage(), $ex);
        }
        return $res;
    }

    public function getOfficialCourses($userCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //GETTING USER'S CAREERS
        $data = [];
        $pensumCodes = [];
        try {
            $careersTable = new TableGateway(['ac' => 'asignacion_carrera'], $this->dbAdapter);
            $select = $careersTable->getSql()->select();
            $select->join(['p' => 'pensum'], 'ac.cod_pensum = p.cod_pensum', ['cod_carrera']);
            $select->join(['c' => 'carrera'], 'p.cod_carrera = c.cod_carrera', ['nombre_carrera' => 'nombre_actual']);
            $select->where([
                'cod_usuario' => $userCode
            ]);
            //IF USER HAS TWO CAREER ASSIGNMENTS WITH THE SAME PENSUM BUT DIFFERENT COHORT, THE LAST ASSIGNED COHORT WILL OVERWRITE THE OTHERS
            //IF USER HAS TWO CAREER ASSIGNMENTS WITH THE SAME CARREER BUT DIFFERENT PENSUM, THE LAST ASSIGNED PENSUM WILL OVERWRITE THE OTHERS
            $select->order('ac.fecha_asignacion ASC');
            $result = $careersTable->selectWith($select)->toArray();
            foreach ($result as $careers) {
                $careerCode = $careers['cod_carrera'];
                $data[$careerCode]['careerAsignmentDate'] = $careers['fecha_asignacion'];
                $data[$careerCode]['name'] = $careers['nombre_carrera'];
                $data[$careerCode]['pensumCode'] = $careers['cod_pensum'];
                $pensumCodes[$careerCode] = $careers['cod_pensum']; //OVERWRITTING IF NECESSARY
                $data[$careerCode]['cohort'] = $careers['fecha_cohorte']; //OVERWRITTING IF NECESSARY
                $data[$careerCode]['average'] = 0;
                $data[$careerCode]['credits'] = 0;
                $data[$careerCode]['courses'] = [];
            }
            //REORDERING DATA TO HAVE LAST ASSIGNED CAREER FIRST
            uasort($data, function($a, $b) {
                $aVal = strtotime($a['careerAsignmentDate']);
                $bVal = strtotime($b['careerAsignmentDate']);
                if ($aVal == $bVal) {
                    return 0;
                }
                return $aVal > $bVal ? -1 : 1;//OLDER CAREER LAST
            });
        } catch (\Exception $ex) {
            $res->failure("No se pudo realizar la consulta de carreras", $ex);
            $res->addError("Usuario: $userCode");
        }

        if ($res->get() && count($pensumCodes) > 0) {
            //GETTING GRADES AVERAGE AND CREDITS SUM
            try {
                $dataTable = new TableGateway(['nf' => 'nota_final'], $this->dbAdapter);
                $select = $dataTable->getSql()->select();
                $select->columns([]);
                $select->join(['c' => 'curso_pensum'], 'nf.cod_pensum = c.cod_pensum and nf.cod_curso = c.cod_curso', []);
                $select->join(['p' => 'pensum'], 'c.cod_pensum = p.cod_pensum', ['cod_carrera']);
                //THIS JOIN RESTRICTS AVERAGE AND CREDITS ONLY TO THE ASIGNED PENSUM
                $select->join(['ac' => 'asignacion_carrera'], 'p.cod_pensum = ac.cod_pensum and nf.cod_usuario = ac.cod_usuario', []);
                $select->columns([
                    'average' => new Expression('AVG(nf.nota)'),
                    'credits' => new Expression('SUM(c.creditos)')
                ]);
                $select->where([
                    'nf.cod_usuario' => $userCode,
                    'nf.aprobado' => true,
                    'nf.cod_estado_nota_final <>' . GradesManager::FG_STATUS_DEGRADED_FOR_GRADE_IMPROVEMENT,
                    'nf.cod_pensum' => $pensumCodes
                ]);
                $select->group('p.cod_carrera');
                $result = $dataTable->selectWith($select)->toArray();
                foreach ($result as $groupData) {
                    $careerCode = $groupData['cod_carrera'];
                    $data[$careerCode]['average'] = $groupData['average'];
                    $data[$careerCode]['credits'] = $groupData['credits'];
                }
            } catch (\Exception $ex) {
                $res->failure("No se pudo realizar la consulta de estadísticas", $ex);
                $res->addError("Usuario: $userCode");
            }
        }

        if ($res->get() && count($pensumCodes) > 0) {
            //GETTING COURSES DATA
            try {
                $finalGradeTable = new TableGateway(['nf' => 'nota_final'], $this->dbAdapter);
                $select = $dataTable->getSql()->select();
                $select->join(['enf' => 'estado_nota_final'], 'nf.cod_estado_nota_final = enf.cod_estado_nota_final', ['estado' => 'nombre']);
                $select->join(['tnf' => 'tipo_nota_final'], 'nf.cod_tipo_nota_final = tnf.cod_tipo_nota_final ', ['tipo' => 'nombre']);
                $select->join(['c' => 'curso_pensum'], 'nf.cod_pensum = c.cod_pensum and nf.cod_curso = c.cod_curso');
                $select->join(['p' => 'pensum'], 'c.cod_pensum = p.cod_pensum', ['cod_carrera']);
                $select->where([
                    'nf.cod_usuario' => $userCode,
                ]);
                $select->order('aprobado DESC');
                $select->order('fecha_oficializacion ASC');
                $result = $finalGradeTable->selectWith($select)->toArray();
                foreach ($result as $gradeData) {
                    $careerCode = $gradeData['cod_carrera'];
                    $data[$careerCode]['courses'][] = $gradeData;
                }
                $res->setObj($data);
            } catch (\Exception $ex) {
                $res->failure("No se pudo realizar la consulta de cursos", $ex);
                $res->addError("Usuario: $userCode");
            }
        }
        return $res;
    }

}
