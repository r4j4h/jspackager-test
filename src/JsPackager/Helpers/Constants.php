<?php
namespace JsPackager\Helpers;

use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Constants
{
    const COMPILED_SUFFIX = 'compiled';
    const MANIFEST_SUFFIX = 'manifest';


}