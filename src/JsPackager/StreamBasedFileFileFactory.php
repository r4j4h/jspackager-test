<?php

namespace JsPackager;
use JsPackager\Helpers\FileHandler;

/**
 * Class StreamBasedFile exists for keeping files in memory or in temporary files using temp:// protocol while still
 * providing easy access to a file on the file system or the direct contents as needed.
 *
 * @package JsPackager
 */
class StreamBasedFileFileFactory
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
     * @var resource
     */
    private $stream;

    /**
     * @var string
     */
    private $filePath;

    /**
     * @var array
     */
    private $metaData;

    /**
     * @var string
     */
    private $temporaryDirectory;

    /**
     * @var boolean
     */
    private $validFile;

    /**
     * @var boolean
     */
    private $silencingMissingFileException;

    /**
     * @param string $filePath
     * @param array $metaData
     * @param string $tmpDir
     * @throws \Exception
     */
    public function __construct($tmpDir, $fileHandler, $silenceMissingFileException = false) {

        $this->fileHandler = ( $fileHandler ? $fileHandler : new FileHandler() );
        $this->silencingMissingFileException = $silenceMissingFileException;
        $this->temporaryDirectory = $tmpDir;
    }

    /**
     * @param string $filePath
     * @param array $metaData
     * @param string $tmpDir
     * @return StreamBasedFile
     * @throws \Exception
     * @throws Exception\MissingFile
     */
    public function createFile($filePath, $metaData) {

        $this->validFile = ( $this->fileHandler->is_file( $filePath ) !== false );

        if ( !$this->validFile && $this->silencingMissingFileException === false )
        {
            throw new Exception\MissingFile($filePath . ' is not a valid file!', 0, null, $filePath);
        }

        if ( $this->validFile ) {
            $stream = fopen($filePath, 'r');
            $this->stream = $stream;
        }

        $file = new StreamBasedFile(
            $filePath,
            array(),
            $this->temporaryDirectory,
            $this->fileHandler,
            $this->silencingMissingFileException
        );

        $file->addMetaData('originalFile', $filePath);

        return $file;
    }

}