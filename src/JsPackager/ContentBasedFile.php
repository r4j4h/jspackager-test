<?php

namespace JsPackager;

/**
 * Class ContentBasedFile exists for inline scripts and script partials for wrapping, etc.
 *
 * If you take a file from the file system and want to transform or modify it, one can write the modified contents
 * to one of these. It can then at any time be converted to the destination file on the file system or a stream for use.
 *
 * @package JsPackager
 */
class ContentBasedFile implements DependencyFileInterface
{
    /**
     * @var string
     */
    private $content;

    /**
     * @var string
     */
    private $temporaryDirectory;

    /**
     * @var array
     */
    private $metaData;

    /**
     * @var string
     */
    private $fileName;

    /**
     * @param string $content
     * @param string $temporaryDirectory
     * @param array $metaData
     */
    public function __construct($content, $temporaryDirectory, $expectedFilename, $metaData) {
        $this->content = $content;
        $this->temporaryDirectory = $temporaryDirectory;
        $this->metaData = $metaData;
        $this->fileName = $expectedFilename;
    }

    public function getPath()
    {
        $tmpPath = tempnam($this->temporaryDirectory, "contentBasedFile");
        file_put_contents($tmpPath, $this->content);
        return $tmpPath;
    }

    public function getStream()
    {
        $tmpStream = fopen('php://temp', 'w+');
        fwrite($tmpStream, $this->content);
        rewind($tmpStream);
        return $tmpStream;
    }

    public function getContents()
    {
        return $this->content;
    }

    public function getMetaData()
    {
        return $this->metaData;
    }

    public function addMetaData($key, $value)
    {
        $this->metaData[$key] = $value;
    }

    public function setContentsFromString(string $newContents)
    {
        $this->content = $newContents;
    }

    public function setContentsFromStream(resource $newContents)
    {
        rewind($newContents);
        $this->content = stream_get_contents($newContents);
    }

    public function getMetaDataKey($key)
    {
        if ( $this->hasMetaDataKey( $key ) ) {
            return $this->metaData[$key];
        }
        return null;
    }

    public function hasMetaDataKey($key)
    {
        return isset( $this->metaData[$key] );
    }

    public function getDirName()
    {
        // TODO: Implement getDirName() method.
    }

    public function getFileName()
    {
        return $this->fileName;
    }
}