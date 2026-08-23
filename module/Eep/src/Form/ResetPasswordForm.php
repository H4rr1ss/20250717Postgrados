<?php
namespace Eep\Form;

use Zend\Form\Form;
use Zend\Form\Element;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\Identical;

class ResetPasswordForm extends Form {
    const PASSWORD = 'password';
    const CONFIRM_PASSWORD = 'confirm_password';

    public function __construct($name = null) {
        parent::__construct('reset-password');

        $password = new Element\Password(self::PASSWORD);
        $password->setLabel('Nueva contraseña')
            ->setAttributes([
                'required' => true,
                'placeholder' => 'Ingrese su nueva contraseña',
                'class' => 'form-control',
            ]);
        $this->add($password);

        $confirm = new Element\Password(self::CONFIRM_PASSWORD);
        $confirm->setLabel('Confirmar contraseña')
            ->setAttributes([
                'required' => true,
                'placeholder' => 'Repita su nueva contraseña',
                'class' => 'form-control',
            ]);
        $this->add($confirm);

        $submit = new Element('submit');
        $submit->setValue('Restablecer contraseña')
            ->setAttributes([
                'type' => 'submit',
                'class' => 'btn btn-primary',
            ]);
        $this->add($submit);

        $inputFilter = new InputFilter();
        $inputFilter->add([
            'name' => self::PASSWORD,
            'required' => true,
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'options' => [
                        'messages' => [
                            NotEmpty::IS_EMPTY => 'Debe ingresar una contraseña.',
                        ],
                    ],
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 6,
                        'max' => 64,
                        'messages' => [
                            StringLength::TOO_SHORT => 'La contraseña debe tener al menos %min% caracteres.',
                            StringLength::TOO_LONG  => 'La contraseña no debe exceder %max% caracteres.',
                        ],
                    ],
                ],
            ],
        ]);
        $inputFilter->add([
            'name' => self::CONFIRM_PASSWORD,
            'required' => true,
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'options' => [
                        'messages' => [
                            NotEmpty::IS_EMPTY => 'Debe confirmar la contraseña.',
                        ],
                    ],
                ],
                [
                    'name' => Identical::class,
                    'options' => [
                        'token' => self::PASSWORD,
                        'messages' => [
                            Identical::NOT_SAME => 'Las contraseñas no coinciden.',
                            Identical::MISSING_TOKEN => 'Las contraseñas no coinciden.',
                        ],
                    ],
                ],
            ],
        ]);
        $this->setInputFilter($inputFilter);
    }
}
