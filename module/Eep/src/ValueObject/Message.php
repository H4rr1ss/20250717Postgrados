<?php

namespace Eep\ValueObject;

use Eep\Entity\Result as R;

class Message {

    private $message;
    private $title;
    private $type;

    const YELLOW = 'warning';
    const BLUE = 'info';
    const GREEN = 'success';
    const RED = 'danger';

    public function __construct($title, $message, $type = self::RED) {
        $this->title = $title;
        if (is_a($message, R::class)) {
            $type = $message->getType();
            $message = $message->getMsg();
        }
        $this->message = self::makeHtmlList($message);
        $this->type = $type;
    }

    private static function recursiveArrayToHtmlList($array, $includeTextHeadersInArray = false, $first = false) {
        if (is_array($array)) {
            $list = (count($array, COUNT_RECURSIVE) > 1 && $first) || $includeTextHeadersInArray;
            $text = $list ? "<ul>\n" : '';
            foreach ($array as $key => $value) {
                if ($includeTextHeadersInArray && (!is_int($key)) && !empty($key)) {
                    $text .= "<li>$key</li>\n<ul>\n";
                }
                $txtResult = Message::recursiveArrayToHtmlList($value);
                if (is_array($value) || ($includeTextHeadersInArray && (!is_int($key)) && !empty($key))&&(is_array($value))) {
                    $text .= $txtResult;
                } else {
                    $text .= "<li>$txtResult</li>\n";
                }
                if ($includeTextHeadersInArray && (!is_int($key)) && !empty($key)) {
                    $text .= "</ul>\n";
                }
            }
            $text .= $list ? "</ul>\n" : '';
        } else {
            //CHECKING IF OBJECT CAN BE CONVERTED TO STRING
            if ((!is_object($array) && settype($array, 'string') !== false ) ||
                    ( is_object($array) && method_exists($array, '__toString') )) {
                $text = "$array";
            } else {
                $text = var_export($array, true);
            }
        }
        return $text;
    }

    public static function makeHtmlList($messages, $includeTextHeadersInArray = false) {
        if (empty($messages)) {
            return "";
        } elseif (is_array($messages)) {
            return (Message::recursiveArrayToHtmlList($messages, $includeTextHeadersInArray, true));
        } elseif (is_string($messages)) {
            return $messages;
        } else {
            return var_export($messages, true);
        }
    }

    public function getMessage() {
        return $this->message;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getType() {
        return $this->type;
    }

}
