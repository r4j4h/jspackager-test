<?php

namespace JsPackager\Resolver;

use JsPackager\Annotations\AnnotationHandlerParameters;
use JsPackager\Annotations\AnnotationHandlers\IsMarkedNoCompiledHandler;
use JsPackager\Annotations\AnnotationHandlers\RequireRemote;
use JsPackager\Annotations\AnnotationHandlers\RequireRemoteStyleAnnotationHandler;
use JsPackager\Annotations\AnnotationHandlers\RootAnnotationHandler;
use JsPackager\Annotations\AnnotationParser;
use JsPackager\DependencyFileInterface;
use JsPackager\AnnotationBasedResolverContext;
use JsPackager\Helpers\FileHandler;
use Psr\Log\LoggerInterface;

class AnnotationBasedFileResolver implements FileResolverInterface {

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DependencyTreeParser
     */
    private $dependencyParser;

    public function __construct($remoteFolderPath, $remoteSymbol, $testsSourcePath = null,
                                $mutingMissingFileExceptions, LoggerInterface $logger, FileHandler $fileHandler)
    {
        $this->logger = $logger;

        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            $remoteFolderPath, $remoteSymbol, $mutingMissingFileExceptions, $logger
        );
        $requireRemoteHandler = new RequireRemote(
            $remoteFolderPath, $remoteSymbol, $mutingMissingFileExceptions, $logger
        );

        $annotationResponseHandlerMapping = array(
            'requireRemote'     => array($requireRemoteHandler, 'doAnnotation_requireRemote' ),
            'require'           => array($requireRemoteHandler, 'doAnnotation_require' ),
            'requireRemoteStyle'=> array($requireRemoteStyleHandler, 'doAnnotation_requireRemoteStyle' ),
            'requireStyle'      => array($requireRemoteHandler, 'doAnnotation_requireStyle' ),
            'tests'             => array($requireRemoteHandler, 'doAnnotation_tests' ),
            'testsRemote'       => array($requireRemoteHandler, 'doAnnotation_testsRemote' ),
            'root'              => array($rootHandler, 'doAnnotation_root' ),
            'nocompile'         => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $parser = new AnnotationParser(
            $annotationResponseHandlerMapping,
            $testsSourcePath,
            $logger,
            $fileHandler
        );

        $this->dependencyParser = new DependencyTreeParser(
            $parser,
            $remoteSymbol,
            $remoteFolderPath,
            $testsSourcePath,
            $logger,
            $mutingMissingFileExceptions,
            $fileHandler
        );

    }

    /**
     * @param DependencyFileInterface $file
     * @return DependencyFileInterface
     */
    public function resolveDependenciesForFile(DependencyFileInterface $file, AnnotationBasedResolverContext $context)
    {
        return $this->dependencyParser->parseFile($file->getPath(), false);
    }
}