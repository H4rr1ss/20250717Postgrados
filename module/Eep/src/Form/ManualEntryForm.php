<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Digits;
use Zend\Validator\Regex;
use Zend\Validator\InArray;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;
use Eep\Service\UserManager;
use Zend\Validator\Date;

class ManualEntryForm extends Form {

    //FORM CONSTANTS
    const FORM_NAME = 'ManualEntryForm';
    const SUBMIT = 'boton_envio';
    //FORM
    const FINAL_GRADE_TYPE = 'Tipo_Nota_Final';
    const ACADEMIC_REGISTRY = 'Registro_Academico';
    const USER_CODE = 'Codigo_Usuario';
    const PENSUM = 'Pensum';
    const COURSE = 'Curso';
    const BALLOT = 'Boleta';
    const ACT = 'Acta_Postgrado';
    const DESCRIPTION = 'Descripcion';
    const PONDER_TYPE = 'Tipo_Ponderacion';
    const CHECK_GRADE = 'Nota_Aprobado_Reprobado';
    const GRADE = 'Nota';
    const DATE = 'Fecha_Cursado';
    const SECTION = 'Seccion';

    private $finalGradeTypes;
    private $pensums;
    private $courses;

    public function __construct($finalGradeTypes, $pensumCodes = null, $courseCodes = null) {
        parent::__construct(self::FORM_NAME);
        $this->finalGradeTypes = $finalGradeTypes;
        $this->pensums = $pensumCodes ?? [];
        $this->courses = $courseCodes ?? [];
        $this->setAttribute('method', 'post');
        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        //USER CODE
        $this->add([
            'type' => 'hidden',
            'name' => self::USER_CODE,
            'attributes' => [
                'id' => self::USER_CODE,
            ]
        ]);

        //FINAL GRADE TYPE
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::FINAL_GRADE_TYPE,
            'options' => [
                'label' => 'Tipo de Nota Final',
                'value_options' => $this->finalGradeTypes,
            ],
            'attributes' => [
                'id' => self::FINAL_GRADE_TYPE,
                'class' => 'form-control'
            ]
        ]);

        //ACADEMIC REGISTRY CODE
        $this->add([
            'type' => 'text',
            'name' => self::ACADEMIC_REGISTRY,
            'options' => [
                'label' => 'Registro Académico',
                'label_attributes' => [
                    'class' => 'control-label',
                ],
            ],
            'attributes' => [
                'id' => self::ACADEMIC_REGISTRY,
                'class' => 'form-control',
                'placeholder' => '(Seleccione al escribir)'
            ],
        ]);

        //PENSUM
        $options = [
            'label' => 'Carrera - Pensum',
            'value_options' => $this->pensums,
        ];
        if (empty($this->pensums)) {
            $options['empty_option'] = '(Indique un estudiante)';
        }
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::PENSUM,
            'options' => $options,
            'attributes' => [
                'id' => self::PENSUM,
                'class' => 'form-control'
            ]
        ]);

        //COURSE
        $options = [
            'label' => 'Curso',
            'value_options' => $this->courses,
        ];
        if (empty($this->courses)) {
            $options['empty_option'] = '(Seleccione una carrera)';
        }
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::COURSE,
            'options' => $options,
            'attributes' => [
                'id' => self::COURSE,
                'class' => 'form-control'
            ]
        ]);

        //BALLOT
        $this->add([
            'type' => 'text',
            'name' => self::BALLOT,
            'options' => [
                'label' => 'Recibo',
                'label_attributes' => [
                    'class' => 'control-label',
                ],
            ],
            'attributes' => [
                'id' => self::BALLOT,
                'class' => 'form-control',
                'placeholder' => 'Recibo del banco'
            ],
        ]);

        //POSTGRADUATE ACT
        $this->add([
            'type' => 'text',
            'name' => self::ACT,
            'options' => [
                'label' => 'Acta de Postgrado',
                'label_attributes' => [
                    'class' => 'control-label',
                ],
            ],
            'attributes' => [
                'id' => self::ACT,
                'class' => 'form-control',
                'placeholder' => 'Código del acta'
            ],
        ]);

        //DESCRIPTION
        $this->add([
            'type' => 'textarea',
            'name' => self::DESCRIPTION,
            'options' => [
                'label' => 'Descripción',
                'label_attributes' => [
                    'class' => 'control-label',
                ],
            ],
            'attributes' => [
                'id' => self::DESCRIPTION,
                'class' => 'form-control',
                'placeholder' => 'Información adicional de sustentación del ingreso de nota'
            ],
        ]);


        //PONDER TYPE
        $this->add([
            'type' => 'radio',
            'name' => self::PONDER_TYPE,
            'attributes' => [
                'id' => self::PONDER_TYPE,
                'class' => 'form-control',
            ],
            'options' => [
                'label' => 'Tipo de ponderación',
                'label_options' => [
                    'disable_html_escape' => true,
                ],
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value_options' => [
                    [
                        'value' => 1,
                        'label' => 'Con nota &nbsp;',
                        'selected' => true
                    ],
                    [
                        'value' => 0,
                        'label' => 'Aprobado/Reprobado',
                        'selected' => false
                    ],
                ]
            ]
        ]);


        //CHECK PONDER GRADE
        $this->add([
            'type' => 'Zend\Form\Element\Checkbox',
            'name' => self::CHECK_GRADE,
            'options' => [
                'label' => 'Aprobado',
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0'
            ],
            'attributes' => [
                'id' => self::CHECK_GRADE,
            //'value' => $isChecked ? '1' : '0'
            ]
        ]);

        //GRADE
        $this->add([
            'type' => 'text',
            'name' => self::GRADE,
            'attributes' => [
                'id' => self::GRADE,
                'class' => 'form-control',
                'placeholder' => '(punteo)'
            ],
        ]);

        //DATE
        $this->add([
            'type' => 'date',
            'name' => self::DATE,
            'options' => [
                'label' => 'Fecha cursado',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
            'attributes' => [
                'id' => self::DATE,
                'class' => 'form-control',
                'step' => '1',
            ]
        ]);

        //SECTION
        $this->add([
            'type' => 'text',
            'name' => self::SECTION,
            'options' => [
                'label' => 'Sección',
                'label_attributes' => [
                    'class' => 'control-label',
                ],
            ],
            'attributes' => [
                'id' => self::SECTION,
                'class' => 'form-control',
                'placeholder' => 'Sección asociada'
            ],
        ]);

        //SUBMIT BUTTON
        $this->add([
            'type' => 'button',
            'name' => self::SUBMIT,
            'options' => [
                'label' => 'Guardar nota',
                'label_attributes' => [
                    'class' => 'control-label',
                ],
            ],
            'attributes' => [
                'type' => 'submit',
                'class' => "btn btn-primary"
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //USER CODE
        $inputFilter->add([
            'name' => self::USER_CODE,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
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

        //USER ID
        $inputFilter->add([
            'name' => self::ACADEMIC_REGISTRY,
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
                        'max' => 30,
                        'messages' => FieldError::STRING_LENGTH
                    ]
                ],
                [
                    'name' => Regex::class,
                    'options' => [
                        'pattern' => UserManager::PASSPORT_PATTERN,
                        'messages' => FieldError::REGEX
                    ]
                ]
            ]
        ]);

        //FINAL GRADE TYPE
        $inputFilter->add([
            'name' => self::FINAL_GRADE_TYPE,
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
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => array_keys($this->finalGradeTypes),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);

        //PENSUM
        $inputFilter->add([
            'name' => self::PENSUM,
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
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => array_keys($this->pensums),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ]
        ]);

        //COURSE
        $inputFilter->add([
            'name' => self::COURSE,
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
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => array_keys($this->courses),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ]
        ]);

        //BALLOT
        $inputFilter->add([
            'name' => self::BALLOT,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 11,
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

        //POSTGRADUATE ACT CODE
        $inputFilter->add([
            'name' => self::ACT,
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
                        'max' => 15,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //DESCRIPTION
        $inputFilter->add([
            'name' => self::DESCRIPTION,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 5000,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);

        //PONDER_TYPE
        $inputFilter->add([
            'name' => self::PONDER_TYPE,
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
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => ['0', '1'], //WITH GRADE IS "1" (==TRUE)
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);

        //GRADE
        $inputFilter->add([
            'name' => self::GRADE,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => Digits::class,
                    'options' => [
                        'messages' => FieldError::DIGITS
                    ],
                ],
            ],
        ]);

        //CHECK_GRADE
        $inputFilter->add([
            'name' => self::CHECK_GRADE,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => ['0', '1'],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);


        //DATE
        $inputFilter->add([
            'name' => self::DATE,
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
                    'name' => Date::class,
                    'options' => [
                        'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
            ],
        ]);


        //SECTION
        $inputFilter->add([
            'name' => self::SECTION,
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
                        'max' => 20,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);
    }

    public function isValid() {
        $valid = parent::isValid();
        if ($valid) {
            $ponderTypeElement = $this->get(self::PONDER_TYPE);
            $hasGrade = $ponderTypeElement->getValue();
            if ($hasGrade === null) {
                $ponderTypeElement->setMessages([FieldError::NOT_EMPTY[\Zend\Validator\NotEmpty::IS_EMPTY]]);
            } else {
                //VALIDATING GRADE
                $validator = new InputFilter();
                if ($hasGrade) {
                    //VALIDATING SINTAX
                    $gradeElement = $this->get(self::GRADE);
                    $grade = $gradeElement->getValue();
                    $validator->add([
                        'name' => self::GRADE,
                        'required' => true,
                        'filters' => [
                            ['name' => 'StringTrim'],
                        ],
                        'validators' => [
                            [
                                'name' => Regex::class,
                                'options' => [
                                    'pattern' => '/^(?:\d+|\d*\.(\d{2}|\d))$/',
                                    'messages' => FieldError::DECIMAL_REGEX
                                ]
                            ]
                        ],
                    ]);
                    $validator->setData([self::GRADE => $grade]);
                    if (!$validator->isValid()) {
                        $gradeElement->setMessages($validator->getMessages());
                        $valid = false;
                    }
                } else {
                    //APPROVAL STATE MUST BE PROVIDED
                    $approvalElement = $this->get(self::CHECK_GRADE);
                    $approved = $approvalElement->getValue();
                    $validator->add([
                        'name' => self::CHECK_GRADE,
                        'required' => true,
                        'filters' => [
                            ['name' => 'StringTrim'],
                        ],
                        'validators' => [
                            [
                                'name' => InArray::class,
                                'options' => [
                                    'haystack' => ['0', '1'],
                                    'messages' => FieldError::IN_ARRAY
                                ],
                            ],
                        ],
                    ]);
                    $validator->setData([self::CHECK_GRADE => $approved]);
                    if (!$validator->isValid()) {
                        $gradeElement->setMessages($validator->getMessages());
                        $valid = false;
                    }
                }
            }
        }
        return $valid;
    }

}
