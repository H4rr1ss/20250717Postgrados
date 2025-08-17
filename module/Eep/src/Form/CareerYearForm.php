<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Eep\Form\FieldError;
use Zend\Validator\InArray;

class CareerYearForm extends Form {

    const FORM_NAME = 'CareerYearForm';
    const YEAR = 'year';
    const CAREER = 'career';
    const SUBMIT = 'submit';

    private $careers;
    private $years;

    public function __construct($careers) {
        parent::__construct(self::FORM_NAME);
        $this->careers = [];
        uasort($careers, function($a, $b) {
            $aVal = $a['cod_grado'];
            $bVal = $b['cod_grado'];
            if ($aVal == $bVal) {
                return 0;
            }
            return $aVal > $bVal ? 1 : -1;
        });
        foreach ($careers as $career) {
            $careerCode = $career['cod_carrera'];
            $careerName = $career['alias_actual'];
            $this->careers[$careerCode] = $careerName;
        }
        $this->years = [];
        for ($year = date('Y'); $year > 1970; $year--) {
            $this->years[$year] = $year;
        }
        $this->setAttribute('method', 'post');
        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        //CAREERS
        $this->add([
            'type' => 'select',
            'name' => self::CAREER,
            'options' => [
                'label' => 'Carrera',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value_options' => $this->careers,
            ],
            'attributes' => [
                'id' => self::CAREER,
                'class' => 'form-control'
            ]
        ]);

        //YEARS
        $this->add([
            'type' => 'select',
            'name' => self::YEAR,
            'options' => [
                'label' => 'Año',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value_options' => $this->years,
            ],
            'attributes' => [
                'id' => self::YEAR,
                'class' => 'form-control'
            ]
        ]);

        //SUBMIT IN BUTTON
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'id' => self::SUBMIT,
                'value' => 'Buscar',
                'class' => 'btn btn-primary',
                'style' => "vertical-align:middle;"
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //CAREERS
        $inputFilter->add([
            'name' => self::CAREER,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => array_keys($this->careers),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);

        //YEARS
        $inputFilter->add([
            'name' => self::YEAR,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => array_keys($this->years),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
    }

}
