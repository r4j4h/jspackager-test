<?php

namespace JsPackagerTest;

use JsPackager\File;
use JsPackager\DependencyTreeParser;
use JsPackager\FileHandler;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;

use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\ManifestResolver;
use JsPackager\PathFinder;

/**
 * @group      JsPackager
 */
class ManifestResolverTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    /******************************************************************
     *
     *****************************************************************/


    public function testParsesCompiledFilesFromPackages()
    {

        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = '.'; //self::fixturesBasePath;
        $manifestResolver->remoteFolderPath = '.';

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            './tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/dep_3.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/main.compiled.js',
            $paths[1]
        );

    }

    public function testUsesBaseFolderPath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->remoteFolderPath = $remotePath;

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

        $manifestResolver->baseFolderPath = 'dorf';

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testDoesNotOutputRemoteSymbols()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = 'basey'; //self::fixturesBasePath;
        $manifestResolver->remoteFolderPath = 'remmy';

        $paths = $manifestResolver->resolveFile( $filePath );


        $this->assertEquals( 8, count( $paths ) );
        $i = 0;
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/local_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/remote_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotepackage/script.compiled.js', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/main.compiled.js', $paths[$i++] );

        $i = 0;
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);
        $this->assertStringStartsNotWith('@remote', $paths[$i++]);

    }

    public function testExpandsRemoteSymbols()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = 'basey'; //self::fixturesBasePath;
        $manifestResolver->remoteFolderPath = 'remmy';

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 8, count( $paths ) );

        $i = 0;
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/local_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/remote_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotepackage/script.compiled.js', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/main.compiled.js', $paths[$i++] );

    }

    public function testUsesSharedFolderPath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->remoteFolderPath = $remotePath;

        $paths = $manifestResolver->resolveFile( $filePath );

//        $this->assertEquals( 2, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

        $manifestResolver->remoteFolderPath = 'dorf';

        $paths = $manifestResolver->resolveFile( $filePath );


        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testReturnsRelativeFilePathsWhenGivenRelativeFilesWithRelativeRemotePaths()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->remoteFolderPath = $remotePath;


        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[1]
        );

    }

    public function testReturnsRelativeFilePathsWhenGivenAbsoluteFilesWithAbsoluteRemotePaths()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = getcwd() . '/' . $basePath;
        $manifestResolver->remoteFolderPath = getcwd() . '/' . $remotePath;



        $paths = $manifestResolver->resolveFile( getcwd() . '/' . $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[1]
        );

    }

    public function testReturnsRelativeFilePathsWhenGivenAbsoluteFilesWithRelativeRemotePath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->remoteFolderPath = $remotePath;

        $paths = $manifestResolver->resolveFile( getcwd() . '/' . $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            $remotePath . '/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            $basePath . '/main.compiled.js',
            $paths[1]
        );

    }

    public function testReturnsRelativeFilePathsWhenGivenRelativeFilesWithAbsoluteRemotePathsAndRelativeBasePath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->remoteFolderPath = getcwd() . '/' . $remotePath;

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            $remotePath . '/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            $basePath . '/main.compiled.js',
            $paths[1]
        );

    }
}
