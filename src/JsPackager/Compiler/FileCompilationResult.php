<?php
/**
 * The FileCompilationResult class is used to represent a file that has been compiled.
 *
 * Used as a return object for Compiler's compileAndWriteFilesAndManifests function.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Compiler;

class FileCompilationResult
{

    public function __construct($sourcePath, $compiledPath, $manifestPath) {
        $this->sourcePath = $sourcePath;
        $this->compiledPath = $compiledPath;
        $this->manifestPath = $manifestPath;
    }

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
     * @var string Source file's path and name
     */
    private $sourcePath;

    /**
     * @var string Compiled file's path and name
     */
    private $compiledPath;

    /**
     * @var string Compiled file's manifest's path and name
     */
    private $manifestPath;

}