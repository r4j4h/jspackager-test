<?php
/**
 * This class uses a Resolver to create Files with the factory method parseFile.
 *
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

namespace JsPackager\Resolver;

use JsPackager\Annotations\AnnotationOrderMap;
use JsPackager\Annotations\AnnotationParser;
use JsPackager\DependencyFileInterface;
use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Helpers\FileHandler;
use JsPackager\Helpers\PathFinder;
use JsPackager\AnnotationBasedResolverContext;
use JsPackager\StreamBasedFile;
use JsPackager\StreamBasedFileFileFactory;
use Psr\Log\LoggerInterface;

class DependencyTreeParser
{

    /**
     * @var string
     */
    public $remoteFolderPath;

    /**
     * @var string
     */
    public $remoteSymbol;

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
    private $mutingMissingFileExceptions;


    /**
     * @var LoggerInterface
     */
    public $logger;


    /**
     * @var AnnotationParser
     */
    private $parser;

    /**
     * @var PathFinder
     */
    private $pathFinder;


    /**
     * @param \JsPackager\Annotations\AnnotationParser $parser
     * @param string $remoteSymbol
     * @param string $remoteFolderPath
     * @param LoggerInterface $logger
     * @param bool $muteMissingFileExceptions
     */
    public function __construct(AnnotationParser $parser, $remoteSymbol = '@remote', $remoteFolderPath = 'shared', $testsSourcePath = null, LoggerInterface $logger, $muteMissingFileExceptions = false, FileHandler $fileHandler)
    {
        $this->logger = $logger;

        $this->parser = $parser;

        $this->remoteSymbol = $remoteSymbol;
        $this->remoteFolderPath = $remoteFolderPath;
        $this->testsSourcePath = $testsSourcePath;

        $this->pathFinder = new PathFinder();

        $this->mutingMissingFileExceptions = $muteMissingFileExceptions;

        $this->fileHandler = $fileHandler;
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

    public function recursivelyParseFile( $filePath )
    {
        return $this->parseFile( $filePath, true);
    }

    /**
     * Creates context
     *
     * Converts a file via path into a JsPackager\File object and
     * parses it for dependencies, caching it for re-use if called again
     * with same file.
     *
     * @param string $filePath File's relative path from public folder
     * @param bool $recursing Internal recursion flag. Always call with false.
     * @return DependencyFileInterface
     * @throws Exception\Recursion If the dependent files have a circular dependency
     * @throws Exception\MissingFile Through internal File object if $filePath does not point to a valid file
     */
    public function parseFile( $filePath, $recursing = false )
    {
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
//            $file = new File( $filePath, $this->mutingMissingFileExceptions );
//            $file = new PathBasedFile($filePath, array());
            $factory = new StreamBasedFileFileFactory(sys_get_temp_dir(), $this->fileHandler, $this->mutingMissingFileExceptions);
            $file = $factory->createFile($filePath, array());

            $file->addMetaData('isRoot' , false);
            $file->addMetaData('isMarkedNoCompile' , false);
            $file->addMetaData('isRemote' , false);
            $file->addMetaData('stylesheets' , array());
            $file->addMetaData('scripts' , array());
            $file->addMetaData('packages' , array());
            $file->addMetaData('annotationOrderMap', new AnnotationOrderMap());
        }
        catch (MissingFileException $e)
        {
            // We could catch and forward to front end as a broken path here (that's
            // the old way of doing things), but instead we'll rethrow.
            throw $e;
        }

        $pathinfo = pathinfo($file->getPath());
        $file_pathinfo_dirname = $pathinfo['dirname'];
        $filetype = ( isset( $pathinfo['extension'] ) ? $pathinfo['extension'] : '' );

        if ( !$this->testsSourcePath )
        {
            // Default test's Source path to be the same folder as the given file if it was not provided
            // which restores the original behavior
            $this->logger->info(
                "testsSourcePath not provided, so defaulting it to file's path ('" . $file_pathinfo_dirname . "')."
            );
            $this->testsSourcePath = $file_pathinfo_dirname;
        }

        // todo we are building AnnotationBasedResolverContext here
        //, but this is already close to being provided as a dependency from
        // \JsPackager\Annotations\AnnotationParser::parseAnnotationsInFile
        $context = new AnnotationBasedResolverContext();
        $context->mutingMissingFileExceptions = $this->mutingMissingFileExceptions;
        $context->remoteFolderPath = $this->remoteFolderPath;
        $context->remoteSymbol = $this->remoteSymbol;
        $context->recursionCb = array($this, 'recursivelyParseFile');
        $context->testsSourcePath = $this->testsSourcePath;


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

        $this->logger->debug("Verifying file type '" . $filetype . "' is on parsing whitelist.");

        if ( in_array( $filetype, $this->filetypesAllowingAnnotations) )
        {
            // Mark as seen
            $this->logger->debug("Marking {$filePath} as seen.");
            $this->seenFiles[] = $filePath;

            $this->logger->debug("Reading annotations in {$filePath}");
            // Read annotations
            $file = $this->parser->parseAnnotationsInFile( $file, $context );
        }

        // Store in cache
        $this->logger->debug("Storing {$file->getPath()} in parsedFiles array.");
        $this->parsedFiles[ $fileIdentifier ] = $file;

        // Return populated object
        return $file;
    }




    /**
     * @var FileHandler
     */
    protected $fileHandler;

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
     * @param DependencyFileInterface $file
     * @return mixed|string
     */
    private function buildIdentifierFromFile(DependencyFileInterface $file)
    {
        $identifier = $this->pathFinder->normalizeRelativePath($file->getPath());
        return $identifier;
    }
}
