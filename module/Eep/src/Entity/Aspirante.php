<?php

namespace Eep\Entity;

class Aspirante {
    
    private $id;
    private $cui;
    private $photoDpi;
    private $nombres;
    private $apellidos;
    private $correoElectronico;
    private $telefono;
    
    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
    }
    
    public function exchangeArray($data) {
        $this->id = (!empty($data['id'])) ? $data['id'] : null;
        $this->cui = (!empty($data['cui'])) ? $data['cui'] : null;
        $this->photoDpi = (!empty($data['photo_dpi'])) ? $data['photo_dpi'] : null;
        $this->nombres = (!empty($data['nombres'])) ? $data['nombres'] : null;
        $this->apellidos = (!empty($data['apellidos'])) ? $data['apellidos'] : null;
        $this->correoElectronico = (!empty($data['correo_electronico'])) ? $data['correo_electronico'] : null;
        $this->telefono = (!empty($data['telefono'])) ? $data['telefono'] : null;
    }
    
    public function getArrayCopy() {
        return [
            'id' => $this->id,
            'cui' => $this->cui,
            'photo_dpi' => $this->photoDpi,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'correo_electronico' => $this->correoElectronico,
            'telefono' => $this->telefono
        ];
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getCui() { return $this->cui; }
    public function getPhotoDpi() { return $this->photoDpi; }
    public function getNombres() { return $this->nombres; }
    public function getApellidos() { return $this->apellidos; }
    public function getCorreoElectronico() { return $this->correoElectronico; }
    public function getTelefono() { return $this->telefono; }
    
    // Setters
    public function setId($value) { $this->id = $value; }
    public function setCui($value) { $this->cui = $value; }
    public function setPhotoDpi($value) { $this->photoDpi = $value; }
    public function setNombres($value) { $this->nombres = $value; }
    public function setApellidos($value) { $this->apellidos = $value; }
    public function setCorreoElectronico($value) { $this->correoElectronico = $value; }
    public function setTelefono($value) { $this->telefono = $value; }
    
    // Métodos de utilidad
    public function getNombreCompleto() {
        return trim($this->nombres . ' ' . $this->apellidos);
    }
    
    public function hasPhotoDpi() {
        return !empty($this->photoDpi);
    }
    
    public function getPhotoDpiUrl() {
        if ($this->hasPhotoDpi()) {
            return '/uploads/dpi/' . $this->photoDpi;
        }
        return null;
    }
}
