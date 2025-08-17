<?php

namespace Eep\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Eep\Form\FieldError;
use Zend\Validator\InArray;
use Zend\Validator\StringLength;
use Zend\Validator\NotEmpty;
use Zend\Form\FormInterface;

class PostActStudentsForm extends Form {

    const FORM_NAME = 'PostActStudentsForm';
    const ACT = 'actCode';
    const COMMENT = 'comments';
    const USERS = 'usersData';
    const FINAL_GRADE = 'finalGrade';
    const BALLOT = 'ballotCode';

    private $users;
    private $finalGrades;
    private $ballots;

    public function __construct($users, $finalGrades = null, $ballots = null) {
        parent::__construct(self::FORM_NAME);

        $this->users = $users ?? [];
        $this->finalGrades = $finalGrades;
        $this->ballots = $ballots;

        $this->setAttribute('method', 'post');
        $this->addElements();
        $this->addInputFilter();
    }

    private function addElements() {
        //ACT
        $this->add([
            'type' => 'text',
            'name' => self::ACT,
            'options' => [
                'label' => 'Acta de Postgrado',
                'label_attributes' => [
                    'class' => 'control-label'
                ],
            ],
            'attributes' => [
                'id' => self::ACT,
                'class' => 'form-control',
                'required' => 'required'
            ]
        ]);

        //USERS
        $this->add([
            'name' => self::USERS
        ]);

        //COMMENTS
        $this->add([
            'name' => self::COMMENT
        ]);
    }

    private function addInputFilter() {
        $inputFilter = new InputFilter();
        $this->setInputFilter($inputFilter);

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
    }

    public function getData($flag = FormInterface::VALUES_NORMALIZED) {
        $data = parent::getData($flag);
        $comments = $data[self::COMMENT];
        $users = $data[self::USERS];
        $keyedComments = [];
        for ($index = 0; $index < count($users); $index++) {
            $keyedComments[$users[$index]] = $comments[$index];
        }
        $data[self::COMMENT] = $keyedComments;
        $data[self::FINAL_GRADE] = $this->finalGrades;
        $data[self::BALLOT] = $this->ballots;
        return $data;
    }

    public function isValid() {
        $valid = parent::isValid();
        if ($valid) {
            //VALIDATING USERS
            $inputFilter = new InputFilter();
            $inputFilter->add([
                'name' => self::USERS,
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'break_chain_on_failure' => true,
                        'options' => [
                            'haystack' => $this->users,
                            'messages' => FieldError::IN_ARRAY
                        ],
                    ],
                ],
            ]);
            $usersElement = $this->get(self::USERS);
            $usersValues = $usersElement->getValue();
            if (empty($usersValues)) {
                $valid = false;
                $usersElement->setMessages(['Deben haber usuarios agregados']);
            } else {
                foreach ($usersValues as $userValue) {
                    $inputFilter->setData([self::USERS => $userValue]);
                    if (!$inputFilter->isValid()) {
                        $usersElement->setMessages($inputFilter->getMessages());
                        $valid = false;
                        break;
                    }
                }
            }
        }
        if ($valid) {
            //VALIDATING COMMENTS
            $commentElement = $this->get(self::COMMENT);
            $comments = $commentElement->getValue();
            $inputFilter = new InputFilter();
            $inputFilter->add([
                'name' => self::COMMENT,
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
            $msgs = [];
            foreach ($comments as $comment) {
                $inputFilter->setData([self::COMMENT => $comment]);
                if (!$inputFilter->isValid()) {
                    $comment = wordwrap($comment, 100, '<br>', true);
                    $msgs[] = "Para la observación: '$comment':" . \Eep\ValueObject\Message::makeHtmlList($inputFilter->getMessages());
                    $valid = false;
                }
            }
            if (!$valid) {
                $commentElement->setMessages($msgs);
            }
        }
        return $valid;
    }

}
