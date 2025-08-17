<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Date;
use Zend\Validator\GreaterThan;
use Zend\Validator\LessThan;
use Eep\Form\FieldError;

class CohortForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'CohortForm';
    //FORM
    const COHORT = 'cohort';
    const START_DATE = 'startDate';
    const FINISH_DATE = 'finishDate';
    const DELETE_COHORT = "deleteCohort";
    //TYPES
    const TYPE_NEW = 'newCohort';
    const TYPE_QUERY = 'queryCohort';

    private $type;

    public function __construct($type, $url) {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');
        $this->type = $type;
        $this->setAttribute('action', $url);
        switch ($type) {
            case self::TYPE_NEW:
                $this->addNewCohortElements();
                $this->addNewCohortInputFilter();
                break;
            case self::TYPE_QUERY:
                $this->addQueryElements();
                $this->addQueryInputFilter();
                break;
            case self::DELETE_COHORT:
            default:
                break;
        }
    }

    public function addNewCohortElements() {
        //COHORT
        $infoButton = '<button type="button" data-container="body" data-toggle="popover" data-placement="top" 
                                                data-content="La fecha de cohorte determina el pensum, nombre y costo de los cursos" 
                                                class="btn-info" data-original-title="" title="">
                                            <i class="fa fa-info"></i>
                                        </button>';
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::COHORT,
            'options' => [
                'label' => "Fecha de cohorte $infoButton",
                'label_attributes' => [
                    'class' => 'control-label',
                //'disable_html_escape' => true,
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
            'attributes' => [
                'id' => self::COHORT,
                'class' => 'form-control',
                'step' => '1',
            ]
        ]);

        //SUBMIT IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::TYPE_NEW,
            'attributes' => [
                'value' => 'Crear cohorte',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addNewCohortInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //COHORT
        $inputFilter->add([
            'name' => self::COHORT,
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
                    'break_chain_on_failure' => true,
                    'options' => [
                        'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
                [
                    'name' => GreaterThan::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => \date('Y') . '-01-01', //'01/01/currentYear',
                        'messages' => FieldError::GREATER_THAN,
                        'inclusive' => true
                    ],
                ],
                [
                    'name' => LessThan::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'max' => (\date('Y') + 1) . '-01-01', //'01/01/nextYear',
                        'messages' => FieldError::LESS_THAN,
                        'inclusive' => true
                    ],
                ],
            ],
        ]);
    }

    public function addQueryElements() {
        //START DATE
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::START_DATE,
            'options' => [
                'label' => 'Fecha de inicio',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
            'attributes' => [
                'value' => \date('Y') . '-01-01',
                'id' => self::START_DATE,
                'class' => 'form-control',
                'step' => '1',
            ]
        ]);

        //FINISH DATE
        $this->add([
            'type' => 'Zend\Form\Element\Date',
            'name' => self::FINISH_DATE,
            'options' => [
                'label' => 'Fecha de fin',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'format' => 'Y-m-d',
                'messages' => FieldError::DATE
            ],
            'attributes' => [
                'value' => \date('Y-m-d'),
                'id' => self::FINISH_DATE,
                'class' => 'form-control',
                'step' => '1',
            ]
        ]);

        //SUBMIT IN BUTTON
        $this->add([
            'type' => 'button',
            'name' => self::TYPE_QUERY,
            'options' => [
                'label' => 'Buscar cohortes'//'<i class="fa fa-trash"></i>',
//                'label_options' => [
//                    'disable_html_escape' => true,
//                ]
            ],
            'attributes' => [
                'type' => 'submit',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addQueryInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //COHORT

        $inputFilter->add([
            'name' => self::START_DATE,
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
                    'break_chain_on_failure' => true,
                    'options' => [
                        'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
                [
                    'name' => GreaterThan::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => '1900-01-01', //'01/01/1900',
                        'messages' => FieldError::GREATER_THAN,
                        'inclusive' => true
                    ],
                ],
                [
                    'name' => LessThan::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'max' => (string) \date('Y-m-d'),
                        'inclusive' => true,
                        'messages' => FieldError::LESS_THAN
                    ],
                ],
            ],
        ]);


        $inputFilter->add([
            'name' => self::FINISH_DATE,
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
                    'break_chain_on_failure' => true,
                    'options' => [
                        'format' => 'Y-m-d',
                        'messages' => FieldError::DATE
                    ],
                ],
                [
                    'name' => GreaterThan::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'min' => '1900-01-01', //'01/01/1900',
                        'messages' => FieldError::GREATER_THAN
                    ],
                ],
            ],
        ]);
    }

    public function isValid() {
        $valid = parent::isValid();
        if ($valid && $this->type == self::TYPE_QUERY) {
            $start = $this->get(self::START_DATE)->getValue();
            $finish = $this->get(self::FINISH_DATE)->getValue();
            if ($start > $finish) {
                $this->get(self::FINISH_DATE)->setMessages(["La fecha de fin debe ser mayor a la de inicio"]);
                $valid = false;
            }
        }
        return $valid;
    }

}
