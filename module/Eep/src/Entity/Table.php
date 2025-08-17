<?php

namespace Eep\Entity;

use Eep\ValueObject\Message;

class Table {

    private $headers;
    private $rows;
    private $alignment;
    private $footers;
    private $title;
    private $rowsColors;
    private $strippedTable;

    const RIGHT = 'text-right';
    const LEFT = 'text-left';
    const CENTER = 'text-center';
    const INFO = Message::BLUE;
    const WARNING = Message::YELLOW;
    const DANGER = Message::RED;
    const SUCCESS = Message::GREEN;
    const BG_DANGER = 'bg-danger';

    function __construct($title, $headers, $alignment = null, $rows = null, $footers = null) {
        $this->title = $title;
        $this->headers = $headers ?? [];
        $this->rows = $rows ?? [];
        $this->alignment = $alignment ?? []; //ARRAY OF ALLIGNMENTS FOR EVERY BODY ROW [LEFT, NULL, CENTER, RIGHT]
        $this->footers = $footers ?? [];
        $this->rowsColors = [];
        $this->strippedTable = true;
    }

    public function addRow($row, $color = null) {
        $this->rows[] = $row;
        $this->rowsColors[] = $color;
    }

    public function addFooter($footers, $clases) {
        $this->footers[] = [
            'data' => $footers,
            'clases' => $clases
        ];
    }

    function setAlignment($alignment) {
        $this->alignment = $alignment;
    }
    
    function getStrippedTable() {
        return $this->strippedTable;
    }

    function setStrippedTable($strippedTable) {
        $this->strippedTable = $strippedTable;
    }

    function getHeaders() {
        return $this->headers;
    }

    function getRows() {
        return $this->rows;
    }

    function getColor($index) {
        if ($index >= count($this->rowsColors)) {
            return null;
        }
        return $this->rowsColors[$index];
    }

    function getAlignment() {
        return $this->alignment;
    }

    function getFooters() {
        return $this->footers;
    }

    function getTitle() {
        return $this->title;
    }

}
