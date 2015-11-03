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

use JsPackager\Compiler\CompiledFile;
use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\FileCompilationResult;
use JsPackager\Exception\CannotWrite as CannotWriteException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Processor\ClosureCompilerProcessor;
use JsPackager\Processor\ProcessingResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileObject;

class Compiler
{

    /**
     * @var LoggerInterface
     */
    public $logger;

    /** @var bool */
    private $verboseWarnings = true;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->rollingPathsMarkedNoCompile = array();

    }

    /**
     * @param bool $enabled
     */
    public function setDisplayIndividualWarnings($enabled)
    {
        if ( ! is_bool( $enabled ) ) {
            throw new \InvalidArgumentException('Must be given a boolean value');
        }
        $this->verboseWarnings = $enabled;
    }

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

    public function expandOutRemoteAnnotation($string) {
        return str_replace( $this->remoteSymbol, $this->remoteFolderPath, $string );
    }

    public function stringContainsRemoteAnnotation($string) {
        return ( strpos($string, $this->remoteSymbol) !== FALSE );
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
        $compiledFileContents = '';
        $compiledFileManifest = '';

        $this->logger->debug("compileDependencySet called. Building manifest...");

        $totalDependencies = count( $dependencySet->dependencies );
        $lastDependency = $dependencySet->dependencies[ $totalDependencies - 1 ];
        $lastDependencyIsRemote = $this->stringContainsRemoteAnnotation( $lastDependency );

        // Expand out any @remote annotations
        foreach( $dependencySet->dependencies as $idx => $dependency ) {
            $dependencySet->dependencies[$idx] = $this->expandOutRemoteAnnotation( $dependency );
        }

        $lastDependency = $dependencySet->dependencies[ $totalDependencies - 1 ];


        $rootFile = new File($lastDependency);

        $rootFilePath = $rootFile->path;
        $rootFilename = $rootFile->filename . '.' . $rootFile->filetype;
        $compiledFilename = $this->getCompiledFilename( $rootFilename );
        $manifestFilename = $this->getManifestFilename( $rootFilename );


        $this->rollingPathsMarkedNoCompile = array_merge(
            $this->rollingPathsMarkedNoCompile,
            $dependencySet->pathsMarkedNoCompile
        );

        $this->logger->debug("Assembling manifest for root file '{$rootFilename}'...");

        $manifestContentsGenerator = new ManifestContentsGenerator();
        $manifestContentsGenerator->logger = $this->logger;

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
                "Skipping building manifest '{$manifestFilename}' for because file has no other dependencies than itself."
            );
            $compiledFileManifest = null;
        }

        $this->logger->debug("Built manifest.");


        if ( in_array( $rootFilePath .'/'. $rootFilename, $dependencySet->pathsMarkedNoCompile ) ) {
            $this->logger->debug("File marked as do not compile. Skipping compiling with Google Closure Compiler .jar by pretending it succeeded with no output...");
            $compilationResults = new ProcessingResult(false, 1);

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




    /**
     * Take an array of file paths and concatenate them into one blob
     *
     * @param array $filePathList Array of file paths
     * @return string Concatenated file's contents
     * @throws Exception\MissingFile If a file in the list does not exist
     * @throws Exception\Parsing If file was unable to be parsed
     */
    protected function concatenateFiles($filePathList)
    {
        $output = '';

        $this->logger->debug("Concatenating files...");

        foreach( $filePathList as $thisFilePath )
        {
            if ( is_file( $thisFilePath ) === false )
            {
                throw new MissingFileException($thisFilePath . ' is not a valid file!', 0, null, $thisFilePath);
            }

            $thisFileContents = file_get_contents( $thisFilePath );

            if ( $thisFileContents === FALSE )
            {
                throw new ParsingException($thisFilePath . ' was unable to be parsed. Perhaps permissions problem?');
            }

            $output .= $thisFileContents;
        }

        $this->logger->debug("Concatenated files.");

        return $output;
    }


    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    public function getSourceFilenameFromCompiledFilename($filename)
    {
        return preg_replace('/.' . Constants::COMPILED_SUFFIX . '.js$/', '.js', $filename);
    }

    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    public function getCompiledFilename($filename)
    {
        return preg_replace('/.js$/', '.' . Constants::COMPILED_SUFFIX . '.js', $filename);
    }


    /**
     * Convert a given filename to its manifest equivalent
     *
     * @param string $filename
     * @return string
     */
    public function getSourceFilenameFromManifestFilename($filename)
    {
        return preg_replace('/.js.' . Constants::MANIFEST_SUFFIX . '$/', '.js', $filename);
    }

    /**
     * Convert a given filename to its manifest equivalent
     *
     * @param $filename
     * @return string
     */
    public function getManifestFilename($filename)
    {
        return preg_replace('/.js$/', '.js.' . Constants::MANIFEST_SUFFIX, $filename);
    }

    /**
     * Compile a given file and any of its dependencies in place.
     *
     * @param string $inputFilename Path to the file to be compiled.
     * @param callable $statusCallback Optional. Callback to update User Interface through progress.
     * @return FileCompilationResult[]
     * @throws Exception\CannotWrite
     */
    public function compileAndWriteFilesAndManifests($inputFilename, $statusCallback = false)
    {
        $compiledFiles = array();
        $dependencyTree = new DependencyTree( $inputFilename, null, false, $this->logger, $this->remoteFolderPath );
        $dependencyTree->logger = $this->logger;
        $dependencySets = $dependencyTree->getDependencySets();

        $this->rollingPathsMarkedNoCompile = array();

        foreach( $dependencySets as $dependencySetIndex => $dependencySet )
        {
            try {
                $result = $this->compileDependencySet( $dependencySet, $dependencySetIndex, $dependencySets );
                $this->logger->notice('Successfully compiled Dependency Set: ' . $result->filename);
                if ( is_callable( $statusCallback ) ) {
                    call_user_func( $statusCallback, 'Successfully compiled Dependency Set: ' . $result->filename, 'success' );
                }

                if ( $this->verboseWarnings === true && strlen( $result->warnings ) > 0 ) {
                    $this->logger->warning('Warnings: ' . PHP_EOL . $result->warnings);
                    if ( is_callable( $statusCallback ) ) {
                        call_user_func( $statusCallback, 'Warnings: ' . PHP_EOL . $result->warnings, 'warning' );
                    }
                }

                $fileCompilationResult = new FileCompilationResult();

                if ( is_null( $result->contents ) ) {

                    $this->logger->info("No compiled file contents, so not writing compiled file to '" . $result->filename . "'.");
                    $fileCompilationResult->setCompiledPath( 'not_compiled' );

                } else {

                    // Write compiled file
                    $outputFilename = $result->path . '/' . $result->filename;
                    $this->logger->info("Writing compiled file to '" . $result->filename . "'.");
                    $outputFile = file_put_contents( $outputFilename, $result->contents );
                    if ( $outputFile === FALSE )
                    {
                        $this->logger->emergency("Cannot write compiled file to {$outputFilename}");
                        throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                    }
                    $this->logger->info("Wrote compiled file to '" . $result->filename . "'.");
                    $fileCompilationResult->setCompiledPath( $result->filename );

                }


                if ( is_null( $result->manifestContents ) ) {

                    $this->logger->info("No manifest file contents, so not writing manifest file to '" . $result->manifestFilename . "'.");
                    $fileCompilationResult->setManifestPath( 'not_compiled' );

                } else {

                    // Write manifest
                    $outputFilename = $result->path . '/' .$result->manifestFilename;
                    $this->logger->info("Writing compiled file manifest to '" . $result->manifestFilename . "'.");
                    $outputFile = file_put_contents( $outputFilename, $result->manifestContents );
                    if ( $outputFile === FALSE )
                    {
                        $this->logger->critical("Cannot write manifest to {$outputFilename}");
                        throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                    }
                    $this->logger->info("Wrote compiled file manifest to '" . $result->manifestFilename . "'.");

                    $fileCompilationResult->setManifestPath( $result->manifestFilename );

                }

                $fileCompilationResult->setSourcePath( $result->path );

                $compiledFiles[] = $fileCompilationResult;
            }
            catch ( ParsingException $e )
            {
                $this->logger->error($e->getMessage() . $e->getErrors());
                if ( is_callable( $statusCallback ) ) {
                    call_user_func(
                        $statusCallback,
                        'VVV ' . $e->getMessage() . ' VVV' . PHP_EOL .
                        $e->getErrors() . PHP_EOL .
                        '^^^ ' . $e->getMessage() . ' ^^^',
                        'error' );

                    throw $e;
                }
                else {
                    throw $e;
                }

            }
        }

        $this->logger->info("Finishing compiling and writing manifests.");

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
        $packageCount = 0;
        $fileCount = 0;
        $success = true;

        $sourceFileCount = 0;
        $sourceFiles = array();

        foreach ($iterator as $file) {
            if ( $file->isFile() && preg_match( '/.js$/', $file->getFilename() ) && !preg_match( '/.compiled.js$/', $file->getFilename() ) ) {

                $this->logger->notice("[Clearing] Detected package {$file->getFilename()}");
                $sourceFileCount++;

                $compiledFilename = $this->getCompiledFilename( $file->getRealPath() );
                $manifestFilename = $this->getManifestFilename( $file->getRealPath() );

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

    public function parseFolderForSourceFiles($folderPath)
    {
        /** @var $file SplFileObject */

        $dirIterator = new RecursiveDirectoryIterator( $folderPath );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $fileCount = 0;
        $files = array();

        $this->logger->debug("Parsing folder '".$folderPath."' for source files...");
        foreach ($iterator as $file) {
            if ( $file->isFile() && preg_match( '/.js$/', $file->getFilename() ) && !preg_match( '/.compiled.js$/', $file->getFilename() ) ) {
                $files[] = $file->getRealPath();
                $fileCount++;
            }
        }
        $this->logger->debug("Finished parsing folder. Found ".$fileCount." source files.");

        return $files;
    }


}
