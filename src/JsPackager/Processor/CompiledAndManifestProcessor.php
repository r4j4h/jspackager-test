<?php

namespace JsPackager\Processor;

use JsPackager\Annotations\FileToDependencySetsService;
use JsPackager\Exception\Parsing;
use JsPackager\ManifestContentsGenerator;
use JsPackager\Processor\ProcessingResult;
use JsPackager\Processor\SimpleProcessorInterface;
use JsPackager\Processor\SimpleProcessorParams;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CompiledAndManifestProcessor implements SimpleProcessorInterface
{
    /**
     * @var SimpleProcessorInterface
     */
    protected $compilerProcessor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ManifestContentsGenerator
     */
    protected $manifestContentsGenerator;

    public function __construct(SimpleProcessorInterface $compilerProcessor, ManifestContentsGenerator $manifestContentsGenerator, LoggerInterface $logger)
    {
        $this->compilerProcessor = $compilerProcessor;
        $this->manifestContentsGenerator = $manifestContentsGenerator;
        $this->logger = $logger;
    }

    /**
     * @param SimpleProcessorParams $params
     * @return ProcessingResult
     * @throws Parsing
     * @throws \Exception
     */
    public function process(SimpleProcessorParams $params)
    {
        $newDepSet = array();
        $tree = $params->dependencySet;

        $service = new FileToDependencySetsService($this->logger);
        $dependencySets = $service->getDependencySets($tree);

        $this->rollingPathsMarkedNoCompile = array();

        foreach( $dependencySets as $dependencySetIndex => $dependencySet )
        {
            try {
                // this should be within the processor itself


                $this->logger->debug("Compiling manifest contents...");
                $manifestResults = $this->manifestContentsGenerator->generateManifestFileContents()>process( $params );

// todo bring in the manifest content generation stuff from Compiler.php into here
                $this->logger->debug("Finished compiling manifest contents.");

                $this->logger->debug("Compiling with Google Closure Compiler .jar...");
                $compilationResults = $this->compilerProcessor->process( $params->dependencySet->dependencies );
                $this->logger->debug("Finished compiling with Google Closure Compiler .jar.");

                $overallSuccess = ( $manifestResults->successful && $compilationResults->successful );



            }
            catch ( ParsingException $e )
            {
                $this->logFatal("Encountered a ParsingException: " . $e->getMessage() . $e->getErrors());
                throw $e;
            }
        }




        return $newDepSet;
    }

}