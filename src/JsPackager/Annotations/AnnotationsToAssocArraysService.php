<?php

namespace JsPackager\Annotations;

use JsPackager\DependencyFileInterface;
use JsPackager\File;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class AnnotationsToAssocArraysService parses a File's metadata for annotation data to flatten it into an array of FileReps
 * @package JsPackager\Annotations
 */
class AnnotationsToAssocArraysService {

    /**
     * @var LoggerInterface
     */
    public $logger;

    public function __construct( LoggerInterface $logger ) {
        $this->logger = $logger;
    }


    /**
     * Recursively converts a File's object hierarchy into flattened arrays with both scripts and stylesheets in
     * separate, ordered arrays.
     *
     * @param DependencyFileInterface $file
     * @param bool $respectingRootPackages Pass true to respect @root annotations, detecting packages. False to
     * assume all dependent files should be included.
     * @return array
     */
    public function flattenFileToAssocArray( DependencyFileInterface $file, $respectingRootPackages = false )
    {
        $this->logger->debug("flattenFile called for '{$file->getPath()}'.'");
        $this->logger->debug( ( ($respectingRootPackages) ? 'Respecting' : 'Not respecting' ) . ' root packages.');
        $flattenedFile = array();
        $flattenedStylesheets = array();
        $metaData = $file->getMetaData();
        $fileIsRoot = $metaData['isRoot'];
        $fileIsNotRoot = !$fileIsRoot;
        $fileIsNotAPackage = $fileIsNotRoot;
        $notRespectingRootPackages = !$respectingRootPackages;

        // If we are at a package and going no further, pull in any of this package's packages
        if ( $respectingRootPackages && $fileIsRoot ) {
            $this->logger->notice("Found a root file. Pulling in its packages/manifest files...");
            $this->parseFileAsPackageRoot($file, $flattenedStylesheets, $flattenedFile);
        }

        // If we are respecting root packages and at one, skip, otherwise, pull in all dependent scripts
        if ( ( $notRespectingRootPackages ) || ( $respectingRootPackages && $fileIsNotAPackage ) ) {
            $this->logger->notice("Found a non-root file (or a root and are not respecting packages). Scanning {$file->getPath()} for dependencies...");
            $this->parseAnnotationsFromFile($file, $respectingRootPackages, $flattenedStylesheets, $flattenedFile);
        }

        // Add the root file itself
        $flattenedFile[] = $file->getPath();

        return array(
            'scripts' => $flattenedFile,
            'stylesheets' => $flattenedStylesheets,
        );
    }

    /**
     * @param DependencyFileInterface $file
     * @param Array $flattenedStylesheets
     * @param Array$flattenedFile
     */
    private function parseFileAsPackageRoot(DependencyFileInterface $file, Array &$flattenedStylesheets, Array &$flattenedFile)
    {
        // TODO Handle order mapping inside packages
        $this->logger->notice("Parsing {$file->getPath()} as a package root file. Pulling in its packages/manifest files...");

        $metaData = $file->getMetaData();
        foreach ( $metaData['stylesheets'] as $stylesheetFile ) {
            $flattenedStylesheets[] = $stylesheetFile;
        }

        foreach ( $metaData['packages'] as $packagePath ) {
            $flattenedFile[] = $packagePath;
        }
    }


    /**
     * @param DependencyFileInterface $file
     * @param bool $respectingRootPackages
     * @param Array $flattenedStylesheets
     * @param Array $flattenedFile
     */
    private function parseAnnotationsFromFile(DependencyFileInterface $file, $respectingRootPackages, Array &$flattenedStylesheets, Array &$flattenedFile)
    {
        $this->logger->notice("Parsing {$file->getPath()} as a dependency file. Pulling in its dependencies via annotation mapping...");

        /**
         * TODO this should probably exist in File or in the object representing the array
         */
        /**
         * @var AnnotationOrderMapping $aOMEntry
         */
        $metaData = $file->getMetaData();
        $annotationOrderMap = $metaData['annotationOrderMap'];
        $anns = $annotationOrderMap->getAnnotationMappings();
        foreach ( $anns as $aOMEntry ) {
            $orderMapEntryAnnotationType = $aOMEntry->getAnnotationName();
            $orderMapEntryBucketIndex = $aOMEntry->getAnnotationIndex();

            if ( $orderMapEntryAnnotationType === 'requireStyle' || $orderMapEntryAnnotationType === 'requireRemoteStyle' )
            {
                $styleFile = $metaData['stylesheets'][$orderMapEntryBucketIndex];
                $flattenedStylesheets[] = $styleFile;
            }
            else if ( $orderMapEntryAnnotationType === 'require' || $orderMapEntryAnnotationType === 'requireRemote' )
            {
                $scriptFile = $metaData['scripts'][$orderMapEntryBucketIndex];

                $flattenedDependency = $this->flattenFileToAssocArray($scriptFile, $respectingRootPackages);
                $flattenedFile = array_merge_recursive($flattenedFile, $flattenedDependency['scripts']);

                // More stylesheets
                $flattenedStylesheets = array_merge_recursive($flattenedStylesheets, $flattenedDependency['stylesheets']);
            }
            else
            {
                $this->logger->warning( 'Unknown annotation ' . $orderMapEntryAnnotationType . ' encountered.' );
                var_dump( 'Unknown annotation ' . $orderMapEntryAnnotationType . ' encountered' );
            }

        }
    }


}