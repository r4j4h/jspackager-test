<?php

namespace JsPackager\Helpers;

use JsPackager\CompiledFileAndManifest\FilenameConverter;
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
     * @var MapDrivenFileTypeRecognizer
     */
    private $recognizer;

    /**
     * @param LoggerInterface [$logger] Defaults to NullLogger.
     */
    public function __construct($logger = false) {
        if ( $logger ) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }

        $this->recognizer = new MapDrivenFileTypeRecognizer();
        $this->recognizer->addRecognition(
            'source-file', array('JsPackager\Helpers\FileTypeRecognizer', 'isSourceFile')
        );
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
            if ( $file->isFile() && $this->recognizer->recognize('source-file', $file->getFilename()) ) {
                $files[] = $file->getRealPath();
                $fileCount++;
            }
        }
        $this->logger->debug("Finished parsing folder. Found ".$fileCount." source files.");

        return $files;
    }

    /**
     * Parse given folder for files with *.compiled.js in the filename across the given path.
     *
     * todo should belong to CompiledAndManifest domain
     *
     * @param string $directory Directory path.
     * @param FilenameConverter $filenameConverter
     * @return \string[]
     */
    public function parseFolderForPackageFiles($directory, FilenameConverter $filenameConverter)
    {
        /** @var $file SplFileObject */

        $dirIterator = new RecursiveDirectoryIterator( $directory );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $fileCount = 0;
        $sourceFileCount = 0;
        $files = array();

        $this->logger->debug("Parsing folder '".$directory."' for package files...");

        foreach ($iterator as $file) {
            if ( $file->isFile() && $this->recognizer->recognize('source-file', $file->getFilename() ) ) {

                $sourceFileCount++;

                $compiledFilename = $filenameConverter->getCompiledFilename( $file->getRealPath() );
                $manifestFilename = $filenameConverter->getManifestFilename( $file->getRealPath() );

                if ( is_file( $compiledFilename ) ) {
                    $files[] = $compiledFilename;
                    $fileCount++;
                }
                if ( is_file( $manifestFilename ) ) {
                    $files[] = $compiledFilename;
                    $fileCount++;
                }

            }
        }

        $this->logger->debug(
            "Finished parsing folder. Found ".$fileCount." package files from ".$sourceFileCount." source files."
        );

        return $files;
    }
}
