<?php

namespace JsPackager\Helpers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileObject;

class FileFinder {

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface [$logger] Defaults to NullLogger.
     */
    public function __construct($logger = false) {
        if ( $logger ) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }
    }

    /**
     * Parse given folder for source javascript files, ignoring compiled files and other metadata.
     * 
     * @param $folderPath
     * @return array
     */
    public function parseFolderForSourceFiles($folderPath)
    {
        /** @var $file SplFileObject */

        $dirIterator = new RecursiveDirectoryIterator( $folderPath );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $fileCount = 0;
        $files = array();

        $this->logger->debug("Parsing folder '".$folderPath."' for source files...");
        foreach ($iterator as $file) {
            if ( $file->isFile() && FileTypeRecognizer::isSourceFile( $file->getFilename() ) ) {
                $files[] = $file->getRealPath();
                $fileCount++;
            }
        }
        $this->logger->debug("Finished parsing folder. Found ".$fileCount." source files.");

        return $files;
    }

}
