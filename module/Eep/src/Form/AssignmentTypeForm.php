<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\Regex;
use Zend\Validator\InArray;
use Eep\Form\FieldError;
use Eep\Service\UserManager;

class AssignmentTypeForm extends Form {

    const FORM_NAME = 'AssignmentTypeForm';
    const ASSIGNMENT_TYPE = 'assignment-type';
    const USER = 'user'; //ANY OF THE USER IDS
    const SUBMIT = 'submit';
    const YEAR = 'anio';
    //TYPES
    const TYPE_REGULAR = 1;
    const TYPE_EXTEMP = 2;
    const TYPE_EXTRA = 3;
    const TYPE_STUDENT_REGULAR = 4;
    //OTHER CONSTANTS
    const START_YEAR = 2005;

    private $years;
    private $type;

    /**
     * 
     * @param type $url
     * @param type $type THIS TYPE MUST BE NULL IF YOU WANT THE FORM TO HAVE VISIBLE ELEMENTS. OTHER WAY IT WILL DISPLAY THE ELEMENTS
     * @param type $name
     */
    public function __construct($url, $type = null, $name = null) {
        parent::__construct($name ?? self::FORM_NAME);
        $this->type = $type;
        if ($type == null || $type != self::TYPE_STUDENT_REGULAR) {//THIS IS USED TO SUPPORT HERITAGE OF AssignmentForm AND SUPPORT THE REGULAR STUDENT TYPE BECAUSE IT DOESN'T NEED ANY OTHER ELEMENT
            //SETTING YEARS
            $this->years = [];
            $actualYear = intval(date('Y'));
            for ($year = $actualYear; $year >= self::START_YEAR; $year--) {
                $this->years[$year] = $year;
            }
            $this->setAttribute('method', 'post');
            $this->setAttribute('action', $url);
            if ($type == null) {
                $this->addElements();
            } else {
                $this->addHiddenElements();
            }
            $this->addInputFilter();
        }
    }

    private function addElements() {
        //TYPE
        $this->add([
            'type' => 'Zend\Form\Element\Radio',
            'name' => self::ASSIGNMENT_TYPE,
            'attributes' => [
                'id' => self::ASSIGNMENT_TYPE,
            ],
            'options' => [
                'label' => 'Tipo de asignación',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value_options' => [//NO STUDENT REGULAR TYPE BECAUSE THIS FORM DOESN'T LEAD TO IT
                    [
                        'value' => self::TYPE_REGULAR,
                        'label' => 'Regular',
                        'selected' => true
                    ],
                    self::TYPE_EXTEMP => 'Extemporánea',
                    self::TYPE_EXTRA => 'Extraordinaria'
                ]
            ]
        ]);

        //USER
        $this->add([
            'type' => 'text',
            'name' => self::USER,
            'attributes' => [
                'id' => self::USER,
                'class' => 'form-control',
                'placeholder' => 'Registro Académico / CUI / Pasaporte'
            ],
            'options' => [
                'label' => 'Estudiante',
                'label_attributes' => [
                    'id' => self::USER,
                    'class' => 'control-label'
                ],
            ],
        ]);

        //SUBMIT IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'id' => self::SUBMIT,
                'value' => 'Obtener cursos',
                'class' => 'btn btn-primary'
            ],
        ]);

        //YEAR
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::YEAR,
            'options' => [
                'label' => 'Año',
                'value_options' => $this->years,
            ],
            'attributes' => [
                'class' => 'form-control',
            ]
        ]);
    }

    public function addHiddenElements() {
        //TYPE
        $this->add([
            'type' => 'hidden',
            'name' => self::ASSIGNMENT_TYPE
        ]);

        //USER
        $this->add([
            'type' => 'hidden',
            'name' => self::USER
        ]);

        //YEAR
        $this->add([
            'type' => 'hidden',
            'name' => self::YEAR
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        $validators = [
            [
                'name' => NotEmpty::class,
                'options' => [
                    'messages' => FieldError::NOT_EMPTY
                ],
            ],
            [
                'name' => StringLength::class,
                'options' => [
                    'min' => 0,
                    'max' => 18, //SIGNED BIG INT MAX VALUE IS 9.~*10^18
                    'messages' => FieldError::STRING_LENGTH
                ],
            ],
        ];
        if ($this->type == null) {
            $validators[] = [
                'name' => Regex::class,
                'options' => [
                    'pattern' => UserManager::PASSPORT_PATTERN,
                    'messages' => FieldError::REGEX
                ]
            ];
        } else {
            $validators[] = [
                'name' => Digits::class,
                'options' => [
                    'messages' => FieldError::DIGITS
                ],
            ];
        }
        //USER
        $inputFilter->add([
            'name' => self::USER,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => $validators
        ]);

        //ASSIGNMENT TYPE
        $inputFilter->add([
            'name' => self::ASSIGNMENT_TYPE,
            'required' => true,
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
                        'haystack' => [//NO STUDENT REGULAR TYPE BECAUSE THIS FORM DOESN'T LEAD TO IT
                            self::TYPE_EXTEMP,
                            self::TYPE_EXTRA,
                            self::TYPE_REGULAR
                        ],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
        //YEAR
        $inputFilter->add([
            'name' => self::YEAR,
            'required' => false,
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
                    'options' => [
                        'haystack' => $this->years,
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 9, //SIGNED INT MAX VALUE IS 1.~*10^9
                        'messages' => FieldError::STRING_LENGTH
                    ]
                ],
                [
                    'name' => Digits::class,
                    'options' => [
                        'messages' => FieldError::DIGITS
                    ],
                ]
            ],
        ]);
    }

    public function isValid() {
        $valid = parent::isValid();
        if ($valid && $this->type != self::TYPE_STUDENT_REGULAR) {
            $typeE = $this->get(self::ASSIGNMENT_TYPE);
            if ($typeE->getValue() == self::TYPE_EXTEMP) {
                $yearE = $this->get(self::YEAR);
                if (($yearE->getValue()) == null) {
                    $typeE->setMessages(['Para la asignación extemporánea se debe especificar el año en el que se realizará la asignación del estudiante.']);
                    $valid = false;
                } else {
                    $yearValue = $yearE->getValue();
                    $actualYear = intval(date('Y'));
                    if ($yearValue < self::START_YEAR || $yearValue > $actualYear) {
                        $yearE->setMessages(['El año ingresado está fuera del límite establecido.' . $yearValue]);
                        $valid = false;
                    }
                }
            }
        }
        return $valid;
    }

}
