<?php
/**
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */
namespace JsPackager\Exception;

class CannotWrite extends \Exception
{
    const ERROR_CODE = 502;

    protected $cannotWriteFilePath;

    public function __construct($message = "", Exception $previous = null, $cannotWriteFilePath) {
        $code = self::ERROR_CODE;
        parent::__construct( $message, $code, $previous );
        $this->cannotWriteFilePath = $cannotWriteFilePath;
    }

    public function getFilePath() {
        return $this->cannotWriteFilePath;
    }
}
