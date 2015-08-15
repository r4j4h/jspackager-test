<?php

namespace JsPackager\CompiledFileAndManifest;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CompiledAndManifestOutputter
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

}