<?php

namespace JsPackager\Helpers\Unit;

use JsPackager\CompiledFileAndManifest\FilenameConverter;
use JsPackager\Helpers\FileFinder;

class FileFinderTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    /******************************************************************
     * parseFolderForSourceFiles
     *****************************************************************/

    public function testParseFolderForSourceFilesDetectsJsFilesByFullPath()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $absBasePath = realpath( $basePath );

        $compiler = new FileFinder();
        $result = $compiler->parseFolderForSourceFiles( $basePath );

        $this->assertEquals(4, count($result), "Should contain 4 files");
        $this->assertContains( $absBasePath . '/dep_1.js', $result, "Should detect dep_1.js" );
        $this->assertContains( $absBasePath . '/dep_2.js', $result, "Should detect dep_2.js" );
        $this->assertContains( $absBasePath . '/dep_3.js', $result, "Should detect dep_3.js" );
        $this->assertContains( $absBasePath . '/main.js', $result, "Should detect main.js" );
    }

    public function testParseFolderForSourceFilesIgnoresCompiledFiles()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $absBasePath = realpath( $basePath );

        $compiler = new FileFinder();
        $result = $compiler->parseFolderForSourceFiles( $basePath );

        $this->assertEquals(4, count($result), "Should contain 4 files");
        $this->assertNotContains( $absBasePath . '/dep_1.compiled.js', $result, "Should not detect dep_1.compiled.js" );
        $this->assertNotContains( $absBasePath . '/dep_3.compiled.js', $result, "Should not detect dep_3.compiled.js" );
        $this->assertNotContains( $absBasePath . '/main.compiled.js', $result, "Should not detect main.compiled.js" );
        $this->assertNotContains( $absBasePath . '/main.js.manifest', $result, "Should not detect main.js.manifest" );
    }

    /******************************************************************
     * parseFolderForPackageFiles
     *****************************************************************/

    public function testParseFolderForPackageFilesDetectsJsFilesByFullPath()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $absBasePath = realpath( $basePath );

        $converter = new FilenameConverter('compiled', 'manifest');

        $compiler = new FileFinder();
        $result = $compiler->parseFolderForPackageFiles( $basePath, $converter );

        $this->assertEquals(6, count($result), "Should contain 6 files");
        $this->assertContains( $absBasePath, $result[0] );
    }

    public function testParseFolderForPackageFilesIgnoresSourceFiles()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $absBasePath = realpath( $basePath );

        $converter = new FilenameConverter('compiled', 'manifest');

        $compiler = new FileFinder();
        $result = $compiler->parseFolderForPackageFiles( $basePath, $converter );

        $this->assertNotContains( $absBasePath . '/dep_1.js', $result, "Should not detect dep_1.js" );
        $this->assertNotContains( $absBasePath . '/dep_2.js', $result, "Should not detect dep_2.js" );
        $this->assertNotContains( $absBasePath . '/dep_3.js', $result, "Should not detect dep_3.js" );
        $this->assertNotContains( $absBasePath . '/main.js', $result, "Should not detect main.js" );
    }

    public function testParseFolderForPackageFilesIncludesCompiledFilesAndManifests()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $absBasePath = realpath( $basePath );

        $converter = new FilenameConverter('compiled', 'manifest');

        $compiler = new FileFinder();
        $result = $compiler->parseFolderForPackageFiles( $basePath, $converter );

        $this->assertEquals(6, count($result), "Should contain 6 files");
        $this->assertContains( $absBasePath . '/dep_2.compiled.js', $result, "Should detect dep_2.compiled.js" );
        $this->assertContains( $absBasePath . '/dep_2.js.manifest', $result, "Should detect dep_2.js.manifest" );
        $this->assertContains( $absBasePath . '/dep_3.compiled.js', $result, "Should detect dep_3.compiled.js" );
        $this->assertContains( $absBasePath . '/dep_3.js.manifest', $result, "Should detect dep_3.js.manifest" );
        $this->assertContains( $absBasePath . '/main.compiled.js', $result, "Should detect main.compiled.js" );
        $this->assertContains( $absBasePath . '/main.js.manifest', $result, "Should detect main.js.manifest" );
    }

}