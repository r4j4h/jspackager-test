<?php
namespace JsPackager\Annotations;

use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\File;
use JsPackager\FileHandler;
use JsPackager\Helpers\ArrayTraversalService;
use JsPackager\PathFinder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AnnotationHandlerParameters
{

    public $filePath;
    public $testsSourcePath;
    public $path;
    public $file;
    public $recursionCb;

    public function __construct($filePath,
                                $testsSourcePath,
                                $path,
                                File &$file,
                                $recursionCb) {
        $this->filePath = $filePath;
        $this->testsSourcePath = $testsSourcePath;
        $this->path = $path;
        $this->file = $file;
        $this->recursionCb = $recursionCb;
    }

}

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

    /**
     * @var String
     */
    public $remoteSymbol;


    public function __construct($remoteSymbol = '@remote')
    {
        $this->logger = new NullLogger();
        $this->arrayTraversalService = new ArrayTraversalService();
        $this->pathFinder = new PathFinder();
        $this->remoteSymbol = $remoteSymbol;
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


    public function doAnnotation_root(AnnotationHandlerParameters $params)
    {
        // This is considered parameterless so if params came through ignore it as it is likely a misread.
        if ( $params->path ) {
            return;
        }

        $params->file->isRoot = true;
    }

    public function doAnnotation_noCompile(AnnotationHandlerParameters $params)
    {
        // This is considered parameterless so if params came through ignore it as it is likely a misread.
        if ( $params->path ) {
            return;
        }

        $params->file->isMarkedNoCompile = true;
    }

    public function doAnnotation_requireRemote(AnnotationHandlerParameters $params)
    {
        // need to load actual file
        // but store @remote/
        // Alter path to remote files' root
        $remotePath = $this->remoteFolderPath;

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $remotePath . '/' . $params->path );

        $this->logger->debug("Calculated {$htmlPath} as remote files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $this->currentlyRecursingInRemoteFile = true;

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);
            $this->recursedPath[] = $basePathFromSourceFileWithoutTrailingSlash;
            $this->recursionDepth++;
            $dependencyFile = call_user_func($params->recursionCb, $htmlPath, $params->testsSourcePath, true );
            $this->recursionDepth--;
            array_pop( $this->recursedPath );
            $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;
            if ( $dependencyFile->isRemote ) {
                // Reset path from actual to using @remote symbol
                $dependencyFile->path = $this->remoteSymbol . '/' . $basePathFromSourceFileWithoutTrailingSlash;
            }
            if ( $this->recursionDepth === 0 && $this->currentlyRecursingInRemoteFile ) {
                $this->currentlyRecursingInRemoteFile = false;
            }

        }
        catch (MissingFileException $e)
        {
            throw new ParsingException("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$params->filePath}\"", null, $e->getMissingFilePath());
        }



        if ( $dependencyFile->isRoot )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $params->file->packages[] = $htmlPath;
        }


        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
        $params->file->scripts[] = $dependencyFile;

        // Switch order map to use require, since we normalized the remote files so they fit in
        // same bucket as normal files.
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation instead of requireRemote since we have normalized the remote file's path.");
        $params->file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $params->file->scripts ) - 1
            )
        );
    }


    public function doAnnotation_require(AnnotationHandlerParameters $params)
    {

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $params->file->path . '/' . $params->path );

        $this->logger->debug("Calculated {$htmlPath} as required files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);

            if ( $this->currentlyRecursingInRemoteFile ) {

                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    $this->recursedPath[] = $this->arrayTraversalService->array_last( $this->recursedPath ) .
                        '/' . $basePathFromSourceFileWithoutTrailingSlash;
                    $this->recursionDepth++;
                }

            }

            $dependencyFile = call_user_func($params->recursionCb, $htmlPath, $params->testsSourcePath, true );
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
                $basePathFromSourceFile = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);
                // don't prepend recursedPath if it already is the beginning

                $dependencyFile->path = rtrim(
                    $this->remoteSymbol . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/'
                );

            }
        }
        catch (MissingFileException $e)
        {
            throw new ParsingException(
                "Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$params->filePath}\"",
                null,
                $e->getMissingFilePath()
            );
        }

        if ( $dependencyFile->isRoot )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $params->file->packages[] = $htmlPath;
        }

        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
        $params->file->scripts[] = $dependencyFile;




        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
        $params->file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $params->file->scripts ) - 1
            )
        );
    }


    public function doAnnotation_requireRemoteStyle(AnnotationHandlerParameters $params)
    {

        $fileHandler = $this->getFileHandler();

        // need to load actual file
        // but store @remote/

        // Alter path to remote files' root
        $remotePath = $this->remoteFolderPath;

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $remotePath . '/' . $params->path );

        $this->logger->debug("Calculated {$htmlPath} as remote files' path.");

        // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
        // be caught and thrown there before parsing. Until that is desired, we will just manually
        // do it without parsing the file.
        if ( $fileHandler->is_file( $remotePath . '/' . $params->path ) === false && $this->mutingMissingFileExceptions === false )
        {
            throw new Exception\MissingFile($remotePath . '/' . $params->path . ' is not a valid file!', 0, null, $remotePath . '/' . $params->path);
        }

        // Reset path from actual to using @remote symbol
        $htmlPath = $this->remoteSymbol . '/' . $params->path;


        // Add to stylesheets list
        $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
        $params->file->stylesheets[] = $htmlPath;

        // Switch order map to use requireStyle, since we normalized the remote files so they fit in
        // same bucket as normal files.
        $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation instead of requireRemoteStyle since we have normalized the remote file's path.");
        $params->file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'requireStyle',
                count( $params->file->stylesheets ) - 1
            )
        );
    }


    public function doAnnotation_requireStyle(AnnotationHandlerParameters $params)
    {

        $fileHandler = $this->getFileHandler();


        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $params->file->path . '/' . $params->path );

        $this->logger->debug("Calculated {$htmlPath} as required files' path.");

        // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
        // be caught and thrown there before parsing. Until that is desired, we will just manually
        // do it without parsing the file.
        if ( $fileHandler->is_file( $params->file->path . '/' . $params->path ) === false && $this->mutingMissingFileExceptions === false )
        {
            throw new Exception\MissingFile($params->file->path . '/' . $params->path . ' is not a valid file!', 0, null, $params->file->path . '/' . $params->path);
        }

        if ( $this->currentlyRecursingInRemoteFile ) {
            // Reset path from actual to using @remote symbol
            // Reset path from actual to using @remote symbol
            $basePathFromDependents = $this->arrayTraversalService->array_last( $this->recursedPath );
            $htmlPath = $this->remoteSymbol . '/' . $basePathFromDependents .'/' . $params->path;
        }

        // Add to stylesheets list
        $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
        $params->file->stylesheets[] = $htmlPath;

        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation.");
        $params->file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'requireStyle',
                count( $params->file->stylesheets ) - 1
            )
        );

    }

    public function doAnnotation_tests(AnnotationHandlerParameters $params)
    {

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $params->testsSourcePath . '/' . $params->path );

        $this->logger->debug("Calculated {$htmlPath} as required files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);

            if ( $this->currentlyRecursingInRemoteFile ) {

                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    $this->recursedPath[] = $this->arrayTraversalService->array_last( $this->recursedPath ) . '/' . $basePathFromSourceFileWithoutTrailingSlash;
                    $this->recursionDepth++;
                }

            }
            $dependencyFile = call_user_func($params->recursionCb, $htmlPath, $params->testsSourcePath, true );
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
                $basePathFromSourceFile = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);
                // don't prepend recursedPath if it already is the beginning

                $dependencyFile->path = rtrim($this->remoteSymbol . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/');

            }
        }
        catch (MissingFileException $e)
        {
            $missingFilePath = $e->getMissingFilePath();
            $errorString = "Failed to include missing file \"{$missingFilePath}\" while trying to parse \"{$params->filePath}\"";
            throw new ParsingException($errorString, null, $missingFilePath);
        }

        if ( $dependencyFile->isRoot )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $params->file->packages[] = $htmlPath;
        }

        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
        $params->file->scripts[] = $dependencyFile;

        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
        $params->file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $params->file->scripts ) - 1 )
        );
    }


    public function doAnnotation_testsRemote(AnnotationHandlerParameters $params)
    {

        // Alter path to remote files' root
        $remotePath = $this->remoteFolderPath;

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $remotePath . '/' . $params->path );

        $this->logger->debug("Calculated {$htmlPath} as required remote files' path.");

        // Call parseFile on it recursively
        try
        {
            $this->logger->debug("Checking it for dependencies...");

            $this->currentlyRecursingInRemoteFile = true;

            $basePathFromSourceFileWithoutTrailingSlash = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);
            $this->recursedPath[] = $basePathFromSourceFileWithoutTrailingSlash;
            $this->recursionDepth++;
            $dependencyFile = call_user_func($params->recursionCb, $htmlPath, $params->testsSourcePath, true );
            $this->recursionDepth--;
            array_pop( $this->recursedPath );
            $dependencyFile->isRemote = $this->currentlyRecursingInRemoteFile;
            if ( $dependencyFile->isRemote ) {
                // Reset path from actual to using @remote symbol
                $dependencyFile->path = $this->remoteSymbol . '/' . $basePathFromSourceFileWithoutTrailingSlash;
            }
            if ( $this->recursionDepth === 0 && $this->currentlyRecursingInRemoteFile ) {
                $this->currentlyRecursingInRemoteFile = false;
            }
        }
        catch (MissingFileException $e)
        {
            $missingFilePath = $e->getMissingFilePath();
            $errorString = "Failed to include missing file \"{$missingFilePath}\" while trying to parse \"{$params->filePath}\"";
            throw new ParsingException($errorString, null, $missingFilePath);
        }

        if ( $dependencyFile->isRoot )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $params->file->packages[] = $htmlPath;
        }

        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
        $params->file->scripts[] = $dependencyFile;

        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
        $params->file->annotationOrderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $params->file->scripts ) - 1
            )
        );
    }
}