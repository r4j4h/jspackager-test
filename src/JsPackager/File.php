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
     * @var array[] Array of tuples: [annotationTypeString, indexInThatAnnotationBucket]
     */
    public $annotationOrderMap;

    /**
     * @param string $filePath Path to file
     * @param bool $silenceMissingFileException
     * @throws Exception\MissingFile If $filePath does not point to a valid file
     */
    public function __construct( $filePath, $silenceMissingFileException = false )
    {
        $fileHandler = $this->getFileHandler();

        if ( $fileHandler->is_file( $filePath ) === false && $silenceMissingFileException === false )
        {
            throw new Exception\MissingFile($filePath . ' is not a valid file!', 0, null, $filePath);
        }

        $filePathParts = pathinfo( $filePath );

        $this->filename = $filePathParts['filename'];
        $this->filetype = ( isset( $filePathParts['extension'] ) ? $filePathParts['extension'] : '' );
        $this->path     = $filePathParts['dirname'];

        $this->isRoot      = false;
        $this->stylesheets = array();
        $this->scripts     = array();
        $this->packages    = array();
        $this->annotationOrderMap = array();
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


    protected $fileHandler;

    /**
     * Get the file handler.
     *
     * @return mixed
     */
    public function getFileHandler()
    {
        return ( $this->fileHandler ? $this->fileHandler : new FileHandler() );
    }

    /**
     * Set the file handler.
     *
     * @param $fileHandler
     * @return File
     */
    public function setFileHandler($fileHandler)
    {
        $this->fileHandler = $fileHandler;
        return $this;
    }
}