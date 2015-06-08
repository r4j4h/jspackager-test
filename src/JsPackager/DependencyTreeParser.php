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
use JsPackager\Resolver\AnnotationBasedFileResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DependencyTreeParser
{



    public $remoteFolderPath = 'shared';

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


    public function __construct()
    {
        $this->logger = new NullLogger();
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
     * @param $sourceFilePath
     * @return string
     */
    protected function getBasePathFromSourceFileWithoutTrailingSlash($sourceFilePath) {
        return ltrim( ( substr( $sourceFilePath, 0, strrpos($sourceFilePath, '/' ) ) ), '/' );
    }

    /**
     * @return mixed
     */
    public function createDefaultResolver()
    {
        $resolver = new AnnotationBasedFileResolver();
        $resolver->logger = $this->logger;
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
     * Grab the last item in an array.
     * 
     * Thanks, http://stackoverflow.com/a/8205332/1347604
     *
     * @param $array
     * @return null
     */
    protected function array_last($array) {
        if (count($array) < 1)
            return null;

        $keys = array_keys($array);
        return $array[$keys[sizeof($keys) - 1]];
    }

    /**
     * Converts a file via path into a JsPackager\File object and
     * parses it for dependencies, caching it for re-use if called again
     * with same file.
     *
     * @param string $filePath File's relative path from public folder
     * @param string $testsSourcePath Optional. For @tests annotations, the source scripts root path with no trailing
     * slash.
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

            $this->parsedFiles = array();
            $this->seenFiles = array();
            $this->currentlyRecursingInRemoteFile = false;
            $this->recursedPath = array(); // Reset path
        } else {
            $this->logger->debug("Recursing.");
        }

        // Create file object
        try
        {
            $file = new File( $filePath, $this->mutingMissingFileExceptions );
        }
        catch (MissingFileException $e)
        {
            // We could catch and forward to front end as a broken path here (that's
            // the old way of doing things), but instead we'll rethrow.
            throw $e;
        }

        // Default test's Source path to be the same folder as the given file if it was not provided
        // which restores the original behavior
        if ( !$testsSourcePath )
        {
            $this->logger->info("testsSourcePath not provided, so defaulting it to file's path ('" . $file->path . "').");
            $testsSourcePath = $file->path;
        }

        // Build identifier
        $fileHtmlPath = $this->normalizeRelativePath( $file->getFullPath() );

        $this->logger->debug("Built identifier: '" . $fileHtmlPath . "'.");

        // Use cached parsed file if we already have parsed this file
        if ( array_key_exists( $fileHtmlPath, $this->parsedFiles ) )
        {
            $this->logger->info('Already parsed ' . $fileHtmlPath . ' - returning its cached File object');
            return $this->parsedFiles[ $fileHtmlPath ];
        }

        // Bail if we already have seen this file
        if ( in_array( $filePath, $this->seenFiles ) )
        {
            throw new RecursionException('Encountered recursion within file "' . $filePath . '".', RecursionException::ERROR_CODE);
        }

        $this->logger->debug("Verifying file type '" . $file->filetype . "' is on parsing whitelist.");

        if ( in_array( $file->filetype, $this->filetypesAllowingAnnotations) )
        {
            $this->logger->debug("Reading annotations in {$filePath}");
            // Read annotations
            $annotationsResponse = $resolver->resolveDependenciesForFile( $filePath );

            $annotations = $annotationsResponse['annotations'];
            $orderingMap = $annotationsResponse['orderingMap'];

            // Mark as seen
            $this->logger->debug("Marking {$filePath} as seen.");
            $this->seenFiles[] = $filePath;

            // Mark isRoot
            if ( $annotations['root'] === true )
            {
                $this->logger->debug("Marking {$filePath} as root.");
                $file->isRoot = true;
            }

            // Mark isMarkedNoCompile
            if ( $annotations['nocompile'] === true )
            {
                $this->logger->debug("Marking {$filePath} as nocompile.");
                $file->isMarkedNoCompile = true;
            }


            // Go through each required file and see if it has requirements and if so
            // Populate scripts/stylesheets w/ File objects
            foreach ( $orderingMap as $index => $orderingMapEntry )
            {
                $action = $orderingMapEntry['action'];
                $bucketIndex = $orderingMapEntry['annotationIndex'];
                $path = $annotations[ $action ][ $bucketIndex ];

                if ( $action === 'requireRemote' )
                {
                    $this->logger->debug("Found requireRemote entry.");

                    // need to load actual file
                    // but store @remote/

                    // Alter path to remote files' root
                    $remotePath = $this->remoteFolderPath;

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $remotePath . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as remote files' path.");

                    // Call parseFile on it recursively
                    try
                    {
                        $this->logger->debug("Checking it for dependencies...");

                        $recursedThisPass = true;
                        $this->currentlyRecursingInRemoteFile = true;

                        $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromSourceFileWithoutTrailingSlash($path);
                        $this->recursedPath[] = $basePathFromSourceFileWithoutTrailingSlash;
                        $this->recursionDepth++;
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                        $this->recursionDepth--;
                        array_pop( $this->recursedPath );
                        $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;
                        if ( $dependencyFile->isRemote ) {
                            // Reset path from actual to using @remote symbol
                            $dependencyFile->path = '@remote' . '/' . $basePathFromSourceFileWithoutTrailingSlash;
                        }
                        if ( $this->recursionDepth === 0 && $this->currentlyRecursingInRemoteFile ) {
                            $this->currentlyRecursingInRemoteFile = false;
                        }

                    }
                    catch (MissingFileException $e)
                    {
                        throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"", null, $e->getMissingFilePath());
                    }



                    if ( $dependencyFile->isRoot )
                    {
                        $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
                        $file->packages[] = $htmlPath;
                    }


                    // It has root set from parseFile so callee can handle that
                    $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
                    $file->scripts[] = $dependencyFile;

                    // Switch order map to use require, since we normalized the remote files so they fit in
                    // same bucket as normal files.
                    $this->logger->debug("Defining entry in annotationOrderMap as a require annotation instead of requireRemote since we have normalized the remote file's path.");
                    $file->annotationOrderMap[] = array(
                        'action' => 'require',
                        'annotationIndex' => count( $file->scripts ) - 1,
                    );

                }
                else if ( $action === 'require' )
                {
                    $this->logger->debug("Found require entry.");

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $file->path . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as required files' path.");

                    // Call parseFile on it recursively
                    try
                    {
                        $this->logger->debug("Checking it for dependencies...");

                        $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromSourceFileWithoutTrailingSlash($path);

                        if ( $this->currentlyRecursingInRemoteFile ) {

                            if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                                $this->recursedPath[] = $this->array_last( $this->recursedPath ) . '/' . $basePathFromSourceFileWithoutTrailingSlash;
                                $this->recursionDepth++;
                            }

                        }

                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                        $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;

                        if ( $this->currentlyRecursingInRemoteFile ) {
                            if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                                array_pop($this->recursedPath);
                                $this->recursionDepth--;
                            }
                        }

                        if ( $dependencyFile->isRemote ) {
                            // Reset path from actual to using @remote symbol
                            $basePathFromDependents = $this->array_last( $this->recursedPath );
                            $basePathFromSourceFile = $this->getBasePathFromSourceFileWithoutTrailingSlash($path);
                            // don't prepend recursedPath if it already is the beginning

                                $dependencyFile->path = rtrim('@remote' . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/');

                        }
                    }
                    catch (MissingFileException $e)
                    {
                        throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"", null, $e->getMissingFilePath());
                    }

                    if ( $dependencyFile->isRoot )
                    {
                        $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
                        $file->packages[] = $htmlPath;
                    }

                    // It has root set from parseFile so callee can handle that
                    $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
                    $file->scripts[] = $dependencyFile;




                    // Populate ordering map on File object
                    $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
                    $file->annotationOrderMap[] = array(
                        'action' => $action,
                        'annotationIndex' => count( $file->scripts ) - 1,
                    );


                }
                else if ( $action === 'requireRemoteStyle' )
                {
                    $this->logger->debug("Found requireRemoteStyle entry.");

                    $fileHandler = $this->getFileHandler();

                    // need to load actual file
                    // but store @remote/

                    // Alter path to remote files' root
                    $remotePath = $this->remoteFolderPath;

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $remotePath . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as remote files' path.");

                    // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
                    // be caught and thrown there before parsing. Until that is desired, we will just manually
                    // do it without parsing the file.
                    if ( $fileHandler->is_file( $remotePath . '/' . $path ) === false && $this->mutingMissingFileExceptions === false )
                    {
                        throw new Exception\MissingFile($remotePath . '/' . $path . ' is not a valid file!', 0, null, $remotePath . '/' . $path);
                    }

                    // Reset path from actual to using @remote symbol
                    $htmlPath = '@remote' . '/' . $path;


                    // Add to stylesheets list
                    $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
                    $file->stylesheets[] = $htmlPath;

                    // Switch order map to use requireStyle, since we normalized the remote files so they fit in
                    // same bucket as normal files.
                    $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation instead of requireRemoteStyle since we have normalized the remote file's path.");
                    $file->annotationOrderMap[] = array(
                        'action' => 'requireStyle',
                        'annotationIndex' => count( $file->stylesheets ) - 1,
                    );

                }
                else if ( $action === 'requireStyle' )
                {
                    $this->logger->debug("Found requireStyle entry.");

                    $fileHandler = $this->getFileHandler();


                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $file->path . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as required files' path.");

                    // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
                    // be caught and thrown there before parsing. Until that is desired, we will just manually
                    // do it without parsing the file.
                    if ( $fileHandler->is_file( $file->path . '/' . $path ) === false && $this->mutingMissingFileExceptions === false )
                    {
                        throw new Exception\MissingFile($file->path . '/' . $path . ' is not a valid file!', 0, null, $file->path . '/' . $path);
                    }

                    if ( $this->currentlyRecursingInRemoteFile ) {
                        // Reset path from actual to using @remote symbol
                        // Reset path from actual to using @remote symbol
                        $basePathFromDependents = $this->array_last( $this->recursedPath );
                        $htmlPath = '@remote' . '/' . $basePathFromDependents .'/' . $path;
                    }

                    // Add to stylesheets list
                    $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
                    $file->stylesheets[] = $htmlPath;

                    // Populate ordering map on File object
                    $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation.");
                    $file->annotationOrderMap[] = array(
                        'action' => $action,
                        'annotationIndex' => count( $file->stylesheets ) - 1,
                    );

                }
                else if ( $action === 'tests' )
                {
                    $this->logger->debug("Found tests entry.");

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $testsSourcePath . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as required files' path.");

                    // Call parseFile on it recursively
                    try
                    {
                        $this->logger->debug("Checking it for dependencies...");

                        $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromSourceFileWithoutTrailingSlash($path);

                        if ( $this->currentlyRecursingInRemoteFile ) {

                            if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                                $this->recursedPath[] = $this->array_last( $this->recursedPath ) . '/' . $basePathFromSourceFileWithoutTrailingSlash;
                                $this->recursionDepth++;
                            }

                        }
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                        $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;

                        if ( $this->currentlyRecursingInRemoteFile ) {
                            if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                                array_pop($this->recursedPath);
                                $this->recursionDepth--;
                            }
                        }

                        if ( $dependencyFile->isRemote ) {
                            // Reset path from actual to using @remote symbol
                            $basePathFromDependents = $this->array_last( $this->recursedPath );
                            $basePathFromSourceFile = $this->getBasePathFromSourceFileWithoutTrailingSlash($path);
                            // don't prepend recursedPath if it already is the beginning

                            $dependencyFile->path = rtrim('@remote' . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/');

                        }
                    }
                    catch (MissingFileException $e)
                    {
                        throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"", null, $e->getMissingFilePath());
                    }

                    if ( $dependencyFile->isRoot )
                    {
                        $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
                        $file->packages[] = $htmlPath;
                    }

                    // It has root set from parseFile so callee can handle that
                    $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
                    $file->scripts[] = $dependencyFile;

                    // Populate ordering map on File object
                    $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
                    $file->annotationOrderMap[] = array(
                        'action' => 'require',
                        'annotationIndex' => count( $file->scripts ) - 1,
                    );
                }
                else if ( $action === 'testsRemote' )
                {
                    $this->logger->debug("Found testsRemote entry.");

                    // Alter path to remote files' root
                    $remotePath = $this->remoteFolderPath;

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $remotePath . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as required remote files' path.");

                    // Call parseFile on it recursively
                    try
                    {
                        $this->logger->debug("Checking it for dependencies...");

                        $recursedThisPass = true;
                        $this->currentlyRecursingInRemoteFile = true;

                        $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromSourceFileWithoutTrailingSlash($path);
                        $this->recursedPath[] = $basePathFromSourceFileWithoutTrailingSlash;
                        $this->recursionDepth++;
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                        $this->recursionDepth--;
                        array_pop( $this->recursedPath );
                        $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;
                        if ( $dependencyFile->isRemote ) {
                            // Reset path from actual to using @remote symbol
                            $dependencyFile->path = '@remote' . '/' . $basePathFromSourceFileWithoutTrailingSlash;
                        }
                        if ( $this->recursionDepth === 0 && $this->currentlyRecursingInRemoteFile ) {
                            $this->currentlyRecursingInRemoteFile = false;
                        }
                    }
                    catch (MissingFileException $e)
                    {
                        throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"", null, $e->getMissingFilePath());
                    }

                    if ( $dependencyFile->isRoot )
                    {
                        $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
                        $file->packages[] = $htmlPath;
                    }

                    // It has root set from parseFile so callee can handle that
                    $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
                    $file->scripts[] = $dependencyFile;

                    // Populate ordering map on File object
                    $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
                    $file->annotationOrderMap[] = array(
                        'action' => 'require',
                        'annotationIndex' => count( $file->scripts ) - 1,
                    );
                }

            }


        }

        // Store in cache
        $this->logger->debug("Storing {$file->getFullPath()} in parsedFiles array.");
        $this->parsedFiles[ $fileHtmlPath ] = $file;

        // Return populated object
        return $file;
    }

    /**
     * Normalize a relative path, purifying it of unnecessary ..'s
     *
     * E.g.
     *    /somewhere/in/a/place/../../heaven
     * becomes
     *    /somewhere/in/heaven
     *
     * @param string $relativePath Path/URL
     */
    public function normalizeRelativePath($relativePath) {
        $pattern = '/[\w\-]+\/\.\.\//';
        while ( preg_match( $pattern, $relativePath ) ) {
            $relativePath = preg_replace( $pattern, '', $relativePath );
        }
        $pattern = '/\/\.\//';
        while ( preg_match( $pattern, $relativePath ) ) {
            $relativePath = preg_replace( $pattern, '/', $relativePath );
        }
        return $relativePath;
    }



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
}
