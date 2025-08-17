<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Regex;
use Zend\Validator\Date;
use Zend\Validator\GreaterThan;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;
use Eep\Service\UserManager;

class LogForm extends Form {

    const FORM_NAME = 'LogForm';
    const USER = 'user';
    const START_DATE = 'fecha_inicio';
    const FINISH_DATE = 'fecha_fin';
    const SEARCH = 'search';

    public function __construct() {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');

        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        //USUARIO
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
                    'class' => 'control-label'
                ]
            ],
        ]);

        //START DATE
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::START_DATE,
            'options' => [
                'label' => 'Fecha de inicio',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
            'attributes' => [
                'value' => \date('Y') . '-' . \date('m') . '-01',
                'id' => self::START_DATE,
                'class' => 'form-control',
                'step' => '1',
            ]
        ]);

        //FINISH DATE
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::FINISH_DATE,
            'options' => [
                'label' => 'Fecha de fin',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
            'attributes' => [
                'value' => \date('Y-m-d'),
                'id' => self::FINISH_DATE,
                'class' => 'form-control',
                'step' => '1',
            ]
        ]);

        //SEARCH
        $this->add([
            'type' => 'button',
            'name' => self::SEARCH,
            'options' => [
                'label' => 'Buscar'
            ],
            'attributes' => [
                'type' => 'submit',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //USER CODE
        $inputFilter->add([
            'name' => self::USER,
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
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 30,
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

        //START_DATE
        $inputFilter->add([
            'name' => self::START_DATE,
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
//                [
//                    'name' => GreaterThan::class,
//                    'break_chain_on_failure' => true,
//                    'options' => [
//                        'inclusive' => true,
//                        'min' => '2018-01-01', //'01/01/1900',
//                        'messages' => FieldError::GREATER_THAN
//                    ],
//                ],
//                [
//                    'name' => LessThan::class,
//                    'break_chain_on_failure' => true,
//                    'options' => [
//                        'max' => (string) \date('Y-m-d'),
//                        'inclusive' => true,
//                        'messages' => FieldError::LESS_THAN
//                    ],
//                ],
            ],
        ]);

        //FINISH DATE
        $inputFilter->add([
            'name' => self::FINISH_DATE,
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
            ],
        ]);
    }

    public function isValid() {
        $valid = parent::isValid();
        if ($valid) {
            $start = $this->get(self::START_DATE)->getValue();
            $finish = $this->get(self::FINISH_DATE)->getValue();
            if ($start > $finish) {
                $this->get(self::FINISH_DATE)->setMessages(["La fecha de fin debe ser mayor a la de inicio"]);
                $valid = false;
            }
        }
        return $valid;
    }

}
