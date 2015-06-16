<?php

namespace JsPackager\CompiledFileAndManifest;

use JsPackager\Constants;

class FilenameConverter
{

    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    public static function getCompiledFilename($filename)
    {
        return preg_replace('/.js$/', '.' . Constants::COMPILED_SUFFIX . '.js', $filename);
    }

    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    public static function getSourceFilenameFromCompiledFilename($filename)
    {
        return preg_replace('/.' . Constants::COMPILED_SUFFIX . '.js$/', '.js', $filename);
    }

    /**
     * Convert a given filename to its manifest equivalent
     *
     * @param $filename
     * @return string
     */
    public static function getManifestFilename($filename)
    {
        return preg_replace('/.js$/', '.js.' . Constants::MANIFEST_SUFFIX, $filename);
    }

    /**
     * Convert a given filename to its manifest equivalent
     *
     * @param string $filename
     * @return string
     */
    public static function getSourceFilenameFromManifestFilename($filename)
    {
        return preg_replace('/.js.' . Constants::MANIFEST_SUFFIX . '$/', '.js', $filename);
    }

}