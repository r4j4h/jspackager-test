<?php

namespace JsPackager\Annotations;

class RemoteAnnotationStringService
{

    /**
     * Path to replace Remote Symbols (`@remote`) with.
     *
     * @var string
     */
    public $remoteFolderPath;

    /**
     * Symbol used to represent remote folders.
     *
     * @var string
     */
    public $remoteSymbol;

    /**
     * @param string $remoteFolderPath Path to replace Remote Symbols with.
     */
    public function __construct($remoteSymbol = '@shared', $remoteFolderPath = 'shared') {
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