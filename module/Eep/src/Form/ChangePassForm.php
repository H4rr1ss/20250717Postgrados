<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;

class ChangePassForm extends Form {

    const FORM_NAME = 'ChangePassForm';
    const ACTUAL = 'user_pass';
    const NEW_PASS = 'new_pass';
    const CONFIRM_PASS = 'confirm_pass';
    const SIGN_IN = 'signin';

    public function __construct() {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');

        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        //ACTUAL PASSWORD
        $this->add([
            'type' => 'password', // Element type
            'name' => self::ACTUAL, // Field name
            'attributes' => [// Array of attributes
                'id' => self::ACTUAL,
                'placeholder' => 'Ingrese la contraseña actual',
                'class' => 'form-control'
            ],
            'options' => [// Array of options
                'label' => 'Ingrese su contraseña actual',
            ],
        ]);

        //USER NEW PASSWORD
        $this->add([
            'type' => 'password',
            'name' => self::NEW_PASS,
            'attributes' => [
                'id' => self::NEW_PASS,
                'placeholder' => 'Ingrese la nueva contraseña',
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Ingrese la nueva contraseña',
            ],
        ]);

        //USER NEW PASSWORD CONFIRMATION
        $this->add([
            'type' => 'password',
            'name' => self::CONFIRM_PASS,
            'attributes' => [
                'id' => self::CONFIRM_PASS,
                'placeholder' => 'Vuelva a ingresar la nueva contraseña',
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Vuelva a ingresar la nueva contraseña',
            ],
        ]);

        //SIGN IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SIGN_IN,
            'attributes' => [
                'value' => 'Actualizar contraseña',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        $inputFilter->add([
            'name' => self::ACTUAL,
            'required' => true,
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
                        'min' => 1, //ACTUAL PASSWORD MIGHT BE TINIER FOR ANY FLAW
                        'max' => 63, //nvarchar 64 IN DATABASE
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name' => self::NEW_PASS,
            'required' => true,
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
                        'min' => 6,
                        'max' => 63, //nvarchar 64 IN DATABASE
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);


        $inputFilter->add([
            'name' => self::CONFIRM_PASS,
            'required' => true,
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
                        'min' => 6,
                        'max' => 63, //nvarchar 64 IN DATABASE
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);
    }

}
