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
use JsPackager\Helpers\FileTypeRecognizer;
use JsPackager\Processor\ClosureCompilerProcessor;
use JsPackager\Processor\ProcessingResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileObject;

class Compiler implements CompilerInterface
{

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var callable|bool
     */
    public $statusCallback = false; // Optional callback to update User Interface through progress.

    /**
     * An aggregated array of paths used to skip compilation.
     *
     * @var array
     */
    private $rollingPathsMarkedNoCompile;

    /**
     * Path to replace `@remote` symbols with.
     *
     * @var string
     */
    public $remoteFolderPath = 'shared';

    public $remoteSymbol = '@remote';


    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->rollingPathsMarkedNoCompile = array();
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
        return $this->compileAndWriteFilesAndManifests( $filePath, false );
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

        $remoteSymbolStringTransfer = new RemoteAnnotationStringService(
            $this->remoteSymbol,
            $this->remoteFolderPath
        );

        // Expand out any @remote annotations
        foreach( $dependencySet->dependencies as $idx => $dependency ) {
            $dependencySet->dependencies[$idx] = $remoteSymbolStringTransfer->expandOutRemoteAnnotation( $dependency );
        }

        $lastDependency = $dependencySet->dependencies[ $totalDependencies - 1 ];


        $rootFile = new File($lastDependency);

        $rootFilePath = $rootFile->path;
        $rootFilename = $rootFile->filename . '.' . $rootFile->filetype;
        $compiledFilename = FilenameConverter::getCompiledFilename( $rootFilename );
        $manifestFilename = FilenameConverter::getManifestFilename( $rootFilename );


        $this->rollingPathsMarkedNoCompile = array_merge(
            $this->rollingPathsMarkedNoCompile,
            $dependencySet->pathsMarkedNoCompile
        );

        $this->logger->debug("Assembling manifest for root file '{$rootFilename}'...");

        $manifestContentsGenerator = $this->getManifestContentsGenerator();

        if ( $dependencySetIndex && $dependencySetIndex > 0 ) {
            $dependencySetIndexIterator = $dependencySetIndex;
            while ( $dependencySetIndexIterator > 0 ) {
                // Roll in dependent package's stylesheets so we only need to read this manifest
                $this->logger->debug(
                    "Using dependency set and it's dependency set's stylesheets so that manifests are all-inclusive"
                );
                $lastDependencySet = $dependencySets[ $dependencySetIndexIterator - 1 ];
                $stylesheets = array_merge( $lastDependencySet->stylesheets, $dependencySet->stylesheets );
                $stylesheets = array_unique( $stylesheets );
                $dependencySetIndexIterator--;
            }
        } else {
            $this->logger->debug("Using dependency set's stylesheets");
            $stylesheets = $dependencySet->stylesheets;
        }

        if ( count( $dependencySet->stylesheets ) > 0 || $totalDependencies > 1 ) {
            // Build manifest first
            $compiledFileManifest = $manifestContentsGenerator->generateManifestFileContents(
                $rootFilePath . '/',
                $dependencySet->packages,
                $stylesheets,
                $this->rollingPathsMarkedNoCompile
            );
        } else {
            $this->logger->debug(
                "Skipping building manifest '{$manifestFilename}' because file has no other dependencies than itself."
            );
            $compiledFileManifest = null;
        }

        $this->logger->debug("Built manifest.");


        if ( in_array( $rootFilePath .'/'. $rootFilename, $dependencySet->pathsMarkedNoCompile ) ) {
            $this->logger->debug("File marked as do not compile or process.");
            $this->logger->debug("Skipping by marking as succeeded with no output..."); // why is this necessary?
            $compilationResults = new ProcessingResult(ProcessingResult::FAILED, ProcessingResult::RETURNCODE_FAIL);

        } else {

            $compilationResults = $this->processOrderedFilePathsArray($dependencySet);

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
            $compiledFileManifest,
            $compilationResults->err
        );
    }

    /**
     * @param $dependencySet
     * @return ProcessingResult
     * @throws \Exception
     */
    protected function processOrderedFilePathsArray($dependencySet)
    {
        $this->logger->debug("Compiling with Google Closure Compiler .jar...");

        // Compile & Concatenate via Google closure Compiler Jar
        $processor = new ClosureCompilerProcessor();
        $processor->logger = $this->logger;
        $compilationResults = $processor->process($dependencySet->dependencies);

        $this->logger->debug("Finished compiling with Google Closure Compiler .jar.");

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
     */
    public function compileAndWriteFilesAndManifests($inputFilename)
    {
        $compiledFiles = new FileCompilationResultCollection();
        $dependencySets = $this->getDependencySetsForFilename($inputFilename);

        $this->rollingPathsMarkedNoCompile = array();

        foreach( $dependencySets as $dependencySetIndex => $dependencySet )
        {
            try {
                $result = $this->compileDependencySet( $dependencySet, $dependencySetIndex, $dependencySets );
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
     * Delete (unlink) any files with *.compiled.js in the filename across the given path.
     *
     * @param string $directory Directory path.
     * @return bool
     */
    public function clearPackages($directory)
    {
        /** @var $file SplFileObject */

        $dirIterator = new RecursiveDirectoryIterator( $directory );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $fileCount = 0;
        $success = true;

        $sourceFileCount = 0;

        foreach ($iterator as $file) {
            if ( $file->isFile() && preg_match( '/.js$/', $file->getFilename() ) && !preg_match( '/.compiled.js$/', $file->getFilename() ) ) {

                $this->logger->notice("[Clearing] Detected package {$file->getFilename()}");

                $sourceFileCount++;

                $compiledFilename = FilenameConverter::getCompiledFilename( $file->getRealPath() );
                $manifestFilename = FilenameConverter::getManifestFilename( $file->getRealPath() );

                if ( is_file( $compiledFilename ) ) {
                    $this->logger->notice('[Clearing] Removing ' . $compiledFilename);

                    $unlinkSuccess = unlink( $compiledFilename );
                    if ( !$unlinkSuccess )
                    {
                        $this->logger->error('[Clearing] Failed to remove ' . $compiledFilename);

                        $success = false;
                        continue;
                    }
                    $fileCount++;
                }
                if ( is_file( $manifestFilename ) ) {
                    $this->logger->notice('[Clearing] Removing ' . $manifestFilename);

                    $unlinkSuccess = unlink( $manifestFilename );
                    if ( !$unlinkSuccess )
                    {
                        $this->logger->error('[Clearing] Failed to remove ' . $manifestFilename);

                        $success = false;
                        continue;
                    }
                    $fileCount++;
                }

            }
        }

        $this->logger->notice("[Clearing] Detected $sourceFileCount packages, resulting in $fileCount files being removed.");
        return $success;
    }

    /**
     * @param $inputFilename
     * @return array
     */
    private function getDependencySetsForFilename($inputFilename)
    {
        $dependencyTree = new DependencyTree($inputFilename, null, false, $this->logger, $this->remoteFolderPath);
        $dependencyTree->logger = $this->logger;
        $dependencySets = $dependencyTree->getDependencySets();
        return $dependencySets;
    }

    /**
     * @return ManifestContentsGenerator
     */
    private function getManifestContentsGenerator()
    {
        $manifestContentsGenerator = new ManifestContentsGenerator($this->remoteSymbol, $this->remoteFolderPath);
        $manifestContentsGenerator->logger = $this->logger;
        return $manifestContentsGenerator;
    }


}
