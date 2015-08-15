<?php

namespace JsPackager\CompiledFileAndManifest;

use JsPackager\Processor\ProcessingResult;
use JsPackager\Processor\SimpleProcessorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CompiledAndManifestProcessor implements SimpleProcessorInterface
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    public function __construct($logger = null) {
        if ( $logger instanceof LoggerInterface ) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }
    }

    /**
     * @param array $orderedFilePaths
     * @return ProcessingResult
     */
    public function process(Array $orderedFilePaths)
    {
        // TODO: Implement process() method.
    }
}