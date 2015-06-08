<?php

namespace JsPackager\Processor;

class ProcessingResult
{
    /**
     * @var bool
     */
    public $successful = null;

    /**
     * @var number
     */
    public $returnCode = null;

    /**
     * @var string
     */
    public $output = null;

    /**
     * @var string
     */
    public $err = null;

    public function __construct($successful, $returnCode, $output = '', $err = '', $numberOfErrors = 0) {
        $this->successful = $successful;
        $this->returnCode = $returnCode;
        $this->output = $output;
        $this->err = $err;
        $this->numberOfErrors = $numberOfErrors;
    }

}
