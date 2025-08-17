<?php

namespace Eep\Entity;

use Eep\Entity\Timetable;
use Eep\Form\CategorizeTimetableForm as CTF;

class Order {

    //TABLE 'orden_pago'
    private $codOrden;
    private $codUsuario;
    private $idPersona;
    private $nombreUsuario;
    private $activa;
    private $pagada;
    private $codBoleta;
    private $fechaGeneracion;
    private $fechaPago;
    private $fechaPagoLocal;
    private $montoTotal;
    private $codBanco;
    private $banco;
    private $codCarrera;
    private $nombreCarrera;
    private $codTipoOrden;
    private $tipoOrden;
    private $llave;
    private $rubro;
    private $unidad;
    private $extension;
    private $carrera;
    private $noTransaccionBanco;
    private $descripcion;
    private $fechaVencimiento;
    //OBJECTS ARRAY
    private $timetables;
    private $detail;

    //ORDER TYPE
    const ASSIGNMENT = 1;
    const INSCRIPTION = 2;
    //OTHER CONSTANTS
    const UNIDAD = '02';
    const EXTENSION = '00';
    const RUBRO_MAESTRIAS = '19';
    const RUBRO_DOCTORADOS = '47';
    const RUBRO_CURSOS_ACTUALIZACION = '19';
    const VARIANTE_MAESTRIAS_INSCRIPCION = '1';
    const VARIANTE_MAESTRIAS_CURSOS = '15';
    const VARIANTE_DOCTORADOS_INSCRIPCION = '1';
    const VARIANTE_DOCTORADOS_CURSOS = '33';
    const VARIANTE_CURSOS_ACTUALIZACION = '61';
    const MAESTRIA = 3;
    const ESPECIALIZACION = 6;
    const DOCTORADO = 7;
    const CURSO_ACTUALIZACION = 999;
    //EMPTY ACT
    const NO_ACT_CODE = 'SIN-ACTA';

    public function __construct($data = null) {
        $this->detail = [];
        if ($data != null) {
            $this->exchangeArray($data);
        }
    }

    public function exchangeArray($data) { //((!empty($data['activa'])||$data['activa']=="0")) 
        $this->codOrden = $data['cod_orden'] ?? $this->codOrden;
        $this->codUsuario = $data['cod_usuario'] ?? $this->codUsuario;
        $this->idPersona = $data['id_persona'] ?? $this->idPersona;
        $this->activa = (isset($data['activa']) && $data['activa'] != '' ) ? $data['activa'] : false;
        $this->pagada = (isset($data['pagada']) && $data['pagada'] != '' ) ? $data['pagada'] == 1 : false;
        $this->codBoleta = $data['cod_boleta'] ?? $this->codBoleta;
        $this->fechaGeneracion = $data['fecha_generacion'] ?? $this->fechaGeneracion;
        $this->fechaPago = $data['fecha_pago'] ?? $this->fechaPago;
        $this->fechaPagoLocal = $data['fecha_pago_local'] ?? $this->fechaPagoLocal;
        $this->montoTotal = $data['monto_total'] ?? $this->montoTotal;
        $this->codBanco = $data['cod_banco'] ?? $this->codBanco;
        $this->banco = $data['banco'] ?? $this->banco;
        $this->codCarrera = $data['cod_carrera'] ?? $this->codCarrera;
        $this->codTipoOrden = $data['cod_tipo_orden'] ?? $this->codTipoOrden;
        $this->tipoOrden = $data['tipo_orden'] ?? $this->tipoOrden;
        $this->llave = $data['llave'] ?? $this->llave;
        $this->unidad = $data['unidad'] ?? $this->unidad;
        $this->extension = $data['extension'] ?? $this->extension;
        $this->carrera = $data['carrera'] ?? $this->carrera;
        $this->noTransaccionBanco = $data['no_transaccion_banco'] ?? $this->noTransaccionBanco;
        $this->nombreCarrera = $data['nombre_carrera'] ?? $this->nombreCarrera;
        $nombres = $data['nombres_usuario'] ?? null;
        $apellidos = $data['apellidos_usuario'] ?? null;
        $this->nombreUsuario = (!empty($nombres) && !empty($apellidos)) ? "$apellidos, $nombres" : null;
        $this->descripcion = $data['descripcion'] ?? $this->descripcion;
        $this->fechaVencimiento = $data['fecha_vencimiento'] ?? $this->fechaVencimiento;
    }

    public function getCodOrden() {
        return $this->codOrden;
    }

    public function getCodUsuario() {
        return $this->codUsuario;
    }

    public function getActiva() {
        return $this->activa;
    }

    public function getPagada() {
        return $this->pagada;
    }

    public function getCodBoleta() {
        return $this->codBoleta;
    }

    public function getFechaGeneracion() {
        return $this->fechaGeneracion;
    }

    public function getFechaPago() {
        return $this->fechaPago;
    }

    public function getFechaPagoLocal() {
        return $this->fechaPagoLocal;
    }

    public function getMontoTotal() {
        return $this->montoTotal;
    }

    public function getCodBanco() {
        return $this->codBanco;
    }

    public function getBanco() {
        return $this->banco;
    }

    public function getCodCarrera() {
        return $this->codCarrera;
    }

    public function getCodTipoOrden() {
        return $this->codTipoOrden;
    }

    public function getTipoOrden() {
        return $this->tipoOrden;
    }

    public function getLlave() {
        return $this->llave;
    }

    public function getUnidad() {
        return $this->unidad;
    }

    public function getExtension() {
        return $this->extension;
    }

    public function getCarrera() {
        return $this->carrera;
    }

    public function getTimetables() {
        return $this->timetables;
    }

    public function getNoTransaccionBanco() {
        return $this->noTransaccionBanco;
    }

    public function getIdPersona() {
        return $this->idPersona;
    }

    public function getDetail() {
        return $this->detail;
    }

    function getNombreCarrera() {
        return $this->nombreCarrera;
    }

    function getRubro() {
        return $this->rubro;
    }

    function getFechaVencimiento() {
        return $this->fechaVencimiento;
    }

    function setFechaVencimiento($dueDate) {
        $this->fechaVencimiento = $dueDate;
    }

    function getNombreUsuario() {
        return $this->nombreUsuario;
    }

    function getDescripcion() {
        return $this->descripcion;
    }

    function setDescripcion($descripcion) {
        $this->descripcion = $descripcion;
    }

    function setNombreUsuario($nombreUsuario) {
        $this->nombreUsuario = $nombreUsuario;
    }

    function setRubro($rubro) {
        $this->rubro = $rubro;
    }

    function setNombreCarrera($nombreCarrera) {
        $this->nombreCarrera = $nombreCarrera;
    }

    public function setDetail($detail) {
        $this->detail = $detail;
    }

    public function setIdPersona($idPersona) {//ACADEMIC REGISTRY / CUI / PASSPORT - THE ONE USED TO CREATE THE PAYMENT ORDER
        $this->idPersona = $idPersona;
    }

    public function setNoTransaccionBanco($noTransaccionBanco) {
        $this->noTransaccionBanco = $noTransaccionBanco;
    }

    public function setCodOrden($codOrden) {
        $this->codOrden = $codOrden;
    }

    public function setCodUsuario($codUsuario) {
        $this->codUsuario = $codUsuario;
    }

    public function setActiva($activa) {
        $this->activa = $activa;
    }

    public function setPagada($pagada) {
        $this->pagada = $pagada;
    }

    public function setCodBoleta($codBoleta) {
        $this->codBoleta = $codBoleta;
    }

    public function setFechaGeneracion($fechaGeneracion) {
        $this->fechaGeneracion = $fechaGeneracion;
    }

    public function setFechaPago($fechaPago) {
        $this->fechaPago = $fechaPago;
    }

    public function setFechaPagoLocal($fechaPagoLocal) {
        $this->fechaPagoLocal = $fechaPagoLocal;
    }

    public function setMontoTotal($montoTotal) {
        $this->montoTotal = $montoTotal;
    }

    public function setCodBanco($codBanco) {
        $this->codBanco = $codBanco;
    }

    public function setBanco($banco) {
        $this->banco = $banco;
    }

    public function setCodCarrera($codCarrera) {
        $this->codCarrera = $codCarrera;
    }

    public function setCodTipoOrden($codTipoOrden) {
        $this->codTipoOrden = $codTipoOrden;
    }

    public function setTipoOrden($tipoOrden) {
        $this->tipoOrden = $tipoOrden;
    }

    public function setLlave($llave) {
        $this->llave = $llave;
    }

    public function setUnidad($unidad) {
        $this->unidad = $unidad;
    }

    public function setExtension($extension) {
        $this->extension = $extension;
    }

    public function setCarrera($carrera) {
        $this->carrera = $carrera;
    }

    public function setTimetables($timetables) {
        $this->timetables = $timetables;
    }

    public function addTimetable(Timetable $timetable) {
        if (empty($this->timetables)) {
            $this->timetables = [$timetable];
        } else {
            $this->timetables[] = $timetable;
        }
    }

    public function addDetail(OrderDetail $detail) {
        if ($this->detail == null) {
            $this->detail = [$detail];
        } else {
            $this->detail[] = $detail;
        }
    }

    public function __toString() {
        return "Orden No. " . $this->getCodOrden();
    }

}
