<?php
/**
 * Dependency Tree Parser is used to parse through front end files linked together via annotations. Generally
 * this operates on javascript, but it may also include stylesheets and other types of resources.
 *
 * It's primary purpose is for automatic file inclusion for browsers, for collating source files for JS unit tests,
 * and for compiling JS files.
 *
 * Annotation Legend:
 *  @require relativepath/to.js
 *      Parses the given file for dependencies and includes all of them
 *  @requireStyle ../../css/relative/path/to.css
 *      Includes the given stylesheet on the page when this script is also included.
 *  @root
 *      If present, this file will not be merged in if another file @require's it. Instead
 *      this file and its dependencies will be pulled into the page, or when compiling this
 *      file and its dependencies will get compiled and that compiled file included on the page.
 * @tests
 *      This annotation is equal to a @require annotation, but handles the alternate base path.
 *      That occurs because test scripts are located elsewhere.
 *      Without this, @require lines inside spec files would have a mess of ../../'s that would
 *      increase the difficulty of refactoring.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager;

use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Helpers\ArrayTraversalService;
use JsPackager\Resolver\AnnotationBasedFileResolver;
use JsPackager\Resolver\FileResolverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class DependencyTreeParser
{

    /**
     * @var string
     */
    public $remoteFolderPath = 'shared';

    /**
     * @var string
     */
    public $remoteSymbol = '@remote';

    /**
     * Definition list of file types that are scanned for annotation tokens
     * @var array
     */
    public $filetypesAllowingAnnotations = array(
        'js',
    );

    /**
     * Filename -> File object map
     *
     * Associative array
     *
     * @var array
     */
    private $parsedFiles = array();


    /**
     * Filename array used in recursion detection.
     *
     * @var array
     */
    private $seenFiles = array();

    /**
     * Flag to disable missing file exceptions. Set and unset via muteMissingFileExceptions and
     * unMuteMissingFileExceptions.
     *
     * @var bool
     */
    private $mutingMissingFileExceptions = false;


    /**
     * @var LoggerInterface
     */
    public $logger;


    /**
     * @var FileResolverInterface
     */
    private $resolver;

    /**
     * @var PathFinder
     */
    private $pathFinder;

    /**
     * @return mixed
     */
    public function createDefaultResolver()
    {
        $resolver = new AnnotationBasedFileResolver($this->logger);
        $resolver->setFileHandler( $this->getFileHandler() );
        $this->setResolver( $resolver );
        return $resolver;
    }

    /**
     * @return mixed
     */
    public function getResolver()
    {
        return ( $this->resolver ? $this->resolver : $this->createDefaultResolver() );
    }

    /**
     * @param mixed $resolver
     */
    public function setResolver($resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @var ArrayTraversalService
     */
    private $arrayTraversalService;

    /**
     * @param string $remoteSymbol
     * @param string $remoteFolderPath
     * @param LoggerInterface [$logger]
     */
    public function __construct($remoteSymbol = '@remote', $remoteFolderPath = 'shared', $logger = null)
    {
        if ( $logger ) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }
        $this->remoteSymbol = $remoteSymbol;
        $this->remoteFolderPath = $remoteFolderPath;


        $this->arrayTraversalService = new ArrayTraversalService();
        $this->pathFinder = new PathFinder();
    }


    /**
     * Prevent missing file exceptions from being thrown.
     */
    public function muteMissingFileExceptions() {
        $this->mutingMissingFileExceptions = true;

    }

    /**
     * Allow missing file exceptions from being thrown after having been muted.
     */
    public function unMuteMissingFileExceptions() {
        $this->mutingMissingFileExceptions = false;

    }


    /**
     * Get the base path to a file.
     *
     * Give it something like '/my/cool/file.jpg' and get '/my/cool/' back.
     * @param $filePath
     * @return string
     */
    protected function getBasePathFromFilePathWithoutTrailingSlash($filePath) {
        return ltrim( ( substr( $filePath, 0, strrpos($filePath, '/' ) ) ), '/' );
    }


    /**
     * Get the base path to a file.
     *
     * Give it something like '/my/cool/file.jpg' and get '/my/cool/' back.
     * @param $sourceFilePath
     * @return string
     */
    protected function getBasePathFromSourceFileWithTrailingSlash($sourceFilePath) {
        return ltrim( ( substr( $sourceFilePath, 0, strrpos($sourceFilePath, '/' )+1 ) ), '/' );
    }


    /**
     * Flag used to indicate when we are recursing into a remote file so that we can catch "locally" required files
     * inside the remotely required files which are in effect remote.
     *
     * @var bool
     */
    public $currentlyRecursingInRemoteFile = false;

    /**
     * Temporary store of paths used when recursing into remote files for rebuilding the relative path from
     * any "locally" required files that are in actuality relative to the remote file.
     *
     * @var array
     */
    public $recursedPath = array();

    /**
     * Counter for tracking recursion depth.
     *
     * @var int
     */
    public $recursionDepth = 0;


    /**
     * Converts a file via path into a JsPackager\File object and
     * parses it for dependencies, caching it for re-use if called again
     * with same file.
     *
     * @param string $filePath File's relative path from public folder
     * @param string $testsSourcePath Optional. For @tests annotations, the source scripts root path with no trailing
     * slash. Default: given file's folder.
     * @param bool $recursing Internal recursion flag. Always call with false.
     * @return File
     * @throws Exception\Recursion If the dependent files have a circular dependency
     * @throws Exception\MissingFile Through internal File object if $filePath does not point to a valid file
     */
    public function parseFile( $filePath, $testsSourcePath = null, $recursing = false )
    {
        $resolver = $this->getResolver();

        $this->logger->info("Parsing file '" . $filePath . "'.");

        // If we're starting off (not recursing), Clear parsed filename caches
        if ( $recursing === false )
        {
            $this->logger->debug("Not recursing, so clearing parsed filename caches.");
            $this->clearParsedFilenameCaches();
        } else {
            $this->logger->debug("Recursing.");
        }

        try
        {
            // Create file object
            $file = new File( $filePath, $this->mutingMissingFileExceptions );
        }
        catch (MissingFileException $e)
        {
            // We could catch and forward to front end as a broken path here (that's
            // the old way of doing things), but instead we'll rethrow.
            throw $e;
        }

        $context = new ResolverContext();

        $context->mutingMissingFileExceptions = $this->mutingMissingFileExceptions;
        $context->remoteFolderPath = $this->remoteFolderPath;
        $context->remoteSymbol = $this->remoteSymbol;
        $context->recursionCb = array($this, 'parseFile');

        if ( !$testsSourcePath )
        {
            // Default test's Source path to be the same folder as the given file if it was not provided
            // which restores the original behavior
            $this->logger->info("testsSourcePath not provided, so defaulting it to file's path ('" . $file->path . "').");
            $context->testsSourcePath = $file->path;
        } else {
            $context->testsSourcePath = $testsSourcePath;
        }

        // Build identifier
        $fileIdentifier = $this->buildIdentifierFromFile($file);
        $this->logger->debug("Built identifier: '" . $fileIdentifier . "'.");

        if ( array_key_exists( $fileIdentifier, $this->parsedFiles ) )
        {
            // Use cached parsed file if we already have parsed this file
            $this->logger->info('Already parsed ' . $fileIdentifier . ' - returning its cached File object');
            return $this->parsedFiles[ $fileIdentifier ];
        }

        if ( in_array( $filePath, $this->seenFiles ) )
        {
            // Bail if we already have seen this file
            throw new RecursionException(
                'Encountered recursion within file "' . $filePath . '".',
                RecursionException::ERROR_CODE
            );
        }

        $this->logger->debug("Verifying file type '" . $file->filetype . "' is on parsing whitelist.");

        if ( in_array( $file->filetype, $this->filetypesAllowingAnnotations) )
        {
            // Mark as seen
            $this->logger->debug("Marking {$filePath} as seen.");
            $this->seenFiles[] = $filePath;

            $this->logger->debug("Reading annotations in {$filePath}");
            // Read annotations
            $file = $resolver->resolveDependenciesForFile( $file, $context );
        }

        // Store in cache
        $this->logger->debug("Storing {$file->getFullPath()} in parsedFiles array.");
        $this->parsedFiles[ $fileIdentifier ] = $file;

        // Return populated object
        return $file;
    }




    /**
     * @var FileHandler
     */
    protected $fileHandler;

    /**
     * Get the file handler.
     *
     * @return mixed
     */
    public function getFileHandler()
    {
        return ( $this->fileHandler ? $this->fileHandler : new FileHandler() );
    }

    /**
     * Set the file handler.
     *
     * @param $fileHandler
     * @return File
     */
    public function setFileHandler($fileHandler)
    {
        $this->fileHandler = $fileHandler;
        return $this;
    }

    private function clearParsedFilenameCaches()
    {
        $this->parsedFiles = array();
        $this->seenFiles = array();
        $this->currentlyRecursingInRemoteFile = false;
        $this->recursedPath = array(); // Reset path
    }

    /**
     * Build identifier from file used for caching
     *
     * @param File $file
     * @return mixed|string
     */
    private function buildIdentifierFromFile(File $file)
    {
        $identifier = $this->pathFinder->normalizeRelativePath($file->getFullPath());
        return $identifier;
    }
}
