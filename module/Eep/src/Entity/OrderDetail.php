<?php

namespace Eep\Entity;

class OrderDetail {

    private $rubro;
    private $fechaInicio;
    private $variante;
    private $anio;
    private $codCurso;
    private $seccion;
    private $subtotal;
    private $codHorario;
    private $nombreCurso;
    private $asignacionEfectuada;

    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
    }

    public function exchangeArray($data) {
        $this->codHorario = $data['cod_horario'] ?? $this->codHorario;
        $this->rubro = $data['rubro'] ?? $this->rubro;
        $this->variante = $data['variante'] ?? $this->variante;
        $this->fechaInicio = $data['fecha_inicio'] ?? $this->fechaInicio;
        $this->anio = $data['fecha_inicio'] ? (date('Y', strtotime($data['fecha_inicio']))) : $this->anio;
        $this->codCurso = $data['cod_curso'] ?? $this->codCurso;
        $this->seccion = $data['seccion'] ?? $this->seccion;
        $this->subtotal = $data['monto'] ?? ($data['subtotal'] ?? $this->subtotal);
        $this->nombreCurso = $data['nombre_curso'] ?? $this->nombreCurso;
        $this->asignacionEfectuada = $data['asignacion_efectuada'] ?? $this->asignacionEfectuada;
    }

    function getRubro() {
        return $this->rubro;
    }

    function getVariante() {
        return $this->variante;
    }

    function getAnio() {
        return $this->anio;
    }

    function getCodCurso() {
        return $this->codCurso;
    }

    function getSeccion() {
        return $this->seccion;
    }

    function getSubtotal() {
        return $this->subtotal;
    }

    function getCodHorario() {
        return $this->codHorario;
    }

    function getNombreCurso() {
        return $this->nombreCurso;
    }

    function getFechaInicio() {
        return $this->fechaInicio;
    }
    
    function getAsignacionEfectuada() {
        return $this->asignacionEfectuada;
    }

    function setAsignacionEfectuada($asignacionEfectuada) {
        $this->asignacionEfectuada = $asignacionEfectuada;
    }

    function setFechaInicio($fechaInicio) {
        $this->fechaInicio = $fechaInicio;
    }

    function setNombreCurso($nombreCurso) {
        $this->nombreCurso = $nombreCurso;
    }

    function setCodHorario($codHorario) {
        $this->codHorario = $codHorario;
    }

    function setRubro($rubro) {
        $this->rubro = $rubro;
    }

    function setVariante($variante) {
        $this->variante = $variante;
    }

    function setAnio($anio) {
        $this->anio = $anio;
    }

    function setCodCurso($codCurso) {
        $this->codCurso = $codCurso;
    }

    function setSeccion($seccion) {
        $this->seccion = $seccion;
    }

    function setSubtotal($subtotal) {
        $this->subtotal = $subtotal;
    }

}
