<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;

class DeleteIdForm extends Form {

    const FORM_NAME = 'DeleteIdForm';
    const DELETE_ID = 'id-to-delete';
    const SUBMIT = 'submit';

    private $deleteText;

    public function __construct($url = null, $deleteText = null) {
        parent::__construct(self::FORM_NAME);
        $this->deleteText = $deleteText ?? '<i class="fa fa-trash"></i>';
        $this->setAttribute('method', 'post');
        if ($url != null) {
            $this->setAttribute('action', $url);
        }

        $this->addElements();
        $this->addInputFilter();
    }

    public function setValue($value) {
        $this->get(self::DELETE_ID)->setValue($value);
    }

    private function addElements() {
        //DELETE_ID
        $this->add([
            'type' => 'hidden',
            'name' => self::DELETE_ID,
        ]);

        //SUBMIT
        $this->add([
            'type' => 'button',
            'name' => self::SUBMIT,
            'options' => [
                'label' => $this->deleteText,
                'label_options' => [
                    'disable_html_escape' => true,
                ]
            ],
            'attributes' => [
                'type' => 'submit',
                'class' => 'btn btn-danger'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //DELETE ID
        $inputFilter->add([
            'name' => self::DELETE_ID,
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
                        'min' => 0,
                        'max' => 9, //SIGNED INT MAX VALUE IS 1.~*10^9
                        'messages' => FieldError::STRING_LENGTH
                    ],
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

}
