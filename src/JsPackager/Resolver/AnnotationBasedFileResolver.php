<?php

namespace JsPackager\Resolver;

use JsPackager\Annotations\AnnotationHandlerParameters;
use JsPackager\Annotations\AnnotationResponseHandler;
use JsPackager\File;
use JsPackager\Helpers\FileHandler;
use JsPackager\ResolverContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AnnotationBasedFileResolver implements FileResolverInterface {


    /**
     * Regexp pattern for detecting annotations with optional space delimited arguments
     * @var string
     */
    const TOKEN_PATTERN                 = '/@(.*?)(?:\s(.*))*$/i';


    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
        if ( isset( $this->annotationResponseHandler ) ) {
            $this->annotationResponseHandler->logger = $this->logger;
        }
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

    public function __construct($logger = null)
    {
        if ( $logger instanceof LoggerInterface ) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }

        $this->annotationResponseHandler = new AnnotationResponseHandler();
        $this->annotationResponseHandler->logger = $this->logger;

        $this->annotationResponseHandlerMapping = array(
            'requireRemote'     => array($this->annotationResponseHandler, 'doAnnotation_requireRemote' ),
            'require'           => array($this->annotationResponseHandler, 'doAnnotation_require' ),
            'requireRemoteStyle'=> array($this->annotationResponseHandler, 'doAnnotation_requireRemoteStyle' ),
            'requireStyle'      => array($this->annotationResponseHandler, 'doAnnotation_requireStyle' ),
            'tests'             => array($this->annotationResponseHandler, 'doAnnotation_tests' ),
            'testsRemote'       => array($this->annotationResponseHandler, 'doAnnotation_testsRemote' ),
            'root'              => array($this->annotationResponseHandler, 'doAnnotation_root' ),
            'nocompile'         => array($this->annotationResponseHandler, 'doAnnotation_noCompile' )
        );

    }

    /**
     * @param File $file
     * @return File
     */
    public function resolveDependenciesForFile(File $file, ResolverContext $context)
    {

        $annotationMapping = $this->getAnnotationsFromFile( $file->getFullPath() );
        return $this->translateMappingIntoExistingFile( $annotationMapping, $file, $context );
    }

    /**
     * @param $annotationsResponse
     * @param File $file
     * @return File
     */
    protected function translateMappingIntoExistingFile($annotationsResponse, File $file, ResolverContext $context) {

        $this->annotationResponseHandler->mutingMissingFileExceptions = $context->mutingMissingFileExceptions;
        $this->annotationResponseHandler->remoteFolderPath = $context->remoteFolderPath;
        $this->annotationResponseHandler->remoteSymbol = $context->remoteSymbol;

        $filePath = $file->getFullPath();
        $annotations = $annotationsResponse['annotations'];
        $orderingMap = $annotationsResponse['orderingMap'];

        // Go through each required file and see if it has requirements and if so
        // Populate scripts/stylesheets w/ File objects
        foreach ( $orderingMap as $orderingMapEntry )
        {
            $action = $orderingMapEntry['action'];
            $bucketIndex = $orderingMapEntry['annotationIndex'];
            $path = $annotations[ $action ][ $bucketIndex ];

            if ( array_key_exists( $action, $this->annotationResponseHandlerMapping ) ) {
                $handler = $this->annotationResponseHandlerMapping[$action];
                $this->logger->debug("Found {$action} entry.");
                $params = new AnnotationHandlerParameters(
                    $filePath, $context->testsSourcePath, $path, $file, $context->recursionCb
                );
                call_user_func($handler, $params);
            }

        }

        return $file;
    }




    /**
     * Extracts tokens from a given file.
     *
     * Tokens are composed of the token start identifier (@), the action name, and any parameters separated by space.
     *
     * A token's definition continues until the end of the line.
     *
     * @param $filePath string File's path
     * @return array
     */
    protected function getAnnotationsFromFile( $filePath )
    {
        $this->logger->debug("Getting annotations from file '{$filePath}'.");
        $fileHandler = $this->getFileHandler();

        // Set up empty containers for all accepted annotations
        $annotations = $this->bucketizeAcceptedTokens( array() );
        $orderingMap = array();

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
                    if ( !array_key_exists( $action, $this->annotationResponseHandlerMapping ) )
                    {
                        $this->logger->debug(
                            "Found potential annotation '{$action}' but it was not mapped in acceptedTokens array."
                        );
                        continue;
                    }
                    // If it has params, split them
                    if ( $matchCount > 2 && $matches[2] !== "" )
                    {
                        // Trim the space delimited arguments string and then explode it on spaces
                        $params = explode( ' ',  trim( $matches[2] ) );

                        foreach( $params as $param ) {
                            // Any extraneous spaces between arguments will
                            // get exploded as empty strings so skip them
                            if ( $param === "" )
                                continue;

                            $this->handleParameterizedAction($action, $param, $annotations, $orderingMap);
                        }

                    }
                    // Otherwise we only care about the action
                    else
                    {
                        $this->handleActionAnnotation($action, $annotations, $orderingMap);
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
     * Prepare buckets for each accepted annotation token.
     *
     * @param array $annotations
     */
    protected function bucketizeAcceptedTokens(array $annotations)
    {

        foreach ($this->annotationResponseHandlerMapping as $acceptedToken => $handler) {
            $annotations[$acceptedToken] = array();
        }
        return $annotations;
    }

    /**
     * Record an action and parameter
     *
     * @param $action
     * @param $param
     * @param $annotations
     * @param $orderingMap
     */
    protected function handleParameterizedAction($action, $param, &$annotations, &$orderingMap)
    {
        $this->logger->info("Found '{$action}' annotation with param '{$param}'.");

        // Add argument for this action
        $annotations[$action][] = $param;

        // Append to annotation ordering map
        $annotationIndex = count($annotations[$action]) - 1;
        $orderingMap[] = array(
            'action' => $action,
            'annotationIndex' => $annotationIndex
        );
    }

    /**
     * Mark an action as present and record it.
     * @param $action
     * @param $annotations
     * @param $orderingMap
     */
    protected function handleActionAnnotation($action, &$annotations, &$orderingMap)
    {
        $this->logger->info("Found '{$action}' action annotation.");

        $annotations[$action] = true;

        // Append to annotation ordering map
        $orderingMap[] = array(
            'action' => $action,
            'annotationIndex' => 0
        );
    }



}