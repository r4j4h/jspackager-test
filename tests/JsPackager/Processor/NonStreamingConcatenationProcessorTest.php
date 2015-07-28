<?php
namespace JsPackagerTest;

use JsPackager\Exception\MissingFile;
use JsPackager\Processor\NonStreamingConcatenationProcessor;


class NonStreamingConcatenationProcessorTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    /******************************************************************
     * concatenateFiles
     *****************************************************************/

    public function testConcatenateFilesHandlesNoFiles()
    {
        $processor = new NonStreamingConcatenationProcessor();
        $dependencies = array();

        $concatenatedFile = $processor->process( $dependencies )->output;

        $this->assertEquals( '', $concatenatedFile, "Concatenated file should be empty" );
    }

    public function testConcatenateFilesHandlesOneFile()
    {
        $basePath = self::fixturesBasePath . '0_deps';
        $mainJsPath = $basePath . '/main.js';

        $mainContents = file_get_contents( $mainJsPath );

        $processor = new NonStreamingConcatenationProcessor();
        $dependencies = array(
            $mainJsPath,
        );

        $concatenatedFile = $processor->process( $dependencies )->output;

        $this->assertEquals(
            $mainContents,
            $concatenatedFile,
            "Concatenated file should contain the given file's contents"
        );
    }

    public function testConcatenateFilesConcatenatesManyFiles()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $dep1JsPath = $basePath . '/dep_1.js';
        $mainJsPath = $basePath . '/main.js';

        $dep1Contents = file_get_contents( $dep1JsPath );
        $mainContents = file_get_contents( $mainJsPath );

        $processor = new NonStreamingConcatenationProcessor();
        $dependencies = array(
            $dep1JsPath,
            $mainJsPath,
        );

        $concatenatedFile = $processor->process( $dependencies )->output;

        $this->assertEquals(
            $dep1Contents . $mainContents,
            $concatenatedFile,
            "Concatenated file should contain both given file's contents in order"
        );
    }

    public function testConcatenateFilesThrowsOnMissingFile()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $dep1JsPath = $basePath . '/dep_1.js';
        $mainJsPath = $basePath . '/main.js.not.real'; // Second file will be broken

        $processor = new NonStreamingConcatenationProcessor();
        $dependencies = array(
            $dep1JsPath,
            $mainJsPath,
        );

        try {
            $processor->process( $dependencies );
            $this->fail('Set should throw a missing file exception');
        } catch (MissingFile $e) {
            $this->assertEquals(
                'tests/JsPackager/fixtures/1_dep/main.js.not.real',
                $e->getMissingFilePath(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                MissingFile::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }
    }

}
