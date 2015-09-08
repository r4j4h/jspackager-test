<?php
/**
 * CompiledFile represents a compiled file and its manifest data
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Compiler;

class CompiledFile
{
    /**
     * The default filename of this compiled file (derived from the source file this object was created from)
     * @var string
     */
    public $filename;

    /**
     * The path of the source file this object was created from
     * @var string
     */
    public $path;

    /**
     * The contents of the compiled file
     * @var string
     */
    public $contents;

    /**
     * The default filename of this compiled file's manifest (derived form the source files this object
     * was created from)
     * @var string
     */
    public $manifestFilename;

    /**
     * The contents of the compiled file's manifest
     * @var string
     */
    public $manifestContents;

    /**
     * The contents of any warnings or errors in the compiled file.
     * @var string
     */
    public $warnings;

    /**
     * CompiledFile's constructor.
     *
     * Note: This is generally used and returned by the Compiler, a la "Factory pattern"
     *
     * @param string $path
     * @param string $filename
     * @param string $contents
     * @param string $manifestPath
     * @param string $manifestContents
     */
    public function __construct( $path, $filename, $contents, $manifestPath, $manifestContents, $warnings )
    {
        $this->path = $path;
        $this->filename = $filename;
        $this->contents = $contents;
        $this->manifestFilename = $manifestPath;
        $this->manifestContents = $manifestContents;
        $this->warnings = $warnings;
    }
}