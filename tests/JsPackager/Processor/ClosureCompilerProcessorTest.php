<?php
namespace JsPackagerTest;

use JsPackager\Compiler;
use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\Processor\ClosureCompilerProcessor;


class ClosureCompilerProcessorTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';


    /******************************************************************
     * generateClosureCompilerCommandString
     *****************************************************************/

    public function testGenerateClosureCompilerCommandStringRespectsCompilationLevel()
    {
        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = new ClosureCompilerProcessor();

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $filesList )
        );

        $this->assertContains(
            '--compilation_level=' . $processor::GCC_COMPILATION_LEVEL,
            $commandString,
            "Command string should set the compilation level to that of the class's constant"
        );
    }

    public function testGenerateClosureCompilerCommandStringIncludesGivenFiles()
    {
        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = new ClosureCompilerProcessor();

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $filesList )
        );

        foreach( $filesList as $dependency )
        {
            $this->assertContains(
                "--js \"{$dependency}\"",
                $commandString
            );

        }
    }

    public function testGenerateClosureCompilerCommandStringUsesDetailLevel3()
    {
        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = new ClosureCompilerProcessor();

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $filesList )
        );

        $this->assertContains(
            '--summary_detail_level=3',
            $commandString,
            "Command string should set summary_detail_level to 3"
        );
    }

    public function testGenerateClosureCompilerCommandStringAcceptsExtraCommandParams()
    {

        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = new ClosureCompilerProcessor();
        $processor->extraCommandParams = '--checks-only';

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $filesList )
        );

        $this->assertContains(
            '--checks-only',
            $commandString,
            "Command string should include extraCommandParams"
        );
    }

}
