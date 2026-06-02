<?php

namespace Eep\Entity;

class RespuestaAspirante {

    private $idRespuesta;
    private $idFormulario;
    private $fechaEnvio;
    private $respuestasCampos;

    // Datos extraidos directamente de respuesta_campo para facil acceso en vistas
    private $aspiranteCui;
    private $aspiranteNombres;
    private $aspiranteApellidos;
    private $aspiranteCorreoElectronico;
    private $aspiranteTelefono;
    private $aspirantePhotoDpi;

    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
        $this->respuestasCampos = [];
    }

    public function exchangeArray($data) {
        $this->idRespuesta = (!empty($data['id_respuesta'])) ? $data['id_respuesta'] : null;
        $this->idFormulario = (!empty($data['id_formulario'])) ? $data['id_formulario'] : null;
        $this->fechaEnvio = (!empty($data['fecha_envio'])) ? $data['fecha_envio'] : null;

        $this->aspiranteCui = (!empty($data['aspirante_cui'])) ? $data['aspirante_cui'] : null;
        $this->aspiranteNombres = (!empty($data['aspirante_nombres'])) ? $data['aspirante_nombres'] : null;
        $this->aspiranteApellidos = (!empty($data['aspirante_apellidos'])) ? $data['aspirante_apellidos'] : null;
        $this->aspiranteCorreoElectronico = (!empty($data['aspirante_correo_electronico'])) ? $data['aspirante_correo_electronico'] : null;
        $this->aspiranteTelefono = (!empty($data['aspirante_telefono'])) ? $data['aspirante_telefono'] : null;
        $this->aspirantePhotoDpi = (!empty($data['aspirante_photo_dpi'])) ? $data['aspirante_photo_dpi'] : null;
    }

    public function getArrayCopy() {
        return [
            'id_respuesta' => $this->idRespuesta,
            'id_formulario' => $this->idFormulario,
            'fecha_envio' => $this->fechaEnvio,
            'aspirante_cui' => $this->aspiranteCui,
            'aspirante_nombres' => $this->aspiranteNombres,
            'aspirante_apellidos' => $this->aspiranteApellidos,
            'aspirante_correo_electronico' => $this->aspiranteCorreoElectronico,
            'aspirante_telefono' => $this->aspiranteTelefono,
            'aspirante_photo_dpi' => $this->aspirantePhotoDpi,
        ];
    }

    // Getters
    public function getIdRespuesta() { return $this->idRespuesta; }
    public function getIdFormulario() { return $this->idFormulario; }
    public function getFechaEnvio() { return $this->fechaEnvio; }
    public function getRespuestasCampos() { return $this->respuestasCampos; }

    public function getAspiranteCui() { return $this->aspiranteCui; }
    public function getAspiranteNombres() { return $this->aspiranteNombres; }
    public function getAspiranteApellidos() { return $this->aspiranteApellidos; }
    public function getAspiranteCorreoElectronico() { return $this->aspiranteCorreoElectronico; }
    public function getAspiranteTelefono() { return $this->aspiranteTelefono; }
    public function getAspirantePhotoDpi() { return $this->aspirantePhotoDpi; }

    // Setters
    public function setIdRespuesta($value) { $this->idRespuesta = $value; }
    public function setIdFormulario($value) { $this->idFormulario = $value; }
    public function setFechaEnvio($value) { $this->fechaEnvio = $value; }
    public function setRespuestasCampos($respuestas) { $this->respuestasCampos = $respuestas; }

    public function setAspiranteCui($value) { $this->aspiranteCui = $value; }
    public function setAspiranteNombres($value) { $this->aspiranteNombres = $value; }
    public function setAspiranteApellidos($value) { $this->aspiranteApellidos = $value; }
    public function setAspiranteCorreoElectronico($value) { $this->aspiranteCorreoElectronico = $value; }
    public function setAspiranteTelefono($value) { $this->aspiranteTelefono = $value; }
    public function setAspirantePhotoDpi($value) { $this->aspirantePhotoDpi = $value; }

    // Métodos de utilidad
    public function getFechaEnvioFormateada() {
        if ($this->fechaEnvio) {
            return date('d/m/Y H:i', strtotime($this->fechaEnvio));
        }
        return null;
    }

    public function getNombreCompletoAspirante() {
        return trim(($this->aspiranteNombres ?? '') . ' ' . ($this->aspiranteApellidos ?? ''));
    }

    public function getCuiAspirante() {
        return $this->aspiranteCui;
    }

    public function getCorreoAspirante() {
        return $this->aspiranteCorreoElectronico;
    }

    public function getTelefonoAspirante() {
        return $this->aspiranteTelefono;
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
