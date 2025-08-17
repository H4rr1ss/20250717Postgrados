<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;

class LoginForm extends Form
{

    const FORM_NAME = 'LoginForms';
    const USER = 'user_id';
    const PASS = 'user_pass';
    const SIGN_IN = 'signin';
    const REMEMBER_ME = 'remember_me';
    const REDIRECT = 'redirect_URL';

    public function __construct()
    {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');

        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements()
    {
        //REDIRECT URL
        $this->add([
            'type' => 'hidden',
            'name' => self::REDIRECT,
        ]);
        //USER ID
        $this->add([
            'type' => 'text',
            'name' => self::USER,
            'attributes' => [
                'id' => self::USER,
                'class' => 'form-control',
                'placeholder' => 'Registro Académico / CUI / Pasaporte / Registro de Personal'
            ],
            'options' => [
                'label' => 'Registro Académico / CUI / Pasaporte / Registro de Personal',
            ],
        ]);

        //USER PASSWORD
        $this->add([
            'type' => 'password',
            'name' => self::PASS,
            'attributes' => [
                'id' => self::PASS,
                'class' => 'form-control',
                'placeholder' => 'Contraseña'
            ],
            'options' => [ // Array of options
                'label' => 'Contraseña',
            ],
        ]);

        //REMEMBER ME CHECKBUTTON
        $this->add([
            'type' => 'checkbox',
            'name' => self::REMEMBER_ME,
            'options' => [
                'label' => 'Recordarme por 30 días'
            ]
        ]);

        //SIGN IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SIGN_IN,
            'attributes' => [
                'id' => self::SIGN_IN,
                'value' => 'Ingresar',
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    private function addInputFilter()
    {
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
                /*[
                    'name' => Digits::class,
                    'options' => [
                        'messages' => FieldError::DIGITS
                    ],
            ],*/
            ],
        ]);

        $inputFilter->add([
            'name' => self::PASS,
            'required' => true,
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'options' => [
                        'messages' => \Eep\Form\FieldError::NOT_EMPTY
                    ],
                ],
            ],
        ]);
    }

}
