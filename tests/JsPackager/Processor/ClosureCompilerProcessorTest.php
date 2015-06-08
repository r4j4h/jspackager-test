<?php
namespace JsPackagerTest;

use JsPackager\Compiler\DependencySet;
use JsPackager\File;
use JsPackager\DependencyTree;
use JsPackager\Compiler;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\Processor\ClosureCompilerProcessor;


class ClosureCompilerProcessorTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    /******************************************************************
     * generateClosureCompilerCommandString
     *****************************************************************/

    /**
     * @runInSeparateProcess
     */
    public function testGenerateClosureCompilerCommandStringRespectsCompilationLevel()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $processor = new ClosureCompilerProcessor();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $dependencySet->dependencies )
        );

        $this->assertContains(
            '--compilation_level=' . $processor::GCC_COMPILATION_LEVEL,
            $commandString,
            "Command string should set the compilation level to that of the class's constant"
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testGenerateClosureCompilerCommandStringIncludesGivenFiles()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $processor = new ClosureCompilerProcessor();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $dependencySet->dependencies )
        );

        foreach( $dependencySet->dependencies as $dependency )
        {
            $this->assertContains(
                "--js \"{$dependency}\"",
                $commandString
            );

        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testGenerateClosureCompilerCommandStringUsesDetailLevel3()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $processor = new ClosureCompilerProcessor();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $dependencySet->dependencies )
        );

        $this->assertContains(
            '--summary_detail_level=3',
            $commandString,
            "Command string should set summary_detail_level to 3"
        );
    }

}
