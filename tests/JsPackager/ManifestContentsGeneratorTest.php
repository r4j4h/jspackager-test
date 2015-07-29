<?php

namespace JsPackagerTest;

use JsPackager\ManifestContentsGenerator;

/**
 * @group      JsPackager
 */
class ManifestContentsGeneratorTest extends \PHPUnit_Framework_TestCase
{


    /******************************************************************
     * generateManifestFileContents
     *****************************************************************/

    public function testGenerateManifestFileContentsHandlesNoFilesGracefully()
    {
        $compiler = new ManifestContentsGenerator('@remote', 'some/remote/path');

        $stylesheets = array();
        $packages = array();

        $manifestFileContents = $compiler->generateManifestFileContents( '', $packages, $stylesheets );

        $this->assertEquals('', $manifestFileContents, "Manifest should be empty" );

    }

    public function testGenerateManifestContainingDependentPackages()
    {
        $compiler = new ManifestContentsGenerator('@remote', 'some/remote/path');

        $stylesheets = array();
        $packages = array(
            'some/package.js',
            'another/package.js'
        );

        $expectedOutput = join( PHP_EOL, array(
                'some/package.compiled.js',
                'another/package.compiled.js'
            )) . PHP_EOL;

        $manifestFileContents = $compiler->generateManifestFileContents( '', $packages, $stylesheets );

        $this->assertEquals($expectedOutput, $manifestFileContents, "Manifest should have packages" );
    }

    public function testGenerateManifestContainingStylesheets()
    {
        $compiler = new ManifestContentsGenerator('@remote', 'some/remote/path');

        $stylesheets = array(
            'some/stylesheet.css',
            'some/modifiers.css'
        );
        $packages = array();

        $manifestFileContents = $compiler->generateManifestFileContents( '', $packages, $stylesheets );

        $expectedContents = <<<BLOCK
some/stylesheet.css
some/modifiers.css

BLOCK;

        $this->assertEquals($expectedContents, $manifestFileContents);

    }

    public function testGenerateManifestContainingMixture()
    {
        $compiler = new ManifestContentsGenerator('@remote', 'some/remote/path');

        $stylesheets = array(
            'some/stylesheet.css',
            'some/modifiers.css'
        );
        $packages = array(
            'some/package.js',
            'another/package.js'
        );

        $manifestFileContents = $compiler->generateManifestFileContents( '', $packages, $stylesheets );

        $expectedContents = <<<BLOCK
some/stylesheet.css
some/modifiers.css
some/package.compiled.js
another/package.compiled.js

BLOCK;

        $this->assertEquals( $expectedContents, $manifestFileContents );
    }

    public function testGenerateManifestPassesNoCompileFilesThrough()
    {
        $compiler = new ManifestContentsGenerator('@remote', 'some/remote/path');

        $stylesheets = array(
            'css/my_stylesheet.css'
        );
        $packages = array(
            'some/nocompile/package.js',
            'some/normal/package.js'
        );
        $filesMarkedNoCompile = array(
            'some/nocompile/package.js'
        );

        $manifestFileContents = $compiler->generateManifestFileContents( '', $packages, $stylesheets, $filesMarkedNoCompile );

        $expectedContents = <<<BLOCK
css/my_stylesheet.css
some/nocompile/package.js
some/normal/package.compiled.js

BLOCK;

        $this->assertEquals( $expectedContents, $manifestFileContents );
    }

    public function testGenerateManifestConvertsAbsolutePathsToRelativeFromWebRoot()
    {
        $compiler = new ManifestContentsGenerator('@remote', 'some/remote/path');

        $stylesheets = array(
            '/some/absolute/path/to/websites/website_1/css/my_stylesheet.css'
        );
        $packages = array(
            '/some/absolute/path/to/websites/website_1/some/nocompile/package.js',
            '/some/absolute/path/to/websites/website_1/some/normal/package.js'
        );
        $filesMarkedNoCompile = array(
            '/some/absolute/path/to/websites/website_1/some/nocompile/package.js'
        );

        $manifestFileContents = $compiler->generateManifestFileContents(
            '/some/absolute/path/to/websites/website_1/',
            $packages,
            $stylesheets,
            $filesMarkedNoCompile
        );

        $expectedContents = <<<BLOCK
css/my_stylesheet.css
some/nocompile/package.js
some/normal/package.compiled.js

BLOCK;

        $this->assertEquals( $expectedContents, $manifestFileContents );
    }

    public function testGenerateManifestDoesNotConvertRemoteFilesToRelative()
    {
        $compiler = new ManifestContentsGenerator('@remote', 'some/remote/path');

        $stylesheets = array(
            '@remote/some/absolute/path/to/websites/website_1/css/my_stylesheet.css'
        );
        $packages = array(
            '@remote/some/absolute/path/to/websites/website_1/some/nocompile/package.js',
            '/some/absolute/path/to/websites/website_1/some/normal/package.js'
        );
        $filesMarkedNoCompile = array(
            '@remote/some/absolute/path/to/websites/website_1/some/nocompile/package.js'
        );

        $manifestFileContents = $compiler->generateManifestFileContents(
            '/some/absolute/path/to/websites/website_1/',
            $packages,
            $stylesheets,
            $filesMarkedNoCompile
        );

        $expectedContents = <<<BLOCK
@remote/some/absolute/path/to/websites/website_1/css/my_stylesheet.css
@remote/some/absolute/path/to/websites/website_1/some/nocompile/package.js
some/normal/package.compiled.js

BLOCK;

        $this->assertEquals( $expectedContents, $manifestFileContents );
    }


}