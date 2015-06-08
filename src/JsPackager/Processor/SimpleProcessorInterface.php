<?php

namespace JsPackager\Processor;

interface SimpleProcessorInterface
{

    /**
     * @param array $orderedFilePaths
     * @return ProcessingResult
     */
    public function process(Array $orderedFilePaths);

}