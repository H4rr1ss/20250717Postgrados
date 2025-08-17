<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Hostname;
use Zend\Validator\StringLength;
use Zend\Validator\EmailAddress;
use Eep\Form\FieldError;

class ProfileForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'ProfileForm';
    const SUBMIT = 'userSubmit';
    //USER
    const EMAIL = 'correo';
    const PHONE = 'telefono';

    private $user;

    public function __construct($user, $url) {
        parent::__construct(self::FORM_NAME);
        $this->setAttribute('method', 'post');
        $this->setAttribute('action', $url);
        $this->setAttribute('class', 'form-horizontal');
        $this->user = $user;
        $this->addElements();
        $this->addInputFilter();
    }

    public function getUser() {
        return $this->user;
    }

    private function addElements() {
        //EMAIL
        $this->add([
            'type' => 'text',
            'name' => self::EMAIL,
            'attributes' => [
                'value' => $this->user->getCorreo(),
                'id' => self::EMAIL,
                'placeholder' => 'Correo electrónico',
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Correo',
                'label_attributes' => [
                    'class' => 'col-md-2 control-label'
                ],
            ],
        ]);

        //TELEFONO
        $this->add([
            'type' => 'text',
            'name' => self::PHONE,
            'attributes' => [
                'value' => $this->user->getTelefono(),
                'id' => self::PHONE,
                'placeholder' => 'Número telefónico',
                'class' => 'form-control'
            ],
            'options' => [
                'label' => 'Teléfono',
                'label_attributes' => [
                    'class' => 'col-md-2 control-label'
                ],
            ],
        ]);

        //SUBMIT BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'value' => 'Actualizar datos',
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
                        'min' => 0,
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
                        'min' => 8,
                        'max' => 15,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);
    }

    public function isValid() {
        $valid = parent::isValid();
        if ($valid === true) {
            $data = $this->getData();
            $this->getUser()->setTelefono($data[self::PHONE]);
            $this->getUser()->setCorreo($data[self::EMAIL]);
        }
        return $valid;
    }

}
