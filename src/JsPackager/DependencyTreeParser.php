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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DependencyTreeParser
{
    /**
     * Regexp pattern for detecting annotations with optional space delimited arguments
     * @var string
     */
    const TOKEN_PATTERN                 = '/@(.*?)(?:\s(.*))*$/i';

    /**
     * Regexp patterns for detecting file types via filename
     * @var string
     */
    const SCRIPT_EXTENSION_PATTERN     = '/.js$/';
    const STYLESHEET_EXTENSION_PATTERN = '/.css$/';

    /**
     * Definition list of allowed annotation tokens
     * @var array
     */
    public $acceptedTokens = array(
        'require',
        'requireRemote',
        'requireStyle',
        'requireRemoteStyle',
        'root',
        'nocompile',
        'tests'
    );

    public $sharedFolderPath = 'shared';

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

    public $currentlyRecursingInRemoteFile = false;

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
        $this->logger->info("Parsing file '" . $filePath . "'.");

        // If we're starting off (not recursing), Clear parsed filename caches
        if ( $recursing === false )
        {
            $this->logger->debug("Not recursing, so clearing parsed filename caches.");

            $this->parsedFiles = array();
            $this->seenFiles = array();
            $this->currentlyRecursingInRemoteFile = false;
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
        $fileHtmlPath = $this->normalizeRelativePath( $file->path . '/' . $file->filename );

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
            $annotationsResponse = $this->getAnnotationsFromFile( $filePath );

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
                    // but store $remote/

                    // Alter path to shared files' root
                    $sharedPath = $this->sharedFolderPath;

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $sharedPath . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as shared files' path.");

                    // Call parseFile on it recursively
                    try
                    {
                        $this->logger->debug("Checking it for dependencies...");
                        $this->currentlyRecursingInRemoteFile = true;
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                        $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;
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

                    // Switch order map to use require, since we normalized the shared files so they fit in
                    // same bucket as normal files.
                    $this->logger->debug("Defining entry in annotationOrderMap as a require annotation instead of requireRemote since we have normalized the shared file's path.");
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
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                        $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;
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

                    // Use convention to alter path to shared files' root
                    $sharedPath = preg_replace( '/\/public\/.*$/', '/public/' . $this->sharedFolderPath, $file->path );

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $sharedPath . '/' . $path );

                    $this->logger->debug("Calculated {$htmlPath} as required files' path.");

                    // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
                    // be caught and thrown there before parsing. Until that is desired, we will just manually
                    // do it without parsing the file.
                    if ( $fileHandler->is_file( $sharedPath . '/' . $path ) === false && $this->mutingMissingFileExceptions === false )
                    {
                        throw new Exception\MissingFile($sharedPath . '/' . $path . ' is not a valid file!', 0, null, $sharedPath . '/' . $path);
                    }

                    // Add to stylesheets list
                    $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
                    $file->stylesheets[] = $htmlPath;

                    // Switch order map to use requireStyle, since we normalized the shared files so they fit in
                    // same bucket as normal files.
                    $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation instead of requireRemoteStyle since we have normalized the shared file's path.");
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
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
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
                    $this->logger->debug("Adding {$dependencyFile} to scripts array.");
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
        return $relativePath;
    }

    /**
     * Extracts tokens from a given file.
     *
     * Tokens are composed of the token start identifier (@), the action name, and any parameters separated by space.
     *
     * A token's definition continues until the end of the line.
     *
     * @param $filePath string File's path
     */
    protected function getAnnotationsFromFile( $filePath )
    {
        $this->logger->debug("Getting annotations from file '{$filePath}'.");
        $fileHandler = $this->getFileHandler();

        // Set up empty containers for all accepted annotations
        $annotations = array();
        $orderingMap = array();

        foreach( $this->acceptedTokens as $acceptedToken )
        {
            $annotations[ $acceptedToken ] = array();
        }

        if ( $fileHandler->is_file( $filePath ) )
        {

            // Parse file line by line
            $fh = $fileHandler->fopen( $filePath, 'r' );
            while ( ( $line = $fileHandler->fgets($fh) ) !== false )
            {

                // Parse for annotation tokens with any arguments space delimited
                $matches = array();
                preg_match( self::TOKEN_PATTERN, trim( $line ), $matches );
                $matchCount = count($matches);

                // Is it a valid token line?
                if ( $matchCount > 1 ) {

                    // Handle the token action
                    $action = $matches[1];

                    // Skip if its not a defined token
                    if ( !in_array( $action, $this->acceptedTokens ) )
                    {
                        $this->logger->debug("Found {$action} but it was not in acceptedTokens array.");
                        continue;
                    }

                    // If it has params, split them
                    if ( $matchCount > 2 && $matches[2] !== "" )
                    {
                        // Trim the space delimited arguments string and then explode it on spaces
                        $params = explode( ' ',  trim( $matches[2] ) );

                        foreach( $params as $key => $param ) {
                            // Any extraneous spaces between arguments will
                            // get exploded as empty strings so skip them
                            if ( $param === "" )
                                continue;

                            $this->logger->info("Found '{$action}' annotation with param '{$param}'.");

                            // Add argument for this action
                            $annotations[ $action ][] = $param;

                            // Append to annotation ordering map
                            $annotationIndex = count( $annotations[ $action ] ) - 1;
                            $orderingMap[] = array(
                                'action' => $action,
                                'annotationIndex' => $annotationIndex
                            );
                        }

                    }
                    // Otherwise we only care about the action
                    else
                    {
                        $this->logger->info("Found '{$action}' action annotation.");

                        $annotations[ $action ] = true;

                        // Append to annotation ordering map
                        $orderingMap[] = array(
                            'action' => $action,
                            'annotationIndex' => 0
                        );
                    }

                }

            }
            $fileHandler->fclose($fh);

        }

        $this->logger->debug("Done getting annotations from file '{$filePath}'.");

        return array(
            'annotations' => $annotations,
            'orderingMap' => $orderingMap
        );
    }


    /**
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    protected function isJavaScriptFile($file) {
        return preg_match( self::SCRIPT_EXTENSION_PATTERN, $file );
    }

    /**
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    private function isStylesheetFile($file) {
        return preg_match( self::STYLESHEET_EXTENSION_PATTERN, $file );
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
