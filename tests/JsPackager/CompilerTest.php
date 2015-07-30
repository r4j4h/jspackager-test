<?php

namespace JsPackager;

use JsPackager\Compiler;

/**
 * Class CompilerTest
 * @group      JsPackager
 */
class CompilerTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';


    /******************************************************************
     * compileDependencySet
     *****************************************************************/

    /**
     */
    public function testCompileDependencySetHandlesDependenciesWithoutPackages()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = 'window.dep_1=!0;window.root_test="the pooh!";' . PHP_EOL;

        $this->assertInstanceOf( 'JsPackager\Compiler\CompiledFile', $result );
        $this->assertEquals( $basePath, $result->path, "Compiled path should default to input path" );
        $this->assertEquals(
            'main.compiled.js',
            $result->filename,
            "Compiled file should default to be named after input filename"
        );
        $this->assertEquals(
            'main.js.manifest',
            $result->manifestFilename,
            "Manifest filename should default to be named after input filename"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain both files" );
        $this->assertEquals( '', $result->manifestContents, "Should have an empty manifest" );
    }

    /**
     */
    public function testCompileDependencySetHandlesDependenciesWithPackages()
    {
        $basePath = self::fixturesBasePath . '2_deps_2_package_2_deep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.dep_5=!0;window.dep_4=!0;" . PHP_EOL;
        $manifestContents = <<<MANIFEST
dep_4_style.css

MANIFEST;

        $this->assertEquals( $basePath . '/package/subpackage', $result->path, "Compiled path should be dep_4's path" );
        $this->assertEquals(
            'dep_4.compiled.js',
            $result->filename,
            "Compiled file should be from dep_4.js"
        );
        $this->assertEquals(
            'dep_4.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_4"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );


        // Grab second dependency set
        $dependencySet = $roots[1];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.dep_3=!0;" . PHP_EOL;
        $manifestContents = <<<MANIFEST
dep_3_style.css
subpackage/dep_4.compiled.js

MANIFEST;

        $this->assertEquals( $basePath . '/package', $result->path, "Compiled path should be dep_3's path" );
        $this->assertEquals(
            'dep_3.compiled.js',
            $result->filename,
            "Compiled file should be from dep_3.js"
        );
        $this->assertEquals(
            'dep_3.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_3"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );


        // Grab third (and last) dependency set
        $dependencySet = $roots[2];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.dep_1=!0;window.dep_2=!0;window.main=!0;" . PHP_EOL;
        $manifestContents = 'package/dep_3.compiled.js' . PHP_EOL;

        $this->assertEquals( $basePath, $result->path, "Compiled path should be main.js's path" );
        $this->assertEquals(
            'main.compiled.js',
            $result->filename,
            "Compiled file should be from main.js"
        );
        $this->assertEquals(
            'main.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from main"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );
    }



    /**
     */
    public function testCompileDependencySetHandlesDependenciesWithPackagesMarkedNoCompile()
    {
        $basePath = self::fixturesBasePath . 'annotation_nocompile';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.normal_package=!0;" . PHP_EOL;
        $manifestContents = <<<MANIFEST

MANIFEST;

        $this->assertEquals( $basePath . '/some/normal', $result->path, "Compiled path should be dep_4's path" );
        $this->assertEquals(
            'package.compiled.js',
            $result->filename,
            "Compiled file should be from dep_4.js"
        );
        // todo when manifests arent needed they should not be generated for optimization!
        $this->assertEquals(
            'package.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_4"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );


        // Grab second dependency set
        $dependencySet = $roots[1];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.nocompile_package=!0;" . PHP_EOL;
        $manifestContents = <<<MANIFEST

MANIFEST;

        $this->assertEquals( $basePath . '/some/nocompile', $result->path, "Compiled path should be dep_3's path" );
        $this->assertEquals(
            'package.compiled.js',
            $result->filename,
            "Compiled file should be from dep_3.js"
        );
        $this->assertEquals(
            'package.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_3"
        );
        $this->assertEquals( null, $result->contents, "Compiled file should be null so compilers can safely skip it" );
        $this->assertEquals( null, $result->manifestContents, "Manifest file should be null so compilers can safely skip it" );


        // Grab third (and last) dependency set
        $dependencySet = $roots[2];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.nocompile_script=!0;window.normal_script=!0;window.main=!0;" . PHP_EOL;
        $manifestContents = <<<MANIFEST
some/nocompile/package.js
some/normal/package.compiled.js

MANIFEST;


        $this->assertEquals( $basePath, $result->path, "Compiled path should be main.js's path" );
        $this->assertEquals(
            'main.compiled.js',
            $result->filename,
            "Compiled file should be from main.js"
        );
        $this->assertEquals(
            'main.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from main"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );
    }


    /**
     */
    public function testCompileDependencySetExpandsRemoteAnnotationsAndWithCustomRemoteAnnotation()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $mainJsPath = $basePath . '/main.js';

        $sharedPath = $basePath . '-remote';

        $dependencyTree = new DependencyTree( $mainJsPath, null, false, null, $sharedPath );
        $dependencyTree->remoteSymbol = '@rawr';
        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();
        $compiler->remoteSymbol = '@rawr';
        $compiler->remoteFolderPath = $sharedPath;

        // Grab first dependency set
        $dependencySet = $roots[0];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = <<< 'COMPILEFILE'
window.remotepackage_local_on_remote=!0;window.remotepackage_remote_on_remote=!0;window.remotepackage_script=!0;

COMPILEFILE;

        $manifestContents = <<< 'MANIFEST'
@rawr/remotepackage/package_subfolder/local_on_remote.css
@rawr/remotepackage/package_subfolder/remote_on_remote.css

MANIFEST;

        $this->assertEquals( $sharedPath . '/remotepackage', $result->path, "Path should point to remote folder" );
        $this->assertEquals(
            'script.compiled.js',
            $result->filename,
            "Compiled file should be from dep_4.js"
        );
        // todo when manifests arent needed they should not be generated for optimization!
        $this->assertEquals(
            'script.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_4"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );


        // Grab second dependency set
        $dependencySet = $roots[1];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = <<<COMPILEFILE
window.main_local_file_before=!0;window.main_local_subfolder_script_before=!0;window.remotescript_local_on_remote=!0;window.remotescript_remote_on_remote=!0;window.remotescript_script=!0;window.main_local_subfolder_script_after=!0;window.main_local_file_after=!0;window.main_js=!0;

COMPILEFILE;

        $manifestContents = <<<'MANIFEST'
stylesheet_before.css
local_subfolder/local_subfolder_before.css
@rawr/remotescript/script_subfolder/local_on_remote.css
@rawr/remotescript/script_subfolder/remote_on_remote.css
local_subfolder/local_subfolder_after.css
stylesheet_after.css
@rawr/remotepackage/script.compiled.js

MANIFEST;

        $this->assertEquals( $basePath, $result->path, "Compiled path should be local path" );
        $this->assertEquals(
            'main.compiled.js',
            $result->filename,
            "Compiled file should be from dep_3.js"
        );
        $this->assertEquals(
            'main.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_3"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents );
        $this->assertEquals( $manifestContents, $result->manifestContents );

    }



    /******************************************************************
     * compileAndWriteFilesAndManifests
     *****************************************************************/


    /******************************************************************
     * compileFileListUsingClosureCompilerJar
     *****************************************************************/

}
