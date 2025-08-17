<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\NotEmpty;
use Zend\Validator\Digits;
use Zend\Validator\InArray;
use Zend\Validator\StringLength;
use Eep\Form\FieldError;
use Eep\Form\AssignmentTypeForm;
use Eep\Entity\Order as O;

class AssignmentForm extends AssignmentTypeForm {

    //FORM CONSTANTS
    const FORM_NAME = 'AssignmentForm';
    //FORM
    const TIMETABLES = 'cod_horario';
    const COURSES = 'cod_curso';
    const SECTION = 'seccion';
    const ADD_TIMETABLE = 'addTimetable';
    const SUBMIT = 'submit';
    //CONSTANTS USED IN JAVASCRIPT
    const TABLE = 'timetablesTable';
    const TOTAL = 'total';
    const LIMIT = 'limit';
    //EXTRAORDINARY AND EXTEMPORARY ASSIGNMENT
    const HAS_ACT = 'has_act';
    const ACT_RECORD = 'cod_acta';
    const ACT_SUBSECTION = 'inciso';
    //ASSIGNMENT TYPE EXTRA DATA
    const USER_CODE = 'cod_usuario'; //THE ACTUAL CODE
    const MONTHS = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private $type;
    private $dataTree;
    private $validTimetableCodes;
    private $inputFilter;
    private $regularDays;
    private $extDays;

    //USER HERE IS THE USER CODE (cod_usuario)
    public function __construct($url, $careerList, $type, $regularDays, $extDays) {
        parent::__construct(null, $type, self::FORM_NAME);

        $this->regularDays = $regularDays;
        $this->extDays = $extDays;
        $this->setAttribute('method', 'post');
        $this->type = $type;
        $this->setAttribute('action', $url);
        $this->dataTree = $this->generateDataTree($careerList);
        $this->validTimetableCodes = $this->generateValidTimetables($careerList);
        $this->inputFilter = new InputFilter();
        $this->setInputFilter($this->inputFilter);
        //IT DOESN'T HAVE BREAK ON PURPOSE
        switch ($type) {
            case self::TYPE_EXTEMP:
            case self::TYPE_EXTRA:
                $this->addExtraElements();
                $this->addExtraInputFilter();
            case self::TYPE_REGULAR:
                //ADD USER
                $this->addUserElementAndFilter();
            case self::TYPE_STUDENT_REGULAR:
                $this->addRegularElements();
                $this->addRegularInputFilter();
                break;
            default:
                break;
        }
    }

    public function getType() {
        return $this->type;
    }

    private function generateValidTimetables($careerList) {
        $timetables = [];
        if (!empty($careerList)) {
            foreach ($careerList as $career) {
                foreach ($career as $timetable) {
                    $timetables[$timetable->getCode()] = true;
                }
            }
        }
        return $timetables;
    }

    private function generateDataTree($careerList) {
        $tree = [];
        /*
         * RESULT STRUCTURE:
         * [
         *  <'cod_curso'> => [
         *      'name' => <'nombre'>,
         *      'selected' => false,
         *      'sections' => [
         *          <'cod_horario'> => [
         *              'section' => <'seccion'>,
         *              'limit' => <'fecha_inicio'+5d>,
         *              'price' => <'precio'>,
         *              'selected' => false,
         *          ],
         *          <'cod_horario'> => [
         *              'section' => <'seccion'>,
         *              'limit' => <'fecha_inicio'+5d>,
         *              'price' => <'precio'>,
         *              'selected' => false,
         *          ],
         *      ]
         *  ],
         *  <'cod_curso'> => [
         *      'name' => <'nombre'>,
         *      'selected' => false,
         *      'sections' => [
         *          <'cod_horario'> => [
         *              'section' => <'seccion'>,
         *              'limit' => <'fecha_inicio'+5d>,
         *              'price' => <'precio'>,
         *              'selected' => false,
         *          ],
         *          <'cod_horario'> => [
         *              'section' => <'seccion'>,
         *              'limit' => <'fecha_inicio'+5d>,
         *              'price' => <'precio'>,
         *              'selected' => false,
         *          ],
         *      ]
         *  ],
         * ]
         */
        if (!empty($careerList)) {
            foreach ($careerList as $career) {
                foreach ($career as $timetable) {
                    $courseCode = $timetable->getCodCurso(); //['cod_curso'];
                    $pensumCode = $timetable->getCodPensum();
                    $courseCodeName = $pensumCode != O::CURSO_ACTUALIZACION ? "$courseCode" : "[CA]$courseCode";
                    $timetableCode = $timetable->getCode(); //['cod_horario'];
                    $section = $timetable->getSeccion(); //['seccion'];
                    if ($this->type == self::TYPE_EXTEMP) {
                        $date = strtotime(date('Y-m-d'));
                        $days = $this->extDays;
                    } elseif ($this->type == self::TYPE_EXTRA) {
                        //$date = strtotime($timetable->getFechaFin());
                        //$days = 0;
                        $date = strtotime(date('Y-m-d'));
                        $days = $this->extDays;
                    } else {
                        $date = strtotime($timetable->getFechaInicio()); //['fecha_inicio'];
                        $days = $this->regularDays;
                    }
                    $price = $timetable->getPrecio(); //['precio'];

                    $limit = date('Y-m-d', strtotime("+ $days weekdays", $date));
                    $tree[$courseCodeName]['selected'] = false;
                    $tree[$courseCodeName]['sections'][$timetableCode]['limit'] = $limit;
                    $tree[$courseCodeName]['sections'][$timetableCode]['price'] = $price;
                    $tree[$courseCodeName]['sections'][$timetableCode]['selected'] = false;
                    $name = $timetable->getAliasCurso();
                    $tree[$courseCodeName]['name'] = $name;
                    //NAME DEPENDING ON TYPE OF FORM
                    switch ($this->type) {
                        case self::TYPE_EXTEMP: //INCLUDE MONTH
                            $sectionName = $section . ' [Mes: ' . self::MONTHS[$timetable->getMes()] . ']';
                            break;
                        case self::TYPE_EXTRA: //INCLUDE COHORT
                            $cohortTimetable = $timetable->getFechaCohorte();
                            if ($cohortTimetable != null) {
                                $date = date('d/m/Y', strtotime($cohortTimetable));
                                $cohort = " [Cohorte: $date]";
                            } else { //UPGRADING COURSES DON'T HAVE COHORT
                                $cohort = '';
                            }
                            $sectionName = $section . $cohort;
                            break;
                        case self::TYPE_REGULAR:
                        case self::TYPE_STUDENT_REGULAR:
                        default:
                            $sectionName = $section;
                            break;
                    }
                    $tree[$courseCodeName]['sections'][$timetableCode]['section'] = $sectionName;
                }
            }
        }
        return $tree;
    }

    public function getJsTree() {
        return str_replace('array (', '{', str_replace(')', '}', str_replace('=>', ':', var_export($this->dataTree, true))));
    }

    public function addRegularElements() {
        //COURSE
        $courses = [];
        foreach ($this->dataTree as $code => $details) {
            $courseName = $details['name'];
            $courses[$code] = "$code - $courseName";
        }
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::COURSES,
            'options' => [
                'label' => 'Curso',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value_options' => $courses,
            ],
            'attributes' => [
                'id' => self::COURSES,
                'class' => 'form-control'
            ]
        ]);

        //SECTION
        $sections = [];
        foreach ($this->dataTree as $courses) {
            $sectionsData = $courses['sections'];
            foreach ($sectionsData as $timetableCode => $details) {
                $sections[$timetableCode] = $details['section'];
            }
        }
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => self::SECTION,
            'options' => [
                'label' => 'Sección',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
                'value_options' => $sections,
            ],
            'attributes' => [
                'id' => self::SECTION,
                'class' => 'form-control'
            ]
        ]);

        //ADDING TIMETABLE BUTTON
        $this->add([
            'type' => 'button',
            'name' => self::ADD_TIMETABLE,
            'options' => [
                'label' => '<i class="fa fa-plus"></i>',
                'label_options' => [
                    'disable_html_escape' => true,
                ]
            ],
            'attributes' => [
                'id' => self::ADD_TIMETABLE,
                'type' => 'button',
                'class' => 'btn btn-green'
            ],
        ]);

        $this->add([
            'type' => 'hidden',
            'name' => self::TIMETABLES
        ]);

        //SUBMIT
        $this->add([
            'type' => 'button',
            'name' => self::SUBMIT,
            'options' => [
                'label' => '<i class="fa fa-sticky-note"></i> Generar orden de pago',
                'label_options' => [
                    'disable_html_escape' => true,
                ]
            ],
            'attributes' => [
                'id' => self::SUBMIT,
                'type' => 'submit',
                'class' => 'btn btn-primary'
            ],
        ]);
    }

    private function addRegularInputFilter() {
        $inputFilter = $this->inputFilter;

        //COURSES
        $inputFilter->add([
            'name' => self::COURSES,
            'required' => false,
        ]);
//        //SECTION
//        $sections = [];
//        foreach ($this->dataTree as $courses) {
//            $sectionsData = $courses['sections'];
//            foreach ($sectionsData as $timetableCode => $details) {
//                $sections[$timetableCode] = $details['section'];
//            }
//        }
        //SECTION
        $inputFilter->add([
            'name' => self::SECTION,
            'required' => false,
//            'filters' => [
//                ['name' => 'StringTrim'],
//            ],
//            'validators' => [
//                [
//                    'name' => InArray::class,
//                    'break_chain_on_failure' => true,
//                    'options' => [
//                        'haystack' => array_keys($sections),
//                        'messages' => FieldError::IN_ARRAY
//                    ],
//                ],
//            ],
        ]);
    }

    public function addExtraElements() {
        //HAS ACT
        $this->add([
            'type' => 'Zend\Form\Element\Checkbox',
            'name' => self::HAS_ACT,
            'options' => [
                'label' => 'Incluye acta',
                'use_hidden_element' => true,
                'checked_value' => 'yes',
                'unchecked_value' => 'no'
            ],
            'attributes' => [
                'id' => self::HAS_ACT,
                'value' => 'yes'
            ]
        ]);
        //ACT
        $this->add([
            'type' => 'text',
            'name' => self::ACT_RECORD,
            'options' => [
                'label' => "Acta",
                'label_attributes' => [
                    'class' => 'control-label',
                    'disable_html_escape' => true,
                ],
            ],
            'attributes' => [
                'id' => self::ACT_RECORD,
                'class' => 'form-control',
                'placeholder' => 'Código'
            ],
        ]);
        //SUBSECTION
        $this->add([
            'type' => 'text',
            'name' => self::ACT_SUBSECTION,
            'options' => [
                'label' => 'Inciso',
                'label_attributes' => [
                    'class' => 'control-label',
                ],
            ],
            'attributes' => [
                'id' => self::ACT_SUBSECTION,
                'class' => 'form-control',
                'placeholder' => 'Código'
            ],
        ]);
    }

    private function addExtraInputFilter() {
        //HAS ACT
        $this->inputFilter->add([
            'name' => self::HAS_ACT,
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
                    'break_chain_on_failure' => true,
                    'options' => [
                        'haystack' => ['yes', 'no'],
                        'messages' => FieldError::IN_ARRAY
                    ],
                ],
            ],
        ]);
        //ACT RECORD
        $this->inputFilter->add([
            'name' => self::ACT_RECORD,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
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
        //ACT SUBSECTION RECORD
        $this->inputFilter->add([
            'name' => self::ACT_SUBSECTION,
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
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
        $timetables = $this->get(self::TIMETABLES)->getValue();
        if ($valid) {
            //CHECKING VALID TIMETABLES CODES PROVIDED
            $courseElement = $this->get(self::COURSES);
            $timetableElement = $this->get(self::TIMETABLES);
            if (empty($timetables)) {
                $courseElement->setMessages(['Debes agregar al menos un curso.']);
                $valid = false;
            } else {
                foreach ($timetables as $timetable) {
                    if (!isset($this->validTimetableCodes[$timetable])) {
                        $courseElement->setMessages(["Se solicitó la asignación de horarios inválidos ($timetable)"]);
                        $valid = false;
                    }
                }
            }
        } else
        //VALIDATING REPEATED VALUES
        if ($valid) {
            $repeatedValues = [];
            for ($i = 0; $i < count($timetables); $i++) {
                for ($j = 0; $j < count($timetables); $j++) {
                    if ($i != $j) {
                        if ($timetables[$i] == $timetables[$j]) {
                            $repeatedValues[] = $timetables[$i];
                        }
                    }
                }
            }
            if (count($repeatedValues) > 0) {
                $courseElement = $this->get(self::COURSES);
                $courseElement->setMessages(['Se seleccionaron cursos repetidos']);
                $valid = false;
            }
        } else
        //VALIDATING ONE SECTION PER COURSE SELECTION
        if ($valid) {
            foreach ($this->dataTree as $course) {
                $count = 0;
                $timetablesTree = $course['sections'];
                foreach ($timetablesTree as $timetableCode => $details) {
                    foreach ($timetables as $timetable) {
                        if ($timetableCode == $timetable) {
                            $count++;
                        }
                    }
                }
                if ($count > 1) {
                    $courseElement = $this->get(self::COURSES);
                    $courseElement->setMessages(['Se debe seleccionar únicamente una sección por curso.']);
                    $valid = false;
                    break;
                }
            }
        }
        //VALIDATING ACT AND SUBSECTION VALUES
        if ($valid && ($this->type == self::TYPE_EXTEMP || $this->type == self::TYPE_EXTRA)) {
            $hasActElement = $this->get(self::HAS_ACT);
            if ($hasActElement->getValue() == 'yes') {
                $actElement = $this->get(self::ACT_RECORD);
                $actSubsectionElement = $this->get(self::ACT_SUBSECTION);
                if (empty($actElement->getValue())) {
                    $actElement->setMessages(['Si tiene acta, hace falta el código de acta']);
                    $valid = false;
                }
                if (empty($actSubsectionElement->getValue())) {
                    $actSubsectionElement->setMessages(['Si tiene acta, hace falta el inciso']);
                    $valid = false;
                }
            }
        }
        //TRANSFERING ELEMENT ERRORS TO DIPLAYED ELEMENT    
        if (!$valid) {
            if ($this->type != self::TYPE_STUDENT_REGULAR) {
                //ELEMENT WHERE ERROR MESSAGES WILL BE PUSHED
                $pivotElement = $this->get(self::COURSES);
                $messages = $pivotElement->getMessages();
                //NAMES HAVE TO BE IN ORDER SO THE MORE USED ARE LISTED FIRST FOR THE EXCEPTION TO OCCUR
                //ONLY IF THE LEAST USED IS NOT FOUND
                $elementNamesToValidate = [self::USER, self::ASSIGNMENT_TYPE, self::YEAR];
                foreach ($elementNamesToValidate as $name) {
                    if ($this->has($name)) {
                        $element = $this->get($name);
                        $messages = array_merge($messages, $element->getMessages());
                    }
                }
                $pivotElement->setMessages($messages);
            }
        }
        return $valid;
    }

    public function getJsTimetables() {
        $ttElement = $this->get(self::TIMETABLES);
        $values = $ttElement->getValue();
        if (!empty($values)) {
            $timetables = $values;
        } else {
            $timetables = [];
        }
        return str_replace('array (', '{', str_replace(')', '}', str_replace('=>', ':', var_export($timetables, true))));
    }

    public function addUserElementAndFilter() {
        //USER
        $this->add([
            'type' => 'hidden',
            'name' => self::USER_CODE,
            'attributes' => [
                'id' => self::USER_CODE
            ]
        ]);

        $this->inputFilter->add([
            'name' => self::USER_CODE,
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
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 9, //SIGNED INT MAX VALUE IS 1.~*10^9
                        'messages' => FieldError::STRING_LENGTH
                    ]
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
