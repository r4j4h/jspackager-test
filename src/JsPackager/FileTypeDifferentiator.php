<?php

namespace JsPackager;

class FileTypeRecognizer {

    /**
     * Regexp patterns for detecting file types via filename
     * @var string
     */
    const SCRIPT_EXTENSION_PATTERN     = '/.js$/';
    const STYLESHEET_EXTENSION_PATTERN = '/.css$/';

    /**
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    protected function isJavaScriptFile($file) {
        return preg_match( self::SCRIPT_EXTENSION_PATTERN, $file );
    }

    /**
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    protected function isStylesheetFile($file) {
        return preg_match( self::STYLESHEET_EXTENSION_PATTERN, $file );
    }

}
