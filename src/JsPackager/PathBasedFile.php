<?php

namespace JsPackager;

/**
 * Class PathBasedFile exists to represent files that exist on the file system. Generally when modified the result
 * is a new object, like a ContentBasedFile containing the modifications in an attempt at immutability.
 *
 * @package JsPackager
 */
class PathBasedFile implements DependencyFileInterface
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var array
     */
    private $metaData;

    /**
     * @param $path
     * @param $metaData
     */
    public function __construct($path, $metaData) {
        $this->path = $path;
        $this->metaData = $metaData;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getStream($mode = 'w+')
    {
        return fopen($this->path, $mode);
    }

    public function getContents()
    {
        return file_get_contents($this->path);
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
        return $this->metaData[$key];
    }

    public function getDirName()
    {
        // TODO: Implement getDirName() method.
    }

    public function getFileName()
    {
        // TODO: Implement getFileName() method.
    }
}
