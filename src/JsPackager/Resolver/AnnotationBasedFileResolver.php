<?php

namespace JsPackager\Resolver;

use JsPackager\File;
use JsPackager\FileHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AnnotationBasedFileResolver implements FileResolverInterface {


    /**
     * Regexp pattern for detecting annotations with optional space delimited arguments
     * @var string
     */
    const TOKEN_PATTERN                 = '/@(.*?)(?:\s(.*))*$/i';


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
        'tests',
        'testsRemote'
    );


    /**
     * @var LoggerInterface
     */
    public $logger;


    protected $fileHandler;


    public function __construct($logger = null)
    {
        if ( $logger instanceof LoggerInterface ) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }
    }

    public function resolveDependenciesForFile($filePath)
    {
        return $this->getAnnotationsFromFile( $filePath );
    }


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
        foreach ($this->acceptedTokens as $acceptedToken) {
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