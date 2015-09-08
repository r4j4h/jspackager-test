<?php

namespace JsPackager\CompiledFileAndManifest;

class FilenameConverter
{
    /**
     * @var string
     */
    private $compiledSuffix;

    /**
     * @var string
     */
    private $manifestSuffix;

    public function __construct($compiledSuffix = 'compiled', $manifestSuffix = 'manifest') {
        $this->compiledSuffix = $compiledSuffix;
        $this->manifestSuffix = $manifestSuffix;
    }

    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    public function getCompiledFilename($filename)
    {
        return preg_replace('/.js$/', '.' . $this->compiledSuffix . '.js', $filename);
    }

    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    public function getSourceFilenameFromCompiledFilename($filename)
    {
        return preg_replace('/.' . $this->compiledSuffix . '.js$/', '.js', $filename);
    }

    /**
     * Convert a given filename to its manifest equivalent
     *
     * @param $filename
     * @return string
     */
    public function getManifestFilename($filename)
    {
        return preg_replace('/.js$/', '.js.' . $this->manifestSuffix, $filename);
    }

    /**
     * Convert a given filename to its manifest equivalent
     *
     * @param string $filename
     * @return string
     */
    public function getSourceFilenameFromManifestFilename($filename)
    {
        return preg_replace('/.js.' . $this->manifestSuffix . '$/', '.js', $filename);
    }

}