<?php

namespace JsPackager;

use JsPackager\Outputter\SimpleOutputterInterface;
use JsPackager\Outputter\SimpleOutputterParams;
use JsPackager\Processor\SimpleProcessorInterface;
use JsPackager\Processor\SimpleProcessorParams;
use JsPackager\Resolver\FileResolverInterface;

class Pipeline
{
    /**
     * @var FileResolverInterface
     */
    private $resolver;

    /**
     * @var SimpleProcessorInterface
     */
    private $processor;

    /**
     * @var SimpleOutputterInterface
     */
    private $outputter;

    /**
     * @param FileResolverInterface $resolver
     * @param SimpleProcessorInterface $processor
     * @param SimpleOutputterInterface $outputter
     */
    public function __construct(FileResolverInterface $resolver, SimpleProcessorInterface $processor, SimpleOutputterInterface $outputter)
    {
        $this->resolver = $resolver;
        $this->processor = $processor;
        $this->outputter = $outputter;
    }

    /**
     * @param DependencyFileInterface $file
     * @return Outputter\SimpleOutputterResult
     */
    public function process(DependencyFileInterface $file)
    {
        $context = new AnnotationBasedResolverContext();
        $resolverContext->remoteFolderPath = $this->remoteFolderPath;
        $resolverContext->remoteSymbol = $this->remoteSymbol;
        $resolved = $this->resolver->resolveDependenciesForFile($file, $context);

        $params = new SimpleProcessorParams( $resolved->dependencies );
        $processed = $this->processor->process($params);
        // todo figuring this out
        $outputterParams = new SimpleOutputterParams($processed);
        $outputterResults = $this->outputter->output($outputterParams);
        return $outputterResults;
    }
}