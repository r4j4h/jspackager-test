<?php

namespace JsPackager\Processor;

use JsPackager\DependencyFileInterface;

class SimpleProcessorParams
{
    /**
     * @var Array
     */
    public $orderedFilePaths;

    /**
     * @var DependencyFileInterface
     */
    public $dependencySet;

    /**
     * @var Array
     */
    public $rollingPathsMarkedNoCompile;

    public function __construct( $orderedFilePaths ) {
        $this->orderedFilePaths = $orderedFilePaths;
    }
}