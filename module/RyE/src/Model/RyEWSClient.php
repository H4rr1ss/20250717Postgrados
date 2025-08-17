<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace RyE\Model;

use Eep\Entity\Result as R;
use Zend\Soap\Client;

/**
 * Description of RyEWSClient
 *
 * @author tobias
 */
class RyEWSClient {

    static $WS_CONSULTA = "http://registro.usac.edu.gt/WS/consultaEstudianteRyEv2.0.php?wsdl";
    static $WS_CONSULTA_DEPENDENCIA = 'UA02';
    static $WS_CONSULTA_LOGIN = 'wsarq';
    static $WS_CONSULTA_PWD = 'PPs/CEJlTA5A6FY';

    public function getInscripcion($id, $anio): R {
        $res = new R();
        $xml_consulta = "<SOLICITUD_DATOS_RYE>
                    <DEPENDENCIA>" . RyEWSClient::$WS_CONSULTA_DEPENDENCIA . "</DEPENDENCIA>
                    <LOGIN>" . RyEWSClient::$WS_CONSULTA_LOGIN . "</LOGIN>
                    <PWD>" . RyEWSClient::$WS_CONSULTA_PWD . "</PWD>
                    <CARNET>" . $id . "</CARNET>
                    <CICLO_ACTIVO>" . $anio . "</CICLO_ACTIVO>
                </SOLICITUD_DATOS_RYE>";
        try {

            $opts = [
                'http' => [
                    'user_agent' => 'PHPSoapClient'
                ]
            ];
            $context = stream_context_create($opts);
            $soapClientOptions = [
                'soap_version' => SOAP_1_1, //Especificando la version 1_1 para compatibilidad con los WS creados por RyE
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => $context
            ];
            $client = new Client(RyEWSClient::$WS_CONSULTA, $soapClientOptions);
            $result = $client->datosGenerales($xml_consulta);
            //$texto = iconv(mb_detect_encoding($result, mb_detect_order(), true), "ISO-8859-1", $result);
            $texto = iconv(mb_detect_encoding($result, mb_detect_order(), true), "UTF-8//TRANSLIT", $result);
            //$texto = utf8_decode($result);
            $res->setObj(new \SimpleXMLElement($texto));
            $res->success();
        } catch (\SoapFault $s) {
            $res->addMsg('Error de RYE: [' . $s->faultcode . '] ' . $s->faultstring);
        } catch (\Exception $e) {
            $res->addMsg('Error de RYE: ' . $e->getMessage());
        }
        return $res;
    }

}
