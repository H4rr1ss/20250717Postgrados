<?php

namespace Eep\Entity;

class RespuestaAspirante {
    
    private $idRespuesta;
    private $idFormulario;
    private $aspiranteId;
    private $fechaEnvio;
    private $aspirante; // Objeto Aspirante
    private $respuestasCampos; // Array de respuestas por campo
    
    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
        $this->respuestasCampos = [];
    }
    
    public function exchangeArray($data) {
        $this->idRespuesta = (!empty($data['id_respuesta'])) ? $data['id_respuesta'] : null;
        $this->idFormulario = (!empty($data['id_formulario'])) ? $data['id_formulario'] : null;
        $this->aspiranteId = (!empty($data['aspirante_id'])) ? $data['aspirante_id'] : null;
        $this->fechaEnvio = (!empty($data['fecha_envio'])) ? $data['fecha_envio'] : null;
        
        // Si viene datos del aspirante en el array, crear el objeto
        if (isset($data['aspirante_nombres'])) {
            $aspiranteData = [
                'id' => $this->aspiranteId,
                'cui' => $data['aspirante_cui'] ?? null,
                'photo_dpi' => $data['aspirante_photo_dpi'] ?? null,
                'nombres' => $data['aspirante_nombres'] ?? null,
                'apellidos' => $data['aspirante_apellidos'] ?? null,
                'correo_electronico' => $data['aspirante_correo_electronico'] ?? null,
                'telefono' => $data['aspirante_telefono'] ?? null
            ];
            $this->aspirante = new Aspirante($aspiranteData);
        }
    }
    
    public function getArrayCopy() {
        return [
            'id_respuesta' => $this->idRespuesta,
            'id_formulario' => $this->idFormulario,
            'aspirante_id' => $this->aspiranteId,
            'fecha_envio' => $this->fechaEnvio
        ];
    }
    
    // Getters
    public function getIdRespuesta() { return $this->idRespuesta; }
    public function getIdFormulario() { return $this->idFormulario; }
    public function getAspiranteId() { return $this->aspiranteId; }
    public function getFechaEnvio() { return $this->fechaEnvio; }
    public function getAspirante() { return $this->aspirante; }
    public function getRespuestasCampos() { return $this->respuestasCampos; }
    
    // Setters
    public function setIdRespuesta($value) { $this->idRespuesta = $value; }
    public function setIdFormulario($value) { $this->idFormulario = $value; }
    public function setAspiranteId($value) { $this->aspiranteId = $value; }
    public function setFechaEnvio($value) { $this->fechaEnvio = $value; }
    public function setAspirante($aspirante) { $this->aspirante = $aspirante; }
    public function setRespuestasCampos($respuestas) { $this->respuestasCampos = $respuestas; }
    
    // Métodos de utilidad
    public function getFechaEnvioFormateada() {
        if ($this->fechaEnvio) {
            return date('d/m/Y H:i', strtotime($this->fechaEnvio));
        }
        return null;
    }
    
    public function getNombreCompletoAspirante() {
        if ($this->aspirante) {
            return $this->aspirante->getNombreCompleto();
        }
        return null;
    }
    
    public function getCuiAspirante() {
        if ($this->aspirante) {
            return $this->aspirante->getCui();
        }
        return null;
    }
    
    public function getCorreoAspirante() {
        if ($this->aspirante) {
            return $this->aspirante->getCorreoElectronico();
        }
        return null;
    }
    
    public function getTelefonoAspirante() {
        if ($this->aspirante) {
            return $this->aspirante->getTelefono();
        }
        return null;
    }
    
    public function addRespuestaCampo($respuestaCampo) {
        $this->respuestasCampos[] = $respuestaCampo;
    }
    
    public function getRespuestaPorCampo($nombreCampo) {
        foreach ($this->respuestasCampos as $respuesta) {
            if ($respuesta['nombre_campo'] === $nombreCampo) {
                return $respuesta['valor_respuesta'];
            }
        }
        return null;
    }
}
