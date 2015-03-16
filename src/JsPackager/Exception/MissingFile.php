<?php
/**
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */
namespace JsPackager\Exception;

class MissingFile extends \Exception
{
    const ERROR_CODE = 404;

    protected $missingFilePath;

    /**
     * Construct a MissingFile exception.
     *
     * On top of standard \Exception behavior, this exception provides the ability to get the file path of the missing
     * file separate from the exception's message.
     *
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     * @param string $cannotWriteFilePath
     */
    public function __construct($message = "", $code = 0, \Exception $previous = null, $cannotWriteFilePath) {
        $code = self::ERROR_CODE;

        parent::__construct( $message, $code, $previous );

        $this->missingFilePath = $cannotWriteFilePath;
    }

    public function getMissingFilePath() {
        return $this->missingFilePath;
    }
}
