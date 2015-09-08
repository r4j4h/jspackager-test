<?php

namespace JsPackager\Processor;

use JsPackager\Annotations\AnnotationParser;
use JsPackager\Annotations\FileToDependencySetsService;
use JsPackager\Annotations\RemoteAnnotationStringService;
use JsPackager\CompiledFileAndManifest\FilenameConverter;
use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\DependencySetCollection;
use JsPackager\ContentBasedFile;
use JsPackager\File;
use JsPackager\Helpers\FileHandler;
use JsPackager\ManifestContentsGenerator;
use JsPackager\Processor\ProcessingResult;
use JsPackager\Processor\SimpleProcessorInterface;
use JsPackager\Processor\SimpleProcessorParams;
use JsPackager\Resolver\DependencyTreeParser;
use Psr\Log\LoggerInterface;

class ManifestGeneratingProcessor implements DependencySetBasedProcessorInterface
{
    /**
     * @var ManifestContentsGenerator
     */
    private $manifestContentsGenerator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct($remoteFolderPath, $remoteSymbol, FileHandler $fileHandler, ManifestContentsGenerator $generator, LoggerInterface $logger, FilenameConverter $filenameConverter)
    {
        $this->manifestContentsGenerator = $generator;
        $this->logger = $logger;
        $this->remoteFolderPath = $remoteFolderPath;
        $this->remoteSymbol = $remoteSymbol;
        $this->fileHandler = $fileHandler;

        $this->remoteAnnotationStringTransformationService = new RemoteAnnotationStringService(
            $this->remoteSymbol,
            $this->remoteFolderPath
        );

        $this->filenameConverter = $filenameConverter; //new FilenameConverter('compiled', 'manifest');

        $this->mutingMissingFileExceptions = false;

    }

    /**
     * @param DependencySetCollection $dependencySets
     * @return DependencySetCollection
     */
    public function process(DependencySetCollection $dependencySets)
    {
        $newDepSets = new DependencySetCollection();
        $rollingPathsMarkedNoCompile = array();

        foreach( $dependencySets as $dependencySetIndex => $dependencySet )
        {
            /**
             * @var DependencySet $dependencySet
             */
            $totalDependencies = count( $dependencySet->dependencies );

            // Expand out any @remote annotations
            foreach( $dependencySet->dependencies as $idx => $dependency ) {
                $dependencySet->dependencies[$idx] = $this->remoteAnnotationStringTransformationService->
                                                                        expandOutRemoteAnnotation( $dependency );
            }

            $lastDependency = $dependencySet->dependencies[ $totalDependencies - 1 ];

            $rootFile = new File($lastDependency, true, $this->fileHandler);

            $rootFilePath = $rootFile->getDirName();
            $rootFilename = $rootFile->getFileName();

//            $compiledFilename = $this->filenameConverter->getCompiledFilename( $rootFilename );
            $manifestFilename = $this->filenameConverter->getManifestFilename( $rootFilename );

            $rollingPathsMarkedNoCompile = array_merge(
                $rollingPathsMarkedNoCompile,
                $dependencySet->pathsMarkedNoCompile
            );

            $compiledFileManifest = $this->assembleManifest(
                $dependencySet, $dependencySetIndex, $dependencySets,
                $rootFilename, $totalDependencies, $rootFilePath,
                $manifestFilename, $rollingPathsMarkedNoCompile);

            $deps = $dependencySet->dependencies;
            array_push($deps, new ContentBasedFile($compiledFileManifest, 'baz.manifest', array(
                'type' => 'manifest'
            )));

            $newDepSet = new DependencySet(
                $dependencySet->stylesheets,
                $dependencySet->packages,
                $deps,
                $dependencySet->pathsMarkedNoCompile
            );

            // add everyone we didn't include to array
            // build new virtual file and add it to array
            $newDepSets->appendDependencySet($dependencySet);

        }

        return $newDepSets;
    }


    /**
     * Assemble and compile a manifest file for a DependencySet
     * @param $dependencySet
     * @param $dependencySetIndex
     * @param $dependencySets
     * @param $rootFilename
     * @param $totalDependencies
     * @param $rootFilePath
     * @param $manifestFilename
     * @return null|string
     */
    protected function assembleManifest($dependencySet, $dependencySetIndex, DependencySetCollection $dependencySets,
                                        $rootFilename, $totalDependencies, $rootFilePath, $manifestFilename,
                                        $rollingPathsMarkedNoCompile)
    {
        $this->logger->debug("Assembling manifest for root file '{$rootFilename}'...");

        if ($dependencySetIndex && $dependencySetIndex > 0) {
            $dependencySetIndexIterator = $dependencySetIndex;
            while ($dependencySetIndexIterator > 0) {
                // Roll in dependent package's stylesheets so we only need to read this manifest
                $this->logger->debug(
                    "Using dependency set and it's dependency set's stylesheets so that manifests are all-inclusive"
                );
                $lastDependencySet = $dependencySets->peekPrevious();
                $stylesheets = array_merge($lastDependencySet->stylesheets, $dependencySet->stylesheets);
                $stylesheets = array_unique($stylesheets);
                $dependencySetIndexIterator--;
            }
        } else {
            $this->logger->debug("Using dependency set's stylesheets");
            $stylesheets = $dependencySet->stylesheets;
        }

        if (count($dependencySet->stylesheets) > 0 || $totalDependencies > 1) {
            // Build manifest first
            $compiledFileManifest = $this->manifestContentsGenerator->generateManifestFileContents(
                $rootFilePath . '/',
                $dependencySet->packages,
                $stylesheets,
                $rollingPathsMarkedNoCompile
            );
        } else {
            $this->logger->debug(
                "Skipping building manifest '{$manifestFilename}' because file has no other dependencies than itself."
            );
            $compiledFileManifest = null;
        }

        $this->logger->debug("Built manifest.");
        return $compiledFileManifest;
    }



}