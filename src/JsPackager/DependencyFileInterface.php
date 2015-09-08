<?php

namespace JsPackager;

interface DependencyFileInterface
{
    public function getPath();
    public function getDirName();
    public function getFileName();
    public function getStream();
    public function getContents();
    public function getMetaData();
    public function getMetaDataKey($key);
    public function addMetaData($key, $value);
}