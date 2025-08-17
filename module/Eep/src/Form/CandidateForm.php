<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Hostname;
use Zend\Validator\Digits;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\Date;
use Zend\Validator\GreaterThan;
use Zend\Validator\LessThan;
use Zend\Validator\EmailAddress;
use Zend\Validator\InArray;
use Eep\Form\FieldError;
use Zend\Validator\Regex;
use Eep\Entity\InfoLaboral;
use Eep\Form\CategorizeTimetableForm as CTF;
use Eep\Entity\Order;
use Eep\Service\UserManager;

class CandidateForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'CandidateForm';
    const SIGN_IN = 'candidateFormSignIn';
    //USER
    const NAMES = 'nombres';
    const LAST_NAMES = 'apellidos';
    const EMAIL = 'correo';
    const PHONE = 'telefono';
    const CUI = 'cui';
    const BIRTH_DATE = 'fecha_nacimiento';
    const PASSPORT = 'pasaporte';
    const ACADEMIC_REGISTRY = 'registro_academico';
    const NATIONALITY = 'cod_pais';
    const GENDER = 'sexo';
    const ACTUAL_DEGREE = 'grado_academico';
    //ACADEMIC INFO
    const COHORT = 'cohorte';
    const ACADEMIC_DEGREE = 'grado_academico_a_ingresar';
    const CAREER = 'carrera';
    //LABORAL INFO
    const CURRENTLY_WORKS = 'trabaja_actualmente';
    const WORK_PLACE = 'ubicacion';
    const START_TIME = 'hora_inicio';
    const FINISH_TIME = 'hora_fin';
    const DAYS = InfoLaboral::DAYS;
    const DAYS_KEY_VALUE = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'domingo' => 'Domingo'
    ];
    const UPG_COURSE_COHORT = CTF::UPG_COURSE_COHORT;
    const UPD_COURSE_CODE = Order::CURSO_ACTUALIZACION;

    private $nationalities;
    private $cohorts;
    private $degrees;
    private $careers;
    private $dataTree;
    private $hasData;
    private $selectedDegree;
    private $selectedCareer;

    public function __construct($url, $nationalities, $cohorts, $degrees, $careers) {//THE URL REDIRECTS TO THE CORRECT ACTION CONTROLLER
        parent::__construct(self::FORM_NAME);
        $this->nationalities = $nationalities;
        $this->cohorts = $cohorts;
        $this->degrees = $degrees;
        $this->careers = $careers;
        $this->hasData = false;

        $this->setAttribute('method', 'post');
        $this->setAttribute('action', $url); //SO THE FORM REDIRECTS TO THE CORRECT ACTION

        $this->addElements();
        //ADDING ELEMENTS WITH DATABASE DATA
        $this->setNationalities($nationalities);
        $this->setCohorts($cohorts);
        $this->setDegrees($degrees);
        $this->setCareers($careers);

        $this->addInputFilter();
        $this->makeTree($careers);
    }

    public function getDegrees() {
        return $this->degrees;
    }

    public function getCareers() {
        return $this->careers;
    }

    public function getNationalities() {
        return $this->nationalities;
    }

    public function getCohorts() {
        return $this->cohorts;
    }

    private function getValues($data, $fieldName) {
        //CLEANING DATA
        $cleanData = [];
        foreach ($data as $element) {
            array_push($cleanData, $element[$fieldName]);
        }
        return $cleanData;
    }

    public function setNationalities($data) {
        //CLEANING DATA
        $cleanData = [];
        foreach ($data as $element) {
            $name = $element['nombre'];
            $cleanData[$element['cod_pais']] = $name;
            if ($name == 'Guatemala') {
                $guatemalaCode = $element['cod_pais'];
            }
        }
        //NATIONALITIIES
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::NATIONALITY,
            'options' => [
                'label' => 'País de nacionalidad',
                'value_options' => $cleanData,
            ],
            'attributes' => [
                'id' => self::NATIONALITY,
                'class' => 'form-control',
                'value' => $guatemalaCode ?? 1
            ]
        ]);
    }

    public function setCohorts($data) {
        //CLEANING DATA
        $cleanData = [];
        foreach ($data as $element) {
            $date = $element['fecha_cohorte'];
            $cleanData[$date] = date("d/m/Y", strtotime($date));
        }
        $cleanData[self::UPG_COURSE_COHORT] = '(Cursos de Actualización)';
        //COHORTS
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::COHORT,
            'options' => [
                'label' => 'Cohorte',
                'value_options' => $cleanData,
            ],
            'attributes' => [
                'id' => self::COHORT,
                'class' => 'form-control'
            ]
        ]);
    }

    public function setDegrees($data) {
        //CLEANING DATA
        $cleanData = [];
        foreach ($data as $element) {
            $cleanData[$element['cod_grado']] = $element['nombre'];
        }
        //DEGREES
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::ACADEMIC_DEGREE,
            'options' => [
                'label' => 'Grado académico a ingresar',
                'value_options' => $cleanData,
            ],
            'attributes' => [
                'id' => self::ACADEMIC_DEGREE,
                'class' => 'form-control'
            ]
        ]);
    }

    public function setCareers($data) {
        //CLEANING DATA
        $cleanData = [];
        foreach ($data as $element) {
            $cleanData[$element['cod_carrera']] = $element['alias_actual'];
        }
        //CAREERS
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::CAREER,
            'options' => [
                'label' => 'Carrera a ingresar',
                'value_options' => $cleanData,
            ],
            'attributes' => [
                'id' => self::CAREER,
                'class' => 'form-control'
            ]
        ]);
    }

    private function addElements() {
        //NAMES
        $this->add([
            'type' => 'text',
            'name' => self::NAMES,
            'attributes' => [
                'id' => self::NAMES,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Nombres',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
            ],
        ]);

        //LAST_NAME
        $this->add([
            'type' => 'text',
            'name' => self::LAST_NAMES,
            'attributes' => [
                'id' => self::LAST_NAMES,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Apellidos',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
            ],
        ]);

        //EMAIL
        $this->add([
            'type' => 'text',
            'name' => self::EMAIL,
            'attributes' => [
                'id' => self::EMAIL,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Correo electrónico',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
            ],
        ]);

        //TELEFONO
        $this->add([
            'type' => 'text',
            'name' => self::PHONE,
            'attributes' => [
                'id' => self::PHONE,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Teléfono/Celular',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
            ],
        ]);

        //CUI
        $this->add([
            'type' => 'text',
            'name' => self::CUI,
            'attributes' => [
                'id' => self::CUI,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'CUI',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
            ],
        ]);

        //BIRTH DATE
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::BIRTH_DATE,
            'attributes' => [
                'id' => self::BIRTH_DATE,
                //'min' => '1900-01-01', //'01/01/1900',
                //'max' => \date('Y-m-d'),
                'step' => '1',
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Fecha de Nacimiento',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE//_ELEMENT
            ],
        ]);

        //PASSPORT
        $this->add([
            'type' => 'text',
            'name' => self::PASSPORT,
            'attributes' => [
                'id' => self::PASSPORT,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Pasaporte',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
            ],
        ]);

        //GENDER
        $this->add([
            'type' => 'Zend\Form\Element\Radio',
            'name' => self::GENDER,
            'options' => [
                'label' => 'Sexo',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value' => ['H'],
                'value_options' => [
                    [
                        'value' => 'H',
                        'label' => 'Hombre',
                        'selected' => true
                    ],
                    'M' => 'Mujer',
                ]
            ]
        ]);

        //ACTUAL DEGREE
        $this->add([
            'type' => 'text',
            'name' => self::ACTUAL_DEGREE,
            'attributes' => [
                'id' => self::ACTUAL_DEGREE,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Grado académico que posee',
            ],
        ]);

        //CURRENTLY WORKS
        $this->add([
            'type' => 'Zend\Form\Element\Checkbox',
            'name' => self::CURRENTLY_WORKS,
            'options' => [
                'label' => 'Labora actualmente',
                'use_hidden_element' => true,
                'checked_value' => 'yes',
                'unchecked_value' => 'no'
            ],
            'attributes' => [
                'id' => self::CURRENTLY_WORKS
            ]
        ]);

        //ACTUAL DEGREE
        $this->add([
            'type' => 'text',
            'name' => self::WORK_PLACE,
            'attributes' => [
                'id' => self::WORK_PLACE,
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Ubicación laboral',
            ],
        ]);

        //START TIME
        $this->add([
            'type' => 'Zend\Form\Element\Time',
            'name' => self::START_TIME,
            'options' => [
                'label' => 'Hora de inicio',
                'format' => 'H:i'
            ],
            'attributes' => [
                'id' => self::START_TIME,
                'min' => '00:00',
                'max' => '23:59',
                'step' => '60', // seconds; default step interval is 60 seconds
                'class' => 'form-control'
            ]
        ]);

        //FINISH TIME
        $this->add([
            'type' => 'Zend\Form\Element\Time',
            'name' => self::FINISH_TIME,
            'options' => [
                'label' => 'Hora de salida',
                'format' => 'H:i'
            ],
            'attributes' => [
                'id' => self::FINISH_TIME,
                'min' => '00:00',
                'max' => '23:59',
                'step' => '60', // seconds; default step interval is 60 seconds
                'class' => 'form-control'
            ]
        ]);

        //DAYS
        $this->add([
            'type' => 'Zend\Form\Element\MultiCheckbox',
            'name' => self::DAYS,
            'options' => [
                'label' => 'Días en que labora',
                'label_attributes' => [/* 'style' => 'white-space: nowrap;', */'class' => 'control-label'],
                'value_options' => self::DAYS_KEY_VALUE,
            ],
            'attributes' => [
                'class' => 'input-inline',
                'id' => self::DAYS
            ]
        ]);



        //SIGN IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SIGN_IN,
            'attributes' => [
                'value' => 'Agregar estudiante',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //NAMES
        $inputFilter->add([
            'name' => self::NAMES,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 2,
                        'max' => 60,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //LAST NAME
        $inputFilter->add([
            'name' => self::LAST_NAMES,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 2,
                        'max' => 60,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //EMAIL
        $inputFilter->add([
            'name' => self::EMAIL,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 1,
                        'max' => 60,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
                [
                    'name' => EmailAddress::class,
                    'options' => [
                        'allow' => Hostname::ALLOW_DNS,
                        'useMxCheck' => false,
                        'messages' => FieldError::EMAIL_ADDRESS
                    ],
                ],
            ],
        ]);

        //PHONE
        $inputFilter->add([
            'name' => self::PHONE,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 8,
                        'max' => 15,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //CUI
        $inputFilter->add([
            'name' => self::CUI,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 0,
                        'max' => 18, //SIGNED BIG INT MAX VALUE IS 9.~*10^18
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
                [
                    'name' => Digits::class,
                    'options' => [
                        'messages' => FieldError::DIGITS
                    ],
                ],
            ],
        ]);

        //BIRTH DATE
        $inputFilter->add([
            'name' => self::BIRTH_DATE,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => Date::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
                [
                    'name' => GreaterThan::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => '1900-01-01', //'01/01/1900',
                        'messages' => FieldError::GREATER_THAN
                    ],
                ],
                [
                    'name' => LessThan::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'max' => (string) \date('Y-m-d'),
                        'inclusive' => true,
                        'messages' => FieldError::LESS_THAN
                    ],
                ],
            ],
        ]);

        //PASSPORT
        $inputFilter->add([
            'name' => self::PASSPORT,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 0,
                        'max' => 30, //SIGNED INT MAX VALUE 4.29*10^9
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
                [
                    'name' => Regex::class,
                    'options' => [
                        'pattern' => UserManager::PASSPORT_PATTERN,
                        'messages' => FieldError::REGEX
                    ]
                ]
            ],
        ]);

        //NATIONALITY
        $inputFilter->add([
            'name' => self::NATIONALITY,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => $this->getValues($this->nationalities, 'cod_pais'),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
                [
                    'name' => Digits::class,
                    'options' => [
                        'messages' => FieldError::DIGITS
                    ],
                ],
            ],
        ]);

        //COHORT
        $cohortValues = $this->getValues($this->cohorts, 'fecha_cohorte');
        $cohortValues[] = self::UPG_COURSE_COHORT;
        $inputFilter->add([
            'name' => self::COHORT,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => $cohortValues,
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
                [
                    'name' => Date::class,
                    'options' => [
                        'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
            ],
        ]);

        //GENDER
        $inputFilter->add([
            'name' => self::GENDER,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => ['H', 'M'],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 1, //SIGNED INT MAX VALUE 4.29*10^9
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //ACADEMIC DEGREE
        $inputFilter->add([
            'name' => self::ACADEMIC_DEGREE,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => $this->getValues($this->degrees, 'cod_grado'),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 0,
                        'max' => 9, //SIGNED INT MAX VALUE 4.29*10^9
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
                [
                    'name' => Digits::class,
                    'options' => [
                        'messages' => FieldError::DIGITS
                    ],
                ],
            ],
        ]);

        //CAREER
        $inputFilter->add([
            'name' => self::CAREER,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => $this->getValues($this->careers, 'cod_carrera'),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => 0,
                        'max' => 9, //SIGNED INT MAX VALUE 4.29*10^9
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
                [
                    'name' => Digits::class,
                    'options' => [
                        'messages' => FieldError::DIGITS
                    ],
                ],
            ],
        ]);

        //ACTUAL ACADEMIC DEGREE
        $inputFilter->add([
            'name' => self::ACTUAL_DEGREE,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 1,
                        'max' => 150,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //CURRENTLY WORKS OR NOT
        $inputFilter->add([
            'name' => self::CURRENTLY_WORKS,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'messages' => FieldError::NOT_EMPTY
                    ],
                ],
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => ['yes', 'no'],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 2, //no
                        'max' => 3, //yes
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //OPTIONAL FIELDS
        //WORK PLACE
        $inputFilter->add([
            'name' => self::WORK_PLACE,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 400,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //START TIME
        $inputFilter->add([
            'name' => self::START_TIME,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 8,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
                [
                    'name' => Date::class,
                    'options' => [
                        'format' => 'H:i',
                        'messages' => FieldError::DATE
                    ],
                ],
                [
                    'name' => Regex::class,
                    'options' => [
                        'pattern' => '#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#',
                        'messages' => FieldError::REGEX
                    ]
                ]
            ],
        ]);

        //FINISH TIME
        $inputFilter->add([
            'name' => self::FINISH_TIME,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 8,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
                [
                    'name' => Date::class,
                    'options' => [
                        'format' => 'H:i',
                        'messages' => FieldError::DATE
                    ],
                ],
                [
                    'name' => Regex::class,
                    'options' => [
                        'pattern' => '#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#',
                        'messages' => FieldError::REGEX
                    ]
                ]
            ],
        ]);

        //DAYS
        $inputFilter->add([
            'name' => self::DAYS,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
        ]);
    }

    //CHECKING IDS INTEGRITY
    public function isValid() {
        $isValid = parent::isValid();

        //CHECKING CAREER - GRADE ASSOCIATION
        $degree = $this->get(self::ACADEMIC_DEGREE);
        $idDegree = $degree->getValue();
        $career = $this->get(self::CAREER);
        $careerCode = $career->getValue();
        if (!isset($this->dataTree[$idDegree][$careerCode])) {
            $message = "El grado académico ($idDegree) no corresponde a la carrera seleccionada ($careerCode)";
            $career->setMessages([$message]);
        }
        //CHECKING IF HAS UPDATING COURSES COHORT CORRELATION
        $cohortElement = $this->get(self::COHORT);
        $cohortDate = $cohortElement->getValue();
        if ($cohortDate == self::UPG_COURSE_COHORT && $careerCode != self::UPD_COURSE_CODE) {
            $cohortElement->setMessages(['Para el grado de Curso de Actualización Profesional, la cohorte debe ser "(Curso de Actualización)"']);
            $isValid = false;
        } elseif ($cohortDate != self::UPG_COURSE_COHORT && $careerCode == self::UPD_COURSE_CODE) {
            $cohortElement->setMessages(['Para un grado diferente a Curso de Actualización Profesional, debe seleccionar una fecha como cohorte']);
            $isValid = false;
        }
        //CHECKING IF ANY ID WAS FILLED
        $cui = $this->get(self::CUI);
        $passp = $this->get(self::PASSPORT);
        if (empty($cui->getValue()) && empty($passp->getValue())) {
            $cui->setMessages(["Debe llenar al menos un campo de identificación"]);
            $passp->setMessages(["Debe llenar al menos un campo de identificación"]);
            $isValid = false;
        } else {
            //CHECKING IF THE USER HAS ID CORRESPONDING TO ITS DATA
            $GuatemalaId = -1;
            foreach ($this->nationalities as $country) {
                if (!empty($country) && $country['nombre'] == 'Guatemala') {
                    $GuatemalaId = $country['cod_pais'];
                    break;
                }
            }
            $nationality = $this->get(self::NATIONALITY);
            $idCountry = $nationality->getValue();
            if ($GuatemalaId == -1) {
                $nationality->setMessages(array_merge(empty($nationality->getMessages()) ? [] : $nationality->getMessages(), ["Debe existir el país \"Guatemala\""]));
                $isValid = false;
            } else {
                if ($idCountry == $GuatemalaId) {
                    //THE USER MUST HAVE CUI
                    if (empty($cui->getValue())) {
                        $cui->setMessages(array_merge(empty($cui->getMessages()) ? [] : $cui->getMessages(), ["Debe tener CUI si es de Guatemala"]));
                        $isValid = false;
                    }
                } else {
                    //THE USER MUST HAVE PASSPORT
                    if (empty($passp->getValue())) {
                        $passp->setMessages(array_merge(empty($passp->getMessages()) ? [] : $passp->getMessages(), ["Debe tener pasaporte si no es de Guatemala"]));
                        $isValid = false;
                    }
                }
            }
        }
        return $isValid;
    }

    public function clearData() {
        $elements = $this->getElements();
        foreach ($elements as $field) {
            if ($field->getName() == self::SIGN_IN) {
                continue;
            }
            if ($field->getName() == self::CAREER) {
                if (!empty($this->careers)) {
                    $field->setValue($this->careers[0]['cod_carrera']);
                }
            } elseif ($field->getName() == self::ACADEMIC_DEGREE) {
                if (!empty($this->degrees)) {
                    $field->setValue($this->degrees[0]['cod_grado']);
                }
            } elseif ($field->getName() == self::NATIONALITY) {
                if (!empty($this->nationalities)) {
                    $field->setValue($this->nationalities[0]['cod_pais']);
                }
            } else {
                $field->setValue('');
            }
        }
    }

    private function makeTree($careers) {
        $this->dataTree = [];
        foreach ($careers as $career) {
            $degreeCode = $career['cod_grado'];
            $careerAlias = $career['alias_actual'];
            $careerCode = $career['cod_carrera'];
            $this->dataTree[$degreeCode][$careerCode] = $careerAlias;
        }
    }

    public function getJsTree() {
        return str_replace('array (', '{', str_replace(')', '}', str_replace('=>', ':', str_replace("'", '"', var_export($this->dataTree, true)))));
    }

    public function setData($data) {
        parent::setData($data);
        $this->hasData = true;
        $this->selectedDegree = $this->get(self::ACADEMIC_DEGREE)->getValue();
        $this->selectedCareer = $this->get(self::CAREER)->getValue();
    }

    public function hasData() {
        return $this->hasData;
    }

    public function getSelectedDegree() {
        if (isset($this->selectedDegree)) {
            return $this->selectedDegree;
        } else {
            return null;
        }
    }

    public function getSelectedCareer() {
        if (isset($this->selectedCareer)) {
            return $this->selectedCareer;
        } else {
            return null;
        }
    }

}
