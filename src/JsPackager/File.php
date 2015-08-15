<?php
/**
 * The File class is used to represent a file that may or may not be a dependency, and may or may not
 * include dependencies. It is used as a pool for annotations so that proper behavior can be easily
 * derived.
 *
 * See DependencyTreeParser class for more information.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager;

use JsPackager\Annotations\AnnotationOrderMap;
use JsPackager\Annotations\AnnotationOrderMapping;
use JsPackager\Helpers\FileHandler;

class File
{
    /**
     * @var string File's filename
     */
    public $filename;

    /**
     * @var string File's type
     */
    public $filetype;

    /**
     * @var string File's relative path (from public directory)
     */
    public $path;

    /**
     * If this file is marked to be a root package
     *
     * @var boolean
     */
    public $isRoot;

    /**
     * If this file is marked to skip compilation
     *
     * @var boolean
     */
    public $isMarkedNoCompile;

    /**
     * If this file is marked as existing on `remote` server
     *
     * @var boolean
     */
    public $isRemote;

    /**
     * Scripts this file is dependent on
     *
     * @var File[]
     */
    public $scripts;

    /**
     * Stylesheets this file is dependent on
     *
     * @var File[]
     */
    public $stylesheets;

    /**
     * @var string[] Filename of packages used
     */
    public $packages;

    /**
     * Each annotation found in order inside the file
     *
     * @var AnnotationOrderMap
     */
    public $annotationOrderMap;

    /**
     * @param string $filePath Path to file
     * @param bool $silenceMissingFileException
     * @throws Exception\MissingFile If $filePath does not point to a valid file
     */
    public function __construct( $filePath, $silenceMissingFileException = false, FileHandler $fileHandler = null )
    {
        $this->fileHandler = ( $fileHandler ? $fileHandler : new FileHandler() );

        if ( $this->fileHandler->is_file( $filePath ) === false && $silenceMissingFileException === false )
        {
            throw new Exception\MissingFile($filePath . ' is not a valid file!', 0, null, $filePath);
        }

        $filePathParts = pathinfo( $filePath );

        $this->filename = $filePathParts['filename'];
        $this->filetype = ( isset( $filePathParts['extension'] ) ? $filePathParts['extension'] : '' );
        $this->path     = $filePathParts['dirname'];

        $this->isRoot      = false;
        $this->isMarkedNoCompile = false;
        $this->isRemote = false;
        $this->stylesheets = array();
        $this->scripts     = array();
        $this->packages    = array();
        $this->annotationOrderMap = new AnnotationOrderMap();
    }

    /**
     * Get the full path to the file this File object represents
     *
     * @return string
     */
    public function getFullPath()
    {
        $filename = $this->path . '/' . $this->filename;
        if ( $this->filetype )
            $filename .= '.' . $this->filetype;
        return $filename;
    }

    /**
     * @var FileHandler
     */
    protected $fileHandler;

}