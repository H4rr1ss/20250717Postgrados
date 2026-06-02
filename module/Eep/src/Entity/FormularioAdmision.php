<?php

namespace Eep\Entity;

class FormularioAdmision {
    
    private $idFormulario;
    private $nombre;
    private $fechaCreacion;
    private $fechaInicioAdmision;
    private $fechaFinAdmision;
    private $activo;
    private $creadoPor;
    private $totalRespuestas; // Campo calculado
    
    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
    }
    
    public function exchangeArray($data) {
    $this->idFormulario = (!empty($data['id_formulario'])) ? $data['id_formulario'] : null;
    $this->nombre = (!empty($data['nombre'])) ? $data['nombre'] : null;
    $this->fechaCreacion = (!empty($data['fecha_creacion'])) ? $data['fecha_creacion'] : null;
    $this->fechaInicioAdmision = (!empty($data['fecha_inicio_admision'])) ? $data['fecha_inicio_admision'] : null;
    $this->fechaFinAdmision = (!empty($data['fecha_fin_admision'])) ? $data['fecha_fin_admision'] : null;
    $this->activo = isset($data['activo']) ? (int)$data['activo'] : 1;
    $this->creadoPor = (!empty($data['creado_por'])) ? $data['creado_por'] : null;
    $this->totalRespuestas = (!empty($data['total_respuestas'])) ? (int)$data['total_respuestas'] : 0;
    }
    
    public function getArrayCopy() {
        return [
            'id_formulario' => $this->idFormulario,
            'nombre' => $this->nombre,
            'fecha_creacion' => $this->fechaCreacion,
            'fecha_inicio_admision' => $this->fechaInicioAdmision,
            'fecha_fin_admision' => $this->fechaFinAdmision,
            'activo' => $this->activo,
            'creado_por' => $this->creadoPor,
            'total_respuestas' => $this->totalRespuestas
        ];
    }
    
    // Getters
    public function getIdFormulario() { return $this->idFormulario; }
    public function getNombre() { return $this->nombre; }
    public function getFechaCreacion() { return $this->fechaCreacion; }
    public function getFechaInicioAdmision() { return $this->fechaInicioAdmision; }
    public function getFechaFinAdmision() { return $this->fechaFinAdmision; }
    public function getActivo() { return $this->activo; }
    public function getCreadoPor() { return $this->creadoPor; }
    public function getTotalRespuestas() { return $this->totalRespuestas; }
    
    // Setters
    public function setIdFormulario($value) { $this->idFormulario = $value; }
    public function setNombre($value) { $this->nombre = $value; }
    public function setFechaCreacion($value) { $this->fechaCreacion = $value; }
    public function setFechaInicioAdmision($value) { $this->fechaInicioAdmision = $value; }
    public function setFechaFinAdmision($value) { $this->fechaFinAdmision = $value; }
    public function setActivo($value) { $this->activo = (bool)$value; }
    public function setCreadoPor($value) { $this->creadoPor = $value; }
    public function setTotalRespuestas($value) { $this->totalRespuestas = (int)$value; }
    
    // Métodos de utilidad
    public function isActivo() {
        return $this->activo;
    }
    
    public function isEnPeriodoAdmision() {
        $now = new \DateTime();
        $inicio = new \DateTime($this->fechaInicioAdmision);
        $fin = new \DateTime($this->fechaFinAdmision);
        
        return $now >= $inicio && $now <= $fin;
    }
    
    
    public function getFechaCreacionFormateada() {
        if ($this->fechaCreacion) {
            return date('d/m/Y H:i', strtotime($this->fechaCreacion));
        }
        return null;
    }
    
    public function getPeriodoFormateado() {
        if ($this->fechaInicioAdmision && $this->fechaFinAdmision) {
            $inicio = date('d/m/Y', strtotime($this->fechaInicioAdmision));
            $fin = date('d/m/Y', strtotime($this->fechaFinAdmision));
            return "$inicio - $fin";
        }
        return null;
    }
}
