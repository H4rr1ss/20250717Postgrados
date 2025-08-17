<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Date;
use Zend\Validator\InArray;
use Eep\Form\FieldError;

class CohortUsersForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'CohortUsersForm';
    const SUBMIT = 'submitCohort';
    //FORM
    const COHORT = 'cohorte';

    private $cohorts;

    public function __construct($cohorts) {
        parent::__construct(self::FORM_NAME);
        $cleanData = [];
        foreach ($cohorts as $element) {
            $cleanData[$element['fecha_cohorte']] = date("d/m/Y", strtotime($element['fecha_cohorte']));
        }
        $this->cohorts = $cleanData;

        $this->setAttribute('method', 'post');


        $this->addElements();
        $this->addInputFilter();
    }

    public function addElements() {
        //CLEANING DATA
        //COHORTS
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::COHORT,
            'options' => [
                'label' => 'Cohorte',
                'label_attributes' => [
                    'class' => 'col-md-1 control-label'
                ],
                'value_options' => $this->cohorts,
                'empty_option' => '(Curso de Actualización)'
            ],
            'attributes' => [
                'id' => self::COHORT,
                'class' => 'form-control'
            ]
        ]);

        //SUBMIT IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'value' => 'Buscar estudiantes',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //COHORT
        $inputFilter->add([
            'name' => self::COHORT,
            'required' => false,
            'validators' => [
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => array_merge(array_keys($this->cohorts), ['']),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
    }

}
