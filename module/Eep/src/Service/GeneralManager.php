<?php

namespace Eep\Service;

use Eep\ValueObject\Message;
use Eep\Entity\Result as R;
use Zend\Db\TableGateway\TableGateway;

class GeneralManager {

    private $dbAdapter;

    public function __construct($dbAdapter) {
        $this->dbAdapter = $dbAdapter;
    }

    const MONTHS = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const REVISION_DAYS = 1;
    const MINIMUM_GRADE_APPROVAL = 2;
    const SERVER_REQUEST_PASSWORD = 3;
    const ASSIGNMENT_DAYS = 4;
    const PAYMENT_ORDER_VALIDITY = 5;
    const EXT_ASSIGNMENT_DAYS = 6;
    const INSCRIPTION_PRICE = 7;
    const SYNC_SATU = 8;
    const USER = 'eep_client_user';

    public static function resultToText(R $result) {
        $text = '';
        if (!empty($result->getMsg())) {
            $text .= "Detalle:";
            $text .= Message::makeHtmlList($result->getMsg());
        }
        if (!empty($result->getError())) {
            $text .= "Error:";
            $text .= Message::makeHtmlList($result->getError());
        }
        return $text;
    }

    public static function Msg($message) {
        return new Message("Pruebas", str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", str_replace("\n", "<br/>", str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", var_export($message, true)
                )))
                , Message::BLUE);
    }

    public function getGlobalVariable($paramCode, $revisionDate = null): R {
        $res = new R();
        $res->success();
        try {
            $table = new TableGateway('detalle_parametro', $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->order('fecha_ingreso DESC');
            $select->where([
                'cod_parametro' => $paramCode
            ]);
            if ($revisionDate != null && strtotime($revisionDate) != false) {
                $select->where([
                    "fecha_ingreso <= $revisionDate"
                ]);
            }
            $result = $table->selectWith($select);
            if ($result->count() == 0) {
                $res->setObj(null);
            } else {
                $res->setObj($result->current()['valor']);
            }
        } catch (\Exception $ex) {
            $res->failure("No se pudieron buscar las variables.", $ex);
        }
        return $res;
    }

}
