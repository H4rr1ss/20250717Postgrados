<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Eep\Entity;

class Role {

    private $estudiante = false;
    private $tesorero = false;
    private $asistente = false;
    private $director = false;
    private $catedratico = false;
    private $coordinador = false;
    private $programador = false;
    private $udicaProgramador = false;
    private $udicaJefe = false;
    private $udicaOperador = false;

    //DO NOT USE "0" ROLLE, BECAUSE IT EQUALLS NULL IN SWITCH BELLOW STATEMENT
    const DIRECTOR = 1;
    const ASISTENTE = 2;
    const TESORERO = 3;
    const COORDINADOR = 4;
    const CATEDRATICO = 5;
    const ESTUDIANTE = 6;
    const PROGRAMADOR = 7;
    const UDICA_PROGRAMADOR = 8;
    const UDICA_JEFE = 9;
    const UDICA_OPERADOR = 10;
    //WEB_SITE ROLES
    const AUTH = -1; //LOGGED IN USERS
    const NO_AUTH = -2; //NOT AUTHENTICATED USERS = NOT LOGGED IN
    const ALL = -3;
    const STR = [
        self::DIRECTOR => 'Director',
        self::ASISTENTE => 'Asistente',
        self::TESORERO => 'Tesorero',
        self::COORDINADOR => 'Coordinador',
        self::CATEDRATICO => 'Catedrático',
        self::ESTUDIANTE => 'Estudiante',
        self::PROGRAMADOR => 'Programador',
        self::UDICA_PROGRAMADOR => 'Programador de la UDICA',
        self::UDICA_JEFE => 'Jefe de la UDICA',
        self::UDICA_OPERADOR => 'Operador de la UDICA'
    ];

    private $startDate;
    private $finishDate;
    private $userRoleCode;
    private $userCode;
    private $userCuiPassport;

    public function __construct($data = null) {
        $this->exchangeArray($data);
    }

    public function exchangeArray($data) {
        if ($data != null && is_array($data)) {
            foreach ($data as $role) {
                $rol = $role['rol'] ?? $role['cod_rol']; //(!empty($role['rol'])) ? $role['rol'] :(!empty($role['cod_rol']))? $role['cod_rol'] : 0; //0 = NULL
                switch ($rol) {
                    case self::ESTUDIANTE:
                        $this->estudiante = true;
                        break;
                    case self::TESORERO:
                        $this->tesorero = true;
                        break;
                    case self::ASISTENTE:
                        $this->asistente = true;
                        break;
                    case self::DIRECTOR:
                        $this->director = true;
                        break;
                    case self::CATEDRATICO:
                        $this->catedratico = true;
                        break;
                    case self::COORDINADOR:
                        $this->coordinador = true;
                        break;
                    case self::PROGRAMADOR:
                        $this->programador = true;
                        break;
                    case self::UDICA_PROGRAMADOR:
                        $this->udicaProgramador = true;
                        break;
                    case self::UDICA_JEFE:
                        $this->udicaJefe = true;
                        break;
                    case self::UDICA_OPERADOR:
                        $this->udicaOperador = true;
                        break;
                    default:
                        break;
                }
            }
            $this->startDate = $role['fecha_inicio'] ?? $this->startDate;
            $this->finishDate = !empty($role['fecha_fin']) ? $role['fecha_fin'] : $this->finishDate;
            $this->userRoleCode = $role['cod_usuario_rol'] ?? $this->userRoleCode;
            $this->userCode = $role['cod_usuario'] ?? $this->userCode;
            $this->userCuiPassport = $role['cui'] ?? ($role['pasaporte'] ?? $this->userCuiPassport);
        }
    }

    public function isEstudiante() {
        return $this->estudiante;
    }

    public function isTesorero() {
        return $this->tesorero;
    }

    public function isAsistente() {
        return $this->asistente;
    }

    public function isDirector() {
        return $this->director;
    }

    public function isCatedratico() {
        return $this->catedratico;
    }

    public function isCoordinador() {
        return $this->coordinador;
    }

    public function isProgramador() {
        return $this->programador;
    }

    public function isUdicaProgramador() {
        return $this->udicaProgramador;
    }

    public function isUdicaJefe() {
        return $this->udicaJefe;
    }

    public function isUdicaOperador() {
        return $this->udicaOperador;
    }

    public function hasAdminRole() {
        return ($this->tesorero || $this->asistente || $this->director || $this->programador);
    }

    public function hasUdicaRole() {
        return ($this->udicaJefe || $this->udicaOperador || $this->udicaProgramador);
    }

    public function hasRole($type = null) {
        if ($type == null) {
            return ($this->estudiante || $this->tesorero || $this->asistente || $this->director || $this->catedratico || $this->coordinador || $this->programador || $this->udicaProgramador || $this->udicaJefe || $this->udicaOperador);
        } else {
            switch ($type) {
                case self::ESTUDIANTE:
                    return $this->estudiante;
                case self::TESORERO:
                    return $this->tesorero;
                case self::ASISTENTE:
                    return $this->asistente;
                case self::DIRECTOR:
                    return $this->director;
                case self::CATEDRATICO:
                    return $this->catedratico;
                case self::COORDINADOR:
                    return $this->coordinador;
                case self::ESTUDIANTE:
                    return $this->estudiante;
                case self::PROGRAMADOR:
                    return $this->programador;
                case self::UDICA_PROGRAMADOR:
                    return $this->udicaProgramador;
                case self::UDICA_JEFE:
                    return $this->udicaJefe;
                case self::UDICA_OPERADOR:
                    return $this->udicaOperador;
                default:
                    return false;
            }
        }
    }

    public function getUserType() {
        //IF THE USER HAS ANY ADMINISTRATIVE ROLE (AT LEAST A ROLE THAT'S NOT STUDENT), IT'S ADMINISTRATIVE USER.
        return ($this->hasRole() && $this->estudiante == false) ? 'admin' : 'student';
    }

    function getStartDate() {
        return $this->startDate;
    }

    function getFinishDate() {
        return $this->finishDate;
    }

    function getUserRoleCode() {
        return $this->userRoleCode;
    }

    function getUserCode() {
        return $this->userCode;
    }

    function getUserCuiPassport() {
        return $this->userCuiPassport;
    }

    function setUserCode($userCode) {
        $this->userCode = $userCode;
    }

    function setUserCuiPassport($userCuiPassport) {
        $this->userCuiPassport = $userCuiPassport;
    }

    function setUserRoleCode($userRoleCode) {
        $this->userRoleCode = $userRoleCode;
    }

    function setStartDate($startTime) {
        $this->startDate = $startTime;
    }

    function setFinishDate($finishTime) {
        $this->finishDate = $finishTime;
    }

    public function getCode() {
        //THIS ORDER DEFINES PRECEDENCE WHEN ONLY ONE ROLE IS REQUIRED AND THE USER HAS MANY OF THEM
        if ($this->programador) {
            return self::PROGRAMADOR;
        }
        if ($this->udicaJefe) {
            return self::UDICA_JEFE;
        }
        if ($this->udicaProgramador) {
            return self::UDICA_PROGRAMADOR;
        }
        if ($this->udicaOperador) {
            return self::UDICA_OPERADOR;
        }
        if ($this->director) {
            return self::DIRECTOR;
        }
        if ($this->tesorero) {
            return self::TESORERO;
        }
        if ($this->asistente) {
            return self::ASISTENTE;
        }
        if ($this->coordinador) {
            return self::COORDINADOR;
        }
        if ($this->catedratico) {
            return self::CATEDRATICO;
        }
        if ($this->estudiante) {
            return self::ESTUDIANTE;
        }
    }

    public static function getStr($code) {
        if (isset(self::STR[$code])) {
            return self::STR[$code];
        } else {
            return "(desconocido)";
        }
    }

}
