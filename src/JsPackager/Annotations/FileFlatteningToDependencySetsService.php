<?php

namespace JsPackager\Annotations;

use JsPackager\Compiler\DependencySet;
use JsPackager\File;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FileFlatteningToDependencySetsService
{

    /**
     * @var LoggerInterface
     */
    public $logger;

    public function __construct(LoggerInterface $logger = null) {
        if ( $logger === null ) {
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * Get a dependency ordered set of files, a package, and any dependent packages, also in dependency order.
     *
     * In other words, get an ordered array of files and the files and packages they depend on.
     *
     * The order of the array is in order of dependency. Each set may depend on one before it, and the last set
     * probably depends on all the prior sets.
     *
     * @return array    Array of DependencySet objects
     *  stylesheets   array  The file paths of any stylesheets this set of files depends on (should be
     *                       included on page w/ compiled file)
     *  packages      array  The file paths of any root package files this set of files depends on (should be
     *                       included on page w/ compiled file)
     *  dependencies  array  The file paths of any files this set of files depends on (should be compiled)
     */
    public function getDependencySets(File $thisTree)
    {
        $this->logger->debug("getDependencySets called.");

        // In the context of the this function:
        //  A collection is a set of a set of dependent files and a set of dependent packages
        //  A root package is the file that depends on the other files and packages
        //  The main file is the file passed to this function

        $rootPackages = array();

        // Consider the main file a starting root package to start the set collection
        $nonRootFiles = $this->getDependencySetFiles( $thisTree );
        $rootPackages[] = new DependencySet(
            $nonRootFiles['stylesheetFilePaths'],
            $nonRootFiles['rootFilePaths'],
            $nonRootFiles['nonRootFilePaths'],
            $nonRootFiles['pathsMarkedNoCompile']
        );

        // Hold a cache of sets that need to be visited
        $rootsToVisit = $nonRootFiles['rootsToVisit'];

        // Consider all remaining sets
        while ( count( $rootsToVisit ) > 0 )
        {
            // Buffer to hold any new sets we need to visit
            $newRootsToVisit = array();

            foreach( $rootsToVisit as $thisRootToVisit )
            {
                // Grab this set's file collection
                $furtherRoots = $this->getDependencySetFiles( $thisRootToVisit );
                $rootPackages[] = new DependencySet(
                    $furtherRoots['stylesheetFilePaths'],
                    $furtherRoots['rootFilePaths'],
                    $furtherRoots['nonRootFilePaths'],
                    $furtherRoots['pathsMarkedNoCompile']
                );

                // Add any new sets to examine to sets remaining to be examined list
                if ( count( $furtherRoots['rootsToVisit'] ) > 0 )
                {
                    $newRootsToVisit = array_merge( $newRootsToVisit, $furtherRoots['rootsToVisit'] );
                }
            }

            // Update looping threshold examination to include any remaining sets to examine
            $rootsToVisit = $newRootsToVisit;
        }

        // We are now finished, but our files are ordered from dependency to root, while our sets are
        // ordered from root to dependency, so let's flip so they both are ordered dependency to root
        // which is more consistent and more useful for the Compiler component.
        $rootPackages = array_reverse( $rootPackages );

        // Ensure we do not return redundant root packages
        $uniquePackages = array();
        foreach( $rootPackages as $thisRootPackage )
        {
            if ( !in_array( $thisRootPackage, $uniquePackages, false ) )
            {
                $uniquePackages[] = $thisRootPackage;
            }
        }

        return $uniquePackages;
    }

    /**
     * Get an array of ordered sets of files and packages
     *
     * (Helper function for getDependencySets)
     *
     * @param File $file
     * @return array
     */
    private function getDependencySetFiles( File $file )
    {
        $this->logger->debug("getDependencySetFiles called with '{$file->getFullPath()}'.");

        $nonRootFilePaths = array();
        $rootFilePaths = array();

        $rootsToVisit = array();
        $stylesheetFilePaths = array();

        $pathsMarkedNoCompile = array();

        $this->getDependencySetFilesFromFileObject($file, $nonRootFilePaths, $pathsMarkedNoCompile, $stylesheetFilePaths, $rootFilePaths, $rootsToVisit);


        // This is faster than array_unique and doesn't cause gaps in array keys
        $nonRootFilePaths = array_merge(array_keys(array_flip($nonRootFilePaths)));

        return array(
            'nonRootFilePaths' => $nonRootFilePaths,
            'rootFilePaths' => $rootFilePaths,
            'rootsToVisit' => $rootsToVisit,
            'stylesheetFilePaths' => $stylesheetFilePaths,
            'pathsMarkedNoCompile' => $pathsMarkedNoCompile,
        );
    }

    /**
     * @param $scriptFile
     * @param $nonRootFilePaths
     * @param $rootFilePaths
     * @param $rootsToVisit
     * @param $stylesheetFilePaths
     * @param $pathsMarkedNoCompile
     */
    private function handleJavaScriptDependencyAnnotationFromFile(File $scriptFile, &$nonRootFilePaths, &$rootFilePaths, &$rootsToVisit, &$stylesheetFilePaths, &$pathsMarkedNoCompile)
    {
        if (!$scriptFile->isRoot) {
            // Add any children to the list
            $children = $this->getDependencySetFiles($scriptFile);

            if (count($children['nonRootFilePaths']) > 0) {
                $nonRootFilePaths = array_merge($nonRootFilePaths, $children['nonRootFilePaths']);
            }

            if (count($children['rootFilePaths']) > 0) {
                $rootFilePaths = array_merge($rootFilePaths, $children['rootFilePaths']);
            }

            if (count($children['rootsToVisit']) > 0) {
                $rootsToVisit = array_merge($rootsToVisit, $children['rootsToVisit']);
            }

            if (count($children['stylesheetFilePaths']) > 0) {
                $stylesheetFilePaths = array_merge($stylesheetFilePaths, $children['stylesheetFilePaths']);
            }
        } else if ($scriptFile->isRoot) {
            // Add to list, and stop here, going into this guy later for a new list
            $rootFilePaths[] = $scriptFile->getFullPath();
            $rootsToVisit[] = $scriptFile;
        }

        if ($scriptFile->isMarkedNoCompile) {
            $pathsMarkedNoCompile[] = $scriptFile->getFullPath();
        }
    }

    /**
     * @param $styleFile
     * @param $stylesheetFilePaths
     */
    private function handleStylesheetDependencyAnnotationFromPath($styleFile, &$stylesheetFilePaths)
    {
        $stylesheetFilePaths[] = $styleFile;
    }

    /**
     * @param File $file
     * @param $nonRootFilePaths
     * @param $pathsMarkedNoCompile
     * @param $stylesheetFilePaths
     * @param $rootFilePaths
     * @param $rootsToVisit
     */
    private function getDependencySetFilesFromFileObject(File $file, &$nonRootFilePaths, &$pathsMarkedNoCompile, &$stylesheetFilePaths, &$rootFilePaths, &$rootsToVisit)
    {
        /**
         * @var AnnotationOrderMapping $aOMEntry
         */
        $anns = $file->annotationOrderMap->getAnnotationMappings();
        foreach ($anns as $aOMEntry) {
            $orderMapEntryAnnotationType = $aOMEntry->getAnnotationName();
            $orderMapEntryBucketIndex = $aOMEntry->getAnnotationIndex();

            if ($orderMapEntryAnnotationType === 'requireStyle' || $orderMapEntryAnnotationType === 'requireRemoteStyle') {
                $styleFile = $file->stylesheets[$orderMapEntryBucketIndex];
                $this->handleStylesheetDependencyAnnotationFromPath(
                    $styleFile,
                    $stylesheetFilePaths
                );
            } else if ($orderMapEntryAnnotationType === 'require' || $orderMapEntryAnnotationType === 'requireRemote') {
                $scriptFile = $file->scripts[$orderMapEntryBucketIndex];
                $this->handleJavaScriptDependencyAnnotationFromFile(
                    $scriptFile,
                    $nonRootFilePaths, $rootFilePaths, $rootsToVisit, $stylesheetFilePaths, $pathsMarkedNoCompile
                );
            } else {
                var_dump('Unknown annotation ' . $orderMapEntryAnnotationType . ' encountered');
            }
        }

        // Add this file to the list
        $nonRootFilePaths[] = $file->getFullPath();

        if ($file->isMarkedNoCompile) {
            $pathsMarkedNoCompile[] = $file->getFullPath();
        }
    }


}