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

class File implements DependencyFileInterface
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
    public $dirname;

    /**
     * If this file is marked to be a root package
     *
     * @var boolean
     */
//    public $isRoot;

    /**
     * If this file is marked to skip compilation
     *
     * @var boolean
     */
//    public $isMarkedNoCompile;

    /**
     * If this file is marked as existing on `remote` server
     *
     * @var boolean
     */
//    public $isRemote;

    /**
     * Scripts this file is dependent on
     *
     * @var File[]
     */
//    public $scripts;

    /**
     * Stylesheets this file is dependent on
     *
     * @var File[]
     */
//    public $stylesheets;

    /**
     * @var string[] Filename of packages used
     */
//    public $packages;

    /**
     * Each annotation found in order inside the file
     *
     * @var AnnotationOrderMap
     */
//    public $annotationOrderMap;

    /**
     * @param string $filePath Path to file
     * @param bool $silenceMissingFileException
     * @throws Exception\MissingFile If $filePath does not point to a valid file
     */
    public function __construct( $filePath, $silenceMissingFileException = false, FileHandler $fileHandler = null )
    {
        $this->fileHandler = ( $fileHandler ? $fileHandler : new FileHandler() );

        // todo change this to a valid file boolean or something? or should we have a MissingFile variant class?
        // or should we not change?
        if ( $this->fileHandler->is_file( $filePath ) === false && $silenceMissingFileException === false )
        {
            throw new Exception\MissingFile($filePath . ' is not a valid file!', 0, null, $filePath);
        }

        $filePathParts = pathinfo( $filePath );

        $this->filename = $filePathParts['filename'];
        $this->filetype = ( isset( $filePathParts['extension'] ) ? $filePathParts['extension'] : '' );
        $this->dirname     = $filePathParts['dirname'];

        $this->addMetaData('isRoot' , false);
        $this->addMetaData('isMarkedNoCompile' , false);
        $this->addMetaData('isRemote' , false);
        $this->addMetaData('stylesheets' , array());
        $this->addMetaData('scripts' , array());
        $this->addMetaData('packages' , array());
        $this->addMetaData('annotationOrderMap', new AnnotationOrderMap());
    }

    public function getDirName()
    {
        return $this->dirname;
    }

    public function getFileName()
    {
        $filename = $this->filename;
        if ( $this->filetype ) {
            $filename .= '.' . $this->filetype;
        }
        return $filename;
    }

    /**
     * Get the full path to the file this File object represents
     *
     * @return string
     */
    public function getFullPath()
    {
        $filename = $this->getDirName() . '/' . $this->getFileName();
        return $filename;
    }

    /**
     * @var FileHandler
     */
    protected $fileHandler;

    private $metaData = array();

    public function getPath()
    {
        return $this->getFullPath();
    }

    public function getStream()
    {
        $source = fopen($this->getFullPath(), 'r');
        $tmp = fopen('php://temp', 'r+');
        stream_copy_to_stream($source, $tmp);
        rewind($tmp);
        return $tmp;
    }

    public function getContents()
    {
        return stream_get_contents($this->getStream());
    }

    public function getMetaData()
    {
        return $this->metaData;
    }

    public function getMetaDataKey($key)
    {
        $metaData = $this->getMetaData();
        return $metaData[$key];
    }

    public function addMetaData($key, $value)
    {
        $this->metaData[$key] = $value;
    }

    public function setContentsFromString(string $newContents)
    {
        throw new \Exception('not implemented');
        // TODO: Implement setContentsFromString() method.
    }

    public function setContentsFromStream(resource $newContents)
    {
        throw new \Exception('not implemented');
        // TODO: Implement setContentsFromStream() method.
    }
}