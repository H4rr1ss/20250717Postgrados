<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Regex;
use Zend\Validator\NotEmpty;
use Zend\Validator\GreaterThan;
use Zend\Validator\LessThan;
use Eep\Form\FieldError;
use Eep\ValueObject\Message;

class GradesEntryForm extends Form {

    const FORM_NAME = 'GradesEntryForm';
    const TIMETABLE = 'timetableCode';
    const BLOCK = 'blockElement';
    const USER_CODE = 'cod_usuario';
    const ATTENDANCE = 'asistencia_cumplida';
    const GRADE = 'grade';

    private $previousGrades;
    private $filteredData;
    private $blocksData;

    public function __construct($previousGrades, $blocksData) {
        parent::__construct(self::FORM_NAME);

        $this->blocksData = $blocksData;
        $this->setAttribute('method', 'post');
        $this->previousGrades = $previousGrades;

        $this->addElements();
    }

    public function getGradesData() {
        return $this->filteredData;
    }

    private function addElements() {
        //TIMETABLE CODE
        $this->add([
            'type' => 'hidden',
            'name' => self::TIMETABLE
        ]);

        //ATTENDANCE CODE
        $this->add([
            'type' => 'hidden',
            'name' => self::ATTENDANCE
        ]);

        //GRADES DATA
        $this->add([
            'type' => 'hidden',
            'name' => self::BLOCK
        ]);
    }

    //DATA HAS TO BE CLEANED IN THE GradesManager::getGrades() FORMAT
    /* $cleanData = [
     *  <cod_usuario> => [
     *      'user' => <UserObject>,
     *      'asistencia_cumplida' => <asistencia_cumplida>,
     *      'nota_final' => <nota_final>,
     *      'grades' => [
     *          <cod_bloque> => [
     *              'grade'=> <nota>,
     *              'error' => null,
     *          ],
     *          <cod_bloque> => [
     *              'grade'=> <nota>,
     *              'error' => null,
     *          ],
     *      ]
     *  ],
     *  <cod_usuario> => [
     *      'user' => <UserObject>,
     *      'asistencia_cumplida' => <asistencia_cumplida>,
     *      'nota_final' => <nota_final>,
     *      'grades' => [
     *          <cod_bloque> => [
     *              'grade'=> <nota>,
     *              'error' => null,
     *          ],
     *          <cod_bloque> => [
     *              'grade'=> <nota>,
     *              'error' => null,
     *          ],
     *      ]
     *  ],
     * ]
     */

    public function isValid() {
        parent::isValid();
        $isValid = true;
        $this->filteredData = $this->previousGrades;

        //CHECKING DATA INTEGRITY
        $data = $this->getData();
        $attendanceData = $data[self::ATTENDANCE];
        $blockReceivedData = $data[self::BLOCK];
        $ttElement = $this->get(self::TIMETABLE);
        //SEARCHING FOR THE SAME USERS
        $previousUserCodes = array_keys($this->previousGrades);
        //ATTENDANCE SAME USERS
        $attendanceUserCodes = array_keys($attendanceData);
        if (!empty(array_diff($previousUserCodes,$attendanceUserCodes))) {
            $ttElement->setMessages(["No coinciden los usuarios de la asistencia con los existentes para el horario", json_encode($attendanceData)]);
            $isValid = false;
        } else {
            //BLOCKS SAME USERS
            $blockUserCodes = array_keys($blockReceivedData);
            if (!empty(array_diff($previousUserCodes, $blockUserCodes))) {
                $ttElement->setMessages(["No coinciden los usuarios de las notas con los existentes para el horario"]);
                $isValid = false;
            } else {
                //SEARCHING FOR HAVING ALL THE BLOCKS
                $blockCodes = array_keys(current($this->previousGrades)["grades"]); //TIMETABLE BLOCK CODES
                foreach ($blockReceivedData as $userCode => $userData) {
                    $dataBlockCodes = array_keys($userData);
                    if ($blockCodes != $dataBlockCodes) {
                        $ttElement->setMessages(["Existen estudiantes ($userCode) que no presentan las notas de todos los bloques de ponderación"]);
                        $isValid = false;
                        break;
                    }
                }
            }
        }
        //DATA HAS TO BE CLEANED IN THE GradesManager::getGrades() FORMAT
        if ($isValid) {
            $filter = $this->getGradeFilter();
            foreach ($blockReceivedData as $userCode => $grades) {
                $attendance = $attendanceData[$userCode] == true;
                $nota_final = null;
                foreach ($grades as $blockCode => $grade) {
                    $gradeError = false;
                    if ($grade === '' || !$attendance) {
                        //IF RECEIVED GRADE IS EMPTY AND THE BLOCK HAS HAD DATA, IT HAS TO BE SETTED TO "0".
                        if ($this->previousGrades[$userCode]["grades"][$blockCode]["grade"] != null) {
                            $grade = '0';
                        } else {
                            $grade = null;
                        }
                    } else {
                        $filter->setData([self::GRADE => $grade]);
                        if ($filter->isValid()) {
                            $limit = $this->blocksData[$blockCode]['valor'] * 1;
                            if ($grade > $limit) {
                                $this->filteredData[$userCode]["grades"][$blockCode]["error"] = "El punteo límite es $limit";
                                $isValid = false;
                            } else {
                                $grade = $filter->getValue(self::GRADE);
                            }
                        } else {
                            $this->filteredData[$userCode]["grades"][$blockCode]["error"] = Message::makeHtmlList($filter->getMessages());
                            $isValid = false;
                            $gradeError = true;
                        }
                    }
                    $this->filteredData[$userCode]["grades"][$blockCode]["grade"] = $grade;
                    if (!$gradeError && $this->filteredData[$userCode]["grades"][$blockCode]["grade"] != null) {
                        $nota_final += $this->filteredData[$userCode]["grades"][$blockCode]["grade"];
                    }
                }
                $this->filteredData[$userCode]["nota_final"] = $nota_final;
                $this->filteredData[$userCode]["asistencia_cumplida"] = $attendance;
            }
        }
        return $isValid;
    }

    private function getGradeFilter() {
        $filter = new InputFilter();
        //CREATING FILTER
        $filter->add([
            'name' => self::GRADE,
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
                    'name' => Regex::class,
                    'options' => [
                        'pattern' => '/^(?:\d+|\d*\.(\d{2}|\d))$/',
                        'messages' => FieldError::DECIMAL_REGEX
                    ]
                ],
                [
                    'name' => GreaterThan::class,
                    'options' => [
                        'min' => 0,
                        'inclusive' => true,
                        'messages' => FieldError::GREATER_THAN
                    ],
                ],
                [
                    'name' => LessThan::class,
                    'options' => [
                        'max' => 100,
                        'inclusive' => true,
                        'messages' => FieldError::LESS_THAN
                    ],
                ],
            ],
        ]);
        return $filter;
    }

}
