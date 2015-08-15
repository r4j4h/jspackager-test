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

/**
 * Class DependencyCollection represents all of the dependencies for a file, including the file.
 * @package JsPackager
 */
class DependencyCollection extends \ArrayObject
{
    protected $sets;

    public function __construct($set) {
        if ( is_array( $set ) && $set[0] instanceof DependencySet ) {
            $this->sets = $set;
        } else if ( $set instanceof DependencySet ) {
            $this->sets = array($set);
        } else {
            throw new \Exception('Must pass DependencySet or DependencySet[]');
        }
    }

    public function getSets() {
        return $this->sets;
    }
}

/**
 * Class DependencySet represents a bundle of dependencies for a file, potentially including the file.
 * @package JsPackager
 */
class DependencySet
{
    protected $files;

    public function __construct($file) {
        if ( is_array( $file ) ) {
            $this->files = $file;
        } else {
            $this->files = array($file);
        }
    }

    public function getFiles() {
        return $this->files;
    }
}

/**
 * Class ConstructionMode aids in constructing a valid DependencyFile.
 * @package JsPackager
 */
class ConstructionMode {}

/**
 * Class PathConstructionMode is for indicating building a DependencyFile referencing a path to an existing file.
 * @package JsPackager
 */
class PathConstructionMode extends ConstructionMode {}

/**
 * Class ContentsConstructionMode is for indicating building a DependencyFile referencing the contents of a potential
 * file.
 * @package JsPackager
 */
class ContentsConstructionMode extends ConstructionMode {}

/**
 * Class UnknownConstructModeException indicates an invalid or unexpected ConstructionMode was used.
 * @package JsPackager
 */
class UnknownConstructModeException extends \Exception {}

/**
 * Class DependencyFile represents a file-based dependency. Given a file or some contents, it can render out
 * a valid filepath and stream out contents. Files should be considered immutable in that given a file `foo`, it will
 * never be modified in place. A `bar` might be created, or `foo.processed` or `dst/foo` but `foo` will always be
 * untouched.
 *
 * Some processes need file paths and cannot accept fragments. Some processes need all things put over stdin and do not
 * understand file references. DependencyFile aids in providing a uniform interface for both kinds of processes.
 *
 * Note for the web fragments are either output as inline scripts or written to a web-accessible file that gets linked.
 * For scripts outside the webroot, a remote root is needed or else they can be streamed into an inline script or
 * web-accessible file as well.
 *
 * @package JsPackager
 */
class DependencyFile
{
    protected $path;
    protected $contents;
    protected $metaData;

    public function __construct($pathOrContents, ConstructionMode $mode, $metaData = null) {

        if ( $mode instanceof PathConstructionMode ) {
            $this->path = $pathOrContents;
        } else if ( $mode instanceof ContentsConstructionMode ) {
            $this->contents = $pathOrContents;
        } else {
            throw new UnknownConstructModeException($mode . ' is an unknown construction mode.', 500);
        }

        if ( $metaData ) {
            $this->metaData = $metaData;
        } else {
            $this->metaData = array();
        }
    }
}

class VFile
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