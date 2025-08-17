<?php

namespace Eep\Service;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Session\SessionManager;
use Eep\Entity\Role;
use Eep\ValueObject\Menu;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;

/**
 * The AuthManager service is responsible for user's login/logout and simple access 
 * filtering. The access filtering feature checks whether the current visitor 
 * is allowed to see the given page or not.  
 */
class AuthManager extends Manager {

    private $authService;
    private $sessionManager;
    private $config;

    public function __construct(AuthenticationService $authService, SessionManager $sessionManager, $config, $dbAdapter) {
        $this->authService = $authService;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
        $this->dbAdapter = $dbAdapter;
    }

    public function login($id, $password, $rememberMe) {
        //IF SESSION HAS ALREADY BEEN LOGGED IN
        if ($this->authService->getIdentity() != null) {
            throw new \Exception('La sesión ya ha sido iniciada');
        }

        //AUTHENTICATE WITH THE ID AND PASSWORD
        $authAdapter = $this->authService->getAdapter();
        $authAdapter->setId($id);
        $authAdapter->setPassword($password);
        $result = $this->authService->authenticate();

        //EXTENDING EXPIRING TIME (global.conf CONFIGURED TO 1 HOUR) TO 1 MONTH
        if ($result->getCode() == Result::SUCCESS) {

            //CHECKING AUTHORIZATION ACCESS
            $roleResult = $this->getUserRole($result->getIdentity());
            if ($roleResult->get() == false) {
                $result = new Result(Result::FAILURE, null, [implode('; ', $roleResult->getMsg())]);
                $this->authService->clearIdentity();
            } else {
                $role = $roleResult->getObj();
//IF USER HAS NEVER BEEN AN STUDENT AND HIS ADMINISTRATIVE ROLE IS OVER, HE IS NOT ALLOWED
                if (!$role->hasRole()) {
                    $result = new Result(Result::FAILURE, null, ['Usted no está autorizado a ingresar; no cuenta con un rol válido actualmente en el sistema.']);
                    $this->authService->clearIdentity();
                } else {
                    //COOKIE EXPIRING IN 30 DAYS (60 SECONDS * 60 MINUTES * 24 HOURS * 30 DAYS)
                    if ($rememberMe) {
                        $this->sessionManager->rememberMe(60* 60 * 60 * 24 * 30);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Performs user logout.
     */
    public function logout() {
        //LOGOUT WHEN THE USER IS LOGGED IN
        if ($this->authService->getIdentity() == null) {
            throw new \Exception('El usuario no ha iniciado sesión');
        }
        //REMOVING THE USER ID (IDENTITY) FROM SESSION
        $this->authService->clearIdentity();
    }

//    private function getConfig() {
//        return var_export($this->config, true);
//    }

    public function hasAccess($role, $controllerName, $actionName) {
        if (!isset($this->config['access_filter'][$controllerName][$actionName]['roles'])) {
            return false; //NO FILTERS, SO NOBODY IS AUTHORIZED
        } else {
            $roles = $this->config['access_filter'][$controllerName][$actionName]['roles'];
            //['view' => View::AVAILABLE_COURSES, 'roles' => [Role::AUTH]]
            return $this->isRoleAuth($roles, $role);
        }
    }

    public function getAuthMenus($role, $controllerName, $actionName) {
        $menus = [];
        if (!isset($this->config['menus'])) {
            //IF MENUS ARE NOT CONFIGURED, ONLY PROFILE MENU WILL BE AVAILABLE
            array_push($menus, new Menu('fa fa-search', 'Mi pefil', 'user', 'profile'));
        } else {
            //GETTING USER ROLE
            $view = isset($this->config['access_filter'][$controllerName][$actionName]['view']) ? $this->config['access_filter'][$controllerName][$actionName]['view'] : '';
            //$controllerName = strtolower(str_replace('Controller', '', substr($controllerName, strrpos($controllerName, '\\', -1) + 1)));
            foreach ($this->config['menus'] as $key => $value) {
                if (isset($value['roles'])) {
                    $roles = $value['roles'];
                    //IF USER CAN SEE THE MENU, ADD TO MENUS STACK
                    if ($this->isRoleAuth($roles, $role)) {
                        $menu = new Menu($value['icon'], $value['text'], $value['controller'], $value['action'], $key == $view);
                        array_push($menus, $menu);
                    }
                }
            }
        }
        return $menus;
    }

    private function isRoleAuth($roles, $role) {
        if (in_array(Role::ALL, $roles)) {
            return true;
        } elseif (in_array(Role::NO_AUTH, $roles) && ($role == null || !$role->hasRole())) {
            return true;
        } elseif ($role != null) {
            if (in_array(Role::AUTH, $roles) && $role->hasRole()) {
                return true;
            }
            foreach ($roles as $roleValue) {
                if ($role->hasRole($roleValue)) {
                    return true;
                }
            }
        }
        //NO RULES SPECIFIED, THEN THE USER HAS NO ACCESS
        return false;
    }

    /*
     * THIS FUNCTION RETURNS THE ACTUAL USER ROLE
     */

    public function getUserRole($id): R {
        $res = new R();
        //JOIN USER AND ROLE WHERE THE USER HAS $id ID, GETTING ONLY THE ROLE NAME 'rol' THAT THE USER HAS
        try {

            $roleTable = new TableGateway(['ur' => 'usuario_rol'], $this->dbAdapter);
            $select = $roleTable->getSql()->select();
            $select->columns(['rol' => 'cod_rol'])
                    ->where(['cod_usuario' => $id])
                    ->where('fecha_inicio <= curdate()')
                    ->where('(fecha_fin >= curdate() OR fecha_fin is NULL)');
            $roles = $roleTable->selectWith($select)->toArray();
            $role = new Role($roles);
            $res->success();
            $res->setObj($role);
        } catch (\Exception $ex) {
            $res->failure('No se pudo consultar el rol del usuario');
            $res->addError($ex);
            $res->setObj(new Role());
        }
        return $res;
    }

    /*
     * THIS FUNCTION RETURNS ALL THE USER ROLES
     */

    public function getUserRoles($userCode) {
        $roleTable = new TableGateway(['ur' => 'usuario_rol'], $this->dbAdapter);
        $date = date('Y-m-d');
        $result = $roleTable->select([
                    'cod_usuario' => $userCode,
                    "(fecha_fin >= '$date' or fecha_fin is NULL)"
                ])->toArray();
        $roles = [];
        foreach ($result as $roleData) {
            $roles[] = new Role([$roleData]); //MADE AN ARRAY AGAIN BECAUSE THAT'S THE EXCHANGE_DATA BEHAVIOR FOR ROLE
        }
        return $roles;
    }

    public function getRole($roleCode) {
        $res = new R();
        $table = new TableGateway(['ur' => 'usuario_rol'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->where([
            'cod_usuario_rol' => $roleCode
        ]);
        $select->join(['u' => 'usuario'], 'ur.cod_usuario = u.cod_usuario', ['cui', 'pasaporte','registro_academico']);
        try {
            $result = $table->selectWith($select);
            if ($result->count() == 0) {
                $res->addMsg('Rol no encontrado');
            } else {
                $res->success();
                $res->setObj($result->current());
            }
        } catch (InvalidQueryException $exc) {
            echo $exc->getTraceAsString();
        }

        return $res;
    }

    public function addUserRole($userCode, $roleCode, $finishDate = null, $startDate = null) {
        $response = new R();
        $initialStartDate = $startDate;
        if (empty($startDate)) {
            $startDate = date('Y-m-d');
        }
        $roleTable = new TableGateway('usuario_rol', $this->dbAdapter);
        //CHECKING IF USER ROLE ALREADY EXISTS
        $result = $roleTable->select([
            'cod_usuario' => $userCode,
            "fecha_inicio <= '$startDate'",
            empty($finishDate) ? "fecha_fin is NULL" : "(fecha_fin >= '$finishDate' or fecha_fin is NULL)",
            'cod_rol' => $roleCode
        ]);
        if ($result->count() > 0) {
            $response->success('El usuario cuenta ya con un rol inclusivo al indicado.');
        } else {
            $set = [
                'fecha_inicio' => $startDate,
                'fecha_fin' => $finishDate,
                'cod_rol' => $roleCode,
                'cod_usuario' => $userCode
            ];
            try {
                $roleTable->insert($set);
                $response->success('Rol de usuario agregado satisfactoriamente.');
            } catch (InvalidQueryException $ex) {
                if ($initialStartDate == null) {
                    $startDate = "Hoy";
                }
                $response->addMsg("No se pudo agregar el rol '" .
                        Role::getStr($roleCode) . "' ('$initialStartDate'-'" .
                        (empty($finishDate) ? 'indefinido' : $finishDate) . "') al usuario $userCode " . $ex->getMessage());
            }
        }
        return $response;
    }

    public function getRoles() {
        $rolesTable = new TableGateway('rol', $this->dbAdapter);
        return $rolesTable->select()->toArray();
    }

    public function deleteRole($roleUserCode) {
        $res = new R();
        $rolesTable = new TableGateway('usuario_rol', $this->dbAdapter);
        try {
            $result = $rolesTable->delete([
                'cod_usuario_rol' => $roleUserCode
            ]);
            if ($result > 0) {
                $res->success();
            } else {
                $res->failure("No existe el código de rol indicado ($roleUserCode)");
            }
        } catch (InvalidQueryException $ex) {
            $res->addMsg('No se pudo eliminar el rol del usuario.');
        }
        return $res;
    }

    public function updateRole($role) {
        $res = new R();
        if ($role == null) {
            $res->addMsg('No se obtuvo un rol para actualizar.');
            return $res;
        }
        //VALIDATING FIELDS
        if (empty($role->getStartDate())) {
            $res->addMsg('El rol no tiene fecha de inicio y es un requisito tenerlo.');
            return $res;
        }
        if (empty($role->getCode())) {
            $res->addMsg('El rol no tiene el rol que se asignará.');
            return $res;
        }
        if (empty($role->getUserRoleCode())) {
            $res->addMsg('El rol no tiene el identificador de usuario rol.');
            return $res;
        }
        $table = new TableGateway('usuario_rol', $this->dbAdapter);
        try {
            $table->update([
                'fecha_inicio' => $role->getStartDate(),
                'cod_rol' => $role->getCode(),
                'fecha_fin' => empty($role->getFinishDate()) ? null : $role->getFinishDate() //NULLABLE
                    ], [
                'cod_usuario_rol' => $role->getUserRoleCode()
            ]);
            $res->success();
        } catch (InvalidQueryException $ex) {
            $res->addMsg('No se pudo actualizar el rol');
        }
        return $res;
    }

    public function getUserRoleCodeInfo($userRoleCode): R {
        $res = new R();
        $table = new TableGateway(['ur' => 'usuario_rol'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['u' => 'usuario'], 'ur.cod_usuario = u.cod_usuario');
        $select->join(['r' => 'rol'], 'ur.cod_rol = r.cod_rol', ['rol' => 'nombre', 'cod_rol']);
        $select->where([
            'cod_usuario_rol' => $userRoleCode
        ]);
        try {
            $result = $table->selectWith($select);
            if ($result->count() == 0) {
                $res->failure("Código de Rol de Usuario \"$userRoleCode\" no encontrado");
            } else {
                $res->success();
                $res->setObj($result->current());
            }
        } catch (\Exception $ex) {
            $res->addMsg("No se pudo buscar información sobre el código de rol \"$userRoleCode\"" . $ex->getMessage());
        }
        return $res;
    }

}
