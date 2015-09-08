<?php
/**
 * The Compiler class takes an ordered array of files and dependent packages and compiles and minifies them
 * using the Google Closure Compiler and generates an accompanying manifest file.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2013 WebPT, Inc.
 */

namespace JsPackager;

use JsPackager\Annotations\AnnotationHandlers\IsMarkedNoCompiledHandler;
use JsPackager\Annotations\AnnotationHandlers\RequireRemote;
use JsPackager\Annotations\AnnotationHandlers\RequireRemoteStyleAnnotationHandler;
use JsPackager\Annotations\AnnotationHandlers\RootAnnotationHandler;
use JsPackager\Annotations\AnnotationParser;
use JsPackager\Annotations\FileToDependencySetsService;
use JsPackager\Annotations\RemoteAnnotationStringService;
use JsPackager\CompiledFileAndManifest\FilenameConverter;
use JsPackager\Compiler\CompiledFile;
use JsPackager\Compiler\CompilerInterface;
use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\FileCompilationResult;
use JsPackager\Compiler\FileCompilationResultCollection;
use JsPackager\Exception\CannotWrite as CannotWriteException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Helpers\FileFinder;
use JsPackager\Helpers\FileHandler;
use JsPackager\Helpers\FileTypeRecognizer;
use JsPackager\Processor\ClosureCompilerProcessor;
use JsPackager\Processor\CompiledAndManifestProcessor;
use JsPackager\Processor\ProcessingResult;
use JsPackager\Processor\SimpleProcessorInterface;
use JsPackager\Processor\SimpleProcessorParams;
use JsPackager\Resolver\DependencyTree;
use JsPackager\Resolver\DependencyTreeParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileObject;
use Streamer\Stream;

class Compiler implements CompilerInterface
{

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * Optional callback to update User Interface through progress.
     *
     * @var callable|bool
     */
    public $statusCallback;

    /**
     * An aggregated array of paths used to skip compilation.
     *
     * @var array
     */
    private $rollingPathsMarkedNoCompile;

    /**
     * Path to replace $remoteSymbol symbols with.
     *
     * @var string
     */
    public $remoteFolderPath;

    /**
     * @var string
     */
    public $remoteSymbol;


    /**
     * @param string $remoteFolderPath
     * @param string $remoteSymbol
     * @param LoggerInterface $logger
     * @param bool|false [$statusCallback] Optional callback to update User Interface through progress. Default: false.
     */
    public function __construct($remoteFolderPath = 'shared', $remoteSymbol = '@remote', LoggerInterface $logger, $statusCallback = false, FileHandler $fileHandler)
    {
        $this->remoteFolderPath = $remoteFolderPath;
        $this->remoteSymbol = $remoteSymbol;
        $this->logger = $logger;
        $this->statusCallback = $statusCallback;
        $this->rollingPathsMarkedNoCompile = array();
        $this->fileHandler = $fileHandler;

        $this->remoteAnnotationStringTransformationService = new RemoteAnnotationStringService(
            $this->remoteSymbol,
            $this->remoteFolderPath
        );

        $this->filenameConverter = new FilenameConverter('compiled', 'manifest');


    }


    /**
     * @param String $filePath
     * @return FileCompilationResultCollection
     * @throws CannotWriteException
     * @throws ParsingException
     * @throws \Exception
     */
    public function compile( $filePath )
    {
        return $this->compileAndWriteFilesAndManifests( $filePath );
    }

    /**
     * Take an array of files in dependency order and compile them, generating a manifest.
     *
     * @param DependencySet $dependencySet Array containing keys 'stylesheets', 'packages', 'dependencies', and 'pathsMarkedNoCompile'
     * @param null|DependencyTree $dependencyTree
     * @return CompiledFile containing the contents of the resulting compiled file and its manifest.
     * @throws ParsingException
     * @throws \Exception
     */
    public function compileDependencySet($dependencySet, $dependencySetIndex = false, $dependencySets = array())
    {
        $this->logger->debug("compileDependencySet called. Building manifest...");

        $totalDependencies = count( $dependencySet->dependencies );

        // Expand out any @remote annotations
        foreach( $dependencySet->dependencies as $idx => $dependency ) {
            $dependencySet->dependencies[$idx] = $this->remoteAnnotationStringTransformationService->
                                                                            expandOutRemoteAnnotation( $dependency );
        }

        $lastDependency = $dependencySet->dependencies[ $totalDependencies - 1 ];


        $rootFile = new File($lastDependency);

        $rootFilePath = $rootFile->getDirName();
        $rootFilename = $rootFile->getFileName();

        $compiledFilename = $this->filenameConverter->getCompiledFilename( $rootFilename );
        $manifestFilename = $this->filenameConverter->getManifestFilename( $rootFilename );


        $this->rollingPathsMarkedNoCompile = array_merge(
            $this->rollingPathsMarkedNoCompile,
            $dependencySet->pathsMarkedNoCompile
        );


        //$resolverPipeline

        $closureCompilerProcessor = new ClosureCompilerProcessor($this->logger, '');
        $manifestContentsGenerator = new ManifestContentsGenerator(
            $this->remoteSymbol, $this->remoteFolderPath, $this->logger
        );
        $compiledAndManifestProcessor = new CompiledAndManifestProcessor(
            // Compile & Concatenate via Google closure Compiler Jar
            $closureCompilerProcessor,
            $manifestContentsGenerator,
            $this->logger
        );

        /*


        */

        // todo this should be CompiledAndManifestFileProcessor and
        // todo this should be added to the list before or after gcc
        $compiledFileManifestContents = $this->assembleManifest(
            $dependencySet, $dependencySetIndex, $dependencySets,
            $rootFilename, $totalDependencies, $rootFilePath,
            $manifestFilename, $this->rollingPathsMarkedNoCompile, $manifestContentsGenerator);

        // I have to figure out how to not only move the above into closureCompilerProcessor but also remove the below without removing the
        // no process at all, except manifests should still be created for those files? or maybe they shouldn't be!
        // or it should be configurable? maybe that logic should live within the closureCompilerProcessor itself?

// Processor Compiler Compliance Classes
// Level 3 - Supports all "Defined MetaData Keys" as of tag x.x.
// Level 2 - Supports all required "Defined MetaData Keys" as of tag x.x.
// Level 1 - Supports at least one "Defined MetaData Keys" as of tag x.x.
// Level 0 - Does not support any "Defined MetaData Keys" as of tag x.x.

        // todo is this marked no process at all, or do we have ability to skip certain ones?
        // todo When separated this is will be handled by a metadata annotated 'skipCompile' which our processors
// will respond to.
        if ( in_array( $rootFilePath .'/'. $rootFilename, $dependencySet->pathsMarkedNoCompile ) ) {
            $this->logger->debug("File marked as do not compile or process.");
            $this->logger->debug("Skipping by marking as succeeded with no output..."); // why is this necessary?
            $compilationResults = new ProcessingResult(ProcessingResult::FAILED, ProcessingResult::RETURNCODE_FAIL);

        } else {

            $compilationResults = $this->processDependencySetDependencies($closureCompilerProcessor, $dependencySet);

        }

        $compiledFileContents = $compilationResults->output;

        $numberOfErrors = $compilationResults->numberOfErrors;
        if ( $numberOfErrors > 0 ) {
            throw new ParsingException( "{$numberOfErrors} errors occurred while parsing {$rootFilename}.", null, $compilationResults->err );
        }

        $this->logger->notice("Compiled dependency set for '" . $rootFilename . "' consisting of " . $totalDependencies . " dependencies.");

        return new CompiledFile(
            $rootFilePath,
            $compiledFilename,
            $compiledFileContents,
            $manifestFilename,
            $compiledFileManifestContents,
            $compilationResults->err
        );
    }


    /**
     * Assemble and compile a manifest file for a DependencySet
     * @param $dependencySet
     * @param $dependencySetIndex
     * @param $dependencySets
     * @param $rootFilename
     * @param $totalDependencies
     * @param $rootFilePath
     * @param $manifestFilename
     * @return null|string
     */
    protected function assembleManifest($dependencySet, $dependencySetIndex, $dependencySets,
                                        $rootFilename, $totalDependencies, $rootFilePath, $manifestFilename,
                                        $rollingPathsMarkedNoCompile, $generator)
    {
        $this->logger->debug("Assembling manifest for root file '{$rootFilename}'...");

        if ($dependencySetIndex && $dependencySetIndex > 0) {
            $dependencySetIndexIterator = $dependencySetIndex;
            while ($dependencySetIndexIterator > 0) {
                // Roll in dependent package's stylesheets so we only need to read this manifest
                $this->logger->debug(
                    "Using dependency set and it's dependency set's stylesheets so that manifests are all-inclusive"
                );
                $lastDependencySet = $dependencySets[$dependencySetIndexIterator - 1];
                $stylesheets = array_merge($lastDependencySet->stylesheets, $dependencySet->stylesheets);
                $stylesheets = array_unique($stylesheets);
                $dependencySetIndexIterator--;
            }
        } else {
            $this->logger->debug("Using dependency set's stylesheets");
            $stylesheets = $dependencySet->stylesheets;
        }

        if (count($dependencySet->stylesheets) > 0 || $totalDependencies > 1) {
            // Build manifest first
            $compiledFileManifest = $generator->generateManifestFileContents(
                $rootFilePath . '/',
                $dependencySet->packages,
                $stylesheets,
                $rollingPathsMarkedNoCompile
            );
        } else {
            $this->logger->debug(
                "Skipping building manifest '{$manifestFilename}' because file has no other dependencies than itself."
            );
            $compiledFileManifest = null;
        }

        $this->logger->debug("Built manifest.");
        return $compiledFileManifest;
    }

    /**
     * @param SimpleProcessorInterface $processor
     * @param $dependencySet
     * @return ProcessingResult
     * @throws \Exception
     */
    protected function processDependencySetDependencies($processor, $dependencySet)
    {
        $this->logger->debug("Processing...");

        $params = new SimpleProcessorParams( $dependencySet->dependencies );

        $compilationResults = $processor->process($params);

        $this->logger->debug("Finished processing.");

        return $compilationResults;
    }

    private function logNotice($string) {
        $this->logger->notice($string);
        $statusCallback = $this->statusCallback;
        if ( is_callable( $statusCallback ) ) {
            call_user_func( $statusCallback, $string, 'success' );
        }
    }

    private function logProblem($string) {
        $this->logger->warning($string);
        $statusCallback = $this->statusCallback;
        if ( is_callable( $statusCallback ) ) {
            call_user_func( $statusCallback, $string, 'warning' );
        }
    }

    private function logFatal($string) {
        $this->logger->critical($string);
        $statusCallback = $this->statusCallback;
        if ( is_callable( $statusCallback ) ) {
            call_user_func( $statusCallback, $string, 'error' );
        }
    }

    /**
     * Compile a given file and any of its dependencies in place.
     *
     * @param string $inputFilename Path to the file to be compiled.
     * @return FileCompilationResultCollection
     * @throws Exception\CannotWrite
     * @throws ParsingException
     * @throws \Exception
     *
     * todo rework to not write output/manifest but to return their contents in some stream and refs to that and move to CompiledAndManifest Generator/Processor
     * todo rework file outputing stuff into CompiledAndManifest Outputter
     *
     * if we change the code here to return a reference to a stream
     * we can then have a function that takes that stream and outputs it the file
     *
     */
    public function compileAndWriteFilesAndManifests($inputFilename)
    {
        $compiledFiles = new FileCompilationResultCollection();
        $dependencySets = $this->getDependencySetsForFilename($inputFilename);

        $this->rollingPathsMarkedNoCompile = array();

        foreach( $dependencySets as $dependencySetIndex => $dependencySet )
        {
            try {
                // this should be within the processor itself
                $result = $this->compileDependencySet( $dependencySet, $dependencySetIndex, $dependencySets );

                // this should be within the outputters and treated as normal files so FileOutputter
                $this->logNotice('Successfully compiled Dependency Set: ' . $result->filename);

                if ( strlen( $result->warnings ) > 0 ) {
                    $this->logProblem('Warnings: ' . PHP_EOL . $result->warnings);
                }

                if ( is_null( $result->contents ) ) {

                    $this->logNotice("No compiled file contents, so not writing compiled file to '" . $result->filename . "'.");

                    $fileCompilationResultCompiledPath = 'not_compiled';

                } else {
                    $this->logNotice("Writing compiled file to '" . $result->filename . "'.");

                    // Write compiled file
                    $outputFilename = $result->path . '/' . $result->filename;
                    $outputFile = file_put_contents( $outputFilename, $result->contents );
                    if ( $outputFile === FALSE )
                    {
                        $this->logFatal("Cannot write compiled file to {$outputFilename}");
                        throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                    }
                    $fileCompilationResultCompiledPath = $result->filename;

                    $this->logNotice("Wrote compiled file to '" . $result->filename . "'.");
                }


                if ( is_null( $result->manifestContents ) ) {

                    $this->logNotice("No manifest file contents, so not writing manifest file to '" . $result->manifestFilename . "'.");

                    $fileCompilationResultManifestPath = 'not_compiled';

                } else {
                    $this->logNotice("Writing compiled file manifest to '" . $result->manifestFilename . "'.");

                    // Write manifest
                    $outputFilename = $result->path . '/' .$result->manifestFilename;
                    $outputFile = file_put_contents( $outputFilename, $result->manifestContents );
                    if ( $outputFile === FALSE )
                    {
                        $this->logFatal("Cannot write manifest to {$outputFilename}");
                        throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                    }
                    $fileCompilationResultManifestPath = $result->manifestFilename;

                    $this->logNotice("Wrote compiled file manifest to '" . $result->manifestFilename . "'.");

                }

                $compiledFiles->add(new FileCompilationResult(
                    $result->path,
                    $fileCompilationResultCompiledPath,
                    $fileCompilationResultManifestPath
                ));
            }
            catch ( ParsingException $e )
            {
                $this->logFatal("Encountered a ParsingException: " . $e->getMessage() . $e->getErrors());
                throw $e;
            }
        }

        $this->logNotice("Finishing compiling and writing manifests.");

        return $compiledFiles;
    }




    /**
     * @param $inputFilename
     * @return array
     */
    private function getDependencySetsForFilename($inputFilename)
    {
        $resolverContext = new AnnotationBasedResolverContext();
        $resolverContext->remoteFolderPath = $this->remoteFolderPath;
        $resolverContext->remoteSymbol = $this->remoteSymbol;

        $testsSourcePath = null;
        $mutingMissingFileExceptions = false;


        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            $this->remoteFolderPath, $this->remoteSymbol, $mutingMissingFileExceptions, $this->logger
        );
        $requireRemoteHandler = new RequireRemote(
            $this->remoteFolderPath, $this->remoteSymbol, $mutingMissingFileExceptions, $this->logger
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

        $annParser = new AnnotationParser(
            $annotationResponseHandlerMapping,
            $testsSourcePath,
            $this->logger,
            $this->fileHandler
        );
        $treeParser = new DependencyTreeParser(
            $annParser,
            $this->remoteSymbol,
            $this->remoteFolderPath,
            $testsSourcePath,
            $this->logger,
            $mutingMissingFileExceptions,
            $this->fileHandler
        );

        $thisTree = $treeParser->parseFile( $inputFilename, false );

        $service = new FileToDependencySetsService( $this->logger );

        $dependencySets = $service->getDependencySets( $thisTree );

        return $dependencySets;
    }


}
