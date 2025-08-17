<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\FileInput;
use Eep\Form\FieldError;
use Eep\Form\CandidateForm as CF;

class FileCandidatesForm extends Form {

    const FORM_NAME = 'FileCandidatesForm';
    const FILE = 'file';
    const SUBMIT = "submitFile";

    private $nationalities;
    private $cohorts;
    private $degrees;
    private $careers;

    public function __construct($url, $nationalities, $cohorts, $degrees, $careers) {//THE URL REDIRECTS TO THE CORRECT ACTION CONTROLLER
        parent::__construct(self::FORM_NAME);
        $this->nationalities = $nationalities;
        $this->cohorts = $cohorts;
        $this->degrees = $degrees;
        $this->careers = $careers;

        $this->setAttribute('method', 'post');
        //SETTING BINARY ENCODING
        $this->setAttribute('enctype', 'multipart/form-data');
        $this->setAttribute('action', $url); //SO THE FORM REDIRECTS TO THE CORRECT ACTION
        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        $this->add([
            'type' => 'file',
            'name' => self::FILE,
            'attributes' => [
                'id' => self::FILE,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Subir archivo de aspirantes',
            ],
        ]);
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'value' => 'Cargar Archivo',
                'id' => self::SUBMIT,
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //WITH FILEINPUT, VALIDATORS ARE EVALUATED FIRST (isValid)
        //AND THE FILTER ARE APPLIED LATER, CALLING THE DATA (getData)
        $inputFilter->add([
            'type' => FileInput::class,
            'name' => self::FILE,
            'required' => true,
            'validators' => [
                [
                    'name' => 'FileUploadFile',
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::UPLOAD_FILE
                    ]
                ],
                [
                    'name' => 'FileCount',
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 0,
                        'max' => 1,
                        'messages' => FieldError::FILE_COUNT
                    ]
                ],
                [
                    'name' => 'FileExtension',
                    'break_chain_on_failure' => true,
                    'options' => [
                        'extension' => ['csv'],
                        'messages' => FieldError::EXTENSION
                    ]
                ],
                [
                    'name' => 'FileFilesSize',
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 1,
                        'max' => 2 * 1024 * 1024 - 1, //2MB - 1
                        'messages' => FieldError::FILES_SIZE
                    ]
                ]
            ],
            'filters' => [
            //THIS IS COMMENTED BECAUSE ACTUALLY WILL NOT BE NECCESARY TO SAVE THE FILE PERMANENTLY
            //BUT JUST TO READ THE FILE ONCE
//                [
//                    'name' => 'FileRenameUpload',
//                    'options' => [
//                        'target' => './data',
//                        'useUploadName' => true,
//                        'useUploadExtension' => true,
//                        'overwrite' => true,
//                        'randomize' => false
//                    ]
//                ]
            ]
        ]);
    }

    /*
     * RETURNS VALIDATED DATA
     * - IF DATA IS DIRTY (NOT VALID), 'clean' INDEX WILL BE FALSE 
     *   AND IT WILL HAVE 'error' SPECIFICATION INDEX DETAIL
     * - IF DATA IS CLEAN (VALID); THE ARRAY WILL RETURN VALIDATED AND CLEAN DATA
     */

    public function filterData($data) {
        $filteredData = [];
        unset($data[0]); //REMOVING HEADERS
        $clean = true;
        $error = "";
        foreach ($data as $fileIndexRow => $userData) {
            $numberColumns = 24;
            $fields = str_getcsv($userData);
            if (count($fields) != $numberColumns) { //NUMBER OF COLUMNS
                $row = [];
                $row['error'] = "<li>Número de columnas erróneo; deben ser $numberColumns columnas.</li>";
                $clean = false;
            } else {
                //SETTING CORRECT KEYS TO THE ARRAY VALUES AND CHANGING DATA
                $row = $this->setKeys($fields);
                $row = $this->validFields($row);
            }
            if (!empty($row['error'])) {
                //ADDING ERROR MESSAGE
                $clean = false;
                $error .= '<h5>Línea ' . ($fileIndexRow + 1) . ":</h5>\n<ul>\n " . $row['error'] . "\n</ul>\n"; //+1 BECAUSE IS THE HUMAN EYE FILE-ROW NUMBER
            } else if ($clean === true) {
                //ADDING CLEAN ROW IF THERE HAS BEEN NO ERRORS
                $filteredData[$fileIndexRow + 1] = $row;
            }
        }
        $filteredData['clean'] = $clean;
        $filteredData['error'] = $error;
        return $filteredData;
    }

    private function getId($codCol, $data, $text, $textCol) {
        if (empty($data)) {
            return false;
        }
        foreach ($data as $element) {
            if (empty($element) || empty($element[$codCol]) || empty($element[$textCol])) {
                return false;
            }
            if ($element[$textCol] == $text) {
                return $element[$codCol];
            }
        }
        return false;
    }

    private function getYmdDate($date) {
        $date = trim($date);
        $separator = "/";
        foreach (str_split($date) as $char) {
            if (!is_numeric($char)) {
                $separator = $char;
                break;
            }
        }
        $dp = explode($separator, $date); //DATE PARTS
        if (count($dp) != 3) {
            return $date;
        } else {
            return "$dp[2]-$dp[1]-$dp[0]";
        }
    }

    private function setKeys($fields) {
        $row = [];
        //SETTING KEYS TO VALUES
        $error = "";
        // CUI"
        $row[CF::CUI] = $fields[self::CUI];

        // "Pasaporte"
        $row[CF::PASSPORT] = $fields[self::PASSPORT];

        // "Nombres"
        $row[CF::NAMES] = $fields[self::NAMES];

        // "Apellidos"
        $row[CF::LAST_NAMES] = $fields[self::LAST_NAMES];

        // "Sexo"
        $text = trim($fields[self::GENDER]);
        $row[CF::GENDER] = empty($text) ? $text : ($text == 'Hombre' ? 'H' : ($text == 'Mujer' ? 'M' : $text));

        // "Email"
        $row[CF::EMAIL] = $fields[self::EMAIL];

        // "Teléfono"
        $row[CF::PHONE] = $fields[self::PHONE];

        // "Fecha de nacimiento"
        $row[CF::BIRTH_DATE] = $this->getYmdDate($fields[self::BIRTH_DATE]);

        // "País de nacionalidad"
        $text = trim($fields[self::NATIONALITY]);
        $id = $this->getId('cod_pais', $this->nationalities, $text, 'nombre');
        if ($id === false) {
            $error .= "<li>'" . CF::NATIONALITY . "' => '" . "No se encontró el país ($text)" . "';</li>";
            $id = $text;
        }
        $row[CF::NATIONALITY] = $id;

        // "Cohorte"
        $row[CF::COHORT] = $this->getYmdDate($fields[self::COHORT]);

        // "Grado académico a ingresar"
        $text = trim($fields[self::ACADEMIC_DEGREE]);
        $id = $this->getId('cod_grado', $this->degrees, $text, 'nombre');
        if ($id === false) {
            $error .= "<li>'" . CF::ACADEMIC_DEGREE . "' => '" . "No se encontró el grado académico ($text)" . "';</li>";
            $id = $text;
        }
        $row[CF::ACADEMIC_DEGREE] = $id;

        // "Carrera a ingresar"
        $text = trim($fields[self::CAREER]);
        $id = $this->getId('cod_carrera', $this->careers, $text, 'alias_actual');
        if ($id === false) {
            $error .= "<li>'" . CF::CAREER . "' => '" . "No se encontró la carrera ($text)" . "';</li>";
            $id = $text;
        }
        $row[CF::CAREER] = $id;

        // "Grado académico que posee"
        $row[CF::ACTUAL_DEGREE] = $fields[self::ACTUAL_DEGREE];

        // "Labora actualmente"
        $works = false;
        $text = trim($fields[self::CURRENTLY_WORKS]);
        if ($text != 'Sí' && $text != 'No') {
            $error .= "<li>'" . CF::CURRENTLY_WORKS . "'('" . $text . "') => '" . "El texto ($text) debe ser 'Sí' o 'No'" . "';</li>";
            $row[CF::CURRENTLY_WORKS] = $text;
        } else {
            $row[CF::CURRENTLY_WORKS] = ($text == 'No') ? 'no' : 'yes';
            if ($text != 'No') {
                $works = true;
            }
        }

        if ($works) {
            // "Ubicación laboral"
            $row[CF::WORK_PLACE] = $fields[self::WORK_PLACE];

            // "Hora inicio"
            $row[CF::START_TIME] = $fields[self::START_TIME];

            // "Hora fin"
            $row[CF::FINISH_TIME] = $fields[self::FINISH_TIME];

            //DAYS
            $days = [];
            $dayNames = [
                self::MONDAY => 'lunes',
                self::TUESDAY => 'martes',
                self::WEDNESDAY => 'miercoles',
                self::THURSDAY => 'jueves',
                self::FRIDAY => 'viernes',
                self::SATURDAY => 'sabado',
                self::SUNDAY => 'domingo'
            ];
            //18 "lunes" | 24 "domingo"
            $startId = self::DAYS_START;
            foreach ($dayNames as $dayIndex => $fieldName) {
                $text = trim($fields[$dayIndex]);
                if ($text != 'Sí' && $text != 'No') {
                    $error .= "<li>'" . $fieldName . "'('" . $text . "') => '" . "El texto ($text) debe ser 'Sí' o 'No'" . "';</li>";
                } elseif ($text == 'Sí') {
                    $days[] = $dayNames[$dayIndex]; // 0=> monday, 1=>tuesday....
                }
            }
            for ($i = $startId; $i < (count($dayNames) + $startId); $i++) {
                $text = trim($fields[$i]);
            }
            $row[CF::DAYS] = $days;
        }

        $row['error'] = $error;
        return $row;
    }

    /*
     * CHECKS EVERY FORM ELEMENT TO CHECK IF THERE IS AN ERROR AND SPECIFY IT
     */

    private function validFields($row) {
        $error = empty($row['error']) ? "" : $row['error'];
        $cf = new CandidateForm("", $this->nationalities, $this->cohorts, $this->degrees, $this->careers);
        //CLEANING UPDATING COURSES COHORT
        if ($row[CandidateForm::COHORT] == '(Curso de Actualización)') {
            $row[CandidateForm::COHORT] = CandidateForm::UPG_COURSE_COHORT;
        }
        $cf->setData($row);
        if (!$cf->isValid()) {
            $elements = $cf->getElements();
            foreach ($elements as $e) {
                if (!empty($e->getMessages())) {
                    $error .= "<li>'" . $e->getName() . "'('" . $e->getValue() . "') => '" . implode("', '", $e->getMessages()) . "';</li>\n";
                }
            }
        }
        return array_merge($cf->getData(), ['error' => $error]);
    }

    //FILE HEADERS
    const HEADERS = [
        'CUI', 'Pasaporte', 'Nombres', 'Apellidos', 'Sexo', 'Email', 'Teléfono', 'Fecha', 'de', 'nacimiento', 'País', 'de', 'nacionalidad', 'Grado', 'académico', 'a', 'ingresar', 'Carrera', 'a', 'ingresar', 'Cohorte', 'Grado', 'académico', 'que', 'posee', 'Labora', 'actualmente', 'Ubicación', 'laboral', 'Hora', 'inicio', 'Hora', 'fin', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'
    ];
    //PERSONAL INFO
    const CUI = 0;
    const PASSPORT = 1;
    const NAMES = 2;
    const LAST_NAMES = 3;
    const GENDER = 4;
    const EMAIL = 5;
    const PHONE = 6;
    const BIRTH_DATE = 7;
    const NATIONALITY = 8;
    const ACADEMIC_DEGREE = 9;
    const CAREER = 10;
    const COHORT = 11;
    const ACTUAL_DEGREE = 12;
    const CURRENTLY_WORKS = 13;
    const WORK_PLACE = 14;
    const START_TIME = 15;
    const FINISH_TIME = 16;
    const DAYS_START = 17;
    const MONDAY = 17;
    const TUESDAY = 18;
    const WEDNESDAY = 19;
    const THURSDAY = 20;
    const FRIDAY = 21;
    const SATURDAY = 22;
    const SUNDAY = 23;

}
