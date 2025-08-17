<?php

namespace Eep\Entity;

class InfoLaboral {

    const DAYS = 'days';

    private $codInfoLaboral;
    private $ubicacion;
    private $horaInicio;
    private $horaFin;
    private $domingo;
    private $lunes;
    private $martes;
    private $miercoles;
    private $jueves;
    private $viernes;
    private $sabado;

    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
    }

    public function exchangeArray($data) {
        //TAKE OUT FROM DAYS INNER FORM DATA
        if (!empty($data[self::DAYS])) {
            foreach ($data[self::DAYS] as $day) {
                $data[$day] = 1; //true //$data['lunes']=true;
            }
        }

        $this->codInfoLaboral = (!empty($data['cod_info_laboral'])) ? $data['cod_info_laboral'] : null;
        $this->ubicacion = (!empty($data['ubicacion'])) ? $data['ubicacion'] : null;
        $this->horaInicio = (!empty($data['hora_inicio'])) ? $data['hora_inicio'] : null;
        $this->horaFin = (!empty($data['hora_fin'])) ? $data['hora_fin'] : null;
        $this->domingo = (isset($data['domingo'])) ? ($data['domingo'] == true) : false;
        $this->lunes = (isset($data['lunes'])) ? ($data['lunes'] == true) : false;
        $this->martes = (isset($data['martes'])) ? ($data['martes'] == true) : false;
        $this->miercoles = (isset($data['miercoles'])) ? ($data['miercoles'] == true) : false;
        $this->jueves = (isset($data['jueves'])) ? ($data['jueves'] == true) : false;
        $this->viernes = (isset($data['viernes'])) ? ($data['viernes'] == true) : false;
        $this->sabado = (isset($data['sabado'])) ? ($data['sabado'] == true) : false;
    }

    public function getCode() {
        return $this->codInfoLaboral;
    }

    public function getUbicacion() {
        return $this->ubicacion;
    }

    public function getHoraInicio() {
        return $this->horaInicio;
    }

    public function getHoraFin() {
        return $this->horaFin;
    }

    public function getDomingo() {
        return $this->domingo;
    }

    public function getLunes() {
        return $this->lunes;
    }

    public function getMartes() {
        return $this->martes;
    }

    public function getMiercoles() {
        return $this->miercoles;
    }

    public function getJueves() {
        return $this->jueves;
    }

    public function getViernes() {
        return $this->viernes;
    }

    public function getSabado() {
        return $this->sabado;
    }

    public function setCode($codInfoLaboral) {
        $this->codInfoLaboral = $codInfoLaboral;
    }

    public function setUbicacion($ubicacion) {
        $this->ubicacion = $ubicacion;
    }

    public function setHoraInicio($horaInicio) {
        $this->horaInicio = $horaInicio;
    }

    public function setHoraFin($horaFin) {
        $this->horaFin = $horaFin;
    }

    public function setDomingo($domingo) {
        $this->domingo = $domingo;
    }

    public function setLunes($lunes) {
        $this->lunes = $lunes;
    }

    public function setMartes($martes) {
        $this->martes = $martes;
    }

    public function setMiercoles($miercoles) {
        $this->miercoles = $miercoles;
    }

    public function setJueves($jueves) {
        $this->jueves = $jueves;
    }

    public function setViernes($viernes) {
        $this->viernes = $viernes;
    }

    public function setSabado($sabado) {
        $this->sabado = $sabado;
    }

}
