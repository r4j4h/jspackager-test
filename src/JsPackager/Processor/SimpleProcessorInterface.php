<?php

namespace JsPackager\Processor;

interface SimpleProcessorInterface
{

    /**
     * @param SimpleProcessorParams $params
     * @return ProcessingResult
     */
    public function process(SimpleProcessorParams $params);

}