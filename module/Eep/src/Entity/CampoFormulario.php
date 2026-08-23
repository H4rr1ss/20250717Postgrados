<?php

namespace Eep\Entity;

class CampoFormulario {

    private $idCampo;
    private $nombreCampo;
    private $etiqueta;
    private $tipoCampo;
    private $opciones;
    private $requerido;
    private $ordenCampo;
    private $seccion;

    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
    }

    public function exchangeArray($data) {
        $this->idCampo = (!empty($data['id_campo'])) ? $data['id_campo'] : null;
        $this->nombreCampo = (!empty($data['nombre_campo'])) ? $data['nombre_campo'] : null;
        $this->etiqueta = (!empty($data['etiqueta'])) ? $data['etiqueta'] : null;
        $this->tipoCampo = (!empty($data['tipo_campo'])) ? $data['tipo_campo'] : null;
        $this->opciones = (!empty($data['opciones'])) ? $data['opciones'] : null;
        $this->requerido = isset($data['requerido']) ? (bool)$data['requerido'] : false;
        $this->ordenCampo = (!empty($data['orden_campo'])) ? (int)$data['orden_campo'] : 0;
        $this->seccion = (!empty($data['seccion'])) ? $data['seccion'] : 'adicional';
    }

    public function getArrayCopy() {
        return [
            'id_campo' => $this->idCampo,
            'nombre_campo' => $this->nombreCampo,
            'etiqueta' => $this->etiqueta,
            'tipo_campo' => $this->tipoCampo,
            'opciones' => $this->opciones,
            'requerido' => $this->requerido,
            'orden_campo' => $this->ordenCampo,
            'seccion' => $this->seccion,
        ];
    }

    // Getters
    public function getIdCampo() { return $this->idCampo; }
    public function getNombreCampo() { return $this->nombreCampo; }
    public function getEtiqueta() { return $this->etiqueta; }
    public function getTipoCampo() { return $this->tipoCampo; }
    public function getOpciones() { return $this->opciones; }
    public function getRequerido() { return $this->requerido; }
    public function getOrdenCampo() { return $this->ordenCampo; }
    public function getSeccion() { return $this->seccion; }

    // Setters
    public function setIdCampo($value) { $this->idCampo = $value; }
    public function setNombreCampo($value) { $this->nombreCampo = $value; }
    public function setEtiqueta($value) { $this->etiqueta = $value; }
    public function setTipoCampo($value) { $this->tipoCampo = $value; }
    public function setOpciones($value) { $this->opciones = $value; }
    public function setRequerido($value) { $this->requerido = (bool)$value; }
    public function setOrdenCampo($value) { $this->ordenCampo = (int)$value; }
    public function setSeccion($value) { $this->seccion = $value; }

    // Métodos de utilidad
    public function isRequerido() {
        return $this->requerido;
    }

    public function getOpcionesArray() {
        if ($this->opciones && $this->tipoCampo === 'select') {
            return explode('|', $this->opciones);
        }
        return [];
    }

    public function getOpcionesSelect() {
        $opciones = ['' => 'Seleccione una opción'];
        foreach ($this->getOpcionesArray() as $opcion) {
            $opciones[trim($opcion)] = trim($opcion);
        }
        return $opciones;
    }

    public function isFileType() {
        return $this->tipoCampo === 'archivo';
    }

    public function isBooleanType() {
        return $this->tipoCampo === 'boolean';
    }

    public function isSelectType() {
        return $this->tipoCampo === 'select';
    }

    public function isMultiCheckboxType() {
        return $this->tipoCampo === 'multicheckbox';
    }
}
