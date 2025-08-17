<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Date;
use Zend\Validator\Digits;
use Zend\Validator\Regex;
use Zend\Validator\InArray;
use Zend\Validator\StringLength;
use Zend\Validator\GreaterThan;
use Eep\Form\FieldError;
use Eep\Service\UserManager;

class RoleForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'RoleForm';
    const SUBMIT = 'submit';
    //FORM
    const ROLE = 'cod_rol';
    const START_DATE = 'fecha_inicio';
    const FINISH_DATE = 'fecha_fin';
    const USER = 'user';
    const USER_ROLE_CODE = 'cod_usuario_rol';
    //TYPES
    const TYPE_DELETE = "deleteRole"; //BUTTON WITH USER CODE ROLE REFERENCE
    const TYPE_NEW = 'newRole';
    const TYPE_QUERY = 'queryRoles';
    const TYPE_EDIT = "editRole";
    const TYPE_EDIT_REQUEST = "editRequest";

    private $type;
    private $roles;

    public function __construct($type, $url, $roles = null) {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');
        $this->type = $type;
        $this->setAttribute('action', $url);

        $this->roles = $roles;
        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        if ($this->type == self::TYPE_EDIT || $this->type == self::TYPE_NEW || $this->type == self::TYPE_QUERY) {
            //USER
            $attributes = [
                'id' => self::USER,
                'class' => 'form-control',
                'placeholder' => 'Reg. Acad. / CUI / Pasaporte / Reg. Personal'
            ];
            if ($this->type == self::TYPE_EDIT) {
                $attributes['readonly'] = 'readonly';
            }
            $this->add([
                'type' => 'text',
                'name' => self::USER,
                'options' => [
                    'label' => 'Usuario',
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                ],
                'attributes' => $attributes,
            ]);
        }

        if ($this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT || $this->type == self::TYPE_EDIT_REQUEST) {
            //USER ROLE CODE
            $this->add([
                'type' => 'hidden',
                'name' => self::USER_ROLE_CODE
            ]);
        }

        if ($this->type == self::TYPE_NEW || $this->type == self::TYPE_EDIT) {
            //START DATE
            $this->add([
                'type' => 'Zend\Form\Element\Date',
                'name' => self::START_DATE,
                'options' => [
                    'label' => "Fecha de inicio de función",
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                    'format' => 'Y-m-d',
                    'messages' => FieldError::DATE
                ],
                'attributes' => [
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
                    'label' => "Fecha de finalización",
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                    'format' => 'Y-m-d',
                    'messages' => FieldError::DATE
                ],
                'attributes' => [
                    'id' => self::FINISH_DATE,
                    'class' => 'form-control',
                    'step' => '1',
                ]
            ]);

            //ROLES
            $cleanData = [];
            foreach ($this->roles as $role) {
                $cleanData[$role['cod_rol']] = $role['nombre'];
            }
            $this->add([
                'type' => 'Zend\Form\Element\Select',
                'name' => self::ROLE,
                'options' => [
                    'label' => 'Roles',
                    'value_options' => $cleanData,
                ],
                'attributes' => [
                    'id' => self::ROLE,
                    'class' => 'form-control'
                ]
            ]);
        }

        switch ($this->type) {
            case self::TYPE_EDIT:
                $label = 'Guardar cambios';
                $color = 'primary';
                break;
            case self::TYPE_EDIT_REQUEST:
                $label = '<i class="fa fa-edit"></i>';
                $color = 'blue';
                break;
            case self::TYPE_DELETE:
                $label = '<i class="fa fa-trash"></i>';
                $color = 'red';
                break;
            case self::TYPE_NEW:
                $label = 'Agregar rol';
                $color = 'primary';
                break;
            case self::TYPE_QUERY:
                $label = 'Buscar roles';
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
                    'disable_html_escape' => $this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT_REQUEST,
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
        if ($this->type == self::TYPE_EDIT || $this->type == self::TYPE_NEW || $this->type == self::TYPE_QUERY) {
            //USER
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
                        ]
                    ], 
                    [
                        'name' => Regex::class,
                        'options' => [
                            'pattern' => UserManager::PASSPORT_PATTERN,
                            'messages' => FieldError::REGEX
                        ]
                    ]
                ]
            ]);
        }

        if ($this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT || $this->type == self::TYPE_EDIT_REQUEST) {
            //USER ROLE CODE
            $inputFilter->add([
                'name' => self::USER_ROLE_CODE,
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

        if ($this->type == self::TYPE_NEW || $this->type == self::TYPE_EDIT) {

            //START DATE
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
                    [
                        'name' => GreaterThan::class,
                        'break_chain_on_failure' => true,
                        'options' => [
                            'inclusive' => true,
                            'min' => '1900-01-01',
                            'messages' => FieldError::GREATER_THAN
                        ],
                    ],
                ],
            ]);

            //FINISH DATE
            $inputFilter->add([
                'name' => self::FINISH_DATE,
                'required' => false,
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
                ],
            ]);

            //ROLES
            $rolesValues = [];
            foreach ($this->roles as $role) {
                $rolesValues[] = $role['cod_rol'];
            }
            $inputFilter->add([
                'name' => self::ROLE,
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
                            'haystack' => $rolesValues,
                            'messages' => FieldError::IN_ARRAY
                        ],
                    ],
                ],
            ]);
        }
    }

    public function setUserRoleCode($userRoleCode) {
        if ($this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT_REQUEST) {
            $roleCodeElement = $this->get(self::USER_ROLE_CODE);
            $roleCodeElement->setValue($userRoleCode);
        }
    }

    function getType() {
        return $this->type;
    }

    public function isValid() {
        $valid = parent::isValid();
        if ($valid && ($this->type == self::TYPE_NEW || $this->type == self::TYPE_EDIT)) {
            $start = $this->get(self::START_DATE)->getValue();
            $finish = $this->get(self::FINISH_DATE)->getValue();
            if (!empty($finish) && $start > $finish) {
                $this->get(self::FINISH_DATE)->setMessages(["La fecha de fin debe ser mayor a la de inicio"]);
                $valid = false;
            }
        }
        return $valid;
    }

    public function cleanData() {
        $elements = $this->getElements();
        foreach ($elements as $element) {
            if ($element->getName() != self::ROLE) {
                $element->setValue('');
            } elseif (!empty($this->roles)) {
                $element->setValue($this->roles[0]['cod_rol']);
            }
        }
    }

}
