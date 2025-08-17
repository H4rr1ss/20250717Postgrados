<?php

namespace Eep\Controller;

use Eep\ValueObject\Message;

class GP {//gral. purpose class

    const MONTHS = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public static function Msg($message) {
        return new Message("Pruebas", str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", str_replace("\n", "<br/>", str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", var_export($message, true)
                )))
                , Message::BLUE);
    }

}

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

