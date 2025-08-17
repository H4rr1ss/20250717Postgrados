<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\Regex;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;
use Eep\Service\UserManager;

class UserIdForm extends Form {

    const FORM_NAME = 'UserIdForm';
    const USER = 'user';
    const SUBMIT = 'submit';

    public function __construct($url = null, $intValue = false) {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');
        if ($url != null) {
            $this->setAttribute('action', $url);
        }

        $this->addElements();
        $this->addInputFilter($intValue);
    }

    private function addElements() {
        //USER
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

        //SUBMIT IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'id' => self::SUBMIT,
                'value' => 'Buscar',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    public function setData($data) {
        parent::setData($data);
        if ($this->has(self::SUBMIT)) {
            $this->get(self::SUBMIT)->setValue('Buscar');
        }
    }

    private function addInputFilter($intValue = false) {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //USER
        $validators = [
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
                    'min' => 0,
                    'max' => $intValue ? 9 : 30,
                    'messages' => FieldError::STRING_LENGTH
                ],
            ],
        ];
        if ($intValue) {
            $validators[] = [
                'name' => Digits::class,
                'options' => [
                    'messages' => FieldError::DIGITS
                ],
            ];
        } else {
            $validators[] = [
                'name' => Regex::class,
                'options' => [
                    'pattern' => UserManager::PASSPORT_PATTERN,
                    'messages' => FieldError::REGEX
                ]
            ];
        }
        $inputFilter->add([
            'name' => self::USER,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => $validators
        ]);
    }

}
