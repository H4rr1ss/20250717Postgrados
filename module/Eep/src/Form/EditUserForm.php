<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Hostname;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\Date;
use Zend\Validator\EmailAddress;
use Zend\Validator\InArray;
use Eep\Form\FieldError;
use Zend\Validator\Regex;
use Eep\Entity\InfoLaboral;

class EditUserForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const USER_CODE_SUBMIT = 'userCodeSubmit';
    const FORM_NAME = 'EditUserForm';
    //USER
    //LABELS
    const NAMES = 'nombres';
    const LAST_NAMES = 'apellidos';
    const CUI_PASSPORT = 'cuiPassport';
    const ACADEMIC_REGISTRY = 'registro_academico';
    //INPUTS
    const EMAIL = 'correo';
    const PHONE = 'telefono';
    const ACTUAL_DEGREE = 'grado_academico';
    const TITULO_PROFESIONAL = 'titulo_profesional';
    const NUMERO_COLEGIADO = 'numero_colegiado';
    //LABORAL INFO
    const CURRENTLY_WORKS = 'worksCurrently';
    const WORK_PLACE = 'ubicacion';
    const START_TIME = 'hora_inicio';
    const FINISH_TIME = 'hora_fin';
    const DAYS = InfoLaboral::DAYS;

    private $user;

    public function __construct($user) {//THE URL REDIRECTS TO THE CORRECT ACTION CONTROLLER
        parent::__construct(self::FORM_NAME);
        if (isset($user)) {
            $this->user = $user;
        } else {
            $this->user = new User();
        }

        $this->setAttribute('method', 'post');

        $this->addElements();
        $this->addInputFilter();
    }

    public function getUser() {
        return $this->user;
    }

    private function addElements() {
        //NAMES
        $this->add([
            'type' => 'text',
            'name' => self::NAMES,
            'attributes' => [
                'id' => self::NAMES,
            ],
            'options' => [
                'label' => $this->user->getNombres(),
                'label_attributes' => [
                    'class' => 'form-control'
                ]
            ],
        ]);

        //LAST_NAME
        $this->add([
            'type' => 'text',
            'name' => self::LAST_NAMES,
            'attributes' => [
                'id' => self::LAST_NAMES,
            ],
            'options' => [
                'label' => $this->user->getApellidos(),
                'label_attributes' => [
                    'class' => 'form-control'
                ]
            ],
        ]);

        //CUI
        $this->add([
            'type' => 'text',
            'name' => self::CUI_PASSPORT,
            'attributes' => [
                'id' => self::CUI_PASSPORT,
            ],
            'options' => [
                'label' => !empty($this->user->getCui()) ? "C-" . $this->user->getCui() : "P-" . $this->user->getPasaporte(),
                'label_attributes' => [
                    'class' => 'form-control'
                ]
            ],
        ]);

        //ACADEMIC REGISTRY
        if (empty($this->user->getRegistroPersonal())) {
            if (empty($this->user->getRegistroAcademico())) {
                $registry = "(pendiente)";
            } else {
                $registry = $this->user->getRegistroAcademico();
            }
        } else {
            $registry = $this->user->getRegistroPersonal();
        }
        $this->add([
            'type' => 'text',
            'name' => self::ACADEMIC_REGISTRY,
            'attributes' => [
                'id' => self::ACADEMIC_REGISTRY,
            ],
            'options' => [
                'label' => $registry,
                'label_attributes' => [
                    'class' => 'form-control'
                ]
            ],
        ]);

        //EDITABLE
        //EMAIL
        $this->add([
            'type' => 'text',
            'name' => self::EMAIL,
            'attributes' => [
                'id' => self::EMAIL,
                'placeholder' => 'Nuevo correo electrónico',
                'class' => 'form-control',
                'value' => $this->user->getCorreo()
            ],
            'options' => [
                'label' => 'Correo electrónico',
                'label_attributes' => [
                    'class' => 'control-label'
                ]
            ],
        ]);

        //TELEFONO
        $this->add([
            'type' => 'text',
            'name' => self::PHONE,
            'attributes' => [
                'id' => self::PHONE,
                'placeholder' => 'Nuevo número telefónico',
                'class' => 'form-control',
                'value' => $this->user->getTelefono()
            ],
            'options' => [
                'label' => 'Teléfono',
                'label_attributes' => [
                    'class' => 'control-label'
                ]
            ],
        ]);

        //ACTUAL DEGREE
        $this->add([
            'type' => 'text',
            'name' => self::ACTUAL_DEGREE,
            'attributes' => [
                'id' => self::ACTUAL_DEGREE,
                'class' => 'form-control',
                'placeholder' => 'Actualice el grado académico actual del estudiante',
                'value' => $this->user->getGradoAcademico()
            ],
            'options' => [
                'label' => 'Grado académico actual',
                'label_attributes' => [
                    'class' => 'control-label'
                ]
            ],
        ]);

        //TITULO PROFESIONAL
        $this->add([
            'type' => 'text',
            'name' => self::TITULO_PROFESIONAL,
            'attributes' => [
                'id' => self::TITULO_PROFESIONAL,
                'class' => 'form-control',
                'placeholder' => 'Ej: Ing., Arq., Dr.',
                'maxlength' => 20,
                'value' => $this->user->getTituloProfesional()
            ],
            'options' => [
                'label' => 'Título profesional',
                'label_attributes' => [
                    'class' => 'control-label'
                ]
            ],
        ]);

        //NUMERO COLEGIADO
        $this->add([
            'type' => 'text',
            'name' => self::NUMERO_COLEGIADO,
            'attributes' => [
                'id' => self::NUMERO_COLEGIADO,
                'class' => 'form-control',
                'placeholder' => 'Número de colegiado',
                'value' => $this->user->getNumeroColegiado()
            ],
            'options' => [
                'label' => 'Número de colegiado',
                'label_attributes' => [
                    'class' => 'control-label'
                ]
            ],
        ]);

        $hasLaboralInfo = !empty($this->user->getInfoLaboral());
        $laboralInfo = $this->user->getInfoLaboral();
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
                'id' => self::CURRENTLY_WORKS,
                'value' => $hasLaboralInfo ? 'yes' : 'no'
            ]
        ]);

        //WORK PLACE
        $this->add([
            'type' => 'text',
            'name' => self::WORK_PLACE,
            'attributes' => [
                'id' => self::WORK_PLACE,
                'class' => 'form-control',
                'placeholder' => 'Nuevo lugar de trabajo',
                'value' => ($hasLaboralInfo && !empty($laboralInfo->getUbicacion())) ? $laboralInfo->getUbicacion() : ""
            ],
            'options' => [
                'label' => 'Ubicación laboral',
                'label_attributes' => [
                    'class' => 'control-label'
                ]
            ],
        ]);

        //START TIME
        $this->add([
            'type' => 'Zend\Form\Element\Time',
            'name' => self::START_TIME,
            'options' => [
                'label' => 'Hora de inicio',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'H:i'
            ],
            'attributes' => [
                'id' => self::START_TIME,
                'value' => ($hasLaboralInfo && !empty($laboralInfo->getHoraInicio())) ? date("H:i", strtotime($laboralInfo->getHoraInicio())) : "",
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
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'H:i'
            ],
            'attributes' => [
                'id' => self::FINISH_TIME,
                'value' => ($hasLaboralInfo && !empty($laboralInfo->getHoraFin())) ? date("H:i", strtotime($laboralInfo->getHoraFin())) : "",
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
                'value_options' => [
                    [
                        'value' => 'lunes',
                        'label' => 'Lunes',
                        'selected' => ($hasLaboralInfo && (null != $laboralInfo->getLunes()) && $laboralInfo->getLunes() == true),
                    ],
                    [
                        'value' => 'martes',
                        'label' => 'Martes',
                        'selected' => ($hasLaboralInfo && (null != $laboralInfo->getMartes()) && $laboralInfo->getMartes() == true),
                    ],
                    [
                        'value' => 'miercoles',
                        'label' => 'Miércoles',
                        'selected' => ($hasLaboralInfo && (null != $laboralInfo->getMiercoles()) && $laboralInfo->getMiercoles() == true),
                    ],
                    [
                        'value' => 'jueves',
                        'label' => 'Jueves',
                        'selected' => ($hasLaboralInfo && (null != $laboralInfo->getJueves()) && $laboralInfo->getJueves() == true),
                    ],
                    [
                        'value' => 'viernes',
                        'label' => 'Viernes',
                        'selected' => ($hasLaboralInfo && (null != $laboralInfo->getViernes()) && $laboralInfo->getViernes() == true),
                    ],
                    [
                        'value' => 'sabado',
                        'label' => 'Sábado',
                        'selected' => ($hasLaboralInfo && (null != $laboralInfo->getSabado()) && $laboralInfo->getSabado() == true),
                    ],
                    [
                        'value' => 'domingo',
                        'label' => 'Domingo',
                        'selected' => ($hasLaboralInfo && (null != $laboralInfo->getDomingo()) && $laboralInfo->getDomingo() == true),
                    ],
                ],
            ],
            'attributes' => [
                'class' => 'input-inline',
                'id' => self::DAYS
            ]
        ]);



        //SUBMIT WITH USER CODE DATA
        $this->add([
            'type' => 'button',
            'name' => self:: USER_CODE_SUBMIT,
            'options' => [
                'label' => 'Guardar cambios'
            ],
            'attributes' => [
                'value' => $this->user->getCode(),
                'type' => 'submit',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

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
                        'max' => 15,
                        'messages' => FieldError::STRING_LENGTH
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

        //TITULO PROFESIONAL
        $inputFilter->add([
            'name' => self::TITULO_PROFESIONAL,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 20,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //NUMERO COLEGIADO
        $inputFilter->add([
            'name' => self::NUMERO_COLEGIADO,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
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

}
