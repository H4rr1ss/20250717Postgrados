<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Eep\Service\UserManager;
use Zend\Validator\Regex;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\InArray;
use Eep\Form\FieldError;

class StudentSearchForm extends Form {

    const FORM_NAME = 'StudentSearchForm';
    const SEARCH_TYPE = 'searchType';
    const USER = 'user';
    const USER_LABEL = self::USER . 'Label';
    const SUBMIT = 'submit';
    const TYPE_CODE = 'code';
    const TYPE_NAME = 'name';

    private $type;

    public function __construct($type = null) {
        parent::__construct(self::FORM_NAME);

        if (empty($type)) {
            $this->type = self::TYPE_CODE;
        } else {
            switch ($type) {
                case self::TYPE_NAME:
                    $this->type = self::TYPE_NAME;
                    break;
                case self::TYPE_CODE:
                default:
                    $this->type = self::TYPE_CODE;
                    break;
            }
        }

        $this->setAttribute('method', 'post');

        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        //TYPE
        $this->add([
            'type' => 'Zend\Form\Element\Radio',
            'name' => self::SEARCH_TYPE,
            'attributes' => [
                'id' => self::SEARCH_TYPE,
            ],
            'options' => [
                'label' => 'Tipo de búsqueda',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                //'value' => ['code'],
                'value_options' => [
                    [
                        'value' => self::TYPE_CODE,
                        'label' => 'Código',
                        'selected' => $this->type == self::TYPE_CODE
                    ],
                    [
                        'value' => self::TYPE_NAME,
                        'label' => 'Nombre',
                        'selected' => $this->type == self::TYPE_NAME
                    ],
                ]
            ]
        ]);

        //DATA
        $this->add([
            'type' => 'text',
            'name' => self::USER,
            'attributes' => [
                'id' => self::USER,
                'class' => 'form-control',
                'placeholder' => 'Registro Académico / CUI / Pasaporte / Registro de Personal || Nombre / Apellido'
            ],
            'options' => [
                'label' => 'Usuario',
                'label_attributes' => [
                    'id' => self::USER_LABEL,
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

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        $validators = [
            [
                'name' => NotEmpty::class,
                'options' => [
                    'messages' => FieldError::NOT_EMPTY
                ],
        ]];
        if ($this->type == self::TYPE_CODE) {
            $validators[] = [
                'name' => StringLength::class,
                'options' => [
                    'min' => 1,
                    'max' => 18, //SIGNED BIG INT MAX VALUE IS 9.~*10^18
                    'messages' => FieldError::STRING_LENGTH
                ],
            ];
            $validators[] = [
                'name' => Regex::class,
                'options' => [
                    'pattern' => UserManager::PASSPORT_PATTERN,
                    'messages' => FieldError::REGEX
                ]
            ];
        } else {
            $validators[] = [
                'name' => StringLength::class,
                'options' => [
                    'min' => 0,
                    'max' => 120, //60 EACH FIELD (NAME AND LASTNAME)
                    'messages' => FieldError::STRING_LENGTH
                ],
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

        $inputFilter->add([
            'name' => self::SEARCH_TYPE,
            'required' => true,
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
                        'haystack' => [self::TYPE_CODE, self::TYPE_NAME],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
    }

}
