<?php

namespace JsPackager\Compiler;

use JsPackager\Exception\CannotWrite;
use JsPackager\Exception\Parsing;

interface CompilerInterface {


    /**
     * @param String $filePath
     * @return FileCompilationResultCollection
     * @throws CannotWrite
     * @throws Parsing
     * @throws \Exception
     */
    public function compile( $filePath );

}