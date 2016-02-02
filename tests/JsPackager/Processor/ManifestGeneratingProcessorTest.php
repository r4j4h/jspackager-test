<?php
namespace JsPackager\Processor\Unit;

use JsPackager\Compiler;
use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\Processor\ClosureCompilerProcessor;
use JsPackager\Processor\ProcessingResult;
use JsPackager\Processor\SimpleProcessorParams;
use Psr\Log\NullLogger;


class ManifestGeneratingProcessorTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    public function testAddsManifestAsImmediateDependencyToRootFiles()
    {
        // assert doesn't append to end of $deps but that it splices into -1 index
    }

}
