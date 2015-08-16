<?php
namespace JsPackagerTest;

use JsPackager\Compiler;
use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\Processor\ClosureCompilerProcessor;
use JsPackager\Processor\ProcessingResult;
use JsPackager\Processor\SimpleProcessorParams;
use Psr\Log\NullLogger;


class ClosureCompilerProcessorTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';


    /******************************************************************
     * process
     *****************************************************************/

    public function testProcessTriesToRunsGoogleClosureCompilerJarWithGivenFile()
    {
        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = $this->getMock('JsPackager\Processor\ClosureCompilerProcessor', array('executeClosureCompilerCommand'), array(new NullLogger()) );

        $processor->expects($this->once())
            ->method('executeClosureCompilerCommand')
            ->with($this->stringContains('--js "somefilea.js"'))
            ->willReturn( array( 'stdout', 'stderr', 0, 1 ) );

        $processorParams = new SimpleProcessorParams( $filesList );
        /**
         * @var ProcessingResult $result
         */
        $result = $processor->process( $processorParams );

        $this->assertInstanceOf(
            'JsPackager\Processor\ProcessingResult',
            $result,
            'Returns a ProcessingResult'
        );

        $this->assertContains(
            'stdout',
            $result->output,
            "Uses returned GCC results to generate ProcessingResult"
        );
        $this->assertContains(
            'stderr',
            $result->err,
            "Uses returned GCC results to generate ProcessingResult"
        );

        $this->assertEquals(
            0,
            $result->returnCode,
            "Uses returned GCC results to generate ProcessingResult"
        );

        $this->assertEquals(
            1,
            $result->successful,
            "Uses returned GCC results to generate ProcessingResult"
        );

        $this->assertEquals(
            0,
            $result->numberOfErrors
        );
    }

    public function testProcessRunningGcc()
    {
        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = new ClosureCompilerProcessor(new NullLogger(), '');
        $processorParams = new SimpleProcessorParams( $filesList );
        $result = $processor->process( $processorParams );

        $this->assertInstanceOf(
            'JsPackager\Processor\ProcessingResult',
            $result,
            'Returns a ProcessingResult'
        );

        $this->assertContains(
            'Cannot read: somefilea.js',
            $result->err,
            "Properly returned GCC expected error of can't read somefilea"
        );

        $this->assertContains(
            'Cannot read: somefileb.js',
            $result->err,
            "Properly returned GCC expected error of can't read somefileb"
        );

        $this->assertEquals(2, $result->numberOfErrors, "Properly detects number of errors");
    }

    /******************************************************************
     * generateClosureCompilerCommandString
     *****************************************************************/

    public function testGenerateClosureCompilerCommandStringRespectsCompilationLevel()
    {
        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = new ClosureCompilerProcessor(new NullLogger());

        $commandString = ReflectionHelper::invoke(
            $processor,
            'generateClosureCompilerCommandString',
            array( $filesList )
        );

        $this->assertContains(
            '--compilation_level=' . $processor->gccCompilationLevel,
            $commandString,
            "Command string should set the compilation level to that of the class's constant"
        );
    }

    public function testGenerateClosureCompilerCommandStringIncludesGivenFiles()
    {
        $filesList = array( "somefilea.js", "somefileb.js" );

        $processor = new ClosureCompilerProcessor(new NullLogger(), '');

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

        $processor = new ClosureCompilerProcessor(new NullLogger(), '');

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

        $processor = new ClosureCompilerProcessor(new NullLogger(), '--checks-only');

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


    /******************************************************************
     * handleClosureCompilerOutput
     *****************************************************************/

    public function testHandlesNormalOutput()
    {
        $stdout = <<<STDOUT
window.dep_1=!0;window.dep_2=!0;window.main=!0;

STDOUT;
        $stderr = <<<STDERR
0 error(s), 0 warning(s)

STDERR;
        $returnCode = 0;
        $successful = true;

        $processor = new ClosureCompilerProcessor(new NullLogger(), '');
        $result = ReflectionHelper::invoke(
            $processor,
            'handleClosureCompilerOutput',
            array( $stdout, $stderr, $returnCode, $successful )
        );

        $this->assertInstanceOf(
            'JsPackager\Processor\ProcessingResult',
            $result,
            'Returns a ProcessingResult'
        );
        $this->assertEquals($stdout, $result->output);
        $this->assertEquals($stderr, $result->err);

        $this->assertEquals( true, $result->successful );
        $this->assertEquals( 0, $result->numberOfErrors );
    }

    public function testHandlesMissingFilesOutput()
    {
        $stdout = <<<STDOUT
STDOUT;
        $stderr = <<<STDERR
ERROR - Cannot read: somefilea.js

ERROR - Cannot read: somefileb.js

2 error(s), 0 warning(s)

STDERR;
        $returnCode = 0;
        $successful = true;

        $processor = new ClosureCompilerProcessor(new NullLogger(), '');
        $result = ReflectionHelper::invoke(
            $processor,
            'handleClosureCompilerOutput',
            array( $stdout, $stderr, $returnCode, $successful )
        );

        $this->assertInstanceOf(
            'JsPackager\Processor\ProcessingResult',
            $result,
            'Returns a ProcessingResult'
        );
        $this->assertEquals($stdout, $result->output);
        $this->assertEquals($stderr, $result->err);

        $this->assertEquals( false, $result->successful );
        $this->assertEquals( 2, $result->numberOfErrors );
    }

    public function testHandlesMalformedJavaScriptOutput()
    {
        $stdout = <<<STDOUT
STDOUT;
        $stderr = <<<STDERR
tests/JsPackager/fixtures/1_dep/dep_1.js:2: ERROR - Parse error. missing ; before statement
window.dep_1 1212qq3= true;
             ^

1 error(s), 0 warning(s)

STDERR;
        $returnCode = 0;
        $successful = true;

        $processor = new ClosureCompilerProcessor(new NullLogger(), '');
        /**
         * @type ProcessingResult $result
         */
        $result = ReflectionHelper::invoke(
            $processor,
            'handleClosureCompilerOutput',
            array( $stdout, $stderr, $returnCode, $successful )
        );

        $this->assertInstanceOf(
            'JsPackager\Processor\ProcessingResult',
            $result,
            'Returns a ProcessingResult'
        );

        $this->assertEquals($stdout, $result->output);
        $this->assertEquals($stderr, $result->err);

        $this->assertEquals( false, $result->successful );
        $this->assertEquals( 1, $result->numberOfErrors );
    }

    public function testHandlesValidJavaScriptOutputWithWarnings()
    {
        $stdout = <<<STDOUT
1==x;window.dep_1=!0;window.root_test="the pooh!";

STDOUT;

        $stderr = <<<STDERR
tests/JsPackager/fixtures/1_dep/dep_1.js:1: WARNING - Suspicious code. The result of the 'eq' operator is not being used.
x == 1;
^

0 error(s), 1 warning(s)

STDERR;

        $returnCode = 0;
        $successful = true;

        $processor = new ClosureCompilerProcessor(new NullLogger(), '');
        /**
         * @type ProcessingResult $result
         */
        $result = ReflectionHelper::invoke(
            $processor,
            'handleClosureCompilerOutput',
            array( $stdout, $stderr, $returnCode, $successful )
        );

        $this->assertInstanceOf(
            'JsPackager\Processor\ProcessingResult',
            $result,
            'Returns a ProcessingResult'
        );
        $this->assertEquals($stdout, $result->output);
        $this->assertEquals($stderr, $result->err);

        $this->assertEquals( true, $result->successful );
        $this->assertEquals( 0, $result->numberOfErrors );
    }

}
