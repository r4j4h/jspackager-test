<?php

namespace JsPackager\Outputter;

use JsPackager\Compiler\DependencySetCollection;

class SimpleOutputterResult
{

    /**
     * @var boolean
     */
    public $wasSuccessful;

    /**
     * @var array<Exception|String>
     */
    public $errors = array();

    /**
     * @var array
     */
    public $metaData = array();

    /**
     * @var DependencySetCollection
     */
    public $dependencySets;

    /**
     * @param boolean $wasSuccessful
     * @param array<Exception|String> $errors
     * @param array $metaData
     * @param DependencySetCollection $dependencySets
     */
    public function __construct($wasSuccessful, $errors, $metaData, DependencySetCollection $dependencySets) {
        $this->wasSuccessful = $wasSuccessful;
        $this->errors = $errors;
        $this->metaData = $metaData;
        $this->dependencySets = $dependencySets;
    }

    /**
     * @return DependencySetCollection
     */
    public function getDependencySets()
    {
        return $this->dependencySets;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        return $this->metaData;
    }

    /**
     * @return boolean
     */
    public function getWasSuccessfulFlag()
    {
        return $this->wasSuccessful;
    }

}