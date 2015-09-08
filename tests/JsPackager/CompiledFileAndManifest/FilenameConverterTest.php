<?php

namespace JsPackager;

use JsPackager\CompiledFileAndManifest\FilenameConverter;
use Psr\Log\NullLogger;

class FilenameConverterTest extends \PHPUnit_Framework_TestCase
{

    /******************************************************************
     * getCompiledFilename
     *****************************************************************/

    public function testGetCompiledFilename()
    {
        $filename = 'some_file.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getCompiledFilename( $filename );
        $this->assertEquals( 'some_file.compiled.js', $compiledFilename );
    }

    public function testGetCompiledFilenameDoesNotHarmPaths()
    {
        $filename = '../some/directory/and/some_file.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getCompiledFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.compiled.js', $compiledFilename );
    }

    public function testGetCompiledFilenameIgnoresImproperFile()
    {
        $filename = 'some_file.css';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getCompiledFilename( $filename );
        $this->assertEquals( 'some_file.css', $compiledFilename );
    }

    public function testGetCompiledFilenameDoesNotHarmOddNamedFile()
    {
        $filename = 'some.js.file.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getCompiledFilename( $filename );
        $this->assertEquals( 'some.js.file.compiled.js', $compiledFilename );
    }

    /******************************************************************
     * getSourceFilenameFromCompiledFilename
     *****************************************************************/

    public function testgetSourceFilenameFromCompiledFilename()
    {
        $filename = 'some_file.compiled.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( 'some_file.js', $compiledFilename );
    }

    public function testgetSourceFilenameFromCompiledFilenameDoesNotHarmPaths()
    {
        $filename = '../some/directory/and/some_file.compiled.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.js', $compiledFilename );
    }

    public function testgetSourceFilenameFromCompiledFilenameIgnoresImproperFile()
    {
        $filename = 'some_file.css';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( 'some_file.css', $compiledFilename );
    }

    public function testgetSourceFilenameFromCompiledFilenameDoesNotHarmOddNamedFile()
    {
        $filename = 'some.js.file.compiled.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $compiledFilename = $filenameConverter->getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( 'some.js.file.js', $compiledFilename );
    }


    /******************************************************************
     * getManifestFilename
     *****************************************************************/

    public function testGetManifestFilename()
    {
        $filename = 'some_file.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getManifestFilename( $filename );
        $this->assertEquals( 'some_file.js.manifest', $manifestFilename );
    }

    public function testGetManifestFilenameDoesNotHarmPaths()
    {
        $filename = '../some/directory/and/some_file.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getManifestFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.js.manifest', $manifestFilename );
    }

    public function testGetManifestFilenameIgnoresImproperFile()
    {
        $filename = 'some_file.css';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getManifestFilename( $filename );
        $this->assertEquals( 'some_file.css', $manifestFilename );
    }

    public function testGetManifestFilenameDoesNotHarmOddNamedFile()
    {
        $filename = 'some.js.file.js';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getManifestFilename( $filename );
        $this->assertEquals( 'some.js.file.js.manifest', $manifestFilename );
    }

    /******************************************************************
     * getSourceFilenameFromManifestFilename
     *****************************************************************/

    public function testgetSourceFilenameFromManifestFilename()
    {
        $filename = 'some_file.js.manifest';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( 'some_file.js', $manifestFilename );
    }

    public function testgetSourceFilenameFromManifestFilenameDoesNotHarmPaths()
    {
        $filename = '../some/directory/and/some_file.js.manifest';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.js', $manifestFilename );
    }

    public function testgetSourceFilenameFromManifestFilenameIgnoresImproperFile()
    {
        $filename = 'some_file.css';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( 'some_file.css', $manifestFilename );
    }

    public function testgetSourceFilenameFromManifestFilenameDoesNotHarmOddNamedFile()
    {
        $filename = 'some.js.file.js.manifest';
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $manifestFilename = $filenameConverter->getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( 'some.js.file.js', $manifestFilename );
    }

}