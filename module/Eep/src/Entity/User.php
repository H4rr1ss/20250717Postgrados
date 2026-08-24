<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Eep\Entity;

class User {

//
//    const SUNDAY = 0;
//    const MONDAY = 1;
//    const TUESDAY = 2;
//    const WEDNESDAY = 3;
//    const THURSDAY = 4;
//    const FRIDAY = 5;
//    const SATURDAY = 6;
    //TABLE 'usuario'
    private $codUsuario;
    private $cui;
    private $pasaporte;
    private $registroAcademico;
    private $registroPersonal;
    private $numeroColegiado;
    private $nombres;
    private $apellidos;
    private $nombreCompleto;
    private $fechaNacimiento;
    private $telefono;
    private $correo;
    private $contrasenia;
    private $gradoAcademico;
    private $tituloProfesional;
    private $fechaCreacion;
    private $codInfoLaboral;
    private $sexo;
    //FIELDS FROM TABLES 'pais' AND 'sexo'
    private $codPais;
    private $pais;
    //ENTITIES
    private $InfoLaboral;
    //INSCRIPTION
    private $inscriptionStatus;

    public function __construct($data = null, $setProtection = false) {
        if ($data != null) {
            $this->exchangeArray($data, $setProtection);
        }
    }

    public function exchangeArray($data, $setProtection) {
        $this->codUsuario = ($data['cod_usuario']) ?? $this->codUsuario;
        $this->cui = !empty($data['cui']) ? $data['cui'] : $this->cui;
        $this->pasaporte = !empty($data['pasaporte']) ? $data['pasaporte'] : $this->pasaporte;
        $this->registroAcademico = !empty($data['registro_academico']) ? $data['registro_academico'] : $this->registroAcademico;
        $this->registroPersonal = !empty($data['registro_personal']) ? $data['registro_personal'] : $this->registroPersonal;
        $this->numeroColegiado = !empty($data['numero_colegiado']) ? $data['numero_colegiado'] : $this->numeroColegiado;
        $this->nombres = ($data['nombres']) ?? $this->nombres;
        $this->apellidos = ($data['apellidos']) ?? $this->apellidos;
        $this->fechaNacimiento = ($data['fecha_nacimiento']) ?? $this->fechaNacimiento;
        $this->telefono = ($data['telefono']) ?? $this->telefono;
        $this->correo = ($data['correo']) ?? $this->correo;
        $this->contrasenia = ($setProtection ? null : (($data['contrasenia']) ?? $this->contrasenia));
        $this->codPais = $data['cod_pais'] ?? $this->codPais;
        $this->pais = $data['pais'] ?? $this->pais;
        $this->sexo = ($data['sexo']) ?? $this->sexo;
        $this->gradoAcademico = ($data['grado_academico']) ?? $this->gradoAcademico;
        $this->tituloProfesional = ($data['titulo_profesional']) ?? $this->tituloProfesional;
        $this->fechaCreacion = ($data['fecha_creacion']) ?? $this->fechaCreacion;
        $this->codInfoLaboral = ($data['cod_info_laboral']) ?? $this->codInfoLaboral;
        $auxFullName = (empty($this->apellidos) ? '' : $this->apellidos . ' ') . $this->nombres;
        $this->nombreCompleto = ($data['nombre_completo']) ?? (empty($auxFullName) ? $this->nombreCompleto : $auxFullName);
        $this->inscriptionStatus = $data['inscrito'] ?? null;
    }

    function getCode() {
        return $this->codUsuario;
    }

    function getCui() {
        return $this->cui;
    }

    function getPasaporte() {
        return $this->pasaporte;
    }

    function getRegistroAcademico() {
        return $this->registroAcademico;
    }

    function getRegistroPersonal() {
        return $this->registroPersonal;
    }

    function getNumeroColegiado() {
        return $this->numeroColegiado;
    }

    function getNombres() {
        return $this->nombres;
    }

    function getApellidos() {
        return $this->apellidos;
    }

    function getFechaNacimiento() {
        return $this->fechaNacimiento;
    }

    function getTelefono() {
        return $this->telefono;
    }

    function getCorreo() {
        return $this->correo;
    }

    function getContrasenia() {
        return $this->contrasenia;
    }

    function getGradoAcademico() {
        return $this->gradoAcademico;
    }

    function getTituloProfesional() {
        return $this->tituloProfesional;
    }

    function getFechaCreacion() {
        return $this->fechaCreacion;
    }

    function getCodInfoLaboral() {
        return $this->codInfoLaboral;
    }

    function getSexo() {
        return $this->sexo;
    }

    function getCodPais() {
        return $this->codPais;
    }

    function getPais() {
        return $this->pais;
    }

    function getInfoLaboral() {
        return $this->InfoLaboral;
    }

    function getInscriptionStatus() {
        return $this->inscriptionStatus;
    }

    function getNombreCompleto() {
        return $this->nombreCompleto;
    }

    function setNombreCompleto($nombreCompleto) {
        $this->nombreCompleto = $nombreCompleto;
    }

    function setInscriptionStatus($inscriptionStatus) {
        $this->inscriptionStatus = $inscriptionStatus;
    }

    function setCodUsuario($codUsuario) {
        $this->codUsuario = $codUsuario;
    }

    function setCui($cui) {
        $this->cui = $cui;
    }

    function setPasaporte($pasaporte) {
        $this->pasaporte = $pasaporte;
    }

    function setRegistroAcademico($registroAcademico) {
        $this->registroAcademico = $registroAcademico;
    }

    function setRegistroPersonal($registroPersonal) {
        $this->registroPersonal = $registroPersonal;
    }

    function setNumeroColegiado($numeroColegiado) {
        $this->numeroColegiado = $numeroColegiado;
    }

    function setNombres($nombres) {
        $this->nombres = $nombres;
    }

    function setApellidos($apellidos) {
        $this->apellidos = $apellidos;
    }

    function setFechaNacimiento($fechaNacimiento) {
        $this->fechaNacimiento = $fechaNacimiento;
    }

    function setTelefono($telefono) {
        $this->telefono = $telefono;
    }

    function setCorreo($correo) {
        $this->correo = $correo;
    }

    function setContrasenia($contrasenia) {
        $this->contrasenia = $contrasenia;
    }

    function setGradoAcademico($gradoAcademico) {
        $this->gradoAcademico = $gradoAcademico;
    }

    function setTituloProfesional($tituloProfesional) {
        $this->tituloProfesional = $tituloProfesional;
    }

    function setFechaCreacion($fechaCreacion) {
        $this->fechaCreacion = $fechaCreacion;
    }

    function setCodInfoLaboral($codInfoLaboral) {
        $this->codInfoLaboral = $codInfoLaboral;
    }

    function setSexo($sexo) {
        $this->sexo = $sexo;
    }

    function setCodPais($codPais) {
        $this->codPais = $codPais;
    }

    function setPais($pais) {
        $this->pais = $pais;
    }

    function setInfoLaboral($InfoLaboral) {
        $this->InfoLaboral = $InfoLaboral;
    }

}
