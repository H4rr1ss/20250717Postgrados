<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\Digits;
use Zend\Validator\Regex;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\GreaterThan;
use Zend\Validator\LessThan;
use Eep\Form\FieldError;
use Eep\Service\UserManager;
use Zend\Validator\Callback;
use Eep\ValueObject\Message;

class PonderBlocksForm extends Form {

    const FORM_NAME = 'ponderBlocksForm';
    const TIMETABLE = 'timetableCode';
    //TABLE ELEMENTS
    const BLOCK_CODE = 'cod_bloque';
    const BLOCK_NAME = 'nombre';
    const BLOCK_VALUE = 'valor';

    private $blocks;
    private $filteredData;

    public function __construct($blocks) {
        parent::__construct(self::FORM_NAME);

        $this->setAttribute('method', 'post');
        $this->blocks = $blocks;

        $this->addElements();
        $this->addInputFilter();
    }

    public function getBlocks() {
        return $this->filteredData;
    }

    private function addElements() {
        //TIMETABLE CODE
        $this->add([
            'type' => 'hidden',
            'name' => self::TIMETABLE
        ]);

        //BLOCK CODE
        $this->add([
            'type' => 'hidden',
            'name' => self::BLOCK_CODE
        ]);

        //BLOCK NAME
        $this->add([
            'type' => 'hidden',
            'name' => self::BLOCK_NAME
        ]);

        //BLOCK VALUE
        $this->add([
            'type' => 'hidden',
            'name' => self::BLOCK_VALUE
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);
        //TIMETABLE CODE
        $inputFilter->add([
            'name' => self::TIMETABLE,
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
                        'max' => 10,
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

    public function isValid() {
        $isValid = parent::isValid();

        $data = $this->getData();

        //CHECKING DATA COHERENCE
        $codes = $data[self::BLOCK_CODE];
        $names = $data[self::BLOCK_NAME];
        $values = $data[self::BLOCK_VALUE];
        $count = count($names);
        $ttElement = $this->get(self::TIMETABLE);
        $msgs = $ttElement->getMessages();
        if (count($codes) != $count || $count != count($values)) {
            $msgs[] = "La cantidad de elementos de nombres, códigos y valores no es el mismo.";
            $ttElement->setMessages($msgs);
            $isValid = false;
            $this->filteredData = $this->blocks;
            return $isValid;
        } else {
            $structuredBlockData = [];
            $numericReceivedBlockCodes = []; //ONLY NUMERIC VALUES
            $allReceivedCodes = [];
            $newCounter = 0;
            for ($i = 0; $i < $count; $i++) {
                $code = $codes[$i];
                //CHECKING REPEATED CODES
                foreach ($allReceivedCodes as $c) {
                    if ($code == $c) {
                        $msgs[] = "Hay bloques de código repetidos ($c)";
                        $ttElement->setMessages($msgs);
                    }
                }
                $allReceivedCodes[] = $code;
                //SEPARETING EXISTING BLOCK CODES
                if (intval($code) != false) { //INTVAL RETURNS "0" WITH NOT NUMERIC VALUES. "0" = FALSE
                    //ONLY PREVIOUSLY CREATED BLOCKS HAVE NUMERIC CODES
                    $numericReceivedBlockCodes[] = intval($code);
                } else {
                    $newCounter++;
                    $code = "N$newCounter"; //E. G.: N5 => NEW BLOCK NO. 5
                }
                //ADDING CODES TO STRUCTURE
                $structuredBlockData[$code] = [
                    'cod_bloque' => $code,
                    'nombre' => trim($names[$i]),
                    'valor' => '' . round(($values[$i]), 2),
                    'notas' => isset($this->blocks[$code]) ? $this->blocks[$code]["notas"] : 0
                ];
            }
            //COMPARING WITH CURRENT BLOCKS
            $currentNotRemovableBlockCodes = [];
            foreach ($this->blocks as $blockCode => $block) {
                if ($block['notas'] != 0) {
                    $currentNotRemovableBlockCodes[] = intval($blockCode);
                }
            }
            $errorNotDeletableCodes = array_diff($currentNotRemovableBlockCodes, $numericReceivedBlockCodes);
            if (count($errorNotDeletableCodes) > 0) {
                //NOT DELETABLE CODES DELETED
                $isValid = false;
                $msgs[] = 'Se eliminó un bloque de ponderación que no estaba permitido eliminar.';
                $ttElement->setMessages($msgs);
                $this->filteredData = $this->blocks;
                return $isValid;
            } else {
                $errorCodedBocks = array_diff($numericReceivedBlockCodes, array_keys($this->blocks));
                if (count($errorCodedBocks) != 0) {
                    $msgs[] = 'Se recibieron códigos de bloque que no pertenecen al horario';
                    $ttElement->setMessages($msgs);
                    $this->filteredData = $this->blocks;
                    $isValid = false;
                    return $isValid;
                } else {
                    //VALIDATING BLOCKS INFO
                    $isValid = $this->filterBlocks($structuredBlockData) && $isValid;
                    //VALIDATING VALUES SUM
                    $sum = 0;
                    foreach ($this->filteredData as $block) {
                        $sum += $block['valor'];
                    }
                    if ($sum != 100) {
                        $msgs[] = "La suma de los bloques de ponderación es de $sum puntos. Deben sumar 100 puntos,.";
                        $ttElement->setMessages($msgs);
                        $isValid = false;
                    }
                }
            }
        }
        return $isValid;
    }

    private function filterBlocks($blockData) {
        $filter = new InputFilter();
        //CREATING FILTER
        $filter->add([
            'name' => self::BLOCK_CODE,
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
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 10,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);
        $filter->add([
            'name' => self::BLOCK_NAME,
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
                    'name' => StringLength::class,
                    'options' => [
                        'min' => 0,
                        'max' => 50,
                        'messages' => FieldError::STRING_LENGTH
                    ],
                ],
            ],
        ]);
        $filter->add([
            'name' => self::BLOCK_VALUE,
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
        $isValid = true;
        foreach ($blockData as $blockCode => $block) {
            $filter->setData($block);
            if (!$filter->isValid()) {
                $blockData[$blockCode]['error'] = Message::makeHtmlList($filter->getMessages(), true);
                $isValid = false;
            }
        }
        $this->filteredData = $blockData;
        return $isValid;
    }

}
