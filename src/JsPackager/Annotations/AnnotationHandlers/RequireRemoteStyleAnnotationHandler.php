<?php

namespace JsPackager\Annotations\AnnotationHandlers;

use JsPackager\Annotations\AnnotationHandlerParameters;
use JsPackager\Annotations\AnnotationOrderMap;
use JsPackager\Annotations\AnnotationOrderMapping;
use JsPackager\Exception\MissingFile;
use JsPackager\Helpers\FileHandler;
use JsPackager\Helpers\PathFinder;
use Psr\Log\LoggerInterface;

class RequireRemoteStyleAnnotationHandler
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

    public function __construct($remoteFolderPath, $remoteSymbol, $mutingMissingFileExceptions, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->remoteSymbol = $remoteSymbol;
        $this->remoteFolderPath = $remoteFolderPath;
        $this->mutingMissingFileExceptions = $mutingMissingFileExceptions;
        $this->pathFinder = new PathFinder();
    }

    /**
     * @var PathFinder
     */
    protected $pathFinder;

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
            throw new MissingFile($remotePath . '/' . $params->path . ' is not a valid file!', 0, null, $remotePath . '/' . $params->path);
        }

        // Reset path from actual to using @remote symbol
        $htmlPath = $this->remoteSymbol . '/' . $params->path;

        $metaData = $params->file->getMetaData();

        // Add to stylesheets list
        $this->logger->debug("Adding {$htmlPath} to stylesheets array.");
        $metaData['stylesheets'][] = $htmlPath;
        $params->file->addMetaData('stylesheets', $metaData['stylesheets']);

        // Switch order map to use requireStyle, since we normalized the remote files so they fit in
        // same bucket as normal files.
        $this->logger->debug("Defining entry in annotationOrderMap as a requireStyle annotation instead of requireRemoteStyle since we have normalized the remote file's path.");
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

}