<?php

namespace Eep\ValueObject;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Site
 *
 * @author kevinj
 */
class Menu {

    private $icon;
    private $name;
    private $state;
    private $section;
    private $action;

    public function __construct($icon, $name, $section = 'user', $action = 'profile', $state = FALSE) {
        $this->icon = $icon;
        $this->name = $name;
        $this->section = $section;
        $this->action = $action;
        $this->state = $state;
    }

    public function getIcon() {
        return $this->icon;
    }

    public function getName() {
        return $this->name;
    }

    public function getAction() {
        return $this->action;
    }

    public function getSection() {
        return $this->section;
    }

    public function isActive() {
        return $this->state;
    }

}
