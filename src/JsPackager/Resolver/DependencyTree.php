<?php
/**
 * Dependency Tree represents a hierarchy of dependent files through JsPackager\File objects and provides
 * methods to convert them down into flat arrays in proper order, as well as pull various file types.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Resolver;

use JsPackager\Annotations\AnnotationHandlers\IsMarkedNoCompiledHandler;
use JsPackager\Annotations\AnnotationHandlers\RequireRemote;
use JsPackager\Annotations\AnnotationHandlers\RequireRemoteStyleAnnotationHandler;
use JsPackager\Annotations\AnnotationHandlers\RootAnnotationHandler;
use JsPackager\Annotations\AnnotationOrderMapping;
use JsPackager\Annotations\AnnotationParser;
use JsPackager\Annotations\AnnotationsToAssocArraysService;
use JsPackager\Annotations\FileToDependencySetsService;
use JsPackager\Compiler\DependencySet;
use JsPackager\DependencyFileInterface;
use JsPackager\File;
use JsPackager\Helpers\FileHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
// todo refactor users of this to use resolvers and have resolver use this
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
    public $remoteFolderPath = 'shared';


    /**
     * @var String
     */
    public $remoteSymbol;


    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var bool
     */
    public $mutingMissingFileExceptions = false;

    /**
     * @var string
     */
    private $filePath;

    /**
     * @var null|string
     */
    private $testsSourcePath = null;


    private $dependencyTreeParser;

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
    public function __construct( $filePath, $testsSourcePath = null, $muteMissingFileExceptions = false, LoggerInterface $logger = null, $sharedPath = 'shared', $remoteSymbol = '@remote', FileHandler $fileHandler ) {
// todo some of this can go into AnnotationBasedResolverContext - which may need to be renamed after since it may help in more than that or just be confined to this resolution stage
// todo move remoteFolderPath into AnnotationBasedResolverContext
// todo move testsSourcePath into AnnotationBasedResolverContext
// todo move $muteMissingFileExceptions into AnnotationBasedResolverContext
        $this->filePath = $filePath;
        $this->testsSourcePath = $testsSourcePath;
        $this->mutingMissingFileExceptions = $muteMissingFileExceptions;
        if ( $logger === null ) {
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
        }
        $this->remoteFolderPath = $sharedPath;
        $this->remoteSymbol = $remoteSymbol;
        $this->fileHandler = $fileHandler;

    }

    /**
     * @return mixed
     */
    public function createDefaultDependencyTreeParser()
    {
        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            $this->remoteFolderPath, $this->remoteSymbol, $this->mutingMissingFileExceptions, $this->logger
        );
        $requireRemoteHandler = new RequireRemote(
            $this->remoteFolderPath, $this->remoteSymbol, $this->mutingMissingFileExceptions, $this->logger
        );

        $annotationResponseHandlerMapping = array(
            'requireRemote'     => array($requireRemoteHandler, 'doAnnotation_requireRemote' ),
            'require'           => array($requireRemoteHandler, 'doAnnotation_require' ),
            'requireRemoteStyle'=> array($requireRemoteStyleHandler, 'doAnnotation_requireRemoteStyle' ),
            'requireStyle'      => array($requireRemoteHandler, 'doAnnotation_requireStyle' ),
            'tests'             => array($requireRemoteHandler, 'doAnnotation_tests' ),
            'testsRemote'       => array($requireRemoteHandler, 'doAnnotation_testsRemote' ),
            'root'              => array($rootHandler, 'doAnnotation_root' ),
            'nocompile'         => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $annParser = new AnnotationParser(
            $annotationResponseHandlerMapping,
            $this->testsSourcePath,
            $this->logger,
            $this->fileHandler
        );
        $dependencyTreeParser = new DependencyTreeParser(
            $annParser,
            $this->remoteSymbol,
            $this->remoteFolderPath,
            $this->testsSourcePath,
            $this->logger,
            $this->mutingMissingFileExceptions,
            $this->fileHandler
        );
        $this->configureTreeParser( $dependencyTreeParser );
        $this->dependencyTreeParser = $dependencyTreeParser;
        return $dependencyTreeParser;
    }

    /**
     * Get a DependencyTreeParser
     *
     * @return DependencyTreeParser
     */
    protected function getDependencyTreeParser()
    {
        return ( $this->dependencyTreeParser ?
            $this->dependencyTreeParser : $this->createDefaultDependencyTreeParser() );
    }

    /**
     * @param DependencyTreeParser $treeParser
     */
    protected function configureTreeParser($treeParser)
    {
        if ( $this->mutingMissingFileExceptions ) {
            $treeParser->muteMissingFileExceptions();
        } else {
            $treeParser->unMuteMissingFileExceptions();
        }
    }

    /**
     * Get the raw nested File object hierarchy representing this dependency tree
     *
     * @return DependencyFileInterface|null
     * @throws \Exception
     */
    public function getTree() {
        if ( !$this->dependencyTreeRootFile ) {

            $treeParser = $this->getDependencyTreeParser();

            $this->dependencyTreeRootFile = $treeParser->parseFile( $this->getFilePath(), false );

            if ( !$this->dependencyTreeRootFile ) {
                throw new \Exception('No file tree parsed');
            }

        }

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
        /**
         * @var DependencyFileInterface $thisTree
         */
        $thisTree = $this->getTree();
        if ( !$thisTree ) {
            throw new \Exception('No File returned!');
        }

        $annotationsToAssocArraysService = new AnnotationsToAssocArraysService( $this->logger );

        $flattenedFile = $annotationsToAssocArraysService->flattenFileToAssocArray( $thisTree, $respectRootPackages );

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

        $annotationsToAssocArraysService = new AnnotationsToAssocArraysService( $this->logger );

        $flattenedFile = $annotationsToAssocArraysService->flattenFileToAssocArray( $thisTree, $respectRootPackages );

        // This is faster than array_unique and doesn't cause gaps in array keys
        $flattenedFile['stylesheets'] = array_merge(array_keys(array_flip($flattenedFile['stylesheets'])));
        $flattenedFile['scripts'] = array_merge(array_keys(array_flip($flattenedFile['scripts'])));

        return $flattenedFile;
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

        $service = new FileToDependencySetsService( $this->logger );

        $uniquePackages = $service->getDependencySets( $thisTree );

        return $uniquePackages;
    }


    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * @param string $filePath
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
        $this->invalidateDependencyTree();
    }

    /**
     * @return null|string
     */
    public function getTestsSourcePath()
    {
        return $this->testsSourcePath;
    }

    /**
     * @param null|string $testsSourcePath
     */
    public function setTestsSourcePath($testsSourcePath)
    {
        $this->testsSourcePath = $testsSourcePath;
        $this->invalidateDependencyTree();
    }


    /**
     * Reset the cached dependencyTreeRootFile, if there is one, causing it to be recalculated on next use.
     */
    private function invalidateDependencyTree()
    {
        $this->dependencyTreeRootFile = null;
    }

}
