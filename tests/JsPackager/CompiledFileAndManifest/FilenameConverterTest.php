<?php

namespace JsPackager;

use JsPackager\CompiledFileAndManifest\FilenameConverter;

class FilenameConverterTest extends \PHPUnit_Framework_TestCase
{

    /******************************************************************
     * getCompiledFilename
     *****************************************************************/

    public function testGetCompiledFilename()
    {
        $compiler = new Compiler();
        $filename = 'some_file.js';

        $compiledFilename = FilenameConverter::getCompiledFilename( $filename );
        $this->assertEquals( 'some_file.compiled.js', $compiledFilename );
    }

    public function testGetCompiledFilenameDoesNotHarmPaths()
    {
        $compiler = new Compiler();
        $filename = '../some/directory/and/some_file.js';

        $compiledFilename = FilenameConverter::getCompiledFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.compiled.js', $compiledFilename );
    }

    public function testGetCompiledFilenameIgnoresImproperFile()
    {
        $compiler = new Compiler();
        $filename = 'some_file.css';

        $compiledFilename = FilenameConverter::getCompiledFilename( $filename );
        $this->assertEquals( 'some_file.css', $compiledFilename );
    }

    public function testGetCompiledFilenameDoesNotHarmOddNamedFile()
    {
        $compiler = new Compiler();
        $filename = 'some.js.file.js';

        $compiledFilename = FilenameConverter::getCompiledFilename( $filename );
        $this->assertEquals( 'some.js.file.compiled.js', $compiledFilename );
    }

    /******************************************************************
     * getSourceFilenameFromCompiledFilename
     *****************************************************************/

    public function testgetSourceFilenameFromCompiledFilename()
    {
        $compiler = new Compiler();
        $filename = 'some_file.compiled.js';

        $compiledFilename = FilenameConverter::getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( 'some_file.js', $compiledFilename );
    }

    public function testgetSourceFilenameFromCompiledFilenameDoesNotHarmPaths()
    {
        $compiler = new Compiler();
        $filename = '../some/directory/and/some_file.compiled.js';

        $compiledFilename = FilenameConverter::getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.js', $compiledFilename );
    }

    public function testgetSourceFilenameFromCompiledFilenameIgnoresImproperFile()
    {
        $compiler = new Compiler();
        $filename = 'some_file.css';

        $compiledFilename = FilenameConverter::getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( 'some_file.css', $compiledFilename );
    }

    public function testgetSourceFilenameFromCompiledFilenameDoesNotHarmOddNamedFile()
    {
        $compiler = new Compiler();
        $filename = 'some.js.file.compiled.js';

        $compiledFilename = FilenameConverter::getSourceFilenameFromCompiledFilename( $filename );
        $this->assertEquals( 'some.js.file.js', $compiledFilename );
    }


    /******************************************************************
     * getManifestFilename
     *****************************************************************/

    public function testGetManifestFilename()
    {
        $compiler = new Compiler();
        $filename = 'some_file.js';

        $manifestFilename = FilenameConverter::getManifestFilename( $filename );
        $this->assertEquals( 'some_file.js.manifest', $manifestFilename );
    }

    public function testGetManifestFilenameDoesNotHarmPaths()
    {
        $compiler = new Compiler();
        $filename = '../some/directory/and/some_file.js';

        $manifestFilename = FilenameConverter::getManifestFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.js.manifest', $manifestFilename );
    }

    public function testGetManifestFilenameIgnoresImproperFile()
    {
        $compiler = new Compiler();
        $filename = 'some_file.css';

        $manifestFilename = FilenameConverter::getManifestFilename( $filename );
        $this->assertEquals( 'some_file.css', $manifestFilename );
    }

    public function testGetManifestFilenameDoesNotHarmOddNamedFile()
    {
        $compiler = new Compiler();
        $filename = 'some.js.file.js';

        $manifestFilename = FilenameConverter::getManifestFilename( $filename );
        $this->assertEquals( 'some.js.file.js.manifest', $manifestFilename );
    }

    /******************************************************************
     * getSourceFilenameFromManifestFilename
     *****************************************************************/

    public function testgetSourceFilenameFromManifestFilename()
    {
        $compiler = new Compiler();
        $filename = 'some_file.js.manifest';

        $manifestFilename = FilenameConverter::getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( 'some_file.js', $manifestFilename );
    }

    public function testgetSourceFilenameFromManifestFilenameDoesNotHarmPaths()
    {
        $compiler = new Compiler();
        $filename = '../some/directory/and/some_file.js.manifest';

        $manifestFilename = FilenameConverter::getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.js', $manifestFilename );
    }

    public function testgetSourceFilenameFromManifestFilenameIgnoresImproperFile()
    {
        $compiler = new Compiler();
        $filename = 'some_file.css';

        $manifestFilename = FilenameConverter::getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( 'some_file.css', $manifestFilename );
    }

    public function testgetSourceFilenameFromManifestFilenameDoesNotHarmOddNamedFile()
    {
        $compiler = new Compiler();
        $filename = 'some.js.file.js.manifest';

        $manifestFilename = FilenameConverter::getSourceFilenameFromManifestFilename( $filename );
        $this->assertEquals( 'some.js.file.js', $manifestFilename );
    }

}