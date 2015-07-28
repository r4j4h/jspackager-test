<?php
/**
 * The NonStreamingConcatenationProcessor class takes an array of file paths and concatenate them into one blob.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2013 WebPT, Inc.
 */

namespace JsPackager\Processor;

use JsPackager\Exception\MissingFile;
use JsPackager\Exception\Parsing;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NonStreamingConcatenationProcessor implements SimpleProcessorInterface
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Take an array of file paths and concatenate them into one blob
     *
     * @param array $orderedFilePaths Array of file paths
     * @return ProcessingResult
     * @throws \Exception
     * @throws MissingFile If a file in the list does not exist
     * @throws Parsing If file was unable to be parsed
     */
    public function process(Array $orderedFilePaths)
    {
        $output = '';

        $this->logger->debug("Concatenating files...");

        foreach( $orderedFilePaths as $thisFilePath )
        {
            if ( is_file( $thisFilePath ) === false )
            {
                throw new MissingFile($thisFilePath . ' is not a valid file!', 0, null, $thisFilePath);
            }

            $thisFileContents = file_get_contents( $thisFilePath );

            if ( $thisFileContents === FALSE )
            {
                throw new Parsing($thisFilePath . ' was unable to be parsed. Perhaps permissions problem?');
            }

            $output .= $thisFileContents;
        }

        $this->logger->debug("Concatenated files.");

        return new ProcessingResult(
            true,
            0,
            $output,
            '',
            0
        );
    }
}
