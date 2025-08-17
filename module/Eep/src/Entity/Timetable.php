<?php

namespace Eep\Entity;

class Timetable {

    const DAYS = 'days';
    const LABORATORY_PRICE = 0;

    //TABLE 'horario'
    private $codHorario;
    private $fechaInicio;
    private $fechaFin;
    private $horaInicio;
    private $horaFin;
    private $seccion;
    private $cupo;
    private $codSalon;
    private $salon;
    private $codUbicacion;
    private $ubicacion;
    private $codTipoCurso;
    private $tipoCurso;
    private $codCoordinador;
    private $coordinador;
    private $codCatedratico;
    private $catedratico;
    private $laboratorio;
    private $lunes;
    private $martes;
    private $miercoles;
    private $jueves;
    private $viernes;
    private $sabado;
    private $domingo;
    private $codCurso;
    private $codPensum;
    private $mes;
    private $anio;
    private $fechaCohorte;
    private $precio;
    private $nombreCurso;
    private $aliasCurso;
    private $fechaAsignacion;
    private $fechaPago;
    private $official;
    private $valida;
    private $fechaLimiteCalificacion;
    private $fechaNotasCompletadas;
    private $fechaGeneracionActa;
    private $actCode;
    private $fechaActaAprobada;
    private $notaFinal;
    private $estadoNota;
    private $codEstadoNota;

    public function __construct($data = null) {
        if ($data != null) {
            $this->exchangeArray($data);
        }
    }

    public function exchangeArray($data) {
        //TAKE OUT FROM DAYS INNER FORM DATA
        if (!empty($data[self::DAYS])) {
            foreach ($data[self::DAYS] as $day) {
                $data[$day] = 1; //$data['lunes']=true;
            }
        }

        $this->codHorario = (!empty($data['cod_horario'])) ? $data['cod_horario'] : null;
        $this->fechaInicio = (!empty($data['fecha_inicio'])) ? $data['fecha_inicio'] : null;
        $this->fechaFin = (!empty($data['fecha_fin'])) ? $data['fecha_fin'] : null;
        $this->horaInicio = (!empty($data['hora_inicio'])) ? $data['hora_inicio'] : null;
        $this->horaFin = (!empty($data['hora_fin'])) ? $data['hora_fin'] : null;
        $this->seccion = (!empty($data['seccion'])) ? $data['seccion'] : null;
        $this->cupo = (isset($data['cupo'])) ? $data['cupo'] : null;
        $this->codPensum = (!empty($data['cod_pensum'])) ? $data['cod_pensum'] : null;
        $this->fechaCohorte = (!empty($data['fecha_cohorte'])) ? $data['fecha_cohorte'] : null;
        $this->laboratorio = isset($data['laboratorio']) && ($data['laboratorio'] == 'yes' || $data['laboratorio'] == 1);
        $this->lunes = (isset($data['lunes']) && $data['lunes'] != '') ? $data['lunes'] == true : false;
        $this->martes = (isset($data['martes']) && $data['martes'] != '') ? $data['martes'] == true : false;
        $this->miercoles = (isset($data['miercoles']) && $data['miercoles'] != '') ? $data['miercoles'] == true : false;
        $this->jueves = (isset($data['jueves']) && $data['jueves'] != '') ? $data['jueves'] == true : false;
        $this->viernes = (isset($data['viernes']) && $data['viernes'] != '') ? $data['viernes'] == true : false;
        $this->sabado = (isset($data['sabado']) && $data['sabado'] != '') ? $data['sabado'] == true : false;
        $this->domingo = (isset($data['domingo']) && $data['domingo'] != '') ? $data['domingo'] == true : false;
        $this->precio = (isset($data['precio']) && $data['precio'] != '') ? $data['precio'] : null;

        $this->mes = $data['mes'] ?? ((!empty($data['fecha_inicio'])) ? date('m', strtotime($data['fecha_inicio'])) : null);
        $this->anio = $data['anio'] ?? ((!empty($data['fecha_inicio'])) ? date('Y', strtotime($data['fecha_inicio'])) : null);

        $this->codSalon = (!empty($data['cod_salon'])) ? $data['cod_salon'] : null;
        $this->salon = (!empty($data['salon'])) ? $data['salon'] : null;
        $this->codUbicacion = (!empty($data['cod_ubicacion'])) ? $data['cod_ubicacion'] : null;
        $this->ubicacion = (!empty($data['ubicacion'])) ? $data['ubicacion'] : null;
        $this->ubicacion = (!empty($this->salon) && !empty($this->ubicacion)) ? $this->ubicacion . " " . $this->salon : null;
        $this->tipoCurso = (!empty($data['tipo_curso'])) ? $data['tipo_curso'] : null;
        $this->codTipoCurso = (!empty($data['cod_tipo_curso'])) ? $data['cod_tipo_curso'] : null;
        $nombresCoordinador = !empty($data['nombres_coordinador']) ? $data['nombres_coordinador'] : null;
        $apellidosCoordinador = !empty($data['apellidos_coordinador']) ? $data['apellidos_coordinador'] : null;
        $this->codCoordinador = (!empty($data['cod_usuario_coordinador'])) ? $data['cod_usuario_coordinador'] : null;
        $this->coordinador = (!empty($nombresCoordinador) && !empty($apellidosCoordinador)) ? $nombresCoordinador . " " . $apellidosCoordinador : null;
        $nombresCatedratico = !empty($data['nombres_catedratico']) ? $data['nombres_catedratico'] : null;
        $apellidosCatedratico = !empty($data['apellidos_catedratico']) ? $data['apellidos_catedratico'] : null;
        $this->codCatedratico = (!empty($data['cod_usuario_catedratico'])) ? $data['cod_usuario_catedratico'] : null;
        $this->catedratico = (!empty($nombresCatedratico) && !empty($apellidosCatedratico)) ? $nombresCatedratico . " " . $apellidosCatedratico : null;
        $this->codCurso = (!empty($data['cod_curso'])) ? $data['cod_curso'] : null;

        $this->nombreCurso = (!empty($data['nombre_curso'])) ? $data['nombre_curso'] : null;
        $this->aliasCurso = (!empty($data['alias'])) ? $data['alias'] : null;

        $this->notaFinal = $data['nota_final'] ?? $this->notaFinal;
        $this->estadoNota = $data['estado_nota'] ?? $this->estadoNota;
        $this->fechaAsignacion = $data['fecha_asignacion'] ?? $this->fechaAsignacion;

        $this->fechaPago = $data['fecha_pago'] ?? $this->fechaPago;
        $this->official = $data['official'] ?? $this->official;
        $this->valida = empty($data['valida']) ? false : true;

        $this->fechaLimiteCalificacion = $data['fecha_limite_calificacion'] ?? null;
        $this->fechaNotasCompletadas = $data['fecha_notas_completadas'] ?? null;
        $this->fechaGeneracionActa = $data['fecha_generacion_acta'] ?? null;

        $this->actCode = $data['cod_acta_oficial'] ?? null;
        $this->fechaActaAprobada = $data['fecha_acta_aprobada'] ?? null;

        $this->codEstadoNota = $data['cod_estado_nota'] ?? null;
    }

    function getCode() {
        return $this->codHorario;
    }

    function getFechaInicio() {
        return $this->fechaInicio;
    }

    function getFechaFin() {
        return $this->fechaFin;
    }

    function getHoraInicio() {
        return $this->horaInicio;
    }

    function getHoraFin() {
        return $this->horaFin;
    }

    function getSeccion() {
        return $this->seccion;
    }

    function getCupo() {
        return $this->cupo;
    }

    function getCodSalon() {
        return $this->codSalon;
    }

    function getSalon() {
        return $this->salon;
    }

    function getUbicacion() {
        return $this->ubicacion;
    }

    function getCodTipoCurso() {
        return $this->codTipoCurso;
    }

    function getTipoCurso() {
        return $this->tipoCurso;
    }

    function getCodCoordinador() {
        return $this->codCoordinador;
    }

    function getCoordinador() {
        return $this->coordinador;
    }

    function getCodCatedratico() {
        return $this->codCatedratico;
    }

    function getCatedratico() {
        return $this->catedratico;
    }

    function getLaboratorio() {
        return $this->laboratorio;
    }

    function getLunes() {
        return $this->lunes;
    }

    function getMartes() {
        return $this->martes;
    }

    function getMiercoles() {
        return $this->miercoles;
    }

    function getJueves() {
        return $this->jueves;
    }

    function getViernes() {
        return $this->viernes;
    }

    function getSabado() {
        return $this->sabado;
    }

    function getDomingo() {
        return $this->domingo;
    }

    function getCodCurso() {
        return $this->codCurso;
    }

    function getCodPensum() {
        return $this->codPensum;
    }

    function getMes() {
        return $this->mes;
    }

    function getAnio() {
        return $this->anio;
    }

    function getFechaCohorte() {
        return $this->fechaCohorte;
    }

    function getPrecio() {
        return $this->precio;
    }

    function getNombreCurso() {
        return $this->nombreCurso;
    }

    function getAliasCurso() {
        return $this->aliasCurso;
    }

    function getCodUbicacion() {
        return $this->codUbicacion;
    }

    function getFechaAsignacion() {
        return $this->fechaAsignacion;
    }

    function getNotaFinal() {
        return $this->notaFinal;
    }

    function getEstadoNota() {
        return $this->estadoNota;
    }

    function getFechaPago() {
        return $this->fechaPago;
    }

    function getOfficial() {
        return $this->official;
    }

    function getValida() {
        return $this->valida;
    }

    function isOfficial() {
        if ($this->codPensum == Order::CURSO_ACTUALIZACION) {
            return true;
        } else {
            return !empty($this->official);
        }
    }

    function getFechaLimiteCalificacion() {
        return $this->fechaLimiteCalificacion;
    }

    function getFechaNotasCompletadas() {
        return $this->fechaNotasCompletadas;
    }

    function getFechaGeneracionActa() {
        return $this->fechaGeneracionActa;
    }

    function getActCode() {
        return $this->actCode;
    }

    function getFechaActaAprobada() {
        return $this->fechaActaAprobada;
    }

    function getCodEstadoNota() {
        return $this->codEstadoNota;
    }

    function setCodEstadoNota($codEstadoNota) {
        $this->codEstadoNota = $codEstadoNota;
    }

    function setFechaActaAprobada($fechaActaAprobada) {
        $this->fechaActaAprobada = $fechaActaAprobada;
    }

    function setActCode($actCode) {
        $this->actCode = $actCode;
    }

    function setFechaLimiteCalificacion($fecha_limite_calificacion) {
        $this->fechaLimiteCalificacion = $fecha_limite_calificacion;
    }

    function setFechaNotasCompletadas($fecha_notas_completadas) {
        $this->fechaNotasCompletadas = $fecha_notas_completadas;
    }

    function setFechaGeneracionActa($fecha_generacion_acta) {
        $this->fechaGeneracionActa = $fecha_generacion_acta;
    }

    function setValida($valida) {
        $this->valida = $valida;
    }

    function setOfficial($year) {
        $this->official = $year;
    }

    function setFechaPago($fechaPago) {
        $this->fechaPago = $fechaPago;
    }

    function setFechaAsignacion($fechaAsignacion) {
        $this->fechaAsignacion = $fechaAsignacion;
    }

    function setNotaFinal($notaFinal) {
        $this->notaFinal = $notaFinal;
    }

    function setEstado($estado) {
        $this->estadoNota = $estado;
    }

    function setCodUbicacion($codUbicacion) {
        $this->codUbicacion = $codUbicacion;
    }

    function setCode($codHorario) {
        $this->codHorario = $codHorario;
    }

    function setFechaInicio($fechaInicio) {
        $this->fechaInicio = $fechaInicio;
    }

    function setFechaFin($fechaFin) {
        $this->fechaFin = $fechaFin;
    }

    function setHoraInicio($horaInicio) {
        $this->horaInicio = $horaInicio;
    }

    function setHoraFin($horaFin) {
        $this->horaFin = $horaFin;
    }

    function setSeccion($seccion) {
        $this->seccion = $seccion;
    }

    function setCupo($cupo) {
        $this->cupo = $cupo;
    }

    function setCodSalon($codSalon) {
        $this->codSalon = $codSalon;
    }

    function setSalon($salon) {
        $this->salon = $salon;
    }

    function setUbicacion($ubicacion) {
        $this->ubicacion = $ubicacion;
    }

    function setCodTipoCurso($codTipoCurso) {
        $this->codTipoCurso = $codTipoCurso;
    }

    function setTipoCurso($tipoCurso) {
        $this->tipoCurso = $tipoCurso;
    }

    function setCodCoordinador($codCoordinador) {
        $this->codCoordinador = $codCoordinador;
    }

    function setCoordinador($coordinador) {
        $this->coordinador = $coordinador;
    }

    function setCodCatedratico($codCatedratico) {
        $this->codCatedratico = $codCatedratico;
    }

    function setCatedratico($catedratico) {
        $this->catedratico = $catedratico;
    }

    function setLaboratorio($laboratorio) {
        $this->laboratorio = $laboratorio;
    }

    function setLunes($lunes) {
        $this->lunes = $lunes;
    }

    function setMartes($martes) {
        $this->martes = $martes;
    }

    function setMiercoles($miercoles) {
        $this->miercoles = $miercoles;
    }

    function setJueves($jueves) {
        $this->jueves = $jueves;
    }

    function setViernes($viernes) {
        $this->viernes = $viernes;
    }

    function setSabado($sabado) {
        $this->sabado = $sabado;
    }

    function setDomingo($domingo) {
        $this->domingo = $domingo;
    }

    function setCodCurso($codCurso) {
        $this->codCurso = $codCurso;
    }

    function setCodPensum($codPensum) {
        $this->codPensum = $codPensum;
    }

    function setMes($mes) {
        $this->mes = $mes;
    }

    function setAnio($anio) {
        $this->anio = $anio;
    }

    function setFechaCohorte($fechaCohorte) {
        $this->fechaCohorte = $fechaCohorte;
    }

    function setPrecio($precio) {
        $this->precio = $precio;
    }

    function setNombreCurso($nombreCurso) {
        $this->nombreCurso = $nombreCurso;
    }

    function setAliasCurso($aliasCurso) {
        $this->aliasCurso = $aliasCurso;
    }

    public function __toString() {
        $course = $this->getCodCurso();
        $alias = $this->getAliasCurso();
        //$startDate = date("d/m/Y", strtotime($this->getFechaInicio()));
        //$finishDate = date("d/m/Y", strtotime($this->getFechaFin()));
        $section = $this->getSeccion();
        $extra = ($this->getFechaCohorte() != null) ? ('; Cohorte ' . date('d/m/Y', strtotime($this->getFechaCohorte()))) : '';
        $updCourse = $this->getCodPensum() == Order::CURSO_ACTUALIZACION ? '(CA) ' : '';
        return "$updCourse$course - $alias (Sección $section$extra)";
    }

}
