<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;

class InscriptionOrderForm extends Form {

    const FORM_NAME = 'InscriptionOrderForm';
    const USER = 'user_id';
    const ORDER = 'order';
    const SUBMIT = 'submit';

    public function __construct() {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');

        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        //USER ID
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
                ],
            ],
        ]);

        //PAYMENT ORDER
        $this->add([
            'type' => 'text',
            'name' => self::ORDER,
            'attributes' => [
                'id' => self::ORDER,
                'class' => 'form-control',
                'placeholder' => 'Número de Órden de Pago'
            ],
            'options' => [
                'label' => 'Orden de pago',
            ],
        ]);

        //SIGN IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'id' => self::SUBMIT,
                'value' => 'Ingresar',
                'class' => 'btn btn-primary',
                'value' => 'Completar inscripción'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

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

        $inputFilter->add([
            'name' => self::ORDER,
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
                        'max' => 9,
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
    }

}
