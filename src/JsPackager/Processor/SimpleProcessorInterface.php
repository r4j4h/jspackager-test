<?php

namespace JsPackager\Processor;

interface SimpleProcessorInterface
{

    /**
     * @param SimpleProcessorParams $orderedFilePaths
     * @return ProcessingResult
     */
    public function process(SimpleProcessorParams $orderedFilePaths);

}