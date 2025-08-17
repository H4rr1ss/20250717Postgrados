<?php

namespace Eep\Entity;

use Eep\ValueObject\Message as M;

class Result {

    private $result;
    private $detail;
    private $type;
    private $object;
    private $error;

    const INFO = M::BLUE;
    const WARNING = M::YELLOW;
    const ERROR = M::RED;
    const SUCCESS = M::GREEN;

    public function __construct($result = null, Array $detail = null, $type = null, $object = null) {
        $this->result = (empty($result)) ? false : $result;
        $this->detail = (empty($detail)) ? [] : $detail;
        $this->type = (empty($type)) ? self::ERROR : $type;
        $this->object = $object;
        $this->error = null;
    }

    public function get() {
        return $this->result;
    }

    public function getMsg() {
        return $this->detail;
    }

    public function getType() {
        return $this->type;
    }

    public function getObj() {
        return $this->object;
    }

    public function hasDetail() {
        return !empty($this->detail);
    }

    public function addMsg($detail) {
        if (!empty($detail)) {
            if (is_a($detail, Result::class)) {
                $this->detail = array_merge($this->detail, $detail->getMsg());
                if (!empty($detail->getError())) {
                    $this->addError($detail->getError());
                }
            } elseif (is_array($detail)) {
                $this->detail = array_merge($this->detail, $detail);
            } elseif (is_string($detail)) {
                $this->detail[] = $detail;
            } else {
                $this->detail[] = var_export($detail, true);
            }
        }
    }

    public function addError($error) {
        if (!empty($error)) {
            if (is_a($error, \Exception::class)) {
                $this->error[] = $error->getMessage();
            } elseif (is_array($error)) {
                if ($this->error == null) {
                    $this->error = $error;
                } else {
                    $this->error = array_merge($this->error, $error);
                }
            } elseif (is_string($error)) {
                $this->error[] = $error;
            } else {
                $this->error[] = var_export($error, true);
            }
        }
    }

    public function getError() {
        return $this->error;
    }

    public function set($result) {
        $this->result = $result;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function setObj($object) {
        $this->object = $object;
    }

    public function success($message = null) {
        if ($message != null) {
            $this->addMsg($message);
        }
        $this->setType(self::SUCCESS);
        $this->result = true;
    }

    public function failure($message = null, $error = null) {
        if ($message != null) {
            $this->addMsg($message);
        }
        $this->setType(self::ERROR);
        $this->result = false;
        if ($error != null) {
            $this->addError($error);
        }
    }

    public function warning($message = null) {
        if ($message != null) {
            $this->addMsg($message);
        }
        $this->setType(self::WARNING);
        $this->result = true;
    }

}
