<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\NotEmpty;
use Zend\Validator\GreaterThan;
use Zend\Validator\StringLength;
use Zend\Validator\Date;
use Zend\Validator\InArray;
use Eep\Form\FieldError;
use Eep\Entity\Timetable;

class TimetableForm extends Form {

    //FIELDS NAMES ARE LIKE THE DB COLUMNS
    //FORM CONSTANTS
    const FORM_NAME = 'TimetableForm';
    const SUBMIT = 'timetableSubmit';
    //TIMETABLE INO
    const COURSE = 'cod_curso';
    const HIDDEN_COURSE = 'hidden_course';
    const SECTION = 'seccion';
    const COORDINATOR = 'cod_usuario_coordinador';
    const PROFESSOR = 'cod_usuario_catedratico';
    const START_TIME = 'hora_inicio';
    const FINISH_TIME = 'hora_fin';
    const START_DATE = 'fecha_inicio';
    const FINISH_DATE = 'fecha_fin';
    const DAYS = Timetable::DAYS;
    const COURSE_TYPE = "cod_tipo_curso";
    const LOCATION = "cod_ubicacion";
    const ROOM = "cod_salon";
    const LABORATORY = 'laboratorio';
    const ROOM_SPACE = 'cupo';
    const TIMETABLE_CODE = 'cod_horario';
    const GRADING_LIMIT_DATE = 'fecha_limite_calificacion';
    //FORM TYPE
    const TYPE_NEW = 0;
    const TYPE_EDIT = 1;
    const TYPE_DELETE = 2;
    const TYPE_EDIT_REQUEST = 3;
    //DAYS
    const DAYS_KEY_VALUE = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'domingo' => 'Domingo'
    ];
    const SECTIONS_KEY_VALUE = [
        'U' => 'U',
        'Sab' => 'Sab',
        'Vier' => 'Vier',
        'A' => 'A',
        'B' => 'B',
        'C' => 'C',
    ];

    private $type;
    private $courses;
    private $coordinators;
    private $professors;
    private $coursesTypes;
    private $locations;
    private $rooms;
    private $hasData;
    private $selectedLocation;
    private $selectedRoom;
    private $dataTree;

    /**
     * 
     * @param type $url
     * @param type $type
     * @param type $data
     *  THIS TYPE SHOULD HAVE
     *  - COURSES
     *  - SECTIONS
     *  - COORDINATORS
     *  - PROFESSORS
     *  - COURSES TYPES
     *  - LOCATIONS
     *  - ROOMS
     * @param type $timetable
     */
    public function __construct($url, $type, $data) {//THE URL REDIRECTS TO THE CORRECT ACTION CONTROLLER
        parent::__construct(self::FORM_NAME);
        $this->type = $type;


        $this->setAttribute('method', 'post');
        $this->setAttribute('action', $url);

        $this->courses = (empty($data[self::COURSE])) ? [] : $data[self::COURSE];
        $this->coordinators = (empty($data[self::COORDINATOR])) ? [] : $data[self::COORDINATOR];
        $this->professors = (empty($data[self::PROFESSOR])) ? [] : $data[self::PROFESSOR];
        $this->coursesTypes = (empty($data[self::COURSE_TYPE])) ? [] : $data[self::COURSE_TYPE];
        $this->locations = (empty($data[self::LOCATION])) ? [] : $data[self::LOCATION];
        $this->rooms = (empty($data[self::ROOM])) ? [] : $data[self::ROOM];

        $this->addElements();
        $this->addInputFilter();

        $this->makeTree($this->locations, $this->rooms);
    }

    private function getValues($data, $fieldName) {
        //CLEANING DATA
        $cleanData = [];
        foreach ($data as $element) {
            $cleanData[] = $element[$fieldName];
        }
        return $cleanData;
    }

    public function getKeyValueElements($data, $keyName, $valueName, $valueName2 = null, $separator = null) {
        $cleanData = [];
        foreach ($data as $element) {
            $cleanData[$element[$keyName]] = $element[$valueName] . (($valueName2) != null ? ($separator ?? ' ') . $element[$valueName2] : '');
        }
        return $cleanData;
    }

    private function addSelectElement($name, $data, $labelText, $keyName, $valueName, $valueName2 = null, $separator = null, $emptyOption = null) {
        $options = [
            'label' => $labelText,
            'label_attributes' => [
                'class' => 'control-label'
            ],
            'value_options' => $this->getKeyValueElements($data, $keyName, $valueName, $valueName2, $separator)
        ];
        if (!empty($emptyOption)) {
            $options['empty_option'] = $emptyOption;
        }
        $this->add([
            'type' => 'Zend\Form\Element\Select',
            'name' => $name,
            'options' => $options,
            'attributes' => [
                'id' => $name,
                'class' => 'form-control'
            ]
        ]);
    }

    private function addSelectInputFilterArray($inputFilter, $name, $data, $valueName, $addDigitFilter = true, $filterValues = true, $required = true) {
        $validators = [
            [
                'name' => NotEmpty::class,
                'options' => [
                    'messages' => FieldError::NOT_EMPTY
                ],
            ],
            [
                'name' => InArray::class,
                'options' => [
                    'haystack' => ($filterValues ? $this->getValues($data, $valueName) : $data ),
                    'messages' => FieldError::IN_ARRAY
                ],
            ],
        ];
        if ($addDigitFilter) {
            $validators[] = [
                'name' => Digits::class,
                'options' => [
                    'messages' => FieldError::DIGITS
                ],
            ];
        }
        $inputFilter->add([
            'name' => $name,
            'required' => $required,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => $validators,
        ]);
    }

    private function addElements() {
        if ($this->type == self::TYPE_NEW || $this->type == self::TYPE_EDIT) {
            //SELECT TYPE ITEMS
            //COURSE DEPENDING ON EDIT SELECTION
            if ($this->type == self::TYPE_EDIT) {
                $this->addSelectElement(self::HIDDEN_COURSE, $this->courses, 'Curso', 'cod_curso', 'cod_curso', 'alias', ' - ');
                $courseElement = $this->get(self::HIDDEN_COURSE);
                $courseElement->setAttribute('disabled', 'disabled');
                $this->add([
                    'type' => 'hidden',
                    'name' => self::COURSE
                ]);
            } else {
                $this->addSelectElement(self::COURSE, $this->courses, 'Curso', 'cod_curso', 'cod_curso', 'alias', ' - ');
            }

            //SECTION
            $attributes = [
                'id' => self::SECTION,
                'class' => 'form-control',
            ];
            if ($this->type == self::TYPE_EDIT) {
                $attributes['disabled'] = 'disabled';
            }
            $this->add([
                //IN EDIT TYPE IS TEXT BECAUSE TIMETABLES MIGHT HAVE A SECTION NAME THAT DOESN'T EXISTS IN THE "SECTIONS_KEY_VALUE" OPTIONS
                'type' => $this->type == self::TYPE_EDIT ? 'text' : 'Zend\Form\Element\Select',
                'name' => self::SECTION,
                'options' => [
                    'label' => 'Sección',
                    'label_attributes' => [
                        'class' => 'control-label'
                    ],
                    'value_options' => self::SECTIONS_KEY_VALUE,
                ],
                'attributes' => $attributes
            ]);
            $this->coordinators[] = "";
            $this->addSelectElement(self::COORDINATOR, $this->coordinators, "Coordinador", "cod_usuario", "nombres", "apellidos", null, '(Sin Coordinador)');
            $this->addSelectElement(self::PROFESSOR, $this->professors, "Catedrático", "cod_usuario", "nombres", "apellidos");

            //START DATE
            $attributes = [
                //'value' => \date('2023-01-01'), //\date('Y-m-d'),
                'value' => \date('Y-m-d'),
                'id' => self::START_DATE,
                'class' => 'form-control',
                'step' => '1'
            ];
            if ($this->type == self::TYPE_EDIT) {
                $attributes['disabled'] = 'disabled';
            }
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
                'attributes' => $attributes
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
                    'id' => self::FINISH_DATE,
                    'class' => 'form-control',
                    'step' => '1',
                ]
            ]);

            //GRADING LIMIT DATE
            $this->add([
                'type' => 'Zend\Form\Element\Date',
                'name' => self::GRADING_LIMIT_DATE,
                'options' => [
                    'label' => 'Fecha limite de calificación',
                    'label_attributes' => [
                        'class' => 'control-label'
                    ],
                    'format' => 'Y-m-d',
                    'messages' => FieldError::DATE
                ],
                'attributes' => [
                    'id' => self::GRADING_LIMIT_DATE,
                    'class' => 'form-control',
                    'step' => '1',
                ]
            ]);

            //START TIME
            $this->add([
                'type' => 'Zend\Form\Element\Time',
                'name' => self::START_TIME,
                'options' => [
                    'label' => 'Hora de inicio',
                    'format' => 'H:i'
                ],
                'attributes' => [
                    'id' => self::START_TIME,
                    'min' => '00:00',
                    'max' => '23:59',
                    'step' => '60', //SECONDS
                    'class' => 'form-control'
                ]
            ]);

            //FINISH TIME
            $this->add([
                'type' => 'Zend\Form\Element\Time',
                'name' => self::FINISH_TIME,
                'options' => [
                    'label' => 'Hora de fin',
                    'format' => 'H:i'
                ],
                'attributes' => [
                    'id' => self::FINISH_TIME,
                    'min' => '00:00',
                    'max' => '23:59',
                    'step' => '60', //SECONDS
                    'class' => 'form-control'
                ]
            ]);


            //DAYS
            $this->add([
                'type' => 'Zend\Form\Element\MultiCheckbox',
                'name' => self::DAYS,
                'options' => [
                    'label' => 'Días en que se impartirá el curso',
                    'label_attributes' => [
                        'class' => 'control-label'
                    ],
                    'value_options' => self::DAYS_KEY_VALUE,
                ],
                'attributes' => [
                    'class' => 'input-inline',
                    'id' => self::DAYS
                ]
            ]);

            $this->addSelectElement(self::COURSE_TYPE, $this->coursesTypes, "Tipo de Curso", "cod_tipo_curso", "nombre");
            $this->addSelectElement(self::LOCATION, $this->locations, "Ubicación", "cod_ubicacion", "nombre", null, null, '[Sin identificar]');
            $this->addSelectElement(self::ROOM, $this->rooms, "Salón", "cod_salon", "nombre", null, null, '[Sin identificar]');

            //CUPO
            $this->add([
                'type' => 'text',
                'name' => self::ROOM_SPACE,
                'attributes' => [
                    'id' => self::ROOM_SPACE,
                    'class' => 'form-control'
                ],
                'options' => [
                    'label' => 'Cupo',
                    'label_attributes' => [
                        'class' => 'control-label'
                    ],
                ],
            ]);

            //LABORATORY
            $attributes = [
                'id' => self::LABORATORY
            ];
            if ($this->type == self::TYPE_EDIT) {
                $attributes['disabled'] = 'disabled';
            }
            $this->add([
                'type' => 'Zend\Form\Element\Checkbox',
                'name' => self::LABORATORY,
                'options' => [
                    'label' => 'Tiene laboratorio',
                    'use_hidden_element' => true,
                    'checked_value' => 'yes',
                    'unchecked_value' => 'no'
                ],
                'attributes' => $attributes
            ]);
        }

        if ($this->type == self::TYPE_EDIT_REQUEST || $this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT) {
            //TIMETABLE CODE HIDDEN ELEMENT
            $this->add([
                'type' => 'hidden',
                'name' => self::TIMETABLE_CODE
            ]);
        }

        switch ($this->type) {
            case self::TYPE_NEW:
                $label = 'Agregar horario';
                break;
            case self::TYPE_EDIT:
                $label = 'Guardar cambios';
                break;
            case self::TYPE_DELETE:
                $label = '<i class="fa fa-trash"></i>';
                break;
            case self::TYPE_EDIT_REQUEST:
                $label = '<i class="fa fa-edit"></i>';
                break;
        }

        //SUBMIT BUTTON
        $this->add([
            'type' => 'button',
            'name' => self::SUBMIT,
            'options' => [
                'label' => $label,
                'label_options' => [
                    'disable_html_escape' => $this->type == self::TYPE_DELETE || $this->type == self::TYPE_EDIT_REQUEST,
                ]
            ],
            'attributes' => [
                'type' => 'submit',
                'class' => 'btn btn-' . ($this->type == self::TYPE_DELETE ? 'red' : ($this->type == self::TYPE_EDIT_REQUEST ? 'blue' : 'primary'))
            ],
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

        if ($this->type == self::TYPE_NEW || $this->type == self::TYPE_EDIT) {
            $this->addSelectInputFilterArray($inputFilter, self::COURSE, $this->courses, 'cod_curso', false);
            if ($this->type == self::TYPE_EDIT) {
                $inputFilter->add([
                    'name' => self::HIDDEN_COURSE,
                    'required' => false
                ]);
            }
            $this->addSelectInputFilterArray($inputFilter, self::COORDINATOR, $this->coordinators, 'cod_usuario', false, true, false);
            $this->addSelectInputFilterArray($inputFilter, self::PROFESSOR, $this->professors, 'cod_usuario');

            //START DATE
            if ($this->type == self::TYPE_EDIT) {
                $inputFilter->add([
                    'name' => self::START_DATE,
                    'required' => false
                ]);
                $inputFilter->add([
                    'name' => self::SECTION,
                    'required' => false
                ]);
                $inputFilter->add([
                    'name' => self::LABORATORY,
                    'required' => false
                ]);
            } else {
                $this->addSelectInputFilterArray($inputFilter, self::LABORATORY, ['yes', 'no'], null, false, false);
                $this->addSelectInputFilterArray($inputFilter, self::SECTION, array_keys(self::SECTIONS_KEY_VALUE), null, false, false);
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
                            'options' => [
                                'inclusive' => true,
                                'min' => date('Y') . '-01-01', //'01/01/2018',
                                'messages' => FieldError::GREATER_THAN
                            ],
                        ],
                    ],
                ]);
            }

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
                        'options' => [
                            'format' => 'Y-m-d',
                            'messages' => FieldError::DATE
                        ],
                    ],
                ],
            ]);

            //GRADINGN LIMIT DATE
            $inputFilter->add([
                'name' => self::GRADING_LIMIT_DATE,
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

            //START TIME
            $inputFilter->add([
                'name' => self::START_TIME,
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
                            'max' => 8,
                            'messages' => FieldError::STRING_LENGTH
                        ],
                    ],
                    [
                        'name' => Date::class,
                        'options' => [
                            'format' => 'H:i',
                            'messages' => FieldError::DATE
                        ],
                    ],
                    [
                        'name' => GreaterThan::class,
                        'options' => [
                            'min' => '00:00',
                            'inclusive' => true,
                            'messages' => FieldError::GREATER_THAN
                        ],
                    ],
//                    [
//                        'name' => \Zend\Validator\Regex::class,
//                        'options' => [
//                            'pattern' => '#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#',
//                            'messages' => FieldError::REGEX
//                        ]
//                    ]
                ],
            ]);

            //FINISH TIME
            $inputFilter->add([
                'name' => self::FINISH_TIME,
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
                            'max' => 8,
                            'messages' => FieldError::STRING_LENGTH
                        ],
                    ],
                    [
                        'name' => Date::class,
                        'options' => [
                            'format' => 'H:i',
                            'messages' => FieldError::DATE
                        ],
                    ],
                    [
                        'name' => GreaterThan::class,
                        'options' => [
                            'min' => '00:00',
                            'inclusive' => true,
                            'messages' => FieldError::GREATER_THAN
                        ],
                    ],
//                [
//                    'name' => Regex::class,
//                    'options' => [
//                        'pattern' => '#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#',
//                        'messages' => FieldError::REGEX
//                    ]
//                ]
                ],
            ]);

            //DAYS
            //$this->addSelectInputFilterArray($inputFilter, self::DAYS, array_keys(self::DAYS_KEY_VALUE), null, false, false);
            $inputFilter->add([
                'name' => self::DAYS,
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
                ]
            ]);

            $this->addSelectInputFilterArray($inputFilter, self::COURSE_TYPE, $this->coursesTypes, 'cod_tipo_curso');
            $this->addSelectInputFilterArray($inputFilter, self::LOCATION, $this->locations, 'cod_ubicacion', true, true, false);
            $this->addSelectInputFilterArray($inputFilter, self::ROOM, $this->rooms, 'cod_salon', true, true, false);

            //ROOM AVAILABLE SPACE
            $inputFilter->add([
                'name' => self::ROOM_SPACE,
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
                            'min' => 1,
                            'max' => 9,
                            'messages' => FieldError::STRING_LENGTH
                        ],
                    ],
                    [
                        'name' => Digits::class,
                        'options' => [
                            'messages' => FieldError::DIGITS
                        ],
                    ]
                ],
            ]);
        }
        if ($this->type != self::TYPE_NEW) {
            $inputFilter->add([
                'name' => self::TIMETABLE_CODE,
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
                            'min' => 1,
                            'max' => 9, //INT
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

    //CHECKING INTEGRITY
    public function isValid() {
        $isValid = parent::isValid();
        if ($this->type == self::TYPE_NEW || $this->type == self::TYPE_EDIT) {
            //CHECKING CAREER
            $room = $this->get(self::ROOM);
            $idLocation = $this->get(self::LOCATION)->getValue();
            $found = false;
            $idRoom = $room->getValue();
            $message = "";
            foreach ($this->rooms as $value) {
                if ($value['cod_salon'] == $idRoom) {
                    if ($value['cod_ubicacion'] == $idLocation) {
                        $found = true;
                    } else {
                        $message = "La ubicación ($idLocation) no corresponde al salón seleccionado ($idRoom)";
                    }
                    break;
                }
            }
            if (!$found && $room->getValue != '') {
                $room->setMessages([empty($message) ? ("El salón seleccionado (" . $room->getValue() . ") no es válido.") : $message]);
                $isValid = false;
            }
            if ($isValid) {
                //CHECKING DATE COHERENCE
                $startDate = $this->get(self::START_DATE);
                $finishDate = $this->get(self::FINISH_DATE);
                if ($startDate->getValue() >= $finishDate->getValue()) {
                    $finishDate->setMessages(['La fecha de finalización debe ser mayor a la de inicio.']);
                    $isValid = false;
                }

                $gradingLimitDate = $this->get(self::GRADING_LIMIT_DATE);
                if ($finishDate->getValue() >= $gradingLimitDate->getValue()) {
                    $gradingLimitDate->setMessages(['La fecha de finalización de ingreso de notas debe ser mayor a la de finalización del curso']);
                    $isValid = false;
                }

                //CHECKING TIME COHERENCE
                $startTime = $this->get(self::START_TIME);
                $finishTime = $this->get(self::FINISH_TIME);
                if ($startTime->getValue() >= $finishTime->getValue()) {
                    $finishTime->setMessages(['La hora de finalización debe ser mayor a la de inicio.']);
                    $isValid = false;
                }
            }
            if ($isValid) {
                $sectionElement = $this->get(self::SECTION);
                $sectionValue = $sectionElement->getValue();
                $daysElement = $this->get(self::DAYS);
                $daysValue = $daysElement->getValue();

                if ($sectionValue == 'Sab' && ($daysValue != ['sabado'])) {
                    $daysElement->setMessages(['La sección sábado debe tener seleccionado únicamente ese día como día de clases.']);
                    $isValid = false;
                }
                if ($sectionValue == 'Vier' && ($daysValue != ['viernes'])) {
                    $daysElement->setMessages(['La sección viernes debe tener seleccionado únicamente ese día como día de clases.']);
                    $isValid = false;
                }
            }
        }

        return $isValid;
    }

    private function makeTree($locations, $rooms) {
        $this->dataTree = [
            "" => [
                "" => '[Sin identificar]'
            ]
        ];
        if (!empty($locations) && !empty($rooms)) {
            foreach ($locations as $locationData) {
                $clearClasifiedRoomData = [];
                $locationCode = $locationData['cod_ubicacion'];
                foreach ($rooms as $roomData) {
                    $roomLocation = $roomData['cod_ubicacion'];
                    if ($locationCode == $roomLocation) {
                        $roomName = $roomData['nombre'];
                        $roomCode = $roomData['cod_salon'];
                        $clearClasifiedRoomData[$roomCode] = $roomName;
                    }
                }
                $this->dataTree[$locationCode] = $clearClasifiedRoomData;
            }
        }
    }

    public function getJsTree() {
        return str_replace('array (', '{', str_replace(')', '}', str_replace('=>', ':', var_export($this->dataTree, true))));
    }

    public function setData($data) {
        if (isset($data[self::START_TIME])) {
            $data[self::START_TIME] = date("H:i", strtotime($data[self::START_TIME]));
        }
        if (isset($data[self::FINISH_TIME])) {
            $data[self::FINISH_TIME] = date("H:i", strtotime($data[self::FINISH_TIME]));
        }
        parent::setData($data);
        $this->hasData = true;
        if ($this->type == self::TYPE_NEW || $this->type == self::TYPE_EDIT) {
            $this->selectedLocation = $this->get(self::LOCATION)->getValue();
            $this->selectedRoom = $this->get(self::ROOM)->getValue();
        }
        if ($this->type == self::TYPE_EDIT) {
            if (isset($data[self::COURSE])) {
                $hiddenCourse = $this->get(self::HIDDEN_COURSE);
                $hiddenCourse->setValue($data[self::COURSE]);
            }
        }
    }

    public function hasData() {
        return $this->hasData;
    }

    public function getSelectedLocation() {
        if (isset($this->selectedLocation)) {
            return var_export($this->selectedLocation, true);
        } else {
            return null;
        }
    }

    public function getSelectedRoom() {
        if (isset($this->selectedRoom)) {
            return $this->selectedRoom;
        } else {
            return null;
        }
    }

    public function setTimetable($timetable) {
        if ($timetable == null) {
            return;
        }
        //GETTING DAY NAMES
        $days = [
            'lunes' => $timetable->getLunes(),
            'martes' => $timetable->getMartes(),
            'miercoles' => $timetable->getMiercoles(),
            'jueves' => $timetable->getJueves(),
            'viernes' => $timetable->getViernes(),
            'sabado' => $timetable->getSabado(),
            'domingo' => $timetable->getDomingo()
        ];
        $realDayNames = [];
        $index = 0;
        foreach ($days as $dayName => $value) {
            if ($value != null && $value == true) {
                $realDayNames[$index] = $dayName;
            }
            $index ++;
        }
        //PREPARING DATA TO SET
        $params = [
            self::COURSE => $timetable->getCodCurso(),
            self::SECTION => $timetable->getSeccion(),
            self::COORDINATOR => $timetable->getCodCoordinador(),
            self::PROFESSOR => $timetable->getCodCatedratico(),
            self::START_TIME => $timetable->getHoraInicio(),
            self::FINISH_TIME => $timetable->getHoraFin(),
            self::START_DATE => $timetable->getFechaInicio(),
            self::FINISH_DATE => $timetable->getFechaFin(),
            self::COURSE_TYPE => $timetable->getCodTipoCurso(),
            self::LOCATION => $timetable->getCodUbicacion(),
            self::ROOM => $timetable->getCodSalon(),
            self::ROOM_SPACE => $timetable->getCupo(),
            self::DAYS => $realDayNames,
            self::LABORATORY => $timetable->getLaboratorio() == true ? 'yes' : 'no',
            self::GRADING_LIMIT_DATE => $timetable->getFechaLimiteCalificacion()
        ];
        if ($timetable->getCode() != null) {
            $params[self::TIMETABLE_CODE] = $timetable->getCode();
        }
        //SETTING DATA TO FORM
        $this->setData($params);
    }

    public function getType() {
        return $this->type;
    }

}
