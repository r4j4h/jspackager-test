<?php
/**
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */
namespace JsPackager\Exception;

class Recursion extends \Exception
{
    const ERROR_CODE = 401;

    public function __construct($message = "", $code = 0, Exception $previous = null) {
        $code = self::ERROR_CODE;
        parent::__construct( $message, $code, $previous );
    }
}
