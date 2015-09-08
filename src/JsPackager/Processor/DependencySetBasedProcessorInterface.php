<?php

namespace JsPackager\Processor;

use JsPackager\Compiler\DependencySetCollection;

interface DependencySetBasedProcessorInterface
{

    /**
     * @param DependencySetCollection $params
     * @return DependencySetCollection
     */
    public function process(DependencySetCollection $dependencySets);

}