<?php

namespace JsPackager\CompiledFileAndManifest;

use JsPackager\Helpers\FileFinder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CompiledAndManifestFileUtilityService
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    public function __construct($logger = null) {
        if ( $logger instanceof LoggerInterface ) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }
    }

    /**
     * Delete (unlink) any files with *.compiled.js in the filename across the given path.
     *
     * @param string $directory Directory path.
     * @return bool
     */
    public function clearPackages($directory)
    {
        $success = true;
        $finder = new FileFinder( $this->logger );
        $foundFiles = $finder->parseFolderForPackageFiles( $directory );

        if ( $foundFiles ) {

            foreach ($foundFiles as $file) {

                $this->logger->notice('[Clearing] Removing ' . $file);

                $unlinkSuccess = unlink( $file );
                if ( !$unlinkSuccess )
                {
                    $this->logger->error('[Clearing] Failed to remove ' . $file);

                    $success = false;
                    continue;
                }

            }
        }

        $this->logger->notice("[Clearing] Cleared compiled files and manifest files relating to packages.");
        return $success;
    }

}