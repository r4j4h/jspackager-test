<?php

namespace JsPackager\Helpers;

class FileTypeRecognizer {

    /**
     * Regexp patterns for detecting file types via filename
     * @var string
     */
    const SCRIPT_EXTENSION_PATTERN     = '/.js$/i';
    const STYLESHEET_EXTENSION_PATTERN = '/.css$/i';

    /**
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    public static function isJavaScriptFile($file) {
        return (bool)(preg_match( self::SCRIPT_EXTENSION_PATTERN, $file ));
    }

    /**
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    public static function isStylesheetFile($file) {
        return (bool)(preg_match( self::STYLESHEET_EXTENSION_PATTERN, $file ));
    }

    public static function isSourceFile($filename) {
        return (
            preg_match( self::SCRIPT_EXTENSION_PATTERN, $filename ) &&
            !preg_match( '/.compiled.js$/', $filename )
        );
    }

    // Pattern map would be better
    // 'javascript' => '/.js$/';
    // 'css' => '/.css$/';
    // then we can add to it and introspect it and see which ones we support
    // * note instead of only regexp strings we should make them support callbacks if callable
    // Done :) See class JsPackager\Helpers\MapDrivenFileTypeRecognizer

}
