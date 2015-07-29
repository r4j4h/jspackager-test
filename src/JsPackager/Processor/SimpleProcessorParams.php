<?php

namespace JsPackager\Processor;

class SimpleProcessorParams
{
    /**
     * @var Array
     */
    public $orderedFilePaths;

    public function __construct( $orderedFilePaths ) {
        $this->orderedFilePaths = $orderedFilePaths;
    }
}