<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Digits;
use Zend\Validator\Regex;
use Zend\Validator\StringLength;
use Zend\Validator\LessThan;
use Zend\Validator\GreaterThan;
use Eep\Form\FieldError;

class UpgCourseForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'upgCourseForm';
    const SUBMIT = 'submit';
    //FORM
    const COURSE = 'cod_curso';
    const NAME = 'name';
    const ALIAS = 'alias';
    const PRICE = 'price';
    const DESCRIPTION = 'descripcion';
    //TYPES
    const TYPE_DELETE = 'delete'; //BUTTON WITH USER CODE ROLE REFERENCE
    const TYPE_NEW = 'new';
    const TYPE_SAVE = 'save';
    const TYPE_EDIT = 'edit';

    private $type;

    public function __construct($type, $url) {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');
        $this->setAttribute('action', $url);
        $this->type = $type;

        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        if ($this->type == self::TYPE_SAVE || $this->type == self::TYPE_NEW) {
            $attributes = [
                'class' => 'form-control',
            ];
            if ($this->type == self::TYPE_SAVE) {
                //$attributes['readonly'] = 'readonly';
                $attributes['disabled'] = 'disabled';
            }
            //NAME
            $attributes['placeholder'] = 'Nombre completo del curso';
            $this->add([
                'type' => 'text',
                'name' => self::NAME,
                'options' => [
                    'label' => 'Nombre',
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                ],
                'attributes' => $attributes,
            ]);
            //ALIAS
            $attributes['placeholder'] = 'Nombre más abreviado del curso';
            $this->add([
                'type' => 'text',
                'name' => self::ALIAS,
                'options' => [
                    'label' => 'Alias',
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                ],
                'attributes' => $attributes,
            ]);
            //PRICE
            $this->add([
                'type' => 'text',
                'name' => self::PRICE,
                'options' => [
                    'label' => 'Precio (Q.)',
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                ],
                'attributes' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: 150.00'
                ],
            ]);
            if ($this->type == self::TYPE_SAVE) {
                //DETAIL
                $this->add([
                    'type' => 'textarea',
                    'name' => self::DESCRIPTION,
                    'options' => [
                        'label' => 'Detalle del cambio de precio',
                        'label_attributes' => [
                            'class' => 'control-label',
                        ],
                    ],
                    'attributes' => [
                        'class' => 'form-control',
                        'placeholder' => 'Escribe aquí la justificación y detalle del cambio de precio, y los documentos correspondientes que respalden el cambio'
                    ],
                ]);
            }
        }

        if ($this->type == self::TYPE_DELETE || $this->type == self::TYPE_SAVE || $this->type == self::TYPE_EDIT) {
            //USER ROLE CODE
            $this->add([
                'type' => 'hidden',
                'name' => self::COURSE
            ]);
        }

        switch ($this->type) {
            case self::TYPE_SAVE:
                $label = 'Guardar cambios';
                $color = 'primary';
                break;
            case self::TYPE_EDIT:
                $label = '<i class="fa fa-edit"></i>';
                $color = 'blue';
                break;
            case self::TYPE_DELETE:
                $label = '<i class="fa fa-trash"></i>';
                $color = 'red';
                break;
            case self::TYPE_NEW:
                $label = 'Agregar curso';
                $color = 'primary';
                break;
        }

        //SUBMIT BUTTON
        $this->add([
            'type' => 'button',
            'name' => self::SUBMIT,
            'options' => [
                'label' => $label,
                'label_options' => [
                    'disable_html_escape' => $this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT,
                ]
            ],
            'attributes' => [
                'type' => 'submit',
                'class' => "btn btn-$color"
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);
        if ($this->type == self::TYPE_NEW) {
            //NAME
            $inputFilter->add([
                'name' => self::NAME,
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
                            'max' => 100,
                            'messages' => FieldError::STRING_LENGTH
                        ]
                    ]
                ]
            ]);
            //ALIAS
            $inputFilter->add([
                'name' => self::ALIAS,
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
                            'max' => 100,
                            'messages' => FieldError::STRING_LENGTH
                        ]
                    ]
                ]
            ]);
        }
        if ($this->type == self::TYPE_NEW || $this->type == self::TYPE_SAVE) {
            //PRICE
            $inputFilter->add([
                'name' => self::PRICE,
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
                        'name' => Regex::class,
                        'options' => [
                            'pattern' => '#^[0-9]+(.[0-9]+)?$#',
                            'messages' => FieldError::REGEX
                        ]
                    ],
                    [
                        'name' => LessThan::class,
                        'options' => [
                            'inclusive' => false,
                            'max' => '100000.00',
                            'messages' => FieldError::LESS_THAN
                        ],
                    ],
                    [
                        'name' => GreaterThan::class,
                        'options' => [
                            'inclusive' => true,
                            'min' => '0.01',
                            'messages' => FieldError::GREATER_THAN
                        ],
                    ],
                ],
            ]);
        }
        if ($this->type == self::TYPE_SAVE) {
            //DETAIL
            $inputFilter->add([
                'name' => self::DESCRIPTION,
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
                            'max' => 5000,
                            'messages' => FieldError::STRING_LENGTH
                        ]
                    ]
                ]
            ]);
        }

        if ($this->type != self::TYPE_NEW) {
            //COURSE
            $inputFilter->add([
                'name' => self::COURSE,
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
                            'max' => 9, //INT
                            'messages' => FieldError::STRING_LENGTH
                        ]
                    ],
                    [
                        'name' => Digits::class,
                        'options' => [
                            'messages' => FieldError::DIGITS
                        ],
                    ]
                ]
            ]);
        }
    }

    public function setCourseCode($code) {
        if ($this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT) {
            $courseElement = $this->get(self::COURSE);
            $courseElement->setValue($code);
        }
    }

    function getType() {
        return $this->type;
    }

}
