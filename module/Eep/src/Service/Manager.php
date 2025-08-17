<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Eep\Entity\Result as R;
use Eep\Service\GeneralManager as GM;

class Manager {

    protected $dbAdapter;
    protected $generalManager;

    public function __construct(Adapter $dbAdapter) {
        $this->dbAdapter = $dbAdapter;
        $this->generalManager = new GM($dbAdapter);
    }

    public function getGlobal(int $paramCode, $default = null) {
        $result = $this->generalManager->getGlobalVariable($paramCode);
        if ($result->get() == false) {
            throw new \Exception("No se pudo obtener la variable global $paramCode");
        } else {
            $var = $result->getObj() ?? $default;
        }
        return $var;
    }

    public function beginTransaction() {
        $this->dbAdapter->getDriver()->getConnection()->beginTransaction();
    }

    public function commit() {
        $this->dbAdapter->getDriver()->getConnection()->commit();
    }

    public function rollback() {
        $this->dbAdapter->getDriver()->getConnection()->rollback();
    }

    public function testDBConnection(): R {
        $res = new R();
        try {
            $this->dbAdapter->getDriver()->getConnection()->connect();
            $res->success();
        } catch (\Exception $ex) {
            $res->failure('Error de conexión a la base de datos');
            $res->addError('Comprobar nombre de host de la base de datos');
        }
        return $res;
    }

}
