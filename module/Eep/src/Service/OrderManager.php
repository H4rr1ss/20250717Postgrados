<?php

namespace Eep\Service;

use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Order;
use Eep\Entity\OrderDetail;
use Eep\Entity\User;
use Eep\Entity\Timetable;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use SIIF\Model\SIIFOrdenPago;
use Zend\Db\Sql\Select;
use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Zend\View\Renderer\PhpRenderer;
use Eep\Service\AssignmentManager as AM;
use Eep\Form\AssignmentForm as AF;
use Eep\Service\GeneralManager as GM;

class OrderManager extends Manager {

    public function getUserOrder($userCode, $active = null, $payed = null, $type = Order::ASSIGNMENT, $year = null): R {
        $res = new R();
        $table = new TableGateway(['op' => 'orden_pago'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $where = [
            'cod_usuario' => $userCode
        ];
        if ($payed != null) {
            if ($payed === true || $payed === false) {
                $where['pagada'] = ($payed === true) ? 1 : 0;
            }
        }
        if ($active != null) {
            if ($active === true || $active === false) {
                $where['activa'] = ($active === true) ? 1 : 0;
            }
        }
        $where['op.cod_tipo_orden'] = $type; //Order::ASSIGNMENT || Order::INSCRIPTION
        if ($year != null) {
            $where[] = "YEAR(op.fecha_generacion) = $year";
        }
        $select->where($where);
        $select->order('op.cod_orden DESC');

        if ($type == Order::ASSIGNMENT) {
            //GETTING ALL THE TIMETABLES RELATED WITH THAT ORDER
            $select->join(['cop' => 'cursos_orden_pago'], 'op.cod_orden = cop.cod_orden', []);

            //GETTING ALL THE TIMETABLES ONLY WITH THE NECESSARY INFO
            $select->join(['h' => 'horario'], 'h.cod_horario = cop.cod_horario');
            $select->join(['c' => 'curso_pensum'], 'h.cod_curso = c.cod_curso and h.cod_pensum = c.cod_pensum', ['cod_curso', 'cod_pensum', 'nombre', 'alias']);
        }
        try {
            $result = $table->selectWith($select)->toArray();
            $orders = [];
            $order = null;
            foreach ($result as $row) {
                if (empty($order) || $row['cod_orden'] != $order->getCodOrden()) {
                    $order = new Order($row);
                    $orders[] = $order;
                }
                if ($order->getCodTipoOrden() == Order::ASSIGNMENT) {
                    $timetable = new Timetable($row);
                    $order->addTimetable($timetable);
                }
            }
            $res->success();
            $res->setObj($orders);
        } catch (\Exception $ex) {
            $res->addMsg('No se pudieron consultar las órdenes de pago del estudiante');
            $res->addError($ex);
        }
        return $res;
    }

    public function getBankCode($name) {
        $res = new R();
        $bankTable = new TableGateway(['b' => 'banco'], $this->dbAdapter);
        if (empty($name)) {
            $res->success();
            $res->setObj(null);
        } else {
            if ($name == 'BANRURAL') {
                $where = "nombre like '%$name%'";
            } elseif (stripos($name, "continental") !== false) {
                /* POSIBLE NAMES FOR G&T:
                 * Banco GyT Continental
                 * Banco G&amp;T Continental
                 * Banco G&T Continental
                 * G&T Continental
                 * G6T Continental
                 */
                $where = "nombre like '%continental%'";
            } else {
                $where = "nombre = '$name'";
            }

            $result = $bankTable->select($where);
            if ($result->count() == 0) {
                //ADD BANK
                $result = $bankTable->insert([
                    'nombre' => $name
                ]);
                if ($result == true) {
                    $res->success();
                    $res->setObj($bankTable->getLastInsertValue());
                } else {
                    $res->failure("No se pudo agregar el banco '$name'");
                }
            } else {
                $res->success();
                $res->setObj($result->current()['cod_banco']);
            }
        }
        return $res;
    }

    /**
     * 
     * @param type $orderCode
     * @param type $user This parameter can be an User object or the user id (Passport, CUI or Academic Registry).
     * @return type
     */
    public function requestOrder($orderCode, $user) {
        $res = new R();

        //GETTING USER ID. PRIMARILY USE ACADEMIC REGISTRY
        //FOR UPDATING COURSES, USERS MIGHT NOT HAVE IT; SO USE CUI OR PASSPORT
        if (!is_a($user, User::class)) {
            $id = $user;
        } elseif (!empty($user->getRegistroAcademico())) {
            $id = $user->getRegistroAcademico();
        } elseif (!empty($user->getCui())) {
            $id = $user->getCui();
        } else {
            $id = $user->getPasaporte();
        }

        $siifWs = new SIIFOrdenPago();
        $result = $siifWs->ordenPago($orderCode, $id);
        if ($result->get() == false) {
            $res = $result;
        } else {
            try {
                $wsResponse = $result->getObj();
                /*
                 * RESULT CODE:
                 *  0: NOT FOUND
                 *  1: PAYED
                 *  2: NOT PAYED
                 */
                $resultCode = $wsResponse->{'CODIGO_RESP'}->__toString();
                $descripcion = $wsResponse->{'DESCRIPCION'}->__toString();
                if ($resultCode == 0) {
                    $res->addMsg($descripcion);
                } else {
                    //GETTING BANK CODE
                    $result = $this->getBankCode($wsResponse->{'BANCO'}->__toString());
                    if ($result->get() == false) {
                        $res = $result;
                    } else {
                        $bankCode = $result->getObj();

                        //GET ORDER FROM SIIF //$wsResponse->{}->__toString()
                        $order = new Order([
                            'cod_boleta' => $wsResponse->{'NO_BOLETA_DEPOSITO'}->__toString(),
                            'id_persona' => $wsResponse->{'ID_PERSONA'}->__toString(),
                            'fecha_generacion' => $wsResponse->{'FECHA_GENERACION'}->__toString(),
                            'fecha_pago' => $wsResponse->{'FECHA_CERTIF_BANCO'}->__toString(),
                            'monto_total' => $wsResponse->{'MONTO'}->__toString(),
                            'cod_carrera' => ($wsResponse->{'UNIDAD'}->__toString() == '2') ? $wsResponse->{'CARRERA'}->__toString() : null,
                            'unidad' => $wsResponse->{'UNIDAD'}->__toString(),
                            'carrera' => $wsResponse->{'CARRERA'}->__toString(),
                            'extension' => $wsResponse->{'EXTENSION'}->__toString(),
                            'no_transaccion_banco' => $wsResponse->{'NO_TRAN_BANCO'}->__toString(),
                        ]); //THIS WAY IF THOSE VALUES ARE EMPTY, THEY WILL BE NULLED
                        $order->setCodOrden($orderCode);
                        $order->setPagada($resultCode == 1); //STATUS DESCRIBED BEFORE
                        //$order->setCodBoleta();
                        //$order->setFechaGeneracion();
                        //$order->setFechaPago($wsResponse->{'FECHA_CERTIF_BANCO'}->__toString());
                        //$order->setMontoTotal($wsResponse->{'MONTO'}->__toString());
                        $order->setCodBanco($bankCode);
//                if ($wsResponse->{'UNIDAD'}->__toString() == '2') {//ARQUITECTURA
//                    $order->setCodCarrera($wsResponse->{'CARRERA'}->__toString());
//                }
                        //$order->setUnidad($wsResponse->{'UNIDAD'}->__toString());
                        //$order->setExtension($wsResponse->{'EXTENSION'}->__toString());
                        //$order->setNoTransaccionBanco($wsResponse->{'NO_TRAN_BANCO'}->__toString());

                        $res->success();
                        $res->setObj($order);
                    }
                }
            } catch (\Exception $ex) {
                $res->addMsg("No se pudo obtener información de la orden de pago $orderCode en el SIIF: " . $ex->getMessage());
            }
        }
        return $res;
    }

    private function saveOrder(Order $order): R {
        $res = new R();
        //SAVING PAYMENT ORDER
        $this->beginTransaction();
        $orderTable = new TableGateway(['o' => 'orden_pago'], $this->dbAdapter);
        try {
            $r = $orderTable->insert([
                'cod_orden' => $order->getCodOrden(),
                'cod_usuario' => $order->getCodUsuario(),
                'id_persona' => $order->getIdPersona(),
                'activa' => $order->getActiva(),
                'pagada' => $order->getPagada(),
                'cod_boleta' => $order->getCodBoleta(),
                'fecha_generacion' => $order->getFechaGeneracion() ?? date('Y-m-d'),
                'fecha_pago' => $order->getFechaPago(),
                'fecha_pago_local' => $order->getFechaPagoLocal(),
                'no_transaccion_banco' => $order->getNoTransaccionBanco(),
                'monto_total' => $order->getMontoTotal(),
                'cod_banco' => $order->getCodBanco(),
                //'banco' => $order->getBanco(),
                'cod_carrera' => $order->getCodCarrera(),
                'cod_tipo_orden' => $order->getCodTipoOrden(),
                //'tipo_orden' => $order->getTipoOrden(),
                'llave' => $order->getLlave(),
                'unidad' => $order->getUnidad(),
                'extension' => $order->getExtension(),
                'carrera' => $order->getCarrera(),
                'rubro' => $order->getRubro(),
                'descripcion' => $order->getDescripcion(),
                'fecha_vencimiento' => $order->getFechaVencimiento()
            ]);
            if ($r == true) {
                $res->success();
            } else {
                $res->failure("No se pudo guardar la orden localmente");
            }
        } catch (InvalidQueryException $ex) {
            $res->failure("Falló el guardado de la orden de pago localmente. Contacte a Control Académico." . $ex->getMessage());
        }
        if ($res->get() == false) {
            $this->rollback();
        } else {
            if ($order->getCodTipoOrden() == Order::ASSIGNMENT) {
                //SAVING ORDER DETAILS
                $detail = $order->getDetail();
                try {
                    if (!empty($detail)) {
                        $courseTimetableTable = new TableGateway(['cop' => 'cursos_orden_pago'], $this->dbAdapter);
                        foreach ($detail as $ctt) {
                            $result = $courseTimetableTable->insert([
                                'cod_horario' => $ctt->getCodHorario(),
                                'cod_orden' => $order->getCodOrden(),
                                'monto' => $ctt->getSubtotal(),
                                'rubro' => $ctt->getRubro(),
                                'variante' => $ctt->getVariante(),
                                'asignacion_efectuada' => false
                            ]);
                            if ($result == false) {
                                throw new Exception();
                            }
                        }
                    }
                    $this->commit();
                } catch (\Exception $ex) {
                    $res->failure("No se logró guardar el detalle de la orden '" . $order->getCodOrden() . "'" . (!empty($ctt) ? " y el horario '" . $ctt->getCodHorario() . "'" : '') . $ex->getMessage());
                    $res->addMsg(var_export($order, true));
                    $this->rollback();
                }
            } else {
                $this->commit();
            }
        }
        return $res;
    }

    public function createOrder(User $user, $timetables = [], $type, $year = null) {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        if ($year == null) {
            $year = date('Y');
        }
        $orderType = (empty($timetables)) ? Order::INSCRIPTION : Order::ASSIGNMENT;
        $orders = [];
        //CREATING ORDER OBJECT
        if (empty($user->getRegistroAcademico())) {
            if (!empty($user->getCui())) {
                $idPersona = $user->getCui();
            } else {
                $idPersona = $user->getPasaporte();
            }
        } else {
            $idPersona = $user->getRegistroAcademico();
        }
        //SEARCHING TIMETABLE DETAILS
        if ($orderType == Order::ASSIGNMENT) {
            //DATABASE SEARCH
            $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['p' => 'pensum'], 'h.cod_pensum = p.cod_pensum', []);
            $select->join(['c' => 'carrera'], 'p.cod_carrera = c.cod_carrera', ['cod_grado', 'cod_carrera', 'nombre_carrera' => 'nombre_actual']);
            $where = [];
            foreach ($timetables as $timetable) {
                $where[] = "cod_horario = $timetable";
            }
            $select->where([
                implode(' or ', $where)
            ]);
            $select->order('cod_carrera ASC');
            try {
                $ttData = $table->selectWith($select)->toArray();
            } catch (\Exception $ex) {
                $res->failure('No se pudieron consultar los horarios solicitados');
            }
            //VALIDATING RESULT
            if ((count($timetables) != count($ttData))) {
                $res->failure('Hubieron horarios solicitados inexistentes');
            } elseif (count($ttData) == 0) {
                $res->failure('No hay datos encontrados con los horarios para asignarse');
            } else {
                //SETTING DETAIL OBJECTS' DATA
                $careerCode = $ttData[0]['cod_carrera'];
                $order = new Order([
                    'cod_carrera' => $careerCode,
                    'nombre_carrera' => $ttData[0]['nombre_carrera'],
                    'cod_usuario' => $user->getCode(),
                    'id_persona' => $idPersona,
                    'activa' => true,
                    'pagada' => false,
                    'fecha_generacion' => date('Y-m-d'),
                    'cod_tipo_orden' => $orderType,
                    'unidad' => Order::UNIDAD,
                    'extension' => Order::EXTENSION,
                    'carrera' => $careerCode
                ]);
                $orders[] = $order;
                $totalAmmount = 0;
                $minTime = null;
                $regularDays = $this->getGlobal(GM::ASSIGNMENT_DAYS, 5);
                $extDays = $this->getGlobal(GM::EXT_ASSIGNMENT_DAYS, 5);
                foreach ($ttData as $tt) {
                    $careerCode = $tt['cod_carrera'];
                    if ($careerCode != $order->getCodCarrera()) {
                        $order->setMontoTotal($totalAmmount);
                        if ($type == AF::TYPE_EXTEMP) {
                            $date = time();
                            $days = $extDays;
                        } elseif ($type == AF::TYPE_EXTRA) {
                            $date = time();
                            $days = $extDays;
                        } else {
                            $date = $minTime;
                            $days = $regularDays;
                        }

                        $limit = date('Ymd', strtotime("+ $days weekdays", $date));

                        $order->setFechaVencimiento($limit);
                        $order = new Order([
                            'cod_carrera' => $careerCode,
                            'nombre_carrera' => $tt['nombre_carrera'],
                            'cod_usuario' => $user->getCode(),
                            'id_persona' => $idPersona,
                            'activa' => true,
                            'pagada' => false,
                            'fecha_generacion' => date('Y-m-d'),
                            'cod_tipo_orden' => $orderType,
                            'unidad' => Order::UNIDAD,
                            'extension' => Order::EXTENSION,
                            'carrera' => $careerCode
                        ]);
                        $totalAmmount = 0;
                        $minTime = null;
                        $orders[] = $order;
                    }
                    $degree = $tt['cod_grado'];
                    switch ($degree) {
                        case Order::MAESTRIA:
                        case Order::ESPECIALIZACION:
                            $rubro = Order::RUBRO_MAESTRIAS;
                            $variante = Order::VARIANTE_MAESTRIAS_CURSOS;
                            break;
                        case Order::DOCTORADO:
                            $rubro = Order::RUBRO_DOCTORADOS;
                            $variante = Order::VARIANTE_DOCTORADOS_CURSOS;
                            break;
                        case Order::CURSO_ACTUALIZACION:
                            $rubro = Order::RUBRO_CURSOS_ACTUALIZACION;
                            $variante = Order::VARIANTE_CURSOS_ACTUALIZACION;
                            break;
                        default:
                            $res->failure("El grado '$degree' del horario no está soportado");
                            break;
                    }
                    if ($res->get() == false) {
                        break;
                    }
                    $detail = new OrderDetail([
                        'cod_horario' => $tt['cod_horario'],
                        'rubro' => $rubro,
                        'variante' => $variante,
                        'fecha_inicio' => $tt['fecha_inicio'],
                        'cod_curso' => $tt['cod_curso'],
                        'seccion' => $tt['seccion'],
                        'subtotal' => $tt['precio']
                    ]);
                    if ($minTime == null || strtotime($tt['fecha_inicio']) < $minTime) {
                        $minTime = strtotime($tt['fecha_inicio']);
                    }
                    $totalAmmount += intval($detail->getSubtotal());
                    $order->addDetail($detail);
                }
                $order->setMontoTotal($totalAmmount);
                if ($type == AF::TYPE_EXTEMP) {
                    $date = time();
                    $days = $extDays;
                } elseif ($type == AF::TYPE_EXTRA) {
                    $date = time();
                    $days = $extDays;
                } else {
                    $date = $minTime;
                    $days = $regularDays;
                }

                $limit = date('Ymd', strtotime("+ $days weekdays", $date));
                $order->setFechaVencimiento($limit);

                //CREATE ORDERS THROUGH SIIF
                if ($res->get() == true) {
                    //NO ERRORS BEFORE
                    foreach ($orders as $order) {
                        $result = $this->requestNewOrderSIIF($order);
                        if ($result->get() == false) {
                            $res = $result;
                            break;
                        }
                    }
                    //SAVING IN LOCAL DATABASE
                    if ($res->get() == true) {
                        foreach ($orders as $order) {
                            $result = $this->saveOrder($order);
                            if ($result->get() == false) {
                                $res = $result;
                                break;
                            }
                        }
                    }
                    $res->setObj($orders);
                }
            }
        }
        return $res;
    }

    private function requestNewOrderSIIF(Order $order): R {
        $siifWs = new SIIFOrdenPago();
        if($order->getCarrera() == Order::CURSO_ACTUALIZACION){
            $order->setCarrera('00');
        }
        $result = $siifWs->generarOrdenPago(Order::EXTENSION, Order::UNIDAD, $order->getCarrera(), $order->getIdPersona(), $order->getNombreCarrera(), $order->getDetail());
        if ($result->get() == true) {
            //VALIDATING RESPONSE
            $response = $result->getObj();
            try {
                if (empty($response)) {
                    throw new \Exception('No se obtuvo respuesta en la solicitud de creación de orden de pago.');
                } else {
                    $responseCode = (string) $response->{'CODIGO_RESP'};
                    if ($responseCode != 1) {
                        $description = (string) $response->{'DESCRIPCION'};
                        $result->failure("Generación de orden de pago fallida: $description");
                    } else {
                        //PASSING DATA FROM RESPONSE TO THE ORDER
                        $orderCode = (string) $response->{'ID_ORDEN_PAGO'};
                        $checksum = (string) $response->{'CHECKSUM'};
                        $rubro = (string) $response->{'RUBROPAGO'};
                        $order->setCodOrden($orderCode);
                        $order->setLlave($checksum);
                        $order->setRubro($rubro);
                        $result->success();
                        $result->setObj($order);
                    }
                }
            } catch (\Exception $ex) {
                $result->failure('Solicitud de creación de orden de pago al SIIF fallida:' . $ex->getMessage());
            }
        }

        return $result;
    }

    /*
     * BEFORE CALLING THIS METHOD, THE USER MUST HAVE BEEN VERIFIED THAT HE IS NOT INSCRIBED ALREADY,
     * OTHER WAY IT WILL PRODUCE AN ERROR
     */

    public function addInscriptionOrder(User $user, $orderCode) {
        $res = new R();
        //BUSCAR SI EXISTE Y SI YA FUE INGRESADO
        //SEARCHING PAYMENT ORDER - IT MUST NOT EXIST LOCALLY
        $orderTable = new TableGateway(['o' => 'orden_pago'], $this->dbAdapter);
        $result = $orderTable->select([
            'cod_orden' => $orderCode
        ]);
        if ($result->count() != 0) {
            //ORDER ALREADY SAVED
            $type = $result->current()['cod_tipo_orden'];
            if ($type != Order::INSCRIPTION) {
                $res->set("Orden Incorrecta");
                $res->addMsg("La orden de pago ya ha sido utilizada para otro destino en el sistema");
            } else {
                //CHECKING IF ORDER HAS ALREADY BEEN ASSIGNED TO THE SAME USER
                $select = $orderTable->getSql()->select();
                $select->columns([]);
                $select->where([
                    'o.cod_orden' => $orderCode
                ]);
                $select->join(['i' => 'inscripcion'], 'i.cod_orden = o.cod_orden', ['cod_usuario']);
                $select->where(['i.anio' => new Expression('YEAR(curdate())')]);
                $result = $orderTable->selectWith($select);
                if ($result->count() == 0) {
                    $res->set("Orden Incongruente");
                    $res->addMsg("La orden de pago es de inscripción pero no está asociada al estudiante especificado.");
                } else {
                    $inscribedUserCode = $result->current()['cod_usuario'];
                    if ($inscribedUserCode == $user->getCode()) {
                        $res->set("Orden Asignada con Anterioridad");
                        $res->addMsg("La orden de pago ya ha sido asignada al estudiante");
                        $res->setType(R::INFO);
                    } else {
                        $res->set("Orden Incongruente");
                        $res->addMsg("La orden de pago es de inscripción actual pero está asociada a otro estudiantes");
                    }
                }
            }
        } else {
            //ORDER NOT FOUND; VERIFY IN SIIF
            $result = $this->requestOrder($orderCode, $user);
            if ($result->get() === false) {
                //ERROR GETTING ORDER FROM SIIF
                $res = $result;
                $res->set("Error en consultando información al SIIF");
            } else {
                $order = $result->getObj();
                $order->setCodUsuario($user->getCode());
                $order->setActiva(true);
                $order->setCodTipoOrden(Order::INSCRIPTION); //INSCRIPTION
                $order->setTipoOrden('Inscripción');
                if ($order->getPagada() == false) {
                    $res->failure("La orden de pago no ha sido pagada según el SIIF");
                    $res->set("Orden Sin Pagar");
                } else {
                    //ADD ORDER 
                    $result = $this->saveOrder($order);
                    if ($result->get() == true) {
                        //ADD TO USER INSCRIPTION
                        $res->success();
                        $res->set("Orden de Pago Agregada Correctamente");
                        $name = $user->getNombres() . " " . $user->getApellidos();
                        $hasCui = true;
                        if (empty($user->getCui())) {
                            $hasCui = false;
                            $id = $user->getPasaporte();
                        } else {
                            $id = $user->getCui();
                        }
                        $regAc = $user->getRegistroAcademico();
                        $res->addMsg("Nombre: $name");
                        $res->addMsg(($hasCui ? "Cui: " : "Pasaporte: ") . $id);
                        $res->addMsg("Registro Académico: $regAc");
                    } else {
                        $res->failure();
                        $res->addMsg($result->getMsg());
                        $res->set("Orden No Guardada");
                    }
                }
            }
        }
        return $res;
    }

    public function getAssignmentOrderType($orderCode): R {
        $res = new R();
        $table = new TableGateway('involucrado', $this->dbAdapter);
        try {
            $result = $table->select([
                'cod_orden' => $orderCode,
                '( cod_tipo_acta = ' . AM::CA_EXTEMPORARY . ' or cod_tipo_acta = ' . AM::CA_EXTRAORDINARY . ' )'
            ]);
            $res->success();
            if ($result->count() == 0) {
                $type = false;
            } else {
                $data = $result->current();
                $type = $data['cod_tipo_acta'];
            }
            $res->setObj($type);
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo consultar si la orden de pago está asociada a un acta.');
        }
        return $res;
    }

    public function getOrder($orderCode): R {
        $res = new R();
        try {
            //SEARCHING ORDER
            $table = new TableGateway(['o' => 'orden_pago'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['u' => 'usuario'], 'o.cod_usuario = u.cod_usuario', ['nombres_usuario' => 'nombres', 'apellidos_usuario' => 'apellidos']);
            $select->join(['c' => 'carrera'], 'o.cod_carrera = c.cod_carrera', ['nombre_carrera' => 'alias_actual']);
            $select->where([
                'o.cod_orden' => $orderCode
            ]);
            $data = $table->selectWith($select);
            if ($data->count() == 0) {
                $res->addMsg("No se encontró la orden de pago No. $orderCode");
            } else {
                $orderData = $data->current();
                $order = new Order($orderData);
                //GETTING DETAILS
                if ($order->getCodTipoOrden() == Order::ASSIGNMENT) {
                    $this->addDetails($order);
                }
                $res->success();
                $res->setObj($order);
            }
        } catch (\Exception $ex) {
            $res->addMsg("No se pudo consultar la orden de pago $orderCode");
        }
        return $res;
    }

    private function addDetails(Order $order) {
        $detailTable = new TableGateway(['cop' => 'cursos_orden_pago'], $this->dbAdapter);
        $select = $detailTable->getSql()->select();
        $select->join(['h' => 'horario'], 'cop.cod_horario = h.cod_horario');
        $select->join(['cp' => 'curso_pensum'], 'h.cod_curso=cp.cod_curso and h.cod_pensum = cp.cod_pensum', ['nombre_curso' => 'nombre']);
        $select->order('h.cod_curso ASC');
        $select->where([
            'cop.cod_orden' => $order->getCodOrden()
        ]);
        $detailResult = $detailTable->selectWith($select)->toArray();
        foreach ($detailResult as $detailData) {
            $detail = new OrderDetail($detailData);
            $order->addDetail($detail);
        }
    }

    public function createOrderPDF(Order $order, PhpRenderer $renderer, $type = false) {
        $res = new R();
        //GETTING HTML TEXT
//        $html = $renderer->render('eep/order', [
//            'order' => $order,
//            'asgType' => $type
//        ]);
        /* ...
          Resto del código que genera el PDF
          ... */
        /* Limpiamos la salida del búfer y lo desactivamos */
        $html = $renderer->render('eep/order', [
            'order' => $order,
            'asgType' => $type,
            'regularDays' => $this->getGlobal(GM::ASSIGNMENT_DAYS, 5),
            'extDays' => $this->getGlobal(GM::EXT_ASSIGNMENT_DAYS, 5),
            'orderValidityDays' => $this->getGlobal(GM::PAYMENT_ORDER_VALIDITY, 0)
        ]);
        try {
            //$path = 'Orden-de-Pago-' . $order->getCodOrden() . '.pdf';
            $pdf = new Html2Pdf('L', 'Letter', 'es', true, 'UTF-8', [10, 10, 10, 10]); //('P', 'Letter', 'es', [mL, mT, mR, mB]);
            $pdf->pdf->SetDisplayMode('fullpage');
            $pdf->WriteHTML($html);
            //$pdf->Output($path); //, 'D'); //->MOVED TO THE CONTROLLER FOR ITS LOGGIN (ENTER IT INTO THE LOG)
            $res->success();
            $res->setObj($pdf);
        } catch (Html2PdfException $ex) {
            $res->addMsg('No se pudo generar el PDF: ' . $ex->getMessage() . '<br>' . $ex->getTraceAsString());
        }
        return $res;
    }

    public function getUserOrdersbyCareer($userCode, $includeActive = null, $includePayed = null): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC

        try {
            $table = new TableGateway(['o' => 'orden_pago'], $this->dbAdapter);
            $select = $table->getSql()->select();
            //GETTING CAREER NAME AND FILTERING ORDERS
            $select->join(['nc1' => 'nombre_carrera'], 'o.cod_carrera = nc1.cod_carrera', ['nombre_carrera' => 'nombre', 'alias_carrera' => 'alias']);
            $where = [
                'cod_usuario' => $userCode
            ];
            if ($includeActive != null) {
                $where['activa'] = $includeActive;
            }
            if ($includePayed != null) {
                $where['pagada'] = $includePayed;
            }
            $where[] = 'nc1.tiempo = (  select max(nc2.tiempo) from nombre_carrera nc2 
                                        where nc2.cod_carrera = nc1.cod_carrera 
                                        and nc2.tiempo <= o.fecha_generacion   )';
            $select->where($where);
            $select->order('o.fecha_generacion DESC');

            $ordersData = $table->selectWith($select)->toArray();
            $ordersByCareer = [];
            foreach ($ordersData as $orderData) {
                $orderCode = $orderData['cod_orden'];
                $careerName = $orderData['nombre_carrera'];
                $result = $this->getOrder($orderCode);
                if ($result->get() == false) {
                    $res = $result;
                    break;
                } else {
                    $ordersByCareer[$careerName][] = $result->getObj();
                }
            }
            if ($res->get() == true) {
                $res->setObj($ordersByCareer);
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar las órdenes de pago');
        }

        return $res;
    }

    public function setOrderInactive($orderCode): R {
        $res = new R();
        $table = new TableGateway('orden_pago', $this->dbAdapter);
        try {
            $result = $table->update([
                //SET
                'activa' => 0
                    ], [
                //WHERE
                'cod_orden' => $orderCode
            ]);
            if ($result == 0) {
                $res->addMsg("La orden No. $orderCode ya estaba inactiva");
            }
            $res->success();
        } catch (\Exception $ex) {
            $res->addMsg("No se pudo marcar como eliminada la orden de pago '$orderCode'");
        }
        return $res;
    }

    /**
     * 
     * @param type $orderCode
     * @param type $status : TRUE -> PAYED
     */
    private function updateOrderPayed(Order $order): R {
        $res = new R();
        $table = new TableGateway('orden_pago', $this->dbAdapter);
        try {
            $where = [
                'cod_orden' => $order->getCodOrden()
            ];
            //SEARCHING
            $result = $table->select($where);
            if ($result->count() != 1) {
                $res->addMsg("No se encontró la orden de pago No. " . $order->getCodOrden());
            } else {
                //UPDATING
                $payed = $order->getPagada();
                $set = [
                    //SET
                    'pagada' => $payed,
                    'cod_boleta' => empty($order->getCodBoleta()) ? null : $order->getCodBoleta(),
                    'fecha_pago' => $payed ? $order->getFechaPago() : null,
                    'fecha_pago_local' => $payed ? date('Y-m-d') : null,
                    'no_transaccion_banco' => $payed ? $order->getNoTransaccionBanco() : null,
                    'cod_banco' => $payed ? $order->getCodBanco() : null,
                ];
                if ($payed) {
                    $set['activa'] = false;
                }
                $table->update($set, $where);
                $res->success();
            }
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo actualizar el estado de pago de la orden de pago No. ' . $order->getCodOrden() . '. Error: ' . $ex->getMessage());
        }
        return $res;
    }

    public function confirmOrder($data): R {
        //READING DATA
//        $extension = $data['EXTENSION'] ?? null;
//        $bank = $data['BANCO'] ?? null;
//        $bollet = $data['NO_BOLETA_DEPOSITO'] ?? null;
//        $paymentDate = $data['FECHA_CERTIF_BCO'] ?? null;
//        $requestType = $data['TIPO_PETICION'] ?? null;
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //VALIDATING IT IS AN EEP ORDER
//        $unit = $data['UNIDAD'] ?? null;
//        $careerCode = $data['CARRERA'] ?? null;
        $personId = $data['CARNET'] ?? null;
        $orderCode = $data['ID_ORDEN_PAGO'] ?? null;
        if ($orderCode == null || $personId == null) {
            $res->failure("Para la orden No. $orderCode se encontraron campos vacíos - "/* UNIDAD: $unit. \nCARRERA: $careerCode. */ . "\nORDEN: $orderCode. \nESTUDIANTE: $personId.");
//        } elseif (Order::UNIDAD != $unit) {
//            $res->failure("La orden de pago No. $orderCode no es de la EEP");
        } else {
//            //SEARCHING CAREER
//            $table = new TableGateway('carrera', $this->dbAdapter);
//            try {
//                $result = $table->select([
//                    'cod_carrera' => $careerCode
//                ]);
//                if ($result->count() == 0) {
//                    $res->failure("En el sistema, la carrera ($careerCode) de la orden No. $orderCode no se encontró en el listado de la EEP");
//                }
//            } catch (\Exception $ex) {
//                $res->failure("No se pudo consultar la carrera indicada ($careerCode). Error:  " . $ex->getMessage());
//            }
//            if ($res->get() == true) {
            //CREATING ORDEN IN SIIF
            $result = $this->requestOrder($orderCode, $personId);
            if ($result->get() == false) {
                $res = $result;
                $res->addMsg("Código de Orden No. $orderCode");
            } else {
                $order = $result->getObj();
                $res = $this->updateOrderPayed($order);
                if ($res->get() == true) {
                    $res->setObj($order);
                }
            }
//            }
        }
        return $res;
    }

    public function createInscriptionOrder($userCode): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        //SEARCHING USER CURRENT CAREER AND ID
        $asgTable = new TableGateway(['ac' => 'asignacion_carrera'], $this->dbAdapter);
        $select = $asgTable->getSql()->select();
        $select->join(['u' => 'usuario'], 'ac.cod_usuario = u.cod_usuario', ['registro_academico', 'cui', 'pasaporte']);
        $select->join(['p' => 'pensum'], 'ac.cod_pensum = p.cod_pensum', []);
        $select->join(['c' => 'carrera'], 'p.cod_carrera = c.cod_carrera', ['cod_carrera', 'nombre_actual', 'degree' => 'cod_grado']);
        $select->where([
            'ac.cod_usuario' => $userCode,
            'activa' => true
        ]);
        try {
            $result = $asgTable->selectWith($select);
            if ($result->count() == 0) {
                $res->failure('No tienes ninguna carrera asignada actualmente.');
            } else {
                $data = $result->current();
                $careerCode = $data['cod_carrera'];
                $userId = $data['registro_academico'] ?? ($data['cui'] ?? $data['pasaporte']);
                $careerName = $data['nombre_actual'];
                $degree = $data['degree'];
            }
        } catch (\Exception $ex) {
            $res->failure('No se pudo buscar la carrera.');
        }
        if ($res->get() == true) {
            //CREATING ORDER
            $order = new Order();
            $order->setCodUsuario($userCode);
            $order->setIdPersona($userId);
            $order->setCarrera($careerCode);
            $order->setCodCarrera($careerCode);
            $order->setExtension(Order::EXTENSION);
            $order->setUnidad(Order::UNIDAD);
            $order->setNombreCarrera($careerName);
            $order->setCodTipoOrden(Order::INSCRIPTION);
            $order->setActiva(true);
            $order->setPagada(false);
            $order->setFechaGeneracion(date('Y-m-d'));
            $order->setFechaVencimiento(date('Y') . '-12-31');
            //CREATING DETAIL
            $detail = new OrderDetail();
            $detail->setAnio(date('Y'));
            switch ($degree) {
                case Order::DOCTORADO:
                    $rubro = Order::RUBRO_DOCTORADOS;
                    $variante = Order::VARIANTE_DOCTORADOS_INSCRIPCION;
                    $price = $this->getGlobal(GM::INSCRIPTION_PRICE, 1031);
                    break;
                case Order::MAESTRIA:
                case Order::ESPECIALIZACION:
                    $rubro = Order::RUBRO_MAESTRIAS;
                    $variante = Order::VARIANTE_MAESTRIAS_INSCRIPCION;
                    $price = $this->getGlobal(GM::INSCRIPTION_PRICE, 1031);
                    break;
            }
            $detail->setRubro($rubro);
            $detail->setVariante($variante);
            $detail->setSubtotal($price);
            $order->setMontoTotal($price);
            $order->addDetail($detail);
            //REQUEST ORDER CREATION
            $result = $this->requestNewOrderSIIF($order);
            if ($result->get() == false) {
                $res = $result;
            } else {
                //SAVING ORDER
                $order = $result->getObj();
                $res = $this->saveOrder($order);
                if ($res->get() == true) {
                    $res->setObj($order);
                }
            }
        }
        return $res;
    }

    public function setDescription($orderCode, $description): R {
        $res = new R();
        $res->success();
        $table = new TableGateway('orden_pago', $this->dbAdapter);
        try {
            $table->update([
                //SET
                'descripcion' => $description
                    ], [
                //WHERE
                'cod_orden' => $orderCode
            ]);
        } catch (\Exception $ex) {
            $res->failure("No se pudo actualizar la descripción de la orden de pago con texto \"$description\": " . $ex->getMessage());
        }
        return $res;
    }

    public function updateOrdersStatus(): R {
        $res = new R();
        $res->success(); //POSITIVE LOGIC
        try {
            $table = new TableGateway(['o' => 'orden_pago'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['cop' => 'cursos_orden_pago'], 'o.cod_orden = cop.cod_orden', []);
            $select->join(['h' => 'horario'], 'cop.cod_horario = h.cod_horario', []);
            $select->join(['i' => 'involucrado'], 'o.cod_orden = i.cod_orden', ['cod_tipo_acta'], Select::JOIN_LEFT);
            $select->where([
                'o.activa' => true,
                'o.cod_tipo_orden' => Order::ASSIGNMENT,
                '(i.cod_tipo_acta = ' . AM::CA_EXTEMPORARY . ' or i.cod_tipo_acta = ' . AM::CA_EXTRAORDINARY . ' or i.cod_tipo_acta is null)'
            ]);
            $select->columns([
                'cod_orden',
                'fecha_generacion',
                'fecha_inicio' => new Expression('MIN(h.fecha_inicio)')
            ]);
            $select->group('o.cod_orden');
            $select->group('o.fecha_generacion');
            $select->group('i.cod_tipo_acta');
            $orders = $table->selectWith($select)->toArray();
            $today = strtotime(date('Ymd'));
            $ordersCodesToSetInactive = [];
            $regularDays = $this->getGlobal(GM::ASSIGNMENT_DAYS, 5);
            $extDays = $this->getGlobal(GM::EXT_ASSIGNMENT_DAYS, 5);
            foreach ($orders as $order) {
                $orderCode = $order['cod_orden'];
                $actType = $order['cod_tipo_acta'] ?? false;
                switch ($actType) {
                    case AM::CA_EXTEMPORARY:
                        $date = $order['fecha_generacion'];
                        $days = $extDays;
                        break;
                    case AM::CA_EXTRAORDINARY:
                        $date = $order['fecha_generacion'];
                        $days = $extDays;
                        break;
                    default:
                        $date = $order['fecha_inicio'];
                        $days = $regularDays;
                        break;
                }
                $limit = strtotime("+ $days weekdays $date");
                if ($limit < $today) {
                    $ordersCodesToSetInactive[] = "$orderCode";
                }
            }
            if (count($ordersCodesToSetInactive) == 0) {
                $result = 0;
            } else {
                $where = implode(', ', $ordersCodesToSetInactive);
                //INACTIVATING PAYMENT ORDERS
                $result = $table->update([
                    //SET
                    'activa' => false
                        ], [
                    //WHERE
                    "cod_orden IN ($where)"
                ]);
            }
            $res->setObj("$result órdenes de pago cambiadas a inactivas");
        } catch (\Exception $ex) {
            $res->failure("Error cambiando las órdenes de pago: " . $ex->getMessage());
        }
        return $res;
    }

}
