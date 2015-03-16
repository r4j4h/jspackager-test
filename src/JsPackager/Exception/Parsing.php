<?php
/**
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */
namespace JsPackager\Exception;

class Parsing extends \Exception
{
    const ERROR_CODE = 501;

    protected $errors;

    public function __construct($message = "", \Exception $previous = null, $errors) {
        $code = self::ERROR_CODE;
        parent::__construct( $message, $code, $previous );
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
