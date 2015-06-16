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

    /**
     * Semantic constant for indicating successful or not boolean statuses
     */
    const SUCCEEDED = true;
    const FAILED = false;

    /**
     * Semantic constant for indicating successful or not error codes
     */
    const RETURNCODE_OK = 0;
    const RETURNCODE_FAIL = 1;

    public function __construct($successful, $returnCode, $output = '', $err = '', $numberOfErrors = 0) {
        $this->successful = $successful;
        $this->returnCode = $returnCode;
        $this->output = $output;
        $this->err = $err;
        $this->numberOfErrors = $numberOfErrors;
    }

}
