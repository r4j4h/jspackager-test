<?php

namespace JsPackager\Compiler;

use Iterator;

class FileCompilationResultCollection implements Iterator
{
    private $position = 0;
    private $array = array();

    public function __construct() {
        $this->position = 0;
    }

    public function add(FileCompilationResult $result) {
        return array_push( $this->array, $result );
    }

    public function getValuesAsArray() {
        return $this->array;
    }

    function rewind() {
        $this->position = 0;
    }

    function current() {
        return $this->array[$this->position];
    }

    function key() {
        return $this->position;
    }

    function next() {
        ++$this->position;
    }

    function valid() {
        return isset($this->array[$this->position]);
    }

}