<?php

namespace JsPackager\Unit;

use JsPackager\CompiledFileAndManifest\FilenameConverter;
use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\DependencySetCollection;
use JsPackager\Helpers\FileHandler;
use JsPackager\ManifestContentsGenerator;
use JsPackager\Processor\ClosureCompilerProcessor;
use JsPackager\Processor\CompiledAndManifestProcessor;
use JsPackager\Processor\ManifestGeneratingProcessor;
use JsPackager\Processor\SimpleProcessorParams;
use Psr\Log\NullLogger;

class CompiledFileAndManifestProcessorTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessCombinesScriptsInDependencySetIntoOneCompiledScript()
    {
        // new SimpleProcessorParams( $dependencySet->dependencies );
        $params = new SimpleProcessorParams(array(
            "tests/JsPackager/fixtures/1_dep/dep_1.js",
            "tests/JsPackager/fixtures/1_dep/main.js"
        ));


        $logger = new NullLogger();
        $compiledAndManifestProcessor = new CompiledAndManifestProcessor(
        // Compile & Concatenate via Google closure Compiler Jar
            new ClosureCompilerProcessor($logger, ''),
            new ManifestContentsGenerator(
                '@remote', 'shared', $logger
            ),
            $logger
        );

//        $newParams = $compiledAndManifestProcessor->process($params);

//        $this->assertEquals('tests/JsPackager/fixtures/1_dep/main.manifeset.js', $newParams[0]);
//        $this->assertEquals('tests/JsPackager/fixtures/1_dep/main.compiled.js', $newParams[1]);

        // todo these strings should become Files and we do getPath that way getContents can return the results
        // error codes etc are for file writing and will be contained within the outputters!
    }

    public function testManifestGeneratingProcessorProcessesManifests()
    {
        $depSets = new DependencySetCollection();
        $depSets->appendDependencySet(
            new DependencySet(
                array('@remote/lib/foo.css'),
                array(),
                array(
                    "lib/foo.js",
                    "lib/main.js"
                ),
                array()
            )
        );
        $depSets->appendDependencySet(
            new DependencySet(
                array('@remote/cdn_vers/tests/JsPackager/fixtures/1_dep/foo.css'),
                array(
                    "lib/main.js"
                ),
                array(
                    "tests/JsPackager/fixtures/1_dep/dep_1.js",
                    "tests/JsPackager/fixtures/1_dep/main.js"
                ),
                array()
            )
        );

        $logger = new NullLogger();

        $processor = new ManifestGeneratingProcessor('shared', '@remote', new FileHandler(), new ManifestContentsGenerator('@remote', 'shared', $logger ), $logger, new FilenameConverter('compiled', 'manifest'));

        /** @var DependencySet $setsa */
        $params = new SimpleProcessorParams( $depSets->getDependencySets()[0]->dependencies );
        $params->dependencySet = $depSets;

        $newParams = $processor->process($params);

//        $this->assertEquals('tests/JsPackager/fixtures/1_dep/main.manifeset.js', $newParams[0]);
//        $this->assertEquals('tests/JsPackager/fixtures/1_dep/main.compiled.js', $newParams[1]);

    }

    public function testProcessListsAllOtherFilesInADependencySetIntoAManifest()
    {

    }

    public function testProcessDoesThisForEachDependencySetInOrder()
    {

    }

    public function testProcessManifesetscontainerOTherManifestsContentsSoOnlyOneManifestReadHydratesEntireTree()
    {

    }

    public function testProcessConvertsFilesInRemotePathWithIsRootToRemoteAnnotationInManifest()
    {

    }

    public function testProcessRemoteFilesAreCompiledIn()
    {

    }

    // manifest
    // looks for packages and stylesheets and combines into newline separated file named <x>.manifest.js
    // appends itself to files in arrays prepending the main dependency as it is now a dependency
    // [foo, bar, main] -> [main.manifest,main]

    // compiledfile
    // looks for root files and combines all non-root dependencies' pathnames and passes to gcc returning result as new file named <x>.compiled.js
    // replaces files in arrays
    // [foo, bar, main] -> [main.compiled.js]
}