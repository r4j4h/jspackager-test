<?php

namespace JsPackagerTest;

use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\ManifestContentsGenerator;

/**
 * @group      JsPackager
 */
class ManifestContentsGeneratorTest extends \PHPUnit_Framework_TestCase
{


    /******************************************************************
     * generateManifestFileContents
     *****************************************************************/

    public function testGenerateManifestFileContentsHandlesNoFiles()
    {
        $compiler = new ManifestContentsGenerator();

        $stylesheets = array();
        $packages = array();

        $manifestFileContents = ReflectionHelper::invoke( $compiler, 'generateManifestFileContents', array( '', $packages, $stylesheets ) );

        $this->assertEquals('', $manifestFileContents, "Manifest should be empty" );

    }

    public function testGenerateManifestContainingDependentPackages()
    {
        $compiler = new ManifestContentsGenerator();

        $stylesheets = array();
        $packages = array(
            'some/package.js',
            'another/package.js'
        );

        $expectedOutput = join( PHP_EOL, array(
                'some/package.compiled.js',
                'another/package.compiled.js'
            )) . PHP_EOL;

        $manifestFileContents = ReflectionHelper::invoke( $compiler, 'generateManifestFileContents', array( '', $packages, $stylesheets ) );

        $this->assertEquals($expectedOutput, $manifestFileContents, "Manifest should have packages" );
    }

    public function testGenerateManifestContainingStylesheets()
    {
        $compiler = new ManifestContentsGenerator();

        $stylesheets = array(
            'some/stylesheet.css',
            'some/modifiers.css'
        );
        $packages = array();

        $manifestFileContents = ReflectionHelper::invoke( $compiler, 'generateManifestFileContents', array( '', $packages, $stylesheets ) );

    }

    public function testGenerateManifestContainingMixture()
    {
        $compiler = new ManifestContentsGenerator();

        $stylesheets = array(
            'some/stylesheet.css',
            'some/modifiers.css'
        );
        $packages = array(
            'some/package.js',
            'another/package.js'
        );

        $manifestFileContents = ReflectionHelper::invoke( $compiler, 'generateManifestFileContents', array( '', $packages, $stylesheets ) );

        $expectedContents = <<<BLOCK
some/stylesheet.css
some/modifiers.css
some/package.compiled.js
another/package.compiled.js

BLOCK;

        $this->assertEquals( $expectedContents, $manifestFileContents );
    }

    public function testGenerateManifestContainingNoCompileFile()
    {
        $compiler = new ManifestContentsGenerator();

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

        $manifestFileContents = ReflectionHelper::invoke( $compiler, 'generateManifestFileContents', array( '', $packages, $stylesheets, $filesMarkedNoCompile ) );

        $expectedContents = <<<BLOCK
css/my_stylesheet.css
some/nocompile/package.js
some/normal/package.compiled.js

BLOCK;

        $this->assertEquals( $expectedContents, $manifestFileContents );
    }

    public function testGenerateManifestHandlesBasePath()
    {
        $compiler = new ManifestContentsGenerator();

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

        $manifestFileContents = ReflectionHelper::invoke( $compiler, 'generateManifestFileContents', array(
            '/some/absolute/path/to/websites/website_1/',
            $packages,
            $stylesheets,
            $filesMarkedNoCompile
        ) );

        $expectedContents = <<<BLOCK
css/my_stylesheet.css
some/nocompile/package.js
some/normal/package.compiled.js

BLOCK;

        $this->assertEquals( $expectedContents, $manifestFileContents );
    }


}