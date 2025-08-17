<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\Date;
use Zend\Validator\InArray;
use Eep\Form\FieldError;
use Eep\Entity\Order;

class CategorizeTimetableForm extends Form {

    //CONTAINER NAME
    const SESSION_CONTAINER = "categorizedTimetableSelection";
    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'CategorizeTimetableForm';
    const SUBMIT = 'categorizeSubmit';
    //ACADEMIC INFO
    const COHORT = 'cohorte';
    const ACADEMIC_DEGREE = 'grado_academico';
    const CAREER = 'career';
    const UPG_COURSE_COHORT = '0000-01-01';
    const UPG_COURSE_CODE = Order::CURSO_ACTUALIZACION; //NOTICE THAT WE ASUME THA THE UPGRADING COURSES CODE IS THE SAME FOR THE DEGREE, CARREER AND PENSUM.

    private $cohorts;
    private $degrees;
    private $careers;
    private $dataTree;
    private $hasData;
    private $selectedDegree;
    private $selectedCareer;
    private $selectedCohort;

    public function __construct($cohorts, $degrees, $careers, $url) {//THE URL REDIRECTS TO THE CORRECT ACTION CONTROLLER
        parent::__construct(self::FORM_NAME);
        $this->makeTree($careers);
        $this->cohorts = $this->getKeyValues($cohorts, 'fecha_cohorte', 'fecha_cohorte', true);
        $this->cohorts[self::UPG_COURSE_COHORT] = "(Cursos de Actualizacion)"; //ADDING UPDATING COURSE AUXILIAR DATE
        $this->degrees = $this->getKeyValues($degrees, 'cod_grado', 'nombre');
        $this->hasData = false;

        $this->setAttribute('method', 'post');
        $this->setAttribute('action', $url);

        $this->addElements();

        $this->addInputFilter();
    }

    private function makeTree($careers) {
        $this->dataTree = [];
        $this->careers = [];
        foreach ($careers as $career) {
            $degreeCode = $career['cod_grado'];
            $careerCode = $career['cod_carrera'];
            $careerAlias = $career['alias_actual'];
            $displayedName = $careerCode == Order::CURSO_ACTUALIZACION ? $careerAlias : $careerCode . ' - ' . $careerAlias;
            $this->dataTree[$degreeCode][$careerCode] = $displayedName;
            $this->careers[$careerCode] = $displayedName;
        }
    }

    private function getKeyValues($data, $keyName, $valueName, $date = false) {
        $keyValues = [];
        foreach ($data as $element) {
            $value = $element[$valueName];
            $keyValues[$element[$keyName]] = $date ? date('d/m/Y', strtotime($value)) : $value;
        }
        return $keyValues;
    }

    private function addElements() {
        //DEGREES
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::ACADEMIC_DEGREE,
            'options' => [
                'label' => 'Estudio de postgrados',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value_options' => $this->degrees,
            ],
            'attributes' => [
                'id' => self::ACADEMIC_DEGREE,
                'class' => 'form-control'
            ]
        ]);
        //CAREERS
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::CAREER,
            'options' => [
                'label' => 'Carrera a ingresar',
                'value_options' => $this->careers,
            ],
            'attributes' => [
                'id' => self::CAREER,
                'class' => 'form-control'
            ]
        ]);
        //COHORTS
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::COHORT,
            'options' => [
                'label' => 'Cohorte',
                'value_options' => $this->cohorts,
            ],
            'attributes' => [
                'id' => self::COHORT,
                'class' => 'form-control'
            ]
        ]);
        //SUBMIT
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'value' => 'Cargar horarios',
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
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => array_keys($this->cohorts),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);

        //ACADEMIC DEGREE
        $inputFilter->add([
            'name' => self::ACADEMIC_DEGREE,
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => array_keys($this->degrees),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);

        //CAREER
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
    }

    //CHECKING IDS INTEGRITY
    public function isValid() {
        $isValid = parent::isValid();
        //CHECKING CAREER
        $careerElement = $this->get(self::CAREER);
        $degreeCode = $this->get(self::ACADEMIC_DEGREE)->getValue();
        $careerCode = $careerElement->getValue();
        if (!isset($this->dataTree[$degreeCode][$careerCode])) {
            $careerElement->setMessages(["El grado académico ($degreeCode) no corresponde a la carrera seleccionada ($careerCode)"]);
            $isValid = false;
        }
        //CHECKING COHORT FOR UPGRADING COURSES
        $cohortElement = $this->get(self::COHORT);
        $cohort = $cohortElement->getValue();
        if (($degreeCode == self::UPG_COURSE_CODE || $careerCode == self::UPG_COURSE_CODE) && ($cohort != self::UPG_COURSE_COHORT)) {
            $cohortElement->setMessages(['Para cursos de actualización la cohorte debe ser de tipo "(Cursos de Actualizacion)".']);
            $isValid = false;
        }
        if (($degreeCode != self::UPG_COURSE_CODE || $careerCode != self::UPG_COURSE_CODE) && ($cohort == self::UPG_COURSE_COHORT)) {
            $cohortElement->setMessages(['La cohorte (Cursos de Actualización) no corresponde al doctorado, maestría ni especialización.']);
            $isValid = false;
        }
        return $isValid;
    }

    public function getJsTree() {
        return str_replace('array (', '{', str_replace(')', '}', str_replace('=>', ':', str_replace("'", '"', var_export($this->dataTree, true)))));
    }

    public function setData($data) {
        parent::setData($data);
        $this->hasData = true;
        $this->selectedDegree = $this->get(self::ACADEMIC_DEGREE)->getValue();
        $this->selectedCareer = $this->get(self::CAREER)->getValue();
        $this->selectedCohort = $this->get(self::COHORT)->getValue();
    }

    public function hasData() {
        return $this->hasData;
    }

    public function getSelectedDegree() {
        if (isset($this->selectedDegree)) {
            return $this->selectedDegree;
        } else {
            return null;
        }
    }

    public function getSelectedCareer() {
        if (isset($this->selectedCareer)) {
            return $this->selectedCareer;
        } else {
            return null;
        }
    }

    public function getSelectedCohort() {
        if (isset($this->selectedCohort)) {
            if ($this->selectedCohort == self::UPG_COURSE_COHORT) {
                return "(Curso de Actualización)";
            } else {
                return date("d/m/Y", strtotime($this->selectedCohort));
            }
        } else {
            return "(No seleccionada)";
        }
    }

    public function getSelectedCareerName() {
        if (isset($this->selectedCareer)) {
            if (isset($this->careers[$this->selectedCareer])) {
                return $this->careers[$this->selectedCareer];
            }
            return "(No encontrada)";
//            $this->dataTree[];
//            foreach ($this->careers as $career) {
//                if ($career['cod_career'] == $this->selectedCareer) {
//                    return $career['nombre_actual'];
//                }
//            }
        } else {
            return "(No seleccionada)";
        }
    }

}
