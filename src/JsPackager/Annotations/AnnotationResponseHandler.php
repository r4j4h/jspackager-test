<?php
namespace JsPackager\Annotations;

use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\FileHandler;
use JsPackager\Helpers\ArrayTraversalService;
use JsPackager\PathFinder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AnnotationResponseHandler
{

    /**
     * Flag to disable missing file exceptions. Set and unset via muteMissingFileExceptions and
     * unMuteMissingFileExceptions.
     *
     * @var bool
     */
    public $mutingMissingFileExceptions = false;

    /**
     * @var LoggerInterface
     */
    public $logger;


    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->arrayTraversalService = new ArrayTraversalService();
        $this->pathFinder = new PathFinder();
    }



    public $remoteFolderPath = 'shared';

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


    public function doAnnotation_requireRemote($filePath, $testsSourcePath, $path, &$file, $recursionCb)
    {
        // need to load actual file
        // but store @remote/

        // Alter path to remote files' root
        $remotePath = $this->remoteFolderPath;

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $remotePath . '/' . $path );

        $this->logger->debug("Calculated {$htmlPath} as remote files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $this->currentlyRecursingInRemoteFile = true;

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($path);
            $this->recursedPath[] = $basePathFromSourceFileWithoutTrailingSlash;
            $this->recursionDepth++;
            $dependencyFile = call_user_func($recursionCb, $htmlPath, $testsSourcePath, true );
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
        $file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $file->scripts ) - 1
            )
        );
    }


    public function doAnnotation_require($filePath, $testsSourcePath, $path, &$file, $recursionCb)
    {

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $file->path . '/' . $path );

        $this->logger->debug("Calculated {$htmlPath} as required files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($path);

            if ( $this->currentlyRecursingInRemoteFile ) {

                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    $this->recursedPath[] = $this->arrayTraversalService->array_last( $this->recursedPath ) .
                        '/' . $basePathFromSourceFileWithoutTrailingSlash;
                    $this->recursionDepth++;
                }

            }

            $dependencyFile = call_user_func($recursionCb, $htmlPath, $testsSourcePath, true );
            $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;

            if ( $this->currentlyRecursingInRemoteFile ) {
                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    array_pop($this->recursedPath);
                    $this->recursionDepth--;
                }
            }

            if ( $dependencyFile->isRemote ) {
                // Reset path from actual to using @remote symbol
                $basePathFromDependents = $this->arrayTraversalService->array_last( $this->recursedPath );
                $basePathFromSourceFile = $this->getBasePathFromFilePathWithoutTrailingSlash($path);
                // don't prepend recursedPath if it already is the beginning

                $dependencyFile->path = rtrim(
                    '@remote' . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/'
                );

            }
        }
        catch (MissingFileException $e)
        {
            throw new ParsingException(
                "Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$filePath}\"",
                null,
                $e->getMissingFilePath()
            );
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
        $file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $file->scripts ) - 1
            )
        );
    }


    public function doAnnotation_requireRemoteStyle($filePath, $testsSourcePath, $path, &$file, $recursionCb)
    {

        $fileHandler = $this->getFileHandler();

        // need to load actual file
        // but store @remote/

        // Alter path to remote files' root
        $remotePath = $this->remoteFolderPath;

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $remotePath . '/' . $path );

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
        $file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'requireStyle',
                count( $file->stylesheets ) - 1
            )
        );
    }


    public function doAnnotation_requireStyle($filePath, $testsSourcePath, $path, &$file, $recursionCb)
    {

        $fileHandler = $this->getFileHandler();


        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $file->path . '/' . $path );

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
            $basePathFromDependents = $this->arrayTraversalService->array_last( $this->recursedPath );
            $htmlPath = '@remote' . '/' . $basePathFromDependents .'/' . $path;
        }

        // Add to stylesheets list
        $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
        $file->stylesheets[] = $htmlPath;

        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation.");
        $file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'requireStyle',
                count( $file->stylesheets ) - 1
            )
        );

    }

    public function doAnnotation_tests($filePath, $testsSourcePath, $path, &$file, $recursionCb)
    {

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $testsSourcePath . '/' . $path );

        $this->logger->debug("Calculated {$htmlPath} as required files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($path);

            if ( $this->currentlyRecursingInRemoteFile ) {

                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    $this->recursedPath[] = $this->arrayTraversalService->array_last( $this->recursedPath ) . '/' . $basePathFromSourceFileWithoutTrailingSlash;
                    $this->recursionDepth++;
                }

            }
            $dependencyFile = call_user_func($recursionCb, $htmlPath, $testsSourcePath, true );
            $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;

            if ( $this->currentlyRecursingInRemoteFile ) {
                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    array_pop($this->recursedPath);
                    $this->recursionDepth--;
                }
            }

            if ( $dependencyFile->isRemote ) {
                // Reset path from actual to using @remote symbol
                $basePathFromDependents = $this->arrayTraversalService->array_last( $this->recursedPath );
                $basePathFromSourceFile = $this->getBasePathFromFilePathWithoutTrailingSlash($path);
                // don't prepend recursedPath if it already is the beginning

                $dependencyFile->path = rtrim('@remote' . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/');

            }
        }
        catch (MissingFileException $e)
        {
            $missingFilePath = $e->getMissingFilePath();
            $errorString = "Failed to include missing file \"{$missingFilePath}\" while trying to parse \"{$filePath}\"";
            throw new ParsingException($errorString, null, $missingFilePath);
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
        $file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $file->scripts ) - 1 )
        );
    }


    public function doAnnotation_testsRemote($filePath, $testsSourcePath, $path, &$file, $recursionCb)
    {

        // Alter path to remote files' root
        $remotePath = $this->remoteFolderPath;

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $remotePath . '/' . $path );

        $this->logger->debug("Calculated {$htmlPath} as required remote files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $this->currentlyRecursingInRemoteFile = true;

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($path);
            $this->recursedPath[] = $basePathFromSourceFileWithoutTrailingSlash;
            $this->recursionDepth++;
            $dependencyFile = call_user_func($recursionCb, $htmlPath, $testsSourcePath, true );
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
            $missingFilePath = $e->getMissingFilePath();
            $errorString = "Failed to include missing file \"{$missingFilePath}\" while trying to parse \"{$filePath}\"";
            throw new ParsingException($errorString, null, $missingFilePath);
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
        $file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $file->scripts ) - 1
            )
        );
    }
}