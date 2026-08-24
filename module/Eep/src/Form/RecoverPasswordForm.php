<?php
namespace Eep\Form;

use Zend\Form\Form;
use Zend\Form\Element;
use Zend\InputFilter\InputFilter;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;

class RecoverPasswordForm extends Form {
    const EMAIL = 'email';

    public function __construct($name = null) {
        parent::__construct('recover-password');

        // Email field
        $email = new Element('email');
        $email->setLabel('Correo electrónico')
              ->setAttributes([
                  'type' => 'email',
                  'required' => true,
                  'placeholder' => 'Ingresa tu correo electrónico',
                  'class' => 'form-control',
              ]);
        $this->add($email);

        // Submit button
        $submit = new Element('submit');
        $submit->setValue('Recuperar contraseña')
               ->setAttributes([
                   'type' => 'submit',
                   'class' => 'btn btn-primary',
               ]);
        $this->add($submit);

        // Input filter
        $inputFilter = new InputFilter();
        $inputFilter->add([
            'name' => self::EMAIL,
            'required' => true,
            'validators' => [
                ['name' => NotEmpty::class],
                ['name' => EmailAddress::class],
            ],
        ]);
        $this->setInputFilter($inputFilter);
    }
}
