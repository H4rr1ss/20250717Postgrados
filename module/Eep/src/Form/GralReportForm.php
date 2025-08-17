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
use Zend\Validator\GreaterThan;
use Zend\Validator\Date;
use Zend\Validator\InArray;
use Eep\Form\FieldError;
use Eep\Entity\Order;

class GralReportForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'GralReportForm';
    const SUBMIT = 'submit';
    const UPDATE = 'update';
    //UPPER LEVEL ELEMENTS
    const ACADEMIC_DEGREE = 'Grado-Academico';
    const PENSUM = 'Pensum';
    const COHORT = 'Cohorte';
    const START_DATE = 'Fecha-Inicio';
    const FINISH_DATE = 'Fecha-Fin';
    //LOWER LEVEL ELEMENTS
    const COURSE = 'Curso';
    const SECTION = 'Seccion';
    //UPGRADING COURSES ELEMENTS
    const UPG_COURSE_COHORT = '0000-01-01';
    const UPG_COURSE_CODE = Order::CURSO_ACTUALIZACION; //NOTICE THAT WE ASUME THA THE UPGRADING COURSES CODE IS THE SAME FOR THE DEGREE, CARREER AND PENSUm.
    //OTHER CONSTANTS
    const EMPTY_OPTION_LABEL = "[Todos]";
    const CLEAR_COURSE_SELECTION = 'clear-course-selection';
    const CLEAR_DATA_CLASS = 'clear-data';

    private $cohorts;
    private $degrees;
    private $pensums;
    private $degreeCareerTree;
    private $pensumCohortTree;
    private $hasData;
    private $validateDate;

    public function __construct($degrees, $pensums, $cohorts, $pensumCohorts, $validateDate = true) {//THE URL REDIRECTS TO THE CORRECT ACTION CONTROLLER
        parent::__construct(self::FORM_NAME);
        $this->makeDegreeCareerTree($pensums);
        $this->makePensumCohortTree($pensumCohorts);
        $this->pensums = $this->convertPensumToKeyValue($pensums);
        $this->degrees = $this->convertToKeyValue($degrees, 'cod_grado', 'nombre');
        $this->cohorts = $this->convertToKeyValue($cohorts, 'fecha_cohorte', 'fecha_cohorte', true);
        $this->hasData = false;
        $this->validateDate = $validateDate;

        //ADDING UPGRADING COURSES PSEUDO COHORT
        $this->cohorts[self::UPG_COURSE_COHORT] = '(Cursos de Actualización)';

        $this->setAttribute('method', 'post');

        //ADDING ELEMENTS WITH DATABASE DATA
        $this->addElements();
        $this->addInputFilter();
    }

    private function makeDegreeCareerTree($pensums) {
        $this->degreeCareerTree = [];
        $this->degreeCareerTree[''][''] = self::EMPTY_OPTION_LABEL;
        $auxDegreeCode = -1;
        foreach ($pensums as $row) {
            $degreeCode = $row['cod_grado'];
            $pensumCode = $row['cod_pensum'];
            $pensumName = $this->getPensumName($row);
            $this->degreeCareerTree[$degreeCode][$pensumCode] = $pensumName;
            $this->degreeCareerTree[''][$pensumCode] = $pensumName;
            if ($auxDegreeCode != $degreeCode) {
                $auxDegreeCode = $degreeCode;
                $this->degreeCareerTree[$auxDegreeCode][""] = self::EMPTY_OPTION_LABEL;
            }
        }
    }

    private function getPensumName($row): string {
        $pensumCode = str_pad($row['cod_pensum'], 2, "0", STR_PAD_LEFT);
        $pensumStartDate = date('Y', strtotime($row['fecha_inicio']));
        $pensumFinishDate = empty($row['fecha_fin']) ? '–––– ' : date('Y', strtotime($row['fecha_fin']));
        $careerCode = $row['cod_carrera'];
        $careerName = $row['alias_carrera'];
        return "Pensum $pensumCode - [$pensumStartDate → $pensumFinishDate] - $careerCode - $careerName";
    }

    public function getJsCareerDegreeTree() {
        return str_replace('array (', '{', str_replace(')', '}', str_replace('=>', ':', str_replace("'", '"', var_export($this->degreeCareerTree, true)))));
    }

    private function convertToKeyValue($data, $keyName, $valueName, $converToDate = false) {
        $cleanData = [];
        foreach ($data as $row) {
            $value = $converToDate ? date('d/m/Y', strtotime($row[$valueName])) : $row[$valueName];
            $cleanData[$row[$keyName]] = $value;
        }
        return $cleanData;
    }

    private function makePensumCohortTree($pensumCohorts) {
        $this->pensumCohortTree = [];
        $this->pensumCohortTree[''][''] = self::EMPTY_OPTION_LABEL;
        foreach ($pensumCohorts as $row) {
            $pensumCode = $row['cod_pensum'];
            $cohort = $row['fecha_cohorte'];
            $cohortName = date('d/m/Y', strtotime($cohort));
            $this->pensumCohortTree[$pensumCode][$cohort] = $cohortName;
            //$this->pensumCohortTree[$pensumCode][''] = self::EMPTY_OPTION_LABEL; //MOVED TO convertPensumToKeyValue SO ALL PENSUMS HAVE THE 'ALL' OPTION EVENTHOUGH THERE IS NO COHORT RELATIONSHIP
            $this->pensumCohortTree[''][$cohort] = $cohortName;
        }
        $this->pensumCohortTree[self::UPG_COURSE_CODE][self::UPG_COURSE_COHORT] = '[Cursos de Actualización]';
        $this->pensumCohortTree[''][self::UPG_COURSE_COHORT] = '[Cursos de Actualización]';
    }

    public function getJsPensumCohortTree() {
        return str_replace('array (', '{', str_replace(')', '}', str_replace('=>', ':', str_replace("'", '"', var_export($this->pensumCohortTree, true)))));
    }

    public function convertPensumToKeyValue($pensums) {
        $cleanData = [];
        foreach ($pensums as $row) {
            $pensumCode = $row['cod_pensum'];
            $cleanData[$pensumCode] = $this->getPensumName($row);
            if ($pensumCode != self::UPG_COURSE_CODE) {
                $this->pensumCohortTree[$pensumCode][''] = self::EMPTY_OPTION_LABEL;
            }
        }
        return $cleanData;
    }

    public function addElements() {
        //COHORTS
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::COHORT,
            'options' => [
                'label' => 'Cohorte',
                'value_options' => $this->cohorts,
                'empty_option' => self::EMPTY_OPTION_LABEL,
            ],
            'attributes' => [
                'id' => self::COHORT,
                'class' => 'form-control ' . self::CLEAR_COURSE_SELECTION
            ]
        ]);

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
                'empty_option' => self::EMPTY_OPTION_LABEL,
            ],
            'attributes' => [
                'id' => self::ACADEMIC_DEGREE,
                'class' => 'form-control ' . self::CLEAR_COURSE_SELECTION
            ]
        ]);

        //CAREERS
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::PENSUM,
            'options' => [
                'label' => 'Pensum / Carrera',
                'value_options' => $this->pensums,
                'empty_option' => self::EMPTY_OPTION_LABEL,
            ],
            'attributes' => [
                'id' => self::PENSUM,
                'class' => 'form-control ' . self::CLEAR_COURSE_SELECTION
            ]
        ]);

        if ($this->validateDate) {
            //START DATE
            $this->add([
                'type' => 'Zend\Form\Element\Date',
                'name' => self::START_DATE,
                'options' => [
                    'label' => "Fecha de inicio",
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                    'format' => 'Y-m-d',
                    'messages' => FieldError::DATE
                ],
                'attributes' => [
                    'id' => self::START_DATE,
                    'class' => 'form-control',
                    'step' => '1',
                    'value' => date('Y-') . '01-01'
                ]
            ]);

            //FINISH DATE
            $this->add([
                'type' => 'Zend\Form\Element\Date',
                'name' => self::FINISH_DATE,
                'options' => [
                    'label' => "Fecha de fin",
                    'label_attributes' => [
                        'class' => 'control-label',
                    ],
                    'format' => 'Y-m-d',
                    'messages' => FieldError::DATE
                ],
                'attributes' => [
                    'id' => self::FINISH_DATE,
                    'class' => 'form-control',
                    'step' => '1',
                    'value' => date('Y-m-d')
                ]
            ]);
        }

        //COURSE
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::COURSE,
            'options' => [
                'label' => 'Curso',
                'value_options' => [],
                'empty_option' => self::EMPTY_OPTION_LABEL,
            ],
            'attributes' => [
                'id' => self::COURSE,
                'class' => 'form-control ' . self::CLEAR_DATA_CLASS,
                'disabled' => 'disabled'
            ]
        ]);

        //SECTION
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::SECTION,
            'options' => [
                'label' => 'Sección',
                'value_options' => [],
                'empty_option' => self::EMPTY_OPTION_LABEL,
            ],
            'attributes' => [
                'id' => self::SECTION,
                'class' => 'form-control ' . self::CLEAR_DATA_CLASS,
                'disabled' => 'disabled'
            ]
        ]);

        //UDPATE BUTTON
        $this->add([
            'type' => 'button',
            'name' => self::UPDATE,
            'options' => [
                'label' => '<i class="fa fa-plus"></i>
                                    <i class="fa fa-filter"></i>
                                    Filtrar más',
                'label_options' => [
                    'disable_html_escape' => true,
                ]
            ],
            'attributes' => [
                'id' => self::UPDATE,
                'type' => 'button',
                'class' => 'btn btn-green'
            ],
        ]);

        //SUBMIT
        $this->add([
            'type' => 'submit',
            'name' => self::SUBMIT,
            'attributes' => [
                'value' => 'Generar reporte',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    public function getDegrees() {
        return $this->degrees;
    }

    public function getPensums() {
        return $this->pensums;
    }

    public function getCohorts() {
        return $this->cohorts;
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        //COHORT
        $inputFilter->add([
            'name' => self::COHORT,
            'required' => false,
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
            'required' => false,
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
            'name' => self::PENSUM,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => array_keys($this->pensums),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);

        if ($this->validateDate) {
            //START DATE
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
                            'inclusive' => true,
                            'min' => '2000-01-01',
                            'messages' => FieldError::GREATER_THAN
                        ],
                    ],
                ],
            ]);

            //FINISH DATE
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
                ],
            ]);
        }

        //COURSE
        $inputFilter->add([
            'name' => self::COURSE,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => [],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);

        //SECTION
        $inputFilter->add([
            'name' => self::SECTION,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => [],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
    }

    public function addCourseSectionFilter($courses, $timetables) {
        $inputFilter = $this->getInputFilter();
        //COURSE
        $inputFilter->remove(self::COURSE);
        $inputFilter->remove(self::SECTION);
        $inputFilter->add([
            'name' => self::COURSE,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => array_keys($this->convertToKeyValue($courses, 'cod_curso', 'nombre')),
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
        $timetableCodes = [];
        foreach ($timetables as $tt) {
            $timetableCodes[] = $tt->getCode();
        }
        //SECTION
        $inputFilter->add([
            'name' => self::SECTION,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => $timetableCodes,
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
    }

    public function setData($data) {
        parent::setData($data);
        $this->hasData = true;
    }

    public function hasData() {
        return $this->hasData;
    }

    public function getDegree() {
        return $this->get(self::ACADEMIC_DEGREE)->getValue();
    }

    public function getCareer() {
        return $this->get(self::PENSUM)->getValue();
    }

    public function getCohort() {
        return $this->get(self::COHORT)->getValue();
    }

    //CHECKING IDS INTEGRITY
    public function isValid() {
        $isValid = parent::isValid();
        //CHECKING CAREER
        $pensumElement = $this->get(self::PENSUM);
        $degreeElement = $this->get(self::ACADEMIC_DEGREE);
        $idDegree = $degreeElement->getValue();
        $idPensum = $pensumElement->getValue();
        if (!isset($this->degreeCareerTree[$idDegree][$idPensum])) {
            $pensumElement->setMessages(["El grado académico ($idDegree) no corresponde a la carrera seleccionada (pensum: $idPensum)"]);
            $isValid = false;
        }
        //CHECKING COHORT FOR UPGRADING COURSES
        $cohortElement = $this->get(self::COHORT);
        $cohort = $cohortElement->getValue();
        //IF UPG_COURSE DEGREE/CAREER IS SELECTED AND HAS COHORT SELECTED DIFFERENT FROM IT'S SPECIFIC COHORT, ADD ERROR MESSAGE
        if (($idDegree == self::UPG_COURSE_CODE || $idPensum == self::UPG_COURSE_CODE) && ($cohort != self::UPG_COURSE_COHORT && !empty($cohort))) {
            $cohortElement->setMessages(['Para cursos de actualización, la cohorte debe ser (Cursos de Actualización) o no seleccionar filtro de cohorte.']);
            $isValid = false;
        }
        //IF VALID CAREER SELECTED (VALIDATING IN THE "CHECKING CAREER" SNIPPET) AND IT'S NOT NULL, IF CAREER IS NOT UPGRADING COURSES, IT DOESN'T HAVE TO HAVE UPGRATING COURSE COHORT SELECTED
        if ($isValid && ((!empty($idPensum)) && ($idPensum != self::UPG_COURSE_CODE) && ($cohort == self::UPG_COURSE_COHORT))) {
            $cohortElement->setMessages(['La cohorte (Cursos de Actualización) no corresponde al doctorado, maestría o especialización.']);
            $isValid = false;
        }
        if ($isValid && $this->validateDate) {
            $startDateElement = $this->get(self::START_DATE);
            $finishDateElement = $this->get(self::FINISH_DATE);
            $startDate = $startDateElement->getValue();
            $finishDate = $finishDateElement->getValue();
            if ($startDate > $finishDate) {
                $finishDateElement->setMessages(['La fecha de finalización debe ser mayor o igual a la de inicio']);
                $isValid = false;
            }
        }
        return $isValid;
    }

    public function hasPensum() {
        $pensumElement = $this->get(self::PENSUM);
        $idPensum = $pensumElement->getValue();
        if (empty($idPensum)) {
            $pensumElement->setMessages(['Debe indicar un pensum para cargar los cursos']);
            return false;
        }
        return true;
    }

}
