<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\Sql\Select;
use Zend\Authentication\AuthenticationService;
use Eep\Entity\Result;
use Eep\ValueObject\Message;
use Eep\Entity\Role;

class LogManager extends Manager {

    private $config;
    private $authService;

    //LOG STATES
    const SUCCESS = 1;
    const WARNING = 2;
    const FAILURE = 3;
    const ERROR = 4;
    //UNDEFINED
    const UNDEFINED_STATE = 5;
    const UNDEFINED_IDENTITY = -1; //ADDED IN THE MIGRATION SUPPORT
    const UNDEFINED_ACTION = 255;
    //SYSTEM USER
    const SYSTEM_USER_CODE = -2;
    //OPERATION TYPES
    const VIEW = 1;
    const CREATE = 2;
    const READ = 3;
    const UPDATE = 4;
    const DELETE = 5;
    //LIMIT
    const LOG_LIMIT_ROWS = 1000;

    public function __construct(Adapter $dbAdapter, $config, AuthenticationService $authService) {//OR ENTITYMANAGER
        parent::__construct($dbAdapter);
        $this->config = $config;
        $this->authService = $authService;
    }

    public function addLog($controllerName, $actionName, $operation, $detail, $ip, $status = self::UNDEFINED_STATE, $roleCode = null, $internalSystem = false) {
        //VALIDATING ACTION ID
        if (isset($this->config['access_filter'][$controllerName][$actionName]['code'])) {
            $actionCode = $this->config['access_filter'][$controllerName][$actionName]['code'];
        } else {
            $actionCode = self::UNDEFINED_ACTION;
        }
        //VALIDATING USER ID
        if ($this->authService->hasIdentity()) {
            $userCode = $this->authService->getIdentity();
        } elseif ($internalSystem) {
            $userCode = self::SYSTEM_USER_CODE;
        } else {
            $userCode = self::UNDEFINED_IDENTITY;
        }
        //VALIDATING DETAIL AND STATUS
        if ($detail == null) {
            $text = null;
        } elseif (is_string($detail)) {
            $text = $detail;
            $finalState = $status;
        } elseif (is_array($detail)) {
            $text = Message::makeHtmlList($detail, true);
        } elseif (is_a($detail, Result::class)) {
            if (!empty($detail->getMsg())) {
                $message = $detail->getMsg();
                $text = Message::makeHtmlList($message);
            } else {
                $text = null;
            }
            if (!empty($detail->getError())) {
                if ($text == null) {
                    $text = '';
                }
                $text .= Message::makeHtmlList(['Errores:' => $detail->getError()], true);
            }
            if ($detail->getType() == R::WARNING) {
                $finalState = self::WARNING;
            } else {
                $finalState = ($detail->get() == true) ? self::SUCCESS : self::ERROR;
            }
        } elseif (is_a($detail, Message::class)) {
            if (!empty($detail->getMessage())) {
                $text = $detail->getMessage();
            } else {
                $text = null;
            }
            switch ($detail->getType()) {
                case Message::GREEN:
                    $finalState = self::SUCCESS;
                    break;
                case Message::YELLOW:
                    $finalState = self::WARNING;
                    break;
                case Message::RED:
                    $finalState = self::FAILURE;
                    break;
                case Message::BLUE:
                default:
                    $finalState = self::UNDEFINED_STATE;
                    break;
            }
        } else {
            $text = null;
        }
        if (!isset($finalState) && $status == null) {
            $finalState = self::UNDEFINED_STATE;
        } else {
            //LEAVING DETAIL STATE IF GIVEN STATUS IS UNDEFINED
            if (!isset($finalState) || (isset($finalState) && $status != self::UNDEFINED_STATE)) {
                $finalState = $status;
            }
        }

        $logTable = new TableGateway('bitacora', $this->dbAdapter);
        try {
            $logTable->insert([
                'tiempo' => new Expression('now()'),
                'detalle' => $text,
                'cod_usuario' => $userCode,
                'cod_accion' => $actionCode,
                'cod_estado' => $finalState,
                'cod_rol' => $roleCode,
                'cod_operacion' => $operation ?? self::VIEW,
                'ip' => $ip
            ]);
        } catch (\Exception $e) {
            throw new \Exception("No está funcionando el servicio de bitácoras para llevar un registro de éstas<br>" . $e->getMessage());
        }
    }

    public function getLog($userCode, $startDate, $finishDate): R {
        $res = new R();
        $table = new TableGateway(['b' => 'bitacora'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->columns(['tiempo', 'detalle','cod_bitacora','ip']);
        $select->join(['e' => 'estado_accion'], 'b.cod_estado = e.cod_estado', ['estado' => 'nombre', 'cod_estado']);
        $select->join(['ta' => 'operacion'], 'b.cod_operacion = ta.cod_operacion', ['operacion' => 'nombre', 'cod_operacion']);
        $select->join(['a' => 'accion'], 'b.cod_accion = a.cod_accion', ['accion' => 'nombre']);
        $select->join(['r' => 'rol'], 'b.cod_rol = r.cod_rol', ['rol' => 'nombre'], Select::JOIN_LEFT);
        //TIME ADJUSTING
        $startDate = "'$startDate 00:00:00'";
        $finishDate = "'$finishDate 23:59:59'";
        $studentRole = Role::ESTUDIANTE;
        $select->where([
            'cod_usuario' => $userCode,
            "tiempo >= $startDate",
            "tiempo <= $finishDate",
                //"(b.cod_rol = $studentRole or b.cod_rol is NULL)"
        ]);
        $select->order('tiempo desc');
        $select->limit(self::LOG_LIMIT_ROWS);
        try {
            $result = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($result);
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo realizar la consulta de la bitácora: ' . $ex->getMessage());
        }
        return $res;
    }

}
