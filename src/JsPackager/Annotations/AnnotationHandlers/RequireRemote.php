<?php

namespace JsPackager\Annotations\AnnotationHandlers;

use JsPackager\Annotations\AnnotationHandlerParameters;
use JsPackager\Annotations\AnnotationOrderMap;
use JsPackager\Annotations\AnnotationOrderMapping;
use JsPackager\Exception\MissingFile;
use JsPackager\Exception\Parsing;
use JsPackager\File;
use JsPackager\Helpers\ArrayTraversalService;
use JsPackager\Helpers\FileHandler;
use JsPackager\Helpers\PathFinder;
use Psr\Log\LoggerInterface;

class RequireRemote
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var String
     */
    public $remoteSymbol;

    /**
     * @var string
     */
    public $remoteFolderPath = 'shared';

    /**
     * @var boolean
     */
    public $mutingMissingFileExceptions;


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

    public function __construct($remoteFolderPath, $remoteSymbol, $mutingMissingFileExceptions, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->remoteSymbol = $remoteSymbol;
        $this->remoteFolderPath = $remoteFolderPath;
        $this->mutingMissingFileExceptions = $mutingMissingFileExceptions;
        $this->pathFinder = new PathFinder();
        $this->arrayTraversalService = new ArrayTraversalService();
    }


    /**
     *
     * Get the base path to a file.
     *
     * Give it something like '/my/cool/file.jpg' and get '/my/cool/' back.
     * @param $filePath
     * @return string
     */
    protected function getBasePathFromFilePathWithoutTrailingSlash($filePath) {
        return ltrim( ( substr( $filePath, 0, strrpos($filePath, '/' ) ) ), '/' );
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
            $dependencyFile = call_user_func($params->recursionCb, $htmlPath);
            $this->recursionDepth--;
            array_pop( $this->recursedPath );
            $dependencyFile->addMetaData('isRemote', $this->currentlyRecursingInRemoteFile);
            $metaData = $dependencyFile->getMetaData();
            if ( $metaData['isRemote'] == true ) {
                // Reset path from actual to using @remote symbol
//                if ( $dependencyFile->path ) { // todo this is temporary
                    $dependencyFile->path = $this->remoteSymbol . '/' . $basePathFromSourceFileWithoutTrailingSlash;
//                }
                $dependencyFile->addMetaData('path',
                    $this->remoteSymbol . '/' . $basePathFromSourceFileWithoutTrailingSlash
                );
            }
            if ( $this->recursionDepth === 0 && $this->currentlyRecursingInRemoteFile ) {
                $this->currentlyRecursingInRemoteFile = false;
            }

        }
        catch (MissingFile $e)
        {
            throw new Parsing("Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$params->filePath}\"", null, $e->getMissingFilePath());
        }


        $metaData = $params->file->getMetaData();
        $dependencyFileMetaData = $dependencyFile->getMetaData();

        if ( $dependencyFileMetaData['isRoot'] )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $metaData['packages'][] = $htmlPath;
            $params->file->addMetaData('packages', $metaData['packages']);
        }

        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getPath()} to scripts array.");
        $metaData['scripts'][] = $dependencyFile;
        $params->file->addMetaData('scripts', $metaData['scripts']);

        // Switch order map to use require, since we normalized the remote files so they fit in
        // same bucket as normal files.
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation instead of requireRemote since we have normalized the remote file's path.");
        $orderMap = $metaData['annotationOrderMap'];
        if ( !$orderMap ) {
            $orderMap = new AnnotationOrderMap();
        }
        $orderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $metaData['scripts'] ) - 1
            )
        );
        $params->file->addMetaData('annotationOrderMap', $orderMap);
    }

    public function doAnnotation_require(AnnotationHandlerParameters $params)
    {
        $pathinfo = pathinfo($params->file->getPath());
        $path = $pathinfo['dirname'];

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $path . '/' . $params->path );

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

            $dependencyFile = call_user_func($params->recursionCb, $htmlPath);

            if ( $this->currentlyRecursingInRemoteFile ) {
                $dependencyFile->addMetaData('isRemote', $this->currentlyRecursingInRemoteFile);
                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    array_pop($this->recursedPath);
                    $this->recursionDepth--;
                }
            }

            $metaData = $dependencyFile->getMetaData();

            if ( $metaData['isRemote'] == true ) {
                // Reset path from actual to using @remote symbol
                $basePathFromDependents = $this->arrayTraversalService->array_last( $this->recursedPath );
                $basePathFromSourceFile = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);
                // don't prepend recursedPath if it already is the beginning

//                if ( $dependencyFile->path ) { // todo this is temporary
                    $dependencyFile->path = rtrim(
                        $this->remoteSymbol . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/'
                    );
//                }
                $dependencyFile->addMetaData('path', rtrim(
                    $this->remoteSymbol . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/'
                ));

            }
        }
        catch (MissingFile $e)
        {
            throw new Parsing(
                "Failed to include missing file \"{$e->getMissingFilePath()}\" while trying to parse \"{$params->filePath}\"",
                null,
                $e->getMissingFilePath()
            );
        }

        $metaData = $params->file->getMetaData();

        $dependencyFileMetaData = $dependencyFile->getMetaData();
        if ( $dependencyFileMetaData['isRoot'] )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $metaData['packages'][] = $htmlPath;
            $params->file->addMetaData('packages', $metaData['packages'] );
        }

        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getPath()} to scripts array.");
        $metaData['scripts'][] = $dependencyFile;
//        $params->file->addMetaData('scripts', $metaData['scripts'] );




        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
        $orderMap = $metaData['annotationOrderMap'];
        if ( !$orderMap ) {
            $orderMap = new AnnotationOrderMap();
        }
        $orderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $metaData['scripts'] ) - 1
            )
        );
        $params->file->addMetaData('scripts', $metaData['scripts']);
        $params->file->addMetaData('annotationOrderMap', $orderMap);
    }



    public function doAnnotation_requireStyle(AnnotationHandlerParameters $params)
    {

        $fileHandler = $this->getFileHandler();

        $pathinfo = pathinfo($params->file->getPath());
        $dirname = $pathinfo['dirname'];

        // Build dependency's identifier
        $htmlPath = $this->pathFinder->normalizeRelativePath( $dirname . '/' . $params->path );

        $this->logger->debug("Calculated {$htmlPath} as required files' path.");

        // When parsing CSS files is desired, it will go through parseFile so non file exceptions will
        // be caught and thrown there before parsing. Until that is desired, we will just manually
        // do it without parsing the file.
        if ( $fileHandler->is_file( $dirname . '/' . $params->path ) === false && $this->mutingMissingFileExceptions === false )
        {
            throw new MissingFile($dirname . '/' . $params->path . ' is not a valid file!', 0, null, $dirname . '/' . $params->path);
        }

        if ( $this->currentlyRecursingInRemoteFile ) {
            // Reset path from actual to using @remote symbol
            // Reset path from actual to using @remote symbol
            $basePathFromDependents = $this->arrayTraversalService->array_last( $this->recursedPath );
            $htmlPath = $this->remoteSymbol . '/' . $basePathFromDependents .'/' . $params->path;
        }

        $metaData = $params->file->getMetaData();

        // Add to stylesheets list
        $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
        $metaData['stylesheets'][] = $htmlPath;
        $params->file->addMetaData('stylesheets', $metaData['stylesheets']);

        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation.");
        $orderMap = $metaData['annotationOrderMap'];
        if ( !$orderMap ) {
            $orderMap = new AnnotationOrderMap();
        }
        $orderMap->addAnnotation(
            new AnnotationOrderMapping(
                'requireStyle',
                count( $metaData['stylesheets'] ) - 1
            )
        );
        $params->file->addMetaData('stylesheets', $metaData['stylesheets']);
        $params->file->addMetaData('annotationOrderMap', $orderMap);

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
            $dependencyFile = call_user_func($params->recursionCb, $htmlPath);
            $dependencyFile->addMetaData('isRemote', $this->currentlyRecursingInRemoteFile);

            if ( $this->currentlyRecursingInRemoteFile ) {
                if ( $basePathFromSourceFileWithoutTrailingSlash !== '' ) {
                    array_pop($this->recursedPath);
                    $this->recursionDepth--;
                }
            }

            $metaData = $dependencyFile->getMetaData();
            if ( $metaData['isRemote'] == true ) {
                // Reset path from actual to using @remote symbol
                $basePathFromDependents = $this->arrayTraversalService->array_last( $this->recursedPath );
                $basePathFromSourceFile = $this->getBasePathFromFilePathWithoutTrailingSlash($params->path);
                // don't prepend recursedPath if it already is the beginning

                $dependencyFile->path = rtrim($this->remoteSymbol . '/' . $basePathFromDependents . '/' . $basePathFromSourceFile, '/');

            }
        }
        catch (MissingFile $e)
        {
            $missingFilePath = $e->getMissingFilePath();
            $errorString = "Failed to include missing file \"{$missingFilePath}\" while trying to parse \"{$params->filePath}\"";
            throw new Parsing($errorString, null, $missingFilePath);
        }

        if ( $dependencyFile->getMetaDataKey('isRoot') )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $packages = $params->file->getMetaDataKey('packages');
            $packages[] = $htmlPath;
            $params->file->addMetaData('packages', $packages);
        }

        $metaData = $params->file->getMetaData();

        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
        $metaData['scripts'][] = $dependencyFile;
        $params->file->addMetaData('scripts', $metaData['scripts']);

        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");
        $orderMap = $metaData['annotationOrderMap'];
        if ( !$orderMap ) {
            $orderMap = new AnnotationOrderMap();
        }
        $orderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $metaData['scripts'] ) - 1
            )
        );
        $params->file->addMetaData('scripts', $metaData['scripts']);
        $params->file->addMetaData('annotationOrderMap', $orderMap);
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
            $dependencyFile = call_user_func($params->recursionCb, $htmlPath);
            $this->recursionDepth--;
            array_pop( $this->recursedPath );
            $dependencyFile->addMetaData('isRemote', $this->currentlyRecursingInRemoteFile);

            $metaData = $dependencyFile->getMetaData();

            if ( $metaData['isRemote'] == true ) {
                // Reset path from actual to using @remote symbol
                $dependencyFile->path = $this->remoteSymbol . '/' . $basePathFromSourceFileWithoutTrailingSlash;
            }
            if ( $this->recursionDepth === 0 && $this->currentlyRecursingInRemoteFile ) {
                $this->currentlyRecursingInRemoteFile = false;
            }
        }
        catch (MissingFile $e)
        {
            $missingFilePath = $e->getMissingFilePath();
            $errorString = "Failed to include missing file \"{$missingFilePath}\" while trying to parse \"{$params->filePath}\"";
            throw new Parsing($errorString, null, $missingFilePath);
        }


        if ( $dependencyFile->getMetaDataKey('isRoot') )
        {
            $this->logger->debug("Dependency is a root file! Adding {$htmlPath} to packages array.");
            $packages = $params->file->getMetaDataKey('packages');
            $packages[] = $htmlPath;
            $params->file->addMetaData('packages', $packages);
        }

        $metaData = $params->file->getMetaData();

        // It has root set from parseFile so callee can handle that
        $this->logger->debug("Adding {$dependencyFile->getFullPath()} to scripts array.");
        $metaData['scripts'][] = $dependencyFile;
        $params->file->addMetaData('scripts', $metaData['scripts']);

        // Populate ordering map on File object
        $this->logger->debug("Defining entry in annotationOrderMap as a require annotation.");

        $orderMap = $metaData['annotationOrderMap'];
        if ( !$orderMap ) {
            $orderMap = new AnnotationOrderMap();
        }
        $orderMap->addAnnotation(
            new AnnotationOrderMapping(
                'require',
                count( $metaData['scripts'] ) - 1
            )
        );
        $params->file->addMetaData('scripts', $metaData['scripts']);
        $params->file->addMetaData('annotationOrderMap', $orderMap);
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