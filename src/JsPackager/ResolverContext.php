<?php

namespace JsPackager;

class ResolverContext
{
    /**
     * @var string
     */
    public $testsSourcePath;

    public $remoteFolderPath;

    public $remoteSymbol;

    public $mutingMissingFileExceptions; // should these be string key/value pairs so it's more flexible?

    public $recursionCb;
}
