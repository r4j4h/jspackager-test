<?php
/**
 * Dependency Tree represents a hierarchy of dependent files through JsPackager\File objects and provides
 * methods to convert them down into flat arrays in proper order, as well as pull various file types.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager;


use JsPackager\Compiler\DependencySet;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DependencyTree
{
    private $dependencyTreeRootFile = null;

    /**
     * Regexp patterns for detecting file types via filename
     */
    const SCRIPT_EXTENSION_PATTERN     = '/.js$/';
    const STYLESHEET_EXTENSION_PATTERN = '/.css$/';


    /**
     * @var String
     */
    public $sharedFolderPath = 'shared';


    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * Constructor for DependencyTree
     * @param string $filePath
     * @param string $testsSourcePath Optional. For @tests annotations, the source scripts root path with no trailing
     * slash.
     * @param bool $muteMissingFileExceptions Optional. If true, missing file exceptions will not be thrown and
     * will be carried through as if they were there. Note: Obviously they will not be parsed for children.
     * @param LoggerInterface $logger
     * @param String $sharedPath
     * @throws Exception\Recursion If the dependent files have a circular dependency
     * @throws Exception\MissingFile Through internal File object if $filePath does not point to a valid file
     */
    public function __construct( $filePath, $testsSourcePath = null, $muteMissingFileExceptions = false, LoggerInterface $logger = null, $sharedPath = 'shared' ) {
        if ( $logger === null ) {
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
        }

        $this->sharedFolderPath = $sharedPath;

        $treeParser = $this->getDependencyTreeParser();

        if ( $muteMissingFileExceptions )
        {
            $treeParser->muteMissingFileExceptions();
        }

        $this->dependencyTreeRootFile = $treeParser->parseFile( $filePath, $testsSourcePath, false );
    }

    /**
     * Get a DependencyTreeParser
     *
     * @return DependencyTreeParser
     */
    protected function getDependencyTreeParser()
    {
        $treeParser = new DependencyTreeParser();
        $treeParser->logger = $this->logger;
        $treeParser->sharedFolderPath = $this->sharedFolderPath;
        return $treeParser;
    }

    /**
     * Get the raw nested File object hierarchy representing this dependency tree
     *
     * @return File|null
     * @throws \Exception
     */
    public function getTree() {
        if ( !$this->dependencyTreeRootFile )
            throw new \Exception('No file tree parsed');

        return $this->dependencyTreeRootFile;
    }

    /**
     * Converts a nested Dependency Tree File object hierarchy into a flat array.
     *
     * @param boolean $respectRootPackages Optional. Whether to respect root packages, defaults to false.
     * @return array
     */
    public function flattenDependencyTree( $respectRootPackages = false )
    {
        $thisTree = $this->getTree();

        $flattenedFile = $this->flattenFileToAssocArray( $thisTree, $respectRootPackages );

        // Combine stylesheets and then scripts for proper dependency ordering
        $flattenedTree = array_merge_recursive( $flattenedFile['stylesheets'], $flattenedFile['scripts'] );

        // This is faster than array_unique and doesn't cause gaps in array keys
        $flattenedTree = array_merge(array_keys(array_flip($flattenedTree)));

        return $flattenedTree;
    }

    /**
     * Converts a nested Dependency Tree File object hierarchy into a flat array.
     *
     * @param boolean $respectRootPackages Optional. Whether to respect root packages, defaults to false.
     * @return array
     */
    public function flattenDependencyTreeIntoAssocArrays( $respectRootPackages = false )
    {
        $thisTree = $this->getTree();

        $flattenedFile = $this->flattenFileToAssocArray( $thisTree, $respectRootPackages );

        // This is faster than array_unique and doesn't cause gaps in array keys
        $flattenedFile['stylesheets'] = array_merge(array_keys(array_flip($flattenedFile['stylesheets'])));
        $flattenedFile['scripts'] = array_merge(array_keys(array_flip($flattenedFile['scripts'])));

        return $flattenedFile;
    }

    /**
     * Recursively converts a File's object hierarchy into flattened arrays with both scripts and stylesheets in
     * separate, ordered arrays.
     *
     * (Helper function for flattenDependencyTree)
     *
     * @param File $file
     * @param bool $respectingRootPackages Pass true to respect @root annotations, detecting packages. False to
     * assume all dependent files should be included.
     * @return array
     */
    private function flattenFileToAssocArray( $file, $respectingRootPackages = false )
    {
        $this->logger->debug("flattenFile called for '{$file->getFullPath()}'.'");
        $this->logger->debug( ( ($respectingRootPackages) ? 'Respecting' : 'Not respecting' ) . ' root packages.');
        $flattenedFile = array();
        $flattenedStylesheets = array();
        $fileIsNotAPackage = !$file->isRoot;
        $notRespectingRootPackages = !$respectingRootPackages;



        // If we are at a package and going no further, pull in any of this package's packages
        if ( $respectingRootPackages && $file->isRoot ) {

            // TODO Handle order mapping inside packages
            $this->logger->notice("Found a root file. Pulling in its packages/manifest files...");

            foreach( $file->stylesheets as $stylesheetFile )
            {
                $flattenedStylesheets[] = $stylesheetFile;
            }

            foreach( $file->packages as $packagePath )
            {
                $flattenedFile[] = $packagePath;
            }

        }

        // If we are respecting root packages and at one, skip, otherwise, pull in all dependent scripts
        if ( ( $notRespectingRootPackages ) || ( $respectingRootPackages && $fileIsNotAPackage ) ) {
            $this->logger->notice("Found a non-root file (or a root and are not respecting packages). Scanning for dependencies...");

            foreach( $file->annotationOrderMap as $aOMEntry )
            {
                $orderMapEntryAnnotationType = $aOMEntry['action'];
                $orderMapEntryBucketIndex = $aOMEntry['annotationIndex'];

                if ( $orderMapEntryAnnotationType === 'requireStyle' || $orderMapEntryAnnotationType === 'requireRemoteStyle' )
                {
                    $styleFile = $file->stylesheets[ $orderMapEntryBucketIndex ];
                    $flattenedStylesheets[] = $styleFile;
                }
                else if ( $orderMapEntryAnnotationType === 'require' || $orderMapEntryAnnotationType === 'requireRemote' )
                {
                    $scriptFile = $file->scripts[ $orderMapEntryBucketIndex ];

                    $flattenedDependency = $this->flattenFileToAssocArray( $scriptFile, $respectingRootPackages );
                    $flattenedFile = array_merge_recursive( $flattenedFile, $flattenedDependency['scripts']);

                    // More stylesheets
                    $flattenedStylesheets = array_merge_recursive( $flattenedStylesheets, $flattenedDependency['stylesheets'] );
                }
                else
                {
                    $this->logger->warning('Unknown annotation ' . $orderMapEntryAnnotationType . ' encountered.');
                    var_dump('Unknown annotation ' . $orderMapEntryAnnotationType . ' encountered');
                }

            }

        }

        // Add the root file itself
        $flattenedFile[] = $file->getFullPath();

        return array(
            'scripts' => $flattenedFile,
            'stylesheets' => $flattenedStylesheets,
        );
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
    public function getDependencySets()
    {
        $this->logger->debug("getDependencySets called.");

        // In the context of the this function:
        //  A collection is a set of a set of dependent files and a set of dependent packages
        //  A root package is the file that depends on the other files and packages
        //  The main file is the file passed to this function
        $thisTree = $this->getTree();
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
    private function getDependencySetFiles( $file )
    {
        $this->logger->debug("getDependencySetFiles called with '{$file->getFullPath()}'.");

        $nonRootFilePaths = array();
        $rootFilePaths = array();

        $rootsToVisit = array();
        $stylesheetFilePaths = array();

        $pathsMarkedNoCompile = array();

        foreach( $file->annotationOrderMap as $aOMEntry )
        {
            $orderMapEntryAnnotationType = $aOMEntry['action'];
            $orderMapEntryBucketIndex = $aOMEntry['annotationIndex'];

            if ( $orderMapEntryAnnotationType === 'requireStyle' || $orderMapEntryAnnotationType === 'requireRemoteStyle' )
            {
                $styleFile = $file->stylesheets[ $orderMapEntryBucketIndex ];
                $stylesheetFilePaths[] = $styleFile;
            }
            else if ( $orderMapEntryAnnotationType === 'require' || $orderMapEntryAnnotationType === 'requireRemote' )
            {
                $scriptFile = $file->scripts[ $orderMapEntryBucketIndex ];

                if ( !$scriptFile->isRoot )
                {
                    // Add any children to the list
                    $children = $this->getDependencySetFiles( $scriptFile );

                    if ( count( $children['nonRootFilePaths'] ) > 0 )
                    {
                        $nonRootFilePaths = array_merge( $nonRootFilePaths, $children['nonRootFilePaths'] );
                    }

                    if ( count( $children['rootFilePaths'] ) > 0 )
                    {
                        $rootFilePaths = array_merge( $rootFilePaths, $children['rootFilePaths'] );
                    }

                    if ( count( $children['rootsToVisit'] ) > 0 )
                    {
                        $rootsToVisit = array_merge( $rootsToVisit, $children['rootsToVisit'] );
                    }

                    if ( count( $children['stylesheetFilePaths'] ) > 0 )
                    {
                        $stylesheetFilePaths = array_merge( $stylesheetFilePaths, $children['stylesheetFilePaths'] );
                    }
                }
                else if ( $scriptFile->isRoot )
                {
                    // Add to list, and stop here, going into this guy later for a new list
                    $rootFilePaths[] = $scriptFile->getFullPath();
                    $rootsToVisit[] = $scriptFile;
                }

                if ( $scriptFile->isMarkedNoCompile ) {
                    $pathsMarkedNoCompile[] = $scriptFile->getFullPath();
                }
            }
            else
            {
                var_dump('Unknown annotation ' . $orderMapEntryAnnotationType . ' encountered');
            }
        }

        // Add this file to the list
        $nonRootFilePaths[] = $file->getFullPath();


        if ( $file->isMarkedNoCompile ) {
            $pathsMarkedNoCompile[] = $file->getFullPath();
        }


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
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    protected function isJavaScriptFile($file) {
        return preg_match( self::SCRIPT_EXTENSION_PATTERN, $file );
    }

    /**
     * Function for array_filter
     * @param  string  $file Filename
     * @return boolean       True if $file has .js extension
     */
    private function isStylesheetFile($file) {
        return preg_match( self::STYLESHEET_EXTENSION_PATTERN, $file );
    }

}
