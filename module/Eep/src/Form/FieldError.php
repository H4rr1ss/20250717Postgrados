<?php

namespace Eep\Form;

class FieldError {

    const BETWEEN = [
        \Zend\Validator\Between::NOT_BETWEEN => "El número debe estar entre %min% y %max% (incluyéndolos)", //"The input is not between '%min%' and '%max%', inclusively",
        \Zend\Validator\Between::NOT_BETWEEN_STRICT => "El número debe estar estrictamente entre %min% y %max% (sin incluir esos números)", //"The input is not strictly between '%min%' and '%max%'",
        \Zend\Validator\Between::VALUE_NOT_NUMERIC => "Sólo números son permitidos", // "The min ('%min%') and max ('%max%') values are numeric, but the input is not",
        \Zend\Validator\Between::VALUE_NOT_STRING => "El valor mínimo ('%min%') y máximo ('%max%') son cadenas no numéricas, pero la entrada no es una cadena de caracteres"//"The min ('%min%') and max ('%max%') values are non-numeric strings, but the input is not a string",
    ];
    const NOT_EMPTY = [
        \Zend\Validator\NotEmpty::IS_EMPTY => "Debe llenar este campo", //"Value is required and can't be empty",
        \Zend\Validator\NotEmpty::INVALID => "\"%value%\" no es de un tipo de dato válido"//"Invalid type given. String, integer, float, boolean or array expected",
    ];
    const STRING_LENGTH = [
        \Zend\Validator\StringLength::INVALID => "\"%value%\" no es de un tipo de dato válido", //"Invalid type given. String, integer, float, boolean or array expected",
        \Zend\Validator\StringLength::TOO_SHORT => "Debe ser de más de %min% caracter(es) de largo",
        \Zend\Validator\StringLength::TOO_LONG => "Debe ser menor de %max% caracteres de largo",
    ];
    const DIGITS = [
        \Zend\Validator\Digits::NOT_DIGITS => "Solamente se pueden ingresar números", //"The input must contain only digits",
        \Zend\Validator\Digits::STRING_EMPTY => "Debe llenar este campo numérico", //"The input is an empty string",
        \Zend\Validator\Digits::INVALID => "\"%value%\" no es de un tipo de dato válido"//"Invalid type given. String, integer or float expected",
    ];
    const EXTENSION = [
        \Zend\Validator\File\Extension::FALSE_EXTENSION => "La extensión del archivo es incorrecta",
        \Zend\Validator\File\Extension::NOT_FOUND => "No se puede leer el archivo o no existe",
    ];
    const UPLOAD_FILE = [
        \Zend\Validator\File\UploadFile::INI_SIZE => "El archivo excede al tamaño permitido (INI).",
        \Zend\Validator\File\UploadFile::FORM_SIZE => "El archivo excede el tamaño definido por el formulario",
        \Zend\Validator\File\UploadFile::PARTIAL => "El archivo fue subido sólo parcialmente",
        \Zend\Validator\File\UploadFile::NO_FILE => "El archivo no se ha podido subir",
        \Zend\Validator\File\UploadFile::NO_TMP_DIR => "No se encontró el directorio temporal para el archivo",
        \Zend\Validator\File\UploadFile::CANT_WRITE => "El archivo no se puede escribir (guardar)",
        \Zend\Validator\File\UploadFile::EXTENSION => "Una extensión de PHP dio error al subir el archivo",
        \Zend\Validator\File\UploadFile::ATTACK => "El archivo fue subido de forma ilegal. Se puede deber a un posible ataque.",
        \Zend\Validator\File\UploadFile::FILE_NOT_FOUND => "No se encontró el archivo",
        \Zend\Validator\File\UploadFile::UNKNOWN => "Error desconocido al subir el archivo",
    ];
    const FILE_COUNT = [
        \Zend\Validator\File\Count::TOO_MANY => "Muchos archivos seleccionados, '%max%' son permitidos. Se obtuvieron '%count%'",
        \Zend\Validator\File\Count::TOO_FEW => "Muy pocos archivos, debe ser al menos '%min%'. Se obtuvieron '%count%'",
    ];
    const FILES_SIZE = [
        \Zend\Validator\File\FilesSize::TOO_BIG => "Los archivos deben de sumar un tamaño máximo de '%max%'. Los archivos sumaron '%size%'",
        \Zend\Validator\File\FilesSize::TOO_SMALL => "Los archivos deben de sumar un tamaño mínimo de '%min%'. Los archivos sumaron '%size%'",
        \Zend\Validator\File\FilesSize::NOT_READABLE => "Uno o más archivos no se pudieron leer",
    ];
    const EMAIL_ADDRESS = [
        \Zend\Validator\EmailAddress::INVALID => "Tiene que ser un tipo de dato válido, se esperaba una cadena de caracteres",
        \Zend\Validator\EmailAddress::INVALID_FORMAT => "El valor no es una dirección de correo vaĺida. Utilice el formato parte-local@nombre-host",
        \Zend\Validator\EmailAddress::INVALID_HOSTNAME => "'%hostname%' no es un nombre de host válido para dirección de correo",
        \Zend\Validator\EmailAddress::INVALID_MX_RECORD => "'%hostname%' no parece tener un registro MX o A válidos para la dirección de correo",
        \Zend\Validator\EmailAddress::INVALID_SEGMENT => "'%hostname%' no está en un segmento de red ruteable. La dirección de correo podría no ser resuelta en una red pública",
        \Zend\Validator\EmailAddress::DOT_ATOM => "'%localPart%' no se puede encontrar como formato dot-atom",
        \Zend\Validator\EmailAddress::QUOTED_STRING => "'%localPart%' no se puede encontrar como formato quoted-string",
        \Zend\Validator\EmailAddress::INVALID_LOCAL_PART => "'%localPart%' no es una parte local válida para dirección de correo",
        \Zend\Validator\EmailAddress::LENGTH_EXCEEDED => "El tamaño del correo excede el límite permitdo",
        \Zend\Validator\Hostname::CANNOT_DECODE_PUNYCODE => "La entrada parece ser un hostname de DNS pero la notación punycode no puede ser decodificada",
        \Zend\Validator\Hostname::INVALID => "Tipo de dato inválido, se espera una cadena",
        \Zend\Validator\Hostname::INVALID_DASH => "La entrada parece ser un hostname de DNS pero contiene un raya en una posición inválida",
        \Zend\Validator\Hostname::INVALID_HOSTNAME => "La entrada no concuerda con la estructura esperada para un hostname de DNS",
        \Zend\Validator\Hostname::INVALID_HOSTNAME_SCHEMA => "La entrada parece ser un hostname de DNS pero no concuerda con el esquema TLD '%tld%'",
        \Zend\Validator\Hostname::INVALID_LOCAL_NAME => "La entrada no parece ser un nombre de red local válido",
        \Zend\Validator\Hostname::INVALID_URI => "La entrada no parece ser un hostname URI válido",
        \Zend\Validator\Hostname::IP_ADDRESS_NOT_ALLOWED => "La entrada parecer ser una dirección IP, pero las direcciones IP no están permitidas",
        \Zend\Validator\Hostname::LOCAL_NAME_NOT_ALLOWED => "La entrada parece ser una red local pero las redes locales no están permitidas",
        \Zend\Validator\Hostname::UNDECIPHERABLE_TLD => "La entrada parece ser un hostname de DNS pero no se puede extraer la parte del TLD",
        \Zend\Validator\Hostname::UNKNOWN_TLD => "La entrada parace ser un hostname de DNS pero el TLD no concuerda contra alguna de la lista conocida",
    ];
    const DATE = [
        \Zend\Validator\Date::INVALID => "Tipo de dato inválido. Debe obtenerse un número, cadena, arreglo o tipo datetime",
        \Zend\Validator\Date::INVALID_DATE => "No parece ser un tiempo válido",
        \Zend\Validator\Date::FALSEFORMAT => "La entrada no cumple con el formato '%format%'",
    ];
    const GREATER_THAN = [
        \Zend\Validator\GreaterThan::NOT_GREATER => "Debe ser mayor a '%min%'",
        \Zend\Validator\GreaterThan::NOT_GREATER_INCLUSIVE => "Debe ser mayor o igual a '%min%'"
    ];
    const LESS_THAN = [
        \Zend\Validator\LessThan::NOT_LESS => "Debe ser menor a '%max%'",
        \Zend\Validator\LessThan::NOT_LESS_INCLUSIVE => "Debe ser menor o igual a '%max%'"
    ];
    const REGEX = [
        \Zend\Validator\Regex::INVALID => "Tipo de dato inválido, se esperaba una cadena, un entero o un número de punto flotante",
        \Zend\Validator\Regex::NOT_MATCH => "La entrada no tiene el patrón correspondiente", // '%pattern%'",
        \Zend\Validator\Regex::ERROROUS => "Hubo un error interno al intentar usar el patrón '%pattern%'",
    ];
    const DECIMAL_REGEX = [
        \Zend\Validator\Regex::INVALID => "Tipo de dato inválido, se esperaba una cadena, un entero o un número de punto flotante",
        \Zend\Validator\Regex::NOT_MATCH => "La nota debe tener como máximo 2 decimales. Por ejemplo: 5.25.", // '%pattern%'",
        \Zend\Validator\Regex::ERROROUS => "Hubo un error interno al intentar usar el patrón '%pattern%'",
    ];
    const IN_ARRAY = [
        \Zend\Validator\InArray::NOT_IN_ARRAY => 'El elemento "%value%" no es parte de las opciones',
    ];

}
