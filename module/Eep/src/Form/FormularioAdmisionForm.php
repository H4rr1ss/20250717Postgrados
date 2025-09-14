<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\Date;
use Zend\Validator\InArray;
use Zend\Validator\GreaterThan;
use Eep\Form\FieldError;

class FormularioAdmisionForm extends Form {

    //FORM CONSTANTS
    const FORM_NAME = 'FormularioAdmisionForm';
    const SUBMIT = 'formularioAdmisionSubmit';
    
    //FIELDS NAMES
    const NOMBRE = 'nombre';
    const FECHA_INICIO_ADMISION = 'fecha_inicio_admision';
    const FECHA_FIN_ADMISION = 'fecha_fin_admision';


    public function __construct($url) {
        parent::__construct(self::FORM_NAME);
        $this->setAttribute('method', 'post');
        $this->setAttribute('action', $url);
        $this->addElements();
        $this->addInputFilter();
    }


    private function addElements() {
        //NOMBRE DEL FORMULARIO
        $this->add([
            'type' => 'text',
            'name' => self::NOMBRE,
            'attributes' => [
                'id' => self::NOMBRE,
                'class' => 'form-control',
                'placeholder' => 'Ej: Formulario de Admisión 2025-1',
                'required' => true
            ],
            'options' => [
                'label' => 'Nombre del Formulario',
            ],
        ]);

        //FECHA INICIO ADMISION
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::FECHA_INICIO_ADMISION,
            'attributes' => [
                'id' => self::FECHA_INICIO_ADMISION,
                'class' => 'form-control',
                'required' => true,
                'step' => '1'
            ],
            'options' => [
                'label' => 'Fecha Inicio Admisión',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
        ]);

        //FECHA FIN ADMISION
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::FECHA_FIN_ADMISION,
            'attributes' => [
                'id' => self::FECHA_FIN_ADMISION,
                'class' => 'form-control',
                'required' => true,
                'step' => '1'
            ],
            'options' => [
                'label' => 'Fecha Fin Admisión',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
        ]);

        //SUBMIT BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'value' => 'Crear Formulario',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //NOMBRE
        $inputFilter->add([
            'name' => self::NOMBRE,
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
                        'min' => 5,
                        'max' => 100,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

    // Eliminado campo de cohorte

        //FECHA_INICIO_ADMISION
        $inputFilter->add([
            'name' => self::FECHA_INICIO_ADMISION,
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
                    'name' => Date::class,
                    'options' => [
                        'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
                [
                    'name' => GreaterThan::class,
                    'options' => [
                        'min' => date('Y-m-d'),
                        'inclusive' => true,
                        'messages' => [
                            GreaterThan::NOT_GREATER_INCLUSIVE => 'La fecha de inicio debe ser posterior a la fecha actual'
                        ]
                    ],
                ],
            ],
        ]);

        //FECHA_FIN_ADMISION
        $inputFilter->add([
            'name' => self::FECHA_FIN_ADMISION,
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
                    'name' => Date::class,
                    'options' => [
                            'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
            ],
        ]);
    }

    //VALIDACION PERSONALIZADA
    public function isValid() {
        $isValid = parent::isValid();

        //VALIDAR QUE FECHA FIN SEA MAYOR QUE FECHA INICIO
        $fechaInicio = $this->get(self::FECHA_INICIO_ADMISION)->getValue();
        $fechaFin = $this->get(self::FECHA_FIN_ADMISION)->getValue();
        
        if (!empty($fechaInicio) && !empty($fechaFin)) {
            if (strtotime($fechaFin) <= strtotime($fechaInicio)) {
                $this->get(self::FECHA_FIN_ADMISION)->setMessages([
                    'La fecha de fin debe ser posterior a la fecha de inicio'
                ]);
                $isValid = false;
            }
        }

        return $isValid;
    }

    public function clearData() {
        $elements = $this->getElements();
        foreach ($elements as $field) {
            if ($field->getName() == self::SUBMIT) {
                continue;
            }
            $field->setValue('');
        }
    }
}
