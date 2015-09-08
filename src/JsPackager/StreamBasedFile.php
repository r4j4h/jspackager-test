<?php

namespace JsPackager;
use JsPackager\Helpers\FileHandler;

/**
 * Class StreamBasedFile exists for keeping files in memory or in temporary files using temp:// protocol while still
 * providing easy access to a file on the file system or the direct contents as needed.
 *
 * @package JsPackager
 */
class StreamBasedFile implements DependencyFileInterface
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
     * @param string $filePath
     * @param array $metaData
     * @param string $tmpDir
     * @throws \Exception
     */
    public function __construct($filePath, $metaData, $tmpDir, $fileHandler, $silenceMissingFileException = false) {

        $this->fileHandler = ( $fileHandler ? $fileHandler : new FileHandler() );

        $this->validFile = ( $this->fileHandler->is_file( $filePath ) !== false );

        if ( !$this->validFile && $silenceMissingFileException === false )
        {
            throw new Exception\MissingFile($filePath . ' is not a valid file!', 0, null, $filePath);
        }

        $filePathParts = pathinfo( $filePath );

        $this->filename = $filePathParts['filename'];
        $this->filetype = ( isset( $filePathParts['extension'] ) ? $filePathParts['extension'] : '' );
        $this->path     = $filePathParts['dirname'];

        if ( $this->validFile ) {
            $stream = fopen($filePath, 'r');

            $this->stream = $stream;
        }
        $this->filePath = $filePath;
        $this->metaData = $metaData;
        $this->temporaryDirectory = $tmpDir;

        $this->addMetaData('originalFile', $filePath);
    }

    public function __destruct() {
        if ($this->validFile) {
            fclose($this->stream);
        }
    }
    /*
     * i am trying to foigure out where whythese streams go why did i spend my entire evening converting all this stuff over o tuse its?
     * it is making things cleaner by revealing hard dependencies on file's public properties in weird places in the move to metadata
     * it is enabling easier transition to other things, although at the cost of two things tring to use the same annotation for different things
     * the flow seems more lost, it's not as obvious and as syntax hard for scripts/stylesheets/packages especially
     *
     * Thes things do see m tob e working and hold some promise though so what is needed to take them further now
     * is to see why dependencies aren't being parsed prperly as contents do seem tobe generated in the temp files.
     *
     * I want to understand the relation between temp files and the source file better, when and what and how it changes
     * from source file into a temp file - and when a temp file changes to what, etc.
     *
     * we need to keep the path loading and splitting properties of the original File for efficient file type resolution
     * - [ ] How will inline scripts be handled then?they won't have a path to be broken up
     *
     * we need to be able to alter the path for @remote and back/forth.
     *  - we could use path or originalPath properties
     *  - bu t that adds confusion around getPath
     *  - maybe the files themselves need to be immutable and clone themsleves when returning altered states?
    */

    private function alterStreamToNewFile()
    {
        // filePath needs tobe co
        $tmpPath = tempnam($this->temporaryDirectory, "streamBasedFile");
        $tmpStream = fopen($tmpPath, 'w+');
        rewind($this->stream);
        stream_copy_to_stream($this->stream, $tmpFile);


    }

    public function getDirName()
    {
        return $this->path;
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
     * Generate a temporary file and place the contents there, returning the file path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getFullPath();
        $metaData = $this->getMetaData();
        return $metaData['originalFile'];
//        $tmpPath = tempnam($this->temporaryDirectory, "streamBasedFile");
//        $tmpFile = fopen($tmpPath, 'w+');
//        rewind($this->stream);
//        stream_copy_to_stream($this->stream, $tmpFile);
//        fclose($tmpFile);
//        rewind($this->stream);
//        return $tmpPath;
    }

    public function getStream()
    {
        if ( !$this->validFile) {
            throw new \Exception('Invalid file');
        }

        return $this->stream;
    }

    public function getContents()
    {
        if ( !$this->validFile) {
            throw new \Exception('Invalid file');
        }

        rewind($this->stream);
        $contents = stream_get_contents($this->stream);
        rewind($this->stream);
        return $contents;
    }

    public function getMetaData()
    {
        return $this->metaData;
    }

    public function addMetaData($key, $value)
    {
        $this->metaData[$key] = $value;
    }
    public function getMetaDataKey($key)
    {
        $metaData = $this->getMetaData();
        return $metaData[$key];
    }



    /**
     * @param string $newContents
     */
    public function setContentsFromString(string $newContents)
    {
        rewind($this->stream);
        $newMetaData['originalFilePath'] = $this->path;

        fwrite($this->stream, $newContents);
        rewind($this->stream);
    }

    /**
     * @param resource $newContents
     */
    public function setContentsFromStream(resource $newContents)
    {
        rewind($this->stream);
        stream_copy_to_stream($newContents, $this->stream);
    }

}