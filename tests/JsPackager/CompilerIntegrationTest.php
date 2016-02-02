<?php

namespace JsPackager\Compiler\Unit;

use JsPackager\Compiler;
use JsPackager\Compiler\FileCompilationResult;
use JsPackager\Compiler\FileCompilationResultCollection;
use JsPackager\Helpers\FileHandler;
use JsPackager\Resolver\DependencyTree;
use Psr\Log\NullLogger;

/**
 * Class CompilerTest
 * @group      JsPackager
 */
class CompilerIntegrationTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    /** @var String */
    public $tmpPath;

    public function setUp()
    {
        $this->tmpPath = '/tmp/JsPackager.CompilerIntegrationTest.'.rand(1,2000);
        mkdir($this->tmpPath, 0777, true );
    }

    public function tearDown()
    {
        if ( is_dir( $this->tmpPath ) ) {
            $this->rrmdir( $this->tmpPath );
        }
        if ( is_dir( $this->tmpPath ) ) {
            throw new \RuntimeException("Unable to delete temporary test directory: " . $this->tmpPath);
        }
    }

    /******************************************************************
     * compileAndWriteFilesAndManifests
     *****************************************************************/

    // should probably have these live in another test suite so we can use its before/after
    // since its like an integration test, integration w the fs?

    public function testCompileAndWriteFilesAndManifestsWritesProperOutput()
    {
        $this->copyDirIntoTestWorkspace(getcwd() . '/' . self::fixturesBasePath . '1_dep_root' . '/');

        $basePath = $this->tmpPath;
        $mainJsPath = $basePath . '/main.js';

        $sharedPath = $basePath . '-remote';

        $dependencyTree = new DependencyTree( $mainJsPath, null, false, null, $sharedPath, '@rawr', new FileHandler() );
        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler($sharedPath, '@rawr', new NullLogger(), false, new FileHandler());

        $returned = $compiler->compileAndWriteFilesAndManifests($mainJsPath);

        $this->assertInstanceOf(FileCompilationResultCollection::class, $returned);
        $this->assertInstanceOf(FileCompilationResult::class, $returned->getValuesAsArray()[0]);
        $this->assertEquals($this->tmpPath . '/' . 'somePackage', $returned->getValuesAsArray()[0]->getSourcePath());
        $this->assertEquals('dep_1.compiled.js', $returned->getValuesAsArray()[0]->getCompiledPath());
        $this->assertEquals('not_compiled', $returned->getValuesAsArray()[0]->getManifestPath());
        $this->assertInstanceOf(FileCompilationResult::class, $returned->getValuesAsArray()[1]);
        $this->assertEquals(rtrim($this->tmpPath,'/'), $returned->getValuesAsArray()[1]->getSourcePath());
        $this->assertEquals('main.compiled.js', $returned->getValuesAsArray()[1]->getCompiledPath());
        $this->assertEquals('main.js.manifest', $returned->getValuesAsArray()[1]->getManifestPath());

        $expectedCompiledFilesContents = <<< 'COMPILEFILE'
window.root_test="the pooh!";

COMPILEFILE;

        $expectedManifestContents = <<< 'MANIFEST'
somePackage/dep_1.compiled.js

MANIFEST;

        $compiledFilesContentsActual = file_get_contents($this->tmpPath . '/' . 'main.compiled.js');
        $manifestContentssActual = file_get_contents($this->tmpPath . '/' . 'main.js.manifest');

        $expectedCompiledFilesContents = str_replace("\r\n", "\n", $expectedCompiledFilesContents);
        $expectedManifestContents = str_replace("\r\n", "\n", $expectedManifestContents);

        $this->assertEquals( $expectedCompiledFilesContents, $compiledFilesContentsActual, "Compiled file should contain minified files" );
        $this->assertEquals( $expectedManifestContents, $manifestContentssActual, "Manifest file should contain dependent files" );

    }

    public function testCompileAndWriteFilesAndManifestsHandlesRemoteAnnotations()
    {
        $this->copyDirIntoTestWorkspace(getcwd() . '/' . self::fixturesBasePath . 'remote_annotation' . '/', 'main');
        $this->copyDirIntoTestWorkspace(getcwd() . '/' . self::fixturesBasePath . 'remote_annotation-remote' . '/', 'remote');

        $basePath = $this->tmpPath;
        $mainJsPath = $basePath . '/main/main.js';

        $sharedPath = $basePath . '/remote';

        $dependencyTree = new DependencyTree( $mainJsPath, null, false, null, $sharedPath, '@rawr', new FileHandler() );
        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler($sharedPath, '@rawr', new NullLogger(), false, new FileHandler());

        $returned = $compiler->compileAndWriteFilesAndManifests($mainJsPath);

        $this->assertInstanceOf(FileCompilationResultCollection::class, $returned);
        $this->assertInstanceOf(FileCompilationResult::class, $returned->getValuesAsArray()[0]);
        $this->assertEquals($this->tmpPath . '/remote/' . 'remotepackage', $returned->getValuesAsArray()[0]->getSourcePath());
        $this->assertEquals('script.compiled.js', $returned->getValuesAsArray()[0]->getCompiledPath());
        $this->assertEquals('script.js.manifest', $returned->getValuesAsArray()[0]->getManifestPath());
        $this->assertInstanceOf(FileCompilationResult::class, $returned->getValuesAsArray()[1]);
        $this->assertEquals($this->tmpPath . '/main', $returned->getValuesAsArray()[1]->getSourcePath());
        $this->assertEquals('main.compiled.js', $returned->getValuesAsArray()[1]->getCompiledPath());
        $this->assertEquals('main.js.manifest', $returned->getValuesAsArray()[1]->getManifestPath());

        $expectedCompiledFilesContents = <<< 'COMPILEFILE'
window.remotepackage_local_on_remote=!0;window.remotepackage_remote_on_remote=!0;window.remotepackage_script=!0;

COMPILEFILE;

        $expectedManifestContents = <<< 'MANIFEST'
@rawr/remotepackage/package_subfolder/local_on_remote.css
@rawr/remotepackage/package_subfolder/remote_on_remote.css

MANIFEST;

        $compiledFilesContentsActual = file_get_contents($this->tmpPath . '/remote/remotepackage/' . 'script.compiled.js');
        $manifestContentssActual = file_get_contents($this->tmpPath . '/remote/remotepackage/' . 'script.js.manifest');

        $expectedCompiledFilesContents = str_replace("\r\n", "\n", $expectedCompiledFilesContents);
        $expectedManifestContents = str_replace("\r\n", "\n", $expectedManifestContents);

        $this->assertEquals( $expectedCompiledFilesContents, $compiledFilesContentsActual, "Compiled file should contain minified files" );
        $this->assertEquals( $expectedManifestContents, $manifestContentssActual, "Manifest file should contain dependent files" );

        $expectedCompiledFilesContents = <<< 'COMPILEFILE'
window.main_local_file_before=!0;window.main_local_subfolder_script_before=!0;window.remotescript_local_on_remote=!0;window.remotescript_remote_on_remote=!0;window.remotescript_script=!0;window.main_local_subfolder_script_after=!0;window.main_local_file_after=!0;window.main_js=!0;

COMPILEFILE;

        $expectedManifestContents = <<< 'MANIFEST'
@rawr/remotepackage/package_subfolder/local_on_remote.css
@rawr/remotepackage/package_subfolder/remote_on_remote.css
stylesheet_before.css
local_subfolder/local_subfolder_before.css
@rawr/remotescript/script_subfolder/local_on_remote.css
@rawr/remotescript/script_subfolder/remote_on_remote.css
local_subfolder/local_subfolder_after.css
stylesheet_after.css
@rawr/remotepackage/script.compiled.js

MANIFEST;

        $compiledFilesContentsActual = file_get_contents($this->tmpPath . '/main/' . 'main.compiled.js');
        $manifestContentssActual = file_get_contents($this->tmpPath . '/main/' . 'main.js.manifest');

        $expectedCompiledFilesContents = str_replace("\r\n", "\n", $expectedCompiledFilesContents);
        $expectedManifestContents = str_replace("\r\n", "\n", $expectedManifestContents);

        $this->assertEquals( $expectedCompiledFilesContents, $compiledFilesContentsActual, "Compiled file should contain minified files" );
        $this->assertEquals( $expectedManifestContents, $manifestContentssActual, "Manifest file should contain dependent files" );

    }

    /////

    /**
     * Removes files and non-empty directories
     * @param $dir
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file)
                if ($file != "." && $file != "..") $this->rrmdir("$dir/$file");
            rmdir($dir);
        }
        else if (file_exists($dir)) unlink($dir);
    }

    /**
     * Copies files and non-empty directories
     * @param $src
     * @param $dst
     */
    private function rcopy($src, $dst) {
        if (file_exists($dst)) $this->rrmdir($dst);
        if (is_dir($src)) {
            mkdir($dst);
            $files = scandir($src);
            foreach ($files as $file)
                if ($file != "." && $file != "..") $this->rcopy("$src/$file", "$dst/$file");
        }
        else if (file_exists($src)) copy($src, $dst);
    }

    protected function copyDirIntoTestWorkspace($dir, $subworkspace = '')
    {
        $subworkspace = $subworkspace == '' ? '' : '/' . ltrim($subworkspace,'/');
        $this->rcopy(
            $dir,
            $this->tmpPath . $subworkspace
        );
    }

}
