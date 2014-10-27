<?php
/**
 * The FileCompilationResult class is used to represent a file that has been compiled.
 *
 * This is basically for a return object for Compiler's compileAndWriteFilesAndManifests function.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Compiler;

class FileCompilationResult
{
    /**
     * @var string Source file's path and name
     */
    public $sourcePath;

    /**
     * @var string Compiled file's path and name
     */
    public $compiledPath;

    /**
     * @var string Compiled file's manifest's path and name
     */
    public $manifestPath;

    /**
     * Get the full path and name to the source file.
     *
     * @return string
     */
    public function getSourcePath()
    {
        return $this->sourcePath;
    }

    /**
     * Get the full path and name to the source file.
     *
     * @return string
     */
    public function getCompiledPath()
    {
        return $this->compiledPath;
    }

    /**
     * Get the full path and name to the source file.
     *
     * @return string
     */
    public function getManifestPath()
    {
        return $this->manifestPath;
    }


    /**
     * @param string $compiledPath
     */
    public function setCompiledPath($compiledPath)
    {
        $this->compiledPath = $compiledPath;
    }

    /**
     * @param string $manifestPath
     */
    public function setManifestPath($manifestPath)
    {
        $this->manifestPath = $manifestPath;
    }

    /**
     * @param string $sourcePath
     */
    public function setSourcePath($sourcePath)
    {
        $this->sourcePath = $sourcePath;
    }
}