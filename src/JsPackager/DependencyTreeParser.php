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
        'tests'
    );

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
        // If we're starting off (not recursing), Clear parsed filename caches
        if ( $recursing === false )
        {
            $this->parsedFiles = array();
            $this->seenFiles = array();
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
            $testsSourcePath = $file->path;
        }

        // Build identifier
        $fileHtmlPath = $this->normalizeRelativePath( $file->path . '/' . $file->filename );

        // Use cached parsed file if we already have parsed this file
        if ( array_key_exists( $fileHtmlPath, $this->parsedFiles ) )
        {
//            echo 'Already parsed ' . $fileHtmlPath . ' - returning its cached File object' . PHP_EOL;
            return $this->parsedFiles[ $fileHtmlPath ];
        }

        // Bail if we already have seen this file
        if ( in_array( $filePath, $this->seenFiles ) )
        {
            throw new RecursionException('Encountered recursion within file "' . $filePath . '".', RecursionException::ERROR_CODE);
        }

        if ( in_array( $file->filetype, $this->filetypesAllowingAnnotations) )
        {
            // Read annotations
            $annotationsResponse = $this->getAnnotationsFromFile( $filePath );

            $annotations = $annotationsResponse['annotations'];
            $orderingMap = $annotationsResponse['orderingMap'];

            // Mark as seen
            $this->seenFiles[] = $filePath;

            // Mark isRoot
            if ( $annotations['root'] === true )
            {
                $file->isRoot = true;
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

                    // Use convention to alter path to shared files' root
                    $sharedPath = preg_replace( '/\/public\/.*$/', '/public/shared', $file->path );

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $sharedPath . '/' . $path );

                    // Call parseFile on it recursively
                    try
                    {
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                    }
                    catch (MissingFileException $e)
                    {
                        throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"", null, $e->getMissingFilePath());
                    }


                    if ( $dependencyFile->isRoot )
                    {
                        $file->packages[] = $htmlPath;
                    }

                    // It has root set from parseFile so callee can handle that
                    $file->scripts[] = $dependencyFile;

                    // Switch order map to use require, since we normalized the shared files so they fit in
                    // same bucket as normal files.
                    $file->annotationOrderMap[] = array(
                        'action' => 'require',
                        'annotationIndex' => count( $file->scripts ) - 1,
                    );

                }
                else if ( $action === 'require' )
                {

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $file->path . '/' . $path );

                    // Call parseFile on it recursively
                    try
                    {
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                    }
                    catch (MissingFileException $e)
                    {
                        throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"", null, $e->getMissingFilePath());
                    }

                    if ( $dependencyFile->isRoot )
                    {
                        $file->packages[] = $htmlPath;
                    }

                    // It has root set from parseFile so callee can handle that
                    $file->scripts[] = $dependencyFile;

                    // Populate ordering map on File object
                    $file->annotationOrderMap[] = array(
                        'action' => $action,
                        'annotationIndex' => count( $file->scripts ) - 1,
                    );


                }
                else if ( $action === 'requireRemoteStyle' )
                {
                    $fileHandler = $this->getFileHandler();

                    // Use convention to alter path to shared files' root
                    $sharedPath = preg_replace( '/\/public\/.*$/', '/public/shared', $file->path );

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $sharedPath . '/' . $path );

                    // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
                    // be caught and thrown there before parsing. Until that is desired, we will just manually
                    // do it without parsing the file.
                    if ( $fileHandler->is_file( $sharedPath . '/' . $path ) === false && $this->mutingMissingFileExceptions === false )
                    {
                        throw new Exception\MissingFile($sharedPath . '/' . $path . ' is not a valid file!', 0, null, $sharedPath . '/' . $path);
                    }

                    // Add to stylesheets list
                    $file->stylesheets[] = $htmlPath;

                    // Switch order map to use requireStyle, since we normalized the shared files so they fit in
                    // same bucket as normal files.
                    $file->annotationOrderMap[] = array(
                        'action' => 'requireStyle',
                        'annotationIndex' => count( $file->stylesheets ) - 1,
                    );

                }
                else if ( $action === 'requireStyle' )
                {
                    $fileHandler = $this->getFileHandler();


                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $file->path . '/' . $path );

                    // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
                    // be caught and thrown there before parsing. Until that is desired, we will just manually
                    // do it without parsing the file.
                    if ( $fileHandler->is_file( $file->path . '/' . $path ) === false && $this->mutingMissingFileExceptions === false )
                    {
                        throw new Exception\MissingFile($file->path . '/' . $path . ' is not a valid file!', 0, null, $file->path . '/' . $path);
                    }

                    // Add to stylesheets list
                    $file->stylesheets[] = $htmlPath;

                    // Populate ordering map on File object
                    $file->annotationOrderMap[] = array(
                        'action' => $action,
                        'annotationIndex' => count( $file->stylesheets ) - 1,
                    );

                }
                else if ( $action === 'tests' )
                {

                    // Build dependency's identifier
                    $htmlPath = $this->normalizeRelativePath( $testsSourcePath . '/' . $path );

                    // Call parseFile on it recursively
                    try
                    {
                        $dependencyFile = $this->parseFile( $htmlPath, $testsSourcePath, true );
                    }
                    catch (MissingFileException $e)
                    {
                        throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"", null, $e->getMissingFilePath());
                    }

                    if ( $dependencyFile->isRoot )
                    {
                        $file->packages[] = $htmlPath;
                    }

                    // It has root set from parseFile so callee can handle that
                    $file->scripts[] = $dependencyFile;

                    // Populate ordering map on File object
                    $file->annotationOrderMap[] = array(
                        'action' => 'require',
                        'annotationIndex' => count( $file->scripts ) - 1,
                    );
                }

            }


        }

        // Store in cache
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
