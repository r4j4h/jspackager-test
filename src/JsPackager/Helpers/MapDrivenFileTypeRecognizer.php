<?php

namespace JsPackager\Helpers;

class MapDrivenFileTypeRecognizer
{
    public $recognitions = array();

    /**
     * Add a recognition to the recognition map
     * @param $entryId
     * @param $recognition
     * @throws \Exception
     */
    public function addRecognition($entryId, $recognition) {
        if ( isset( $this->recognitions[ $entryId ] ) ) {
            throw new \Exception("Recognition entry id \"{$entryId}\" is already registered. Please try a different id.");
        }
        $this->recognitions[ $entryId ] = $recognition;
    }

    /**
     * Attempt to recognize a specific recognition type
     *
     * @param String $recognition Recognition entry id
     * @param String $file File path
     * @return Boolean
     * @throws \Exception
     */
    public function recognize($recognition, $file) {
        if ( !isset( $this->recognitions[ $recognition ] ) ) {
            throw new \Exception("Recognition \"{$recognition}\" is not currently loaded or does not exist.\n");
        }

        return call_user_func( $this->recognitions[ $recognition ], $file );
    }

    /**
     * Recognize a give file.
     *
     * Acts as a form of introspection.
     *
     * @param $file
     * @return array Array of recognition entry ids that recognized this file
     */
    public function isRecognizedAs($file) {
        $recognizedAs = array();
        foreach( $this->recognitions as $recognitionId => $recognition ) {
            $recognized = call_user_func( $recognition, $file );
            if ( $recognized ) {
                array_push($recognizedAs, $recognitionId);
            }
        }
        return $recognizedAs;
    }

}