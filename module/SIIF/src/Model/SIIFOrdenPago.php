<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace SIIF\Model;

use Zend\Soap\Client;
use Eep\Entity\Result as R;

/**
 * Description of OrdenPago
 *
 * @author tobias
 */
class SIIFOrdenPago {

    var $siif_wsdl_pruebas = "http://pruebassiif.usac.edu.gt/WSGeneracionOrdenPago/WSGeneracionOrdenPagoSoapHttpPort?wsdl";
    var $siif_wsdl = "http://arquitectura.farusac.edu.gt/ws/WSGeneracionOrdenPagoSoapHttpPort.xml";
    /*var $siif_wsdl = "http://arquitectura.usac.edu.gt/ws/WSGeneracionOrdenPagoSoapHttpPort.xml";*/

    const SIIF_WSDL = "https://siif.usac.edu.gt/WSGeneracionOrdenPagoV2/WSGeneracionOrdenPagoV2SoapHttpPort?WSDL";
    const SIIF_PRUEBAS_WSDL = "https://pruebassiif.usac.edu.gt/WSGeneracionOrdenPagoV2/WSGeneracionOrdenPagoV2SoapHttpPort?WSDL";
    const SIIF_PARNASO = "http://parnaso.usac.edu.gt:7777/WSGeneracionOrdenPagoV2/WSGeneracionOrdenPagoV2SoapHttpPort";
    const SIIF_FIXED = "http://postgrados.farusac.edu.gt/WSGeneracionOrdenPagoV2SoapHttpPort-fixed.xml?WSDL";
    /* const SIIF_FIXED = "http://postgrados.arquitectura.usac.edu.gt/WSGeneracionOrdenPagoV2SoapHttpPort-fixed.xml?WSDL";*/

    public function generarOrdenPago($extension, $unidad, $carrera, $carnet, $nombre, $detalle) {
        //crear la boleta en el SIIF
        $xmlSolicitud = $this->crearXMLPeticionSIIF($extension, $unidad, $carnet, $carrera, $nombre, $detalle);
        return $this->requestSIIFGenerarOrdenPago($xmlSolicitud);
    }

    private function crearXMLPeticionSIIF($extension, $unidad, $estudiante, $carrera, $nombre, $array_detalle) {
        $detailXml = "";
	$totalAmmount = 0;

	if (strlen(strval($estudiante)) > 9){
		$carnet = "999999999";
		$cui = $estudiante;
	} else {
		$carnet = $estudiante;
		$cui = "";
	}

        foreach ($array_detalle as $detail) {
            $detailXml .= "<DETALLE_ORDEN_PAGO>" .
                    "<ANIO_TEMPORADA>" . $detail->getAnio() . "</ANIO_TEMPORADA>" .
                    "<ID_RUBRO>" . $detail->getRubro() . "</ID_RUBRO>" .
                    "<ID_VARIANTE_RUBRO>" . $detail->getVariante() . "</ID_VARIANTE_RUBRO>" .
                    //"<TIPO_CURSO>" . $detalle['tipo_curso'] . "</TIPO_CURSO>" .
                    '<TIPO_CURSO>' . (empty($detail->getCodCurso()) ? '' : 'CURSO') . '</TIPO_CURSO>' .
                    "<CURSO>" . (empty($detail->getCodCurso()) ? '' :  $carrera . '.' . $detail->getCodCurso()) . "</CURSO>" .
                    "<SECCION>" . $detail->getSeccion() . "</SECCION>" .
                    "<SUBTOTAL>" . $detail->getSubtotal() . "</SUBTOTAL>" .
                    "</DETALLE_ORDEN_PAGO>";
            $totalAmmount += $detail->getSubtotal();
        }
        
        $studentData = "<CARNET>" . $carnet . "</CARNET>" . "<CUI>" . $cui . "</CUI>" .
                "<UNIDAD>" . $unidad . "</UNIDAD>" .
                "<EXTENSION>" . str_pad($extension, 2, "0", STR_PAD_LEFT) . "</EXTENSION>" .
//                "<EXTENSION>" . O::EXTENSION . "</EXTENSION>" .
                "<CARRERA>" . str_pad($carrera, 2, "0", STR_PAD_LEFT) . "</CARRERA>" .
//                "<CARRERA>14</CARRERA>" .
                "<NOMBRE>" . substr($nombre, 0, 100) . "</NOMBRE>" . //IF CAREER NAME IS TOO LONG, SIIF RETURNS ERROR
                "<MONTO>" . $totalAmmount . "</MONTO>";

        return "<GENERAR_ORDEN>" . $studentData . $detailXml . "</GENERAR_ORDEN>";
    }

    function requestSIIFGenerarOrdenPago($pxml): R {
        $res = new R();
        /* RESPONSE:
          <RESPUESTA>
          <CODIGO_RESP>1</CODIGO_RESP>
          <DESCRIPCION>Transaccion exitosa.</DESCRIPCION>
          <ID_ORDEN_PAGO>9491426</ID_ORDEN_PAGO>
          <UNIDAD>2</UNIDAD>
          <EXTENSION>0</EXTENSION>
          <CARRERA>80</CARRERA>
          <CARNET>201314572</CARNET>
          <NOMBRE>Doctorado en Arquitectura</NOMBRE>
          <FECHA>20190211</FECHA>
          <CHECKSUM>59667</CHECKSUM>
          <MONTO>800</MONTO>
          <RUBROPAGO>102</RUBROPAGO>
          </RESPUESTA>
         */
	try {
            //Especificando la version 1_1 para compatibilidad con los WS creados por Procesamiento de datos..
            $client = new \Zend\Soap\Client(self::SIIF_FIXED, array("soap_version" => SOAP_1_1));
            //$client = new \Zend\Soap\Client($this->siif_wsdl, array("soap_version" => SOAP_1_1));
	    $response = $client->generarOrdenPago(["pxml" => $pxml]);
	    $encoding = mb_detect_encoding($response->result, mb_detect_order(), true);
            $texto = iconv($encoding, "UTF-8//TRANSLIT", $response->result);
            //$texto = iconv($encoding, "ISO-8859-1//TRANSLIT", $response->result);
            $xmlResult = new \SimpleXMLElement($texto);
            if (empty($xmlResult)) {
                $res->addMsg('No se obtuvo una respuesta del SIIF');
            } elseif (empty($xmlResult->{'CODIGO_RESP'}) && ($xmlResult->{'CODIGO_RESP'} != 0)) {
                $res->addMsg('No se obtuvo un código de respuesta de la solicitud de generación de órden de pago al SIIF"' . htmlspecialchars($texto) . '"');
            } else {
                $res->success();
                $res->setObj($xmlResult);
            }
        } catch (\SoapFault $s) {
            $res->addMsg('Error del SIIF: [' . $s->faultcode . '] ' . $s->faultstring); // . $pxml);
//            $res->addMsg(htmlspecialchars($pxml));
        } catch (\Exception $e) {
            $res->addMsg('Error del SIIF: ' . $e->getMessage());
//            $res->addMsg(htmlspecialchars($pxml));
        }
        return $res;
    }

    function ordenPago($orderCode, $userRegAcad): R {
        $res = new R();
        /*
         * <CONSULTA_ORDEN>
          <ID_ORDEN_PAGO></ID_ORDEN_PAGO>
          <ID_PERSONA></ID_PERSONA>
          </CONSULTA_ORDEN>
         */
        $pxml = "<CONSULTA_ORDEN>
          <ID_ORDEN_PAGO>$orderCode</ID_ORDEN_PAGO>
          <ID_PERSONA>$userRegAcad</ID_PERSONA>
          </CONSULTA_ORDEN>";
        try {
            //Especificando la version 1_1 para compatibilidad con los WS creados por Procesamiento de datos..
            $client = new Client(self::SIIF_FIXED, array("soap_version" => SOAP_1_1));
            $response = $client->consultaOrdenPago(["pxml" => $pxml]);
            $encoding = mb_detect_encoding($response->result, mb_detect_order(), true);
            $texto = iconv($encoding, "UTF-8//TRANSLIT", $response->result);
            //$texto = iconv($encoding, "ISO-8859-1//TRANSLIT", $response->result);
            $xmlResult = new \SimpleXMLElement($texto);
            if (empty($xmlResult)) {
                $res->addMsg('No se obtuvo una respuesta del SIIF');
            } elseif (empty($xmlResult->{'CODIGO_RESP'})) {
                $res->addMsg('No se obtuvo un código de respuesta de la solicitud de generación de órden de pago al SIIF "' . htmlspecialchars($pxml) . '"');
            } else {
                $res->success();
                $res->setObj($xmlResult);
            }
        } catch (\SoapFault $s) {
            $res->addMsg('Error del SIIF: [' . $s->faultcode . '] ' . $s->faultstring);
        } catch (exc\Exception $e) {
            $res->addMsg('Error del SIIF: ' . $e->getMessage()); // . "Texto: \"$texto\". Enconding: \"$encoding\"");
//            $res->addMsg(htmlspecialchars($pxml));
        }
        return $res;
    }

}
