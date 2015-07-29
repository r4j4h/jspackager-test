<?php

namespace JsPackager\Annotations;

use JsPackager\Constants;

class RemoteAnnotationStringService
{

    /**
     * Path to replace Remote Symbols (`@remote`) with.
     *
     * @var string
     */
    public $remoteFolderPath = 'shared';

    /**
     * Symbol used to represent remote folders.
     *
     * @var string
     */
    public $remoteSymbol = '@shared';

    /**
     * @param string $remoteFolderPath Path to replace Remote Symbols with.
     */
    public function __construct($remoteSymbol, $remoteFolderPath) {
        $this->remoteSymbol = $remoteSymbol;
        $this->remoteFolderPath = $remoteFolderPath;
    }

    /**
     * @param $string
     * @return string
     */
    public function expandOutRemoteAnnotation($string) {
        return str_replace( $this->remoteSymbol, $this->remoteFolderPath, $string );
    }

    /**
     * @param $string
     * @return bool
     */
    public function stringContainsRemoteAnnotation($string) {
        return ( strpos($string, $this->remoteSymbol) !== FALSE );
    }

}